<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * 結算服務（方案 §7.3）：整局結算 / 單獨結算 + 淨額清算（computeTransfers）+ 戰績快照。
 *
 * 關鍵約束：
 * 1. 快照/排名/勝負一律用**修正分**（餘額 + 本段清算淨流入），清算轉賬一律用**餘額**；
 * 2. **先快照、後清算**（computeTransfers 依餘額計算，與修正分互不干擾）；
 * 3. 禁止循環內操作數據庫：循環只組裝數組，出循環一次 batchInsert / 一次 UPDATE ... CASE；
 * 4. 併發：事務內 `lockForUpdate(room)` 串行化，重複結算冪等返回（冪等先於權限校驗）。
 */

namespace Plugin\Zhh\Scoring\Service;

use App\Exception\BusinessException;
use App\Http\Common\ResultCode;
use App\Service\IService;
use Hyperf\DbConnection\Db;
use Plugin\Zhh\Scoring\Enum\RoomEventType;
use Plugin\Zhh\Scoring\Enum\RoomMemberStatus;
use Plugin\Zhh\Scoring\Enum\RoomStatus;
use Plugin\Zhh\Scoring\Enum\SettleType;
use Plugin\Zhh\Scoring\Model\Battle;
use Plugin\Zhh\Scoring\Model\Room;
use Plugin\Zhh\Scoring\Model\RoomMember;
use Plugin\Zhh\Scoring\Repository\BattleMemberRepository;
use Plugin\Zhh\Scoring\Repository\BattleRepository;
use Plugin\Zhh\Scoring\Repository\RecordRepository;
use Plugin\Zhh\Scoring\Repository\RoomEventRepository;
use Plugin\Zhh\Scoring\Repository\RoomMemberRepository;
use Plugin\Zhh\Scoring\Repository\RoomRepository;
use Plugin\Zhh\Scoring\Repository\SettleTransferRepository;
use Plugin\Zhh\Scoring\Support\OwnerTransfer;
use Plugin\Zhh\Scoring\Support\RoomPush;

/**
 * @extends IService<Battle>
 */
final class SettleService extends IService
{
    public function __construct(
        protected readonly BattleRepository $repository,
        protected readonly RoomRepository $roomRepository,
        protected readonly RoomMemberRepository $roomMemberRepository,
        protected readonly BattleMemberRepository $battleMemberRepository,
        protected readonly SettleTransferRepository $settleTransferRepository,
        protected readonly RecordRepository $recordRepository,
        protected readonly RoomEventRepository $roomEventRepository,
        protected readonly OwnerTransfer $ownerTransfer,
        protected readonly RoomPush $roomPush
    ) {}

    /**
     * 整局結算（方案 §7.3）：全員快照 + 淨額清算 + 分數歸零 + 標記結束。
     *
     * @return array<string, mixed>
     */
    public function settle(int $uid, int $roomId): array
    {
        return Db::transaction(function () use ($uid, $roomId) {
            $room = $this->roomRepository->lockForUpdate($roomId);
            if (! $room instanceof Room) {
                throw new BusinessException(ResultCode::NOT_FOUND, '房間不存在');
            }

            // 冪等必須先於權限校驗（方案 §7.3 併發結算處理）
            if ((int) $room->status >= RoomStatus::SETTLED) {
                return $this->settledResult((int) ($room->current_battle_id ?? 0));
            }

            if ((int) $room->owner_id !== $uid) {
                throw new BusinessException(ResultCode::FORBIDDEN, '僅房主可發起整局結算');
            }

            $now = date('Y-m-d H:i:s');
            $battleId = (int) ($room->current_battle_id ?? 0);

            // 從未計分（無對局）：直接結束房間，無需快照與清算
            if ($battleId === 0) {
                $this->roomRepository->updateById($roomId, [
                    'status' => RoomStatus::SETTLED,
                    'finished_at' => $now,
                ]);

                return ['battle_id' => 0, 'idempotent' => false, 'settle_type' => SettleType::BATTLE, 'transfers' => []];
            }

            $netIn = $this->settleTransferRepository->netInByUser($battleId);
            $ranked = $this->rankMembers($this->roomMemberRepository->activeList($roomId)->all(), $netIn);
            $teaFees = $this->recordRepository->teaFeeByUser($battleId);
            $total = count($ranked);

            // 1. 快照批量寫入（循環內僅組裝數組）
            $snapshotRows = [];
            foreach ($ranked as $item) {
                $snapshotRows[] = $this->snapshotRow(
                    $battleId,
                    $item,
                    $this->computeResult((string) $item->effective, (int) $item->rank, $total),
                    (string) ($teaFees[(int) $item->user_id] ?? '0.00'),
                    SettleType::BATTLE,
                    $now
                );
            }
            $this->battleMemberRepository->batchInsert($snapshotRows);
            // 標記已結算（一次批量 UPDATE）
            $this->roomMemberRepository->batchSettle($roomId);

            // 2. 淨額清算（按餘額計算）並批量落地
            $transfers = $this->computeTransfers($ranked);
            $this->persistTransfers($room, $battleId, $transfers, SettleType::BATTLE, $uid, '整局結算清算');
            // 清算落地後清除餘額（score 語義＝當前未清算餘額）
            $this->roomMemberRepository->resetScores($roomId);

            // 3. 回填對局統計並結束對局
            $this->repository->updateById($battleId, [
                'total_rounds' => (int) $room->current_round,
                'tea_fee_total' => $this->recordRepository->teaFeeTotalByBattle($battleId),
                'status' => 1,
                'finished_at' => $now,
            ]);

            // 4. 房間標記已結算
            $this->roomRepository->updateById($roomId, [
                'status' => RoomStatus::SETTLED,
                'finished_at' => $now,
            ]);

            // 5. 系統事件（過程時間線）
            $this->roomEventRepository->create([
                'room_id' => $roomId,
                'room_no' => (string) $room->room_no,
                'battle_id' => $battleId,
                'event_type' => RoomEventType::BATTLE_SETTLED,
                'user_id' => $uid,
                'nickname' => $this->nicknameOf($roomId, $uid),
                'remark' => '整局結算',
                'created_at' => $now,
            ]);

            // 6. 廣播結算完成（payload 帶 battle_id），全員跳轉結算頁（方案 §10.4）
            $this->roomPush->pushSettleDone($roomId, $battleId);

            return ['battle_id' => $battleId, 'idempotent' => false, 'settle_type' => SettleType::BATTLE, 'transfers' => $transfers];
        });
    }

