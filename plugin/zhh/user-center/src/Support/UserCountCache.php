<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/user-center plugin.
 *
 * 首頁用戶數緩存（方案 §6.5）：緩存鍵 uc:user_count:{app_id}，
 * 讀路徑 O(1) GET、永不回源；命中判定必須用 false，"0" 是合法值（方案 §6.5.1 問題 1）。
 */

namespace Plugin\Zhh\UserCenter\Support;

use Hyperf\Redis\Redis;

final class UserCountCache
{
    private const PREFIX = 'uc:user_count:';

    /**
     * 單飛鎖存活時間（秒），防止鎖未釋放時永久阻塞
     */
    private const LOCK_TTL = 10;

    public function __construct(private readonly Redis $redis) {}

    /**
     * 讀取緩存值；未命中返回 false（注意不能用 ! $cached 判斷）。
     */
    public function get(string $appId): int|false
    {
        $cached = $this->redis->get($this->key($appId));

        return $cached === false ? false : (int) $cached;
    }

    /**
     * 擊穿降級用的舊值（無則 0）。
     */
    public function stale(string $appId): int
    {
        return (int) $this->redis->get($this->key($appId) . ':stale');
    }

    /**
     * 搶佔單飛鎖：同一時刻僅一個請求回源，其餘返回舊值。
     */
    public function lock(string $appId): bool
    {
        return (bool) $this->redis->set($this->key($appId) . ':lock', 1, ['NX', 'EX' => self::LOCK_TTL]);
    }

    public function unlock(string $appId): void
    {
        $this->redis->del($this->key($appId) . ':lock');
    }

    /**
     * 寫入緩存：同時更新降級舊值；不設 TTL，失效由寫操作與定時預熱驅動（方案 §6.5.2）。
     */
    public function put(string $appId, int $count): void
    {
        $this->redis->set($this->key($appId), (string) $count);
        $this->redis->set($this->key($appId) . ':stale', (string) $count);
    }

    /**
     * 寫操作即時失效：僅刪主鍵，保留 :stale 作為回源期間的降級值。
     */
    public function forget(string $appId): void
    {
        $this->redis->del($this->key($appId));
    }

    private function key(string $appId): string
    {
        return self::PREFIX . $appId;
    }
}
