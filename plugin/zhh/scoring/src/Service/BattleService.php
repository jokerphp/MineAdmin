<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * 戰績查詢服務（方案 §7.3）：我的戰績分頁、個人統計、對局詳情（過程還原）、結算結果。
 */

namespace Plugin\Zhh\Scoring\Service;

use App\Exception\BusinessException;
use App\Http\Common\ResultCode;
use App\Service\IService;
use Plugin\Zhh\Scoring\Enum\RecordType;
use Plugin\Zhh\Scoring\Enum\RoomEventType;
use Plugin\Zhh\Scoring\Model\Battle;
use Plugin\Zhh\Scoring\Repository\BattleMemberRepository;
use Plugin\Zhh\Scoring\Repository\BattleRepository;
use Plugin\Zhh\Scoring\Repository\RecordRepository;
use Plugin\Zhh\Scoring\Repository\RoomEventRepository;
use Plugin\Zhh\Scoring\Repository\RoomMemberRepository;
use Plugin\Zhh\Scoring\Repository\SettleTransferRepository;

/**
 * @extends IService<Battle>
 */
final class BattleService extends IService
{
    public function __construct(
        protected readonly BattleRepository $repository,
        protected readonly BattleMemberRepository $battleMemberRepository,
        protected readonly SettleTransferRepository $settleTransferRepository,
        protected readonly RecordRepository $recordRepository,
        protected readonly RoomEventRepository $roomEventRepository,
        protected readonly RoomMemberRepository $roomMemberRepository
    ) {}

    /**
     * 我的戰績（方案 §7.3）：按結算時間倒序分頁，並批量補齊對局信息。
     *
     * @return array<string, mixed>
     */
    public function myRecords(int $uid, int $page, int $size): array
    {
        $result = $this->battleMemberRepository->pageByUser($uid, $page, $size);

        $battleIds = [];
        foreach ($result['list'] as $row) {
            $battleIds[] = (int) $row['battle_id'];
        }

        $battles = $this->repository->mapByIds($battleIds);

        $list = [];
        foreach ($result['list'] as $row) {
            $battleId = (int) $row['battle_id'];
            $battle = $battles[$battleId] ?? [];
            $list[] = [
                'battle_id' => $battleId,
                'room_no' => (string) ($battle['room_no'] ?? ''),
                'total_score' => (string) $row['total_score'],
                'rank' => (int) $row['rank'],
                'result' => (int) $row['result'],
                'tea_fee' => (string) $row['tea_fee'],
                'settle_type' => (int) $row['settle_type'],
                'settled_at' => (string) $row['settled_at'],
                'total_rounds' => (int) ($battle['total_rounds'] ?? 0),
                'finished_at' => $battle['finished_at'] ?? null,
            ];
        }

        return ['list' => $list, 'total' => (int) $result['total']];
    }

    /**
     * 個人統計（方案 §7.3）：勝/負/平/勝率/累計分/對戰局數。
     *
     * @return array<string, mixed>
     */
    public function stats(int $uid): array
    {
        $rows = $this->battleMemberRepository->statsRows($uid);

        $totalScore = '0.00';
        $win = $lose = $draw = 0;

        foreach ($rows as $row) {
            $totalScore = bcadd($totalScore, (string) $row->total_score, 2);
            $result = (int) $row->result;
            if ($result === 1) {
                ++$win;
            } elseif ($result === 2) {
                ++$lose;
            } elseif ($result === 3) {
                ++$draw;
            }
        }

        $played = $rows->count();

        return [
            'total_score' => $totalScore,
            'win' => $win,
            'lose' => $lose,
            'draw' => $draw,
            'played' => $played,
            'win_rate' => ($win + $lose) > 0 ? round($win / ($win + $lose) * 100, 2) : 0.0,
        ];
    }

    /**
     * 對局詳情（方案 §7.3）：結果（排行榜快照 + 清算方案）+ 過程（流水 + 系統事件時間線）。
     *
     * @return array<string, mixed>
     */
    public function detail(int $uid, int $battleId): array
    {
        $battle = $this->requireBattle($battleId);
        $this->ensureParticipant($uid, $battle);

        return [
            'battle' => $this->formatBattle($battle),
            'ranking' => $this->battleMemberRepository->listByBattle($battleId)->toArray(),
            'transfers' => $this->settleTransferRepository->listByBattle($battleId)->toArray(),
            'records' => $this->timeline($battle),
        ];
    }