    /**
     * 單獨結算成員（方案 §7.3）：僅結算指定成員 + 以其為端點的清算轉賬，對局繼續。
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function settleMember(int $uid, array $params): array
    {
        return Db::transaction(function () use ($uid, $params) {
            $roomId = (int) $params['room_id'];
            $targetUserId = (int) $params['target_user_id'];

            $room = $this->roomRepository->lockForUpdate($roomId);
            if (! $room instanceof Room) {
                throw new BusinessException(ResultCode::NOT_FOUND, '房間不存在');
            }

            // 冪等：整局已結算（可能同時有人點了整局結算）→ 返回整局結果
            if ((int) $room->status >= RoomStatus::SETTLED) {
                $result = $this->settledResult((int) ($room->current_battle_id ?? 0));
                $result['settle_type'] = SettleType::BATTLE;

                return $result;
            }

            $target = $this->roomMemberRepository->lockForUpdate([
                'room_id' => $roomId,
                'user_id' => $targetUserId,
            ]);
            if (! $target instanceof RoomMember) {
                throw new BusinessException(ResultCode::NOT_FOUND, '該成員不在房間內');
            }

            // 權限：僅房主或本人可發起（方案 §7.3）
            if ($uid !== $targetUserId && (int) $room->owner_id !== $uid) {
                throw new BusinessException(ResultCode::FORBIDDEN, '無權結算該成員');
            }

            if ((int) $target->status !== RoomMemberStatus::IN) {
                throw new BusinessException(ResultCode::UNPROCESSABLE_ENTITY, '該成員已結算或已退出');
            }

            $battleId = (int) ($room->current_battle_id ?? 0);
            if ($battleId === 0) {
                throw new BusinessException(ResultCode::UNPROCESSABLE_ENTITY, '尚未開始對局，無法單獨結算');
            }

            $now = date('Y-m-d H:i:s');
            $netIn = $this->settleTransferRepository->netInByUser($battleId);
            $ranked = $this->rankMembers($this->roomMemberRepository->activeList($roomId)->all(), $netIn);
            $teaFees = $this->recordRepository->teaFeeByUser($battleId);
            $total = count($ranked);
            $myRank = $this->rankOf($ranked, $targetUserId);
            $targetEffective = bcadd((string) $target->score, $netIn[$targetUserId] ?? '0', 2);

            // 1. 快照（先快照、後清算；total_score 用修正分）
            $this->battleMemberRepository->batchInsert([[
                'battle_id' => $battleId,
                'user_id' => $targetUserId,
                'nickname' => (string) $target->nickname,
                'avatar' => (string) $target->avatar,
                'total_score' => $targetEffective,
                'rank' => $myRank,
                'result' => $this->computeResult($targetEffective, $myRank, $total),
                'tea_fee' => (string) ($teaFees[$targetUserId] ?? '0.00'),
                'settle_type' => SettleType::MEMBER,
                'settled_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]]);

            // 2. 淨額清算：整池計算，僅落地以被結算者為端點的轉賬
            $all = $this->computeTransfers($ranked);
            $transfers = [];
            foreach ($all as $transfer) {
                if ($transfer['from_user_id'] === $targetUserId || $transfer['to_user_id'] === $targetUserId) {
                    $transfers[] = $transfer;
                }
            }

            // 分數增量：付款方向 0 收斂（+）、收款方清減（−）；僅內存累計，最後單條 UPDATE ... CASE
            $deltas = [];
            foreach ($transfers as $transfer) {
                $otherId = $transfer['from_user_id'] === $targetUserId ? $transfer['to_user_id'] : $transfer['from_user_id'];
                $delta = $transfer['from_user_id'] === $otherId ? $transfer['amount'] : '-' . $transfer['amount'];
                $deltas[$otherId] = bcadd($deltas[$otherId] ?? '0', (string) $delta, 2);
            }

            $this->persistTransfers($room, $battleId, $transfers, SettleType::MEMBER, $uid, '單獨結算清算');
            $this->roomMemberRepository->adjustScores($roomId, $deltas);

            // 3. 被結算者標記「已單獨結算」且分數歸零
            $this->roomMemberRepository->updateById($target->id, [
                'status' => RoomMemberStatus::SETTLED,
                'settled_at' => $now,
                'score' => 0,
            ]);

            // 4. 系統事件
            $this->roomEventRepository->create([
                'room_id' => $roomId,
                'room_no' => (string) $room->room_no,
                'battle_id' => $battleId,
                'event_type' => RoomEventType::MEMBER_SETTLED,
                'user_id' => $targetUserId,
                'nickname' => (string) $target->nickname,
                'remark' => '單獨結算',
                'created_at' => $now,
            ]);

            // 5. 若被結算者是房主 → 移交；無可移交則整局結算（同類內呼叫，不構成循環依賴）
            if ((int) $room->owner_id === $targetUserId) {
                if ($this->ownerTransfer->transfer($room, $targetUserId) === null) {
                    $this->settle($uid, $roomId);
                }
            }

            $this->roomRepository->incrementVersion($roomId);

            // 6. 廣播：單獨結算完成（全員刷新剩餘成員與排名）+ 分數已變動
            $this->roomPush->pushMemberSettled($roomId, $targetUserId, $this->roomMemberRepository->countActive($roomId));
            $this->roomPush->pushScoreUpdate($roomId, (int) $room->score_version + 1);

            return [
                'battle_id' => $battleId,
                'idempotent' => false,
                'settle_type' => SettleType::MEMBER,
                'transfers' => $transfers,
            ];
        });
    }

    /**
     * 淨額貪心清算（方案 §7.3 computeTransfers）：按**餘額**計算，返回 [{from_user_id,to_user_id,amount}]。
     *
     * @param array<int, RoomMember> $members
     * @return array<int, array{from_user_id: int, to_user_id: int, amount: string}>
     */
    public function computeTransfers(array $members): array
    {
        $debtors = $creditors = [];
        foreach ($members as $member) {
            $score = bcadd((string) $member->score, '0', 2);
            $id = (int) $member->user_id;
            if (bccomp($score, '0', 2) < 0) {
                $debtors[] = ['user_id' => $id, 'score' => $score];
            } elseif (bccomp($score, '0', 2) > 0) {
                $creditors[] = ['user_id' => $id, 'score' => $score];
            }
        }

        // 最虧在前 / 最贏在前：固定順序保證配對方案唯一確定
        usort($debtors, static fn (array $a, array $b) => bccomp($a['score'], $b['score'], 2));
        usort($creditors, static fn (array $a, array $b) => bccomp($b['score'], $a['score'], 2));

        $transfers = [];
        $i = 0;
        $j = 0;
        $debtorCount = count($debtors);
        $creditorCount = count($creditors);

        while ($i < $debtorCount && $j < $creditorCount) {
            $payable = bcmul($debtors[$i]['score'], '-1', 2);
            $amount = bccomp($payable, $creditors[$j]['score'], 2) <= 0 ? $payable : $creditors[$j]['score'];

            if (bccomp($amount, '0', 2) > 0) {
                $transfers[] = [
                    'from_user_id' => $debtors[$i]['user_id'],
                    'to_user_id' => $creditors[$j]['user_id'],
                    'amount' => $amount,
                ];
            }

            $debtors[$i]['score'] = bcadd($debtors[$i]['score'], $amount, 2);
            $creditors[$j]['score'] = bcsub($creditors[$j]['score'], $amount, 2);

            if (bccomp($debtors[$i]['score'], '0', 2) === 0) {
                ++$i;
            }
            if (bccomp($creditors[$j]['score'], '0', 2) === 0) {
                ++$j;
            }
        }

        return $transfers;
    }

