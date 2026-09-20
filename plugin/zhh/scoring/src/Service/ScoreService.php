<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * 計分 / 支出服務（方案 §7.3 計分）：
 * 計分為**轉賬式零和雙向更新**（收款方 +amount、付款方 −amount，單條 UPDATE ... CASE），
 * 全房分數總和恆為 0；支出（茶水費）只記帳不動分數。
 */

namespace Plugin\Zhh\Scoring\Service;

use App\Exception\BusinessException;
use App\Http\Common\ResultCode;
use App\Service\IService;
use Hyperf\DbConnection\Db;
use Plugin\Zhh\Scoring\Enum\RecordType;
use Plugin\Zhh\Scoring\Enum\RoomMemberStatus;
use Plugin\Zhh\Scoring\Enum\RoomStatus;
use Plugin\Zhh\Scoring\Model\Record;
use Plugin\Zhh\Scoring\Model\Room;
use Plugin\Zhh\Scoring\Model\RoomMember;
use Plugin\Zhh\Scoring\Repository\RecordRepository;
use Plugin\Zhh\Scoring\Repository\RoomMemberRepository;
use Plugin\Zhh\Scoring\Repository\RoomRepository;
use Plugin\Zhh\Scoring\Support\RoomPush;

/**
 * @extends IService<Record>
 */
final class ScoreService extends IService
{
    public function __construct(
        protected readonly RecordRepository $repository,
        protected readonly RoomRepository $roomRepository,
        protected readonly RoomMemberRepository $roomMemberRepository,
        protected readonly RoomService $roomService,
        protected readonly RoomPush $roomPush
    ) {}

    /**
     * 新增計分（方案 §7.2/§7.3）：操作人本人為付款方、target_user_id 為收款方，amount 恆為正。
     *
     * @param array<string, mixed> $params
     */
    public function add(int $uid, array $params): void
    {
        Db::transaction(function () use ($uid, $params) {
            $room = $this->requirePlayingRoom((int) $params['room_id']);
            $battleId = $this->roomService->ensureBattle($room, (int) $room->owner_id);

            $targetUserId = (int) $params['target_user_id'];
            if ($targetUserId === $uid) {
                throw new BusinessException(ResultCode::UNPROCESSABLE_ENTITY, '不能向自己轉賬計分');
            }

            $this->requireActiveMember((int) $room->id, $uid, '您已結算或已退出房間，無法計分');
            $this->requireActiveMember((int) $room->id, $targetUserId, '收款方不在房間內');

            $amount = (string) $params['amount'];

            // 零和雙向更新：單條 UPDATE ... CASE（方案 §7.3）
            $this->roomMemberRepository->adjustScores((int) $room->id, [
                $targetUserId => $amount,
                $uid => '-' . $amount,
            ]);

            $this->repository->create([
                'room_id' => (int) $room->id,
                'room_no' => (string) $room->room_no,
                'battle_id' => $battleId,
                'round_no' => (int) $room->current_round,
                'user_id' => $uid,
                'target_user_id' => $targetUserId,
                'record_type' => RecordType::SCORE,
                'amount' => $amount,
                'remark' => (string) ($params['remark'] ?? '轉賬計分'),
                'created_by' => $uid,
            ]);

            $this->roomRepository->incrementVersion((int) $room->id);
            $this->roomPush->pushScoreUpdate((int) $room->id, (int) $room->score_version + 1);
        });
    }

    /**
     * 新增支出（茶水費，方案 §7.2/§5.2 record_type=2）：只記帳，不改變分數。
     *
     * @param array<string, mixed> $params
     */
    public function expense(int $uid, array $params): void
    {
        Db::transaction(function () use ($uid, $params) {
            $room = $this->requirePlayingRoom((int) $params['room_id']);
            $battleId = $this->roomService->ensureBattle($room, (int) $room->owner_id);

            $this->requireActiveMember((int) $room->id, $uid, '您已結算或已退出房間，無法記錄支出');

            $this->repository->create([
                'room_id' => (int) $room->id,
                'room_no' => (string) $room->room_no,
                'battle_id' => $battleId,
                'round_no' => (int) $room->current_round,
                'user_id' => $uid,
                'target_user_id' => null,
                'record_type' => RecordType::EXPENSE,
                'amount' => (string) $params['amount'],
                'remark' => (string) ($params['remark'] ?? '茶水費'),
                'created_by' => $uid,
            ]);

            $this->roomRepository->incrementVersion((int) $room->id);
            $this->roomPush->pushScoreUpdate((int) $room->id, (int) $room->score_version + 1);
        });
    }

    /**
     * 取房間並加行鎖（與結算互斥，方案 §7.3 併發處理）。
     */
    private function requirePlayingRoom(int $roomId): Room
    {
        $room = $this->roomRepository->lockForUpdate($roomId);
        if (! $room instanceof Room) {
            throw new BusinessException(ResultCode::NOT_FOUND, '房間不存在');
        }

        if ((int) $room->status >= RoomStatus::SETTLED) {
            throw new BusinessException(ResultCode::UNPROCESSABLE_ENTITY, '該房間已結束');
        }

        return $room;
    }

    private function requireActiveMember(int $roomId, int $userId, string $message): void
    {
        $member = $this->roomMemberRepository->lockForUpdate([
            'room_id' => $roomId,
            'user_id' => $userId,
        ]);

        if (! $member instanceof RoomMember || (int) $member->status !== RoomMemberStatus::IN) {
            throw new BusinessException(ResultCode::UNPROCESSABLE_ENTITY, $message);
        }
    }
}
