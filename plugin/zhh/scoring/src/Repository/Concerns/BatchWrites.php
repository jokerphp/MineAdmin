<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * 批量數據庫助手（方案 §7.3「不得在循環中操作數據庫」的落地方式）。
 * MineAdmin 的 IRepository 未內置任何批量方法，故由插件 Repository 自行擴展；
 * 底層為 hyperf/database 原生 API：Query\Builder::insert / Model\Builder::update / Db::update。
 */

namespace Plugin\Zhh\Scoring\Repository\Concerns;

use Hyperf\DbConnection\Db;

trait BatchWrites
{
    /**
     * 批量插入：單條 SQL。
     *
     * 注意：繞過模型事件與自動時間戳，$rows 內每行必須自帶 created_at。
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function batchInsert(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        return $this->getQuery()->insert($rows);
    }

    /**
     * 批量同值更新：單條 SQL，自動補 updated_at（不觸發模型事件）。
     *
     * @param array<string, mixed> $where
     * @param array<string, mixed> $data
     */
    public function batchUpdate(array $where, array $data): int
    {
        return $this->getQuery()->where($where)->update($data);
    }

    /**
     * 按用戶差異增減分數：單條 UPDATE ... CASE WHEN（方案 §7.3，計分/結算路徑專用）。
     *
     * @param int $roomId 房間ID
     * @param array<int, int|string> $deltas [user_id => ±amount]，正負號寫在值裡（如 '-20'）
     */
    public function adjustScores(int $roomId, array $deltas): int
    {
        if ($deltas === []) {
            return 0;
        }

        $cases = $bindings = [];
        foreach ($deltas as $userId => $delta) {
            $sign = str_starts_with((string) $delta, '-') ? '-' : '+';
            $cases[] = "WHEN ? THEN score {$sign} ?";
            $bindings[] = $userId;
            $bindings[] = ltrim((string) $delta, '-');
        }

        $ids = array_map(intval(...), array_keys($deltas));
        $sql = 'UPDATE scoring_room_member SET score = CASE user_id '
            . implode(' ', $cases)
            . ' END, updated_at = NOW() WHERE room_id = ? AND user_id IN (' . implode(',', $ids) . ')';

        return Db::update($sql, [...$bindings, $roomId]);
    }
}
