<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * 房主接管（方案 §7.3）：房主退出或單獨結算時，按 joined_at 升序把房主移交給
 * 「第一位仍在房且未單獨結算」的成員；無可接管成員時返回 null，由呼叫方觸發整局結算。
 *
 * 獨立為 Support 類的原因：RoomService（離開）與 SettleService（單獨結算）都要用它，
 * 而「無可接管 → 整局結算」需要呼叫 SettleService；若把本邏輯放在 RoomService，
 * 會與 SettleService 形成構造注入的循環依賴。
 */

namespace Plugin\Zhh\Scoring\Support;

use Plugin\Zhh\Scoring\Enum\RoomEventType;
use Plugin\Zhh\Scoring\Model\Room;
use Plugin\Zhh\Scoring\Model\RoomMember;
use Plugin\Zhh\Scoring\Repository\RoomEventRepository;
use Plugin\Zhh\Scoring\Repository\RoomMemberRepository;
use Plugin\Zhh\Scoring\Repository\RoomRepository;

final class OwnerTransfer
{
    public function __construct(
        private readonly RoomRepository $roomRepository,
        private readonly RoomMemberRepository $roomMemberRepository,
        private readonly RoomEventRepository $roomEventRepository
    ) {}

    /**
     * @return null|RoomMember 接管者；null 表示已無可接管成員
     */
    public function transfer(Room $room, int $oldOwnerId): ?RoomMember
    {
        $successor = $this->roomMemberRepository->findNextOwner((int) $room->id, $oldOwnerId);
        if ($successor === null) {
            return null;
        }

        $this->roomRepository->updateById($room->id, ['owner_id' => $successor->user_id]);
        $this->roomMemberRepository->updateById($successor->id, ['role' => 1]);
        $this->roomEventRepository->create([
            'room_id' => $room->id,
            'room_no' => $room->room_no,
            'battle_id' => $room->current_battle_id,
            'event_type' => RoomEventType::OWNER_TRANSFER,
            'user_id' => $oldOwnerId,
            'nickname' => (string) ($this->roomMemberRepository->findByRoomAndUser((int) $room->id, $oldOwnerId)?->nickname ?? ''),
            'target_user_id' => $successor->user_id,
            'target_nickname' => $successor->nickname,
            'remark' => '房主接管',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $successor;
    }
}