    /**
     * 在房成員按**修正分**降序排名（並發競爭排名：同分同名次，方案 §7.3）。
     *
     * @param array<int, RoomMember> $members
     * @param array<int, string> $netIn [user_id => 本段清算淨流入]
     * @return array<int, RoomMember>
     */
    private function rankMembers(array $members, array $netIn): array
    {
        foreach ($members as $member) {
            $member->effective = bcadd((string) $member->score, $netIn[(int) $member->user_id] ?? '0', 2);
        }

        usort($members, static fn (RoomMember $a, RoomMember $b) => bccomp((string) $b->effective, (string) $a->effective, 2));

        $rank = 0;
        $order = 0;
        $previous = null;
        foreach ($members as $member) {
            ++$order;
            if ($previous === null || bccomp((string) $member->effective, (string) $previous, 2) !== 0) {
                $rank = $order;
                $previous = (string) $member->effective;
            }
            $member->rank = $rank;
        }

        return $members;
    }

    /**
     * @param array<int, RoomMember> $ranked
     */
    private function rankOf(array $ranked, int $userId): int
    {
        foreach ($ranked as $member) {
            if ((int) $member->user_id === $userId) {
                return (int) $member->rank;
            }
        }

        return 0;
    }

    /**
     * 勝負判定（方案 §7.3 result:1=勝,2=負,3=平）。
     *
     * 規則（在相對排名之外補上分數正負，覆蓋「只支付茶水」的單邊扣減場景）：
     * 1. 只有一人：沒有對手，僅負分判負（只付了茶水 → 負，而非平），其餘為平；
     * 2. 第 1 名為勝；
     * 3. 負分一律判負 —— 只看名次的話，「全場只有茶水支出」等情況會被算成平，與實際輸分不符；
     * 4. 0 分且非第 1 名：末名為負，其餘為平。
     */
    private function computeResult(string $score, int $rank, int $total): int
    {
        $negative = bccomp($score, '0', 2) < 0;

        if ($total <= 1) {
            return $negative ? 2 : 3;
        }

        if ($rank === 1) {
            return 1;
        }

        if ($negative) {
            return 2;
        }

        return $rank === $total ? 2 : 3;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotRow(int $battleId, RoomMember $member, int $result, string $teaFee, int $settleType, string $now): array
    {
        return [
            'battle_id' => $battleId,
            'user_id' => (int) $member->user_id,
            'nickname' => (string) $member->nickname,
            'avatar' => (string) $member->avatar,
            'total_score' => (string) $member->effective,
            'rank' => (int) $member->rank,
            'result' => $result,
            'tea_fee' => $teaFee,
            'settle_type' => $settleType,
            'settled_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * 清算方案一次落地：`scoring_settle_transfer` 與 `scoring_record(type=3)` 各一次 batchInsert。
     *
     * @param array<int, array{from_user_id: int, to_user_id: int, amount: string}> $transfers
     */
    private function persistTransfers(Room $room, int $battleId, array $transfers, int $settleType, int $operatorId, string $remark): void
    {
        if ($transfers === []) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $transferRows = $recordRows = [];

        foreach ($transfers as $transfer) {
            $transferRows[] = [
                'room_id' => (int) $room->id,
                'battle_id' => $battleId,
                'settle_type' => $settleType,
                'from_user_id' => $transfer['from_user_id'],
                'to_user_id' => $transfer['to_user_id'],
                'amount' => $transfer['amount'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $recordRows[] = [
                'room_id' => (int) $room->id,
                'room_no' => (string) $room->room_no,
                'battle_id' => $battleId,
                'user_id' => $transfer['from_user_id'],
                'target_user_id' => $transfer['to_user_id'],
                'record_type' => 3,
                'amount' => $transfer['amount'],
                'remark' => $remark,
                'created_by' => $operatorId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->settleTransferRepository->batchInsert($transferRows);
        $this->recordRepository->batchInsert($recordRows);
    }

    /**
     * 冪等返回：已有結算結果時，讀取已固化的清算方案（全端同一份，方案 §7.3）。
     *
     * @return array<string, mixed>
     */
    private function settledResult(int $battleId): array
    {
        $transfers = [];
        if ($battleId > 0) {
            foreach ($this->settleTransferRepository->listByBattle($battleId) as $row) {
                $transfers[] = [
                    'from_user_id' => (int) $row->from_user_id,
                    'to_user_id' => (int) $row->to_user_id,
                    'amount' => (string) $row->amount,
                ];
            }
        }

        return [
            'battle_id' => $battleId,
            'idempotent' => true,
            'settle_type' => SettleType::BATTLE,
            'transfers' => $transfers,
        ];
    }

    private function nicknameOf(int $roomId, int $userId): string
    {
        return (string) ($this->roomMemberRepository->findByRoomAndUser($roomId, $userId)?->nickname ?? '');
    }
}
