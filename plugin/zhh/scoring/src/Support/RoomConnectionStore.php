<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * 房間 → WebSocket 連接映射（方案 §10.3「Redis 或容器內存存儲」）。
 * 以 Redis Sorted Set 存 `workerId:fd`（score = 最後活躍時間），支持：
 * - 多進程共享連接視圖；
 * - 心跳續期與過期連接清理。
 */

namespace Plugin\Zhh\Scoring\Support;

use Hyperf\Redis\Redis;

final class RoomConnectionStore
{
    private const PREFIX = 'scoring:room_conn:';

    /**
     * fd → room 索引前綴
     */
    private const FD_INDEX_PREFIX = 'scoring:room_conn_fd:';

    /**
     * 連接視圖過期時間（秒），由心跳/訂閱續期
     */
    private const TTL = 3600;

    public function __construct(private readonly Redis $redis) {}

    public function bind(int $roomId, int $workerId, int $fd): void
    {
        $key = $this->key($roomId);
        $this->redis->zAdd($key, [microtime(true) => $this->member($workerId, $fd)]);
        $this->redis->expire($key, self::TTL);
        // fd → room 索引：連接關閉時無需掃描全部房間
        $this->redis->set($this->fdKey($workerId, $fd), (string) $roomId, self::TTL);
    }

    public function unbind(int $roomId, int $workerId, int $fd): void
    {
        $this->redis->zRem($this->key($roomId), $this->member($workerId, $fd));
    }

    /**
     * 連接關閉時清理（經 fd → room 索引定位房間）。
     */
    public function unbindFd(int $workerId, int $fd): void
    {
        $fdKey = $this->fdKey($workerId, $fd);
        $roomId = (int) $this->redis->get($fdKey);

        if ($roomId > 0) {
            $this->redis->zRem($this->key($roomId), $this->member($workerId, $fd));
        }

        $this->redis->del($fdKey);
    }

    /**
     * 心跳續期：刷新該連接的最後活躍時間，並清理超過 TTL 的殘留連接。
     */
    public function touch(int $roomId, int $workerId, int $fd): void
    {
        $key = $this->key($roomId);
        $this->redis->zAdd($key, [microtime(true) => $this->member($workerId, $fd)]);
        $this->redis->zRemRangeByScore($key, '-inf', (string) (microtime(true) - self::TTL));
    }

    /**
     * 某房間的全部連接（一次讀取，循環內僅做推送，不查庫）。
     *
     * @return array<int, array{worker_id: int, fd: int}>
     */
    public function connections(int $roomId): array
    {
        $members = $this->redis->zRange($this->key($roomId), 0, -1);
        $connections = [];

        foreach ($members as $member) {
            [$workerId, $fd] = explode(':', (string) $member, 2);
            $connections[] = ['worker_id' => (int) $workerId, 'fd' => (int) $fd];
        }

        return $connections;
    }

    /**
     * 房間內的連接數（推送 payload 用）。
     */
    public function count(int $roomId): int
    {
        return (int) $this->redis->zCard($this->key($roomId));
    }

    private function key(int $roomId): string
    {
        return self::PREFIX . $roomId;
    }

    private function fdKey(int $workerId, int $fd): string
    {
        return self::FD_INDEX_PREFIX . $workerId . ':' . $fd;
    }

    private function member(int $workerId, int $fd): string
    {
        return $workerId . ':' . $fd;
    }
}
