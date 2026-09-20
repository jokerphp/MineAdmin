<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * 業務觸發的房間推送（方案 §10.4）：`Support\RoomPush` 只負責把消息投遞到 Redis 頻道，
 * 由 `Process\RoomPushSubscriber`（位於 ws 服務進程組）訂閱後推送給本進程可觸達的連接。
 *
 * 消息格式（方案 §10.4）：
 *   { "type": "score_update",   "room_id": 123, "score_version": 10, "data": {...} }
 *   { "type": "room_update",    "room_id": 123, "data": { "member_count": 5 } }
 *   { "type": "member_settled", "room_id": 123, "user_id": 55, "data": { "remaining_count": 3 } }
 *   { "type": "settle_done",    "room_id": 123, "battle_id": 456, "data": {...} }
 *   { "type": "system_notice",  "room_id": 123, "data": { "message": "..." } }
 */

namespace Plugin\Zhh\Scoring\Support;

use Hyperf\Redis\Redis;

final class RoomPush
{
    public const CHANNEL_PREFIX = 'scoring:room_ws:';

    public function __construct(
        private readonly Redis $redis,
        private readonly RoomConnectionStore $connectionStore
    ) {}

    public function pushScoreUpdate(int $roomId, int $scoreVersion): void
    {
        $this->publish($roomId, [
            'type' => 'score_update',
            'room_id' => $roomId,
            'score_version' => $scoreVersion,
            'data' => [
                'member_count' => $this->connectionStore->count($roomId),
            ],
        ]);
    }

    public function pushRoomUpdate(int $roomId, int $memberCount): void
    {
        $this->publish($roomId, [
            'type' => 'room_update',
            'room_id' => $roomId,
            'data' => ['member_count' => $memberCount],
        ]);
    }

    public function pushMemberSettled(int $roomId, int $userId, int $remainingCount): void
    {
        $this->publish($roomId, [
            'type' => 'member_settled',
            'room_id' => $roomId,
            'user_id' => $userId,
            'data' => ['remaining_count' => $remainingCount],
        ]);
    }

    public function pushSettleDone(int $roomId, int $battleId): void
    {
        $this->publish($roomId, [
            'type' => 'settle_done',
            'room_id' => $roomId,
            'battle_id' => $battleId,
            'data' => [],
        ]);
    }

    public function pushSystemNotice(int $roomId, string $message): void
    {
        $this->publish($roomId, [
            'type' => 'system_notice',
            'room_id' => $roomId,
            'data' => ['message' => $message],
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function publish(int $roomId, array $payload): void
    {
        $this->redis->publish(self::CHANNEL_PREFIX . $roomId, json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '{}');
    }
}