    /**
     * 結算結果（方案 §7.3）：全員同一份——一律讀已固化的清算方案。
     *
     * @return array<string, mixed>
     */
    public function settleResult(int $uid, int $battleId): array
    {
        $battle = $this->requireBattle($battleId);
        $this->ensureParticipant($uid, $battle);

        return [
            'battle' => $this->formatBattle($battle),
            'ranking' => $this->battleMemberRepository->listByBattle($battleId)->toArray(),
            'transfers' => $this->settleTransferRepository->listByBattle($battleId)->toArray(),
        ];
    }

    /**
     * 過程時間線：金額流水 ∪ 系統事件，按時間升序（方案 §7.3 對局詳情）。
     *
     * @return array<int, array<string, mixed>>
     */
    private function timeline(Battle $battle): array
    {
        $battleId = (int) $battle->id;
        // 流水僅存 user_id，昵稱由房間成員快照一次性補齊（成員已退出也能還原）
        $nicknames = $this->roomMemberRepository->nicknameMap((int) $battle->room_id);

        $items = [];

        foreach ($this->recordRepository->listByBattle($battleId) as $record) {
            $userId = (int) $record->user_id;
            $targetId = $record->target_user_id === null ? null : (int) $record->target_user_id;
            $items[] = [
                'event_type' => match ((int) $record->record_type) {
                    RecordType::SCORE => 'score',
                    RecordType::EXPENSE => 'expense',
                    default => 'transfer',
                },
                'round_no' => $record->round_no === null ? null : (int) $record->round_no,
                'user_id' => $userId,
                'nickname' => (string) ($nicknames[$userId] ?? ''),
                'target_user_id' => $targetId,
                'target_nickname' => (int) $record->record_type === RecordType::EXPENSE
                    ? '茶水'
                    : ($targetId === null ? null : (string) ($nicknames[$targetId] ?? '')),
                'record_type' => (int) $record->record_type,
                'amount' => (string) $record->amount,
                'remark' => (string) $record->remark,
                'created_at' => $record->created_at?->toDateTimeString(),
                'sort_id' => (int) $record->id,
            ];
        }

        foreach ($this->roomEventRepository->listByBattle($battleId) as $event) {
            $items[] = [
                'event_type' => match ((int) $event->event_type) {
                    RoomEventType::JOIN => 'join',
                    RoomEventType::LEAVE => 'leave',
                    RoomEventType::OWNER_TRANSFER => 'owner_transfer',
                    RoomEventType::MEMBER_SETTLED => 'member_settled',
                    default => 'battle_settled',
                },
                'round_no' => null,
                'user_id' => (int) $event->user_id,
                'nickname' => (string) $event->nickname,
                'target_user_id' => $event->target_user_id === null ? null : (int) $event->target_user_id,
                'target_nickname' => $event->target_nickname,
                'record_type' => null,
                'amount' => null,
                'remark' => (string) $event->remark,
                'created_at' => $event->created_at?->toDateTimeString(),
                'sort_id' => (int) $event->id,
            ];
        }

        // 金額流水與系統事件合併排序：時間升序，同秒按各自表 id
        usort($items, static fn (array $a, array $b): int => [$a['created_at'], $a['sort_id']] <=> [$b['created_at'], $b['sort_id']]);

        return $items;
    }

    private function requireBattle(int $battleId): Battle
    {
        $battle = $this->repository->findById($battleId);
        if (! $battle instanceof Battle) {
            throw new BusinessException(ResultCode::NOT_FOUND, '對局不存在');
        }

        return $battle;
    }

    private function ensureParticipant(int $uid, Battle $battle): void
    {
        if ((int) $battle->owner_id === $uid) {
            return;
        }

        if (! $this->battleMemberRepository->isParticipant((int) $battle->id, $uid)) {
            throw new BusinessException(ResultCode::FORBIDDEN, '無權查看該對局');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formatBattle(Battle $battle): array
    {
        return [
            'id' => (int) $battle->id,
            'room_no' => (string) $battle->room_no,
            'started_at' => $battle->started_at?->toDateTimeString(),
            'finished_at' => $battle->finished_at?->toDateTimeString(),
            'total_rounds' => (int) $battle->total_rounds,
            'tea_fee_total' => (string) $battle->tea_fee_total,
        ];
    }
}
