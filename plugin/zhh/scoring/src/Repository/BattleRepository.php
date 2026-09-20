<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * 對局/戰績主表數據倉庫（方案 §7.3）。
 */

namespace Plugin\Zhh\Scoring\Repository;

use App\Repository\IRepository;
use Hyperf\Collection\Arr;
use Hyperf\Collection\Collection;
use Hyperf\Database\Model\Builder;
use Plugin\Zhh\Scoring\Model\Battle;
use Plugin\Zhh\Scoring\Repository\Concerns\BatchWrites;

/**
 * Class BattleRepository.
 * @extends IRepository<Battle>
 */
final class BattleRepository extends IRepository
{
    use BatchWrites;

    public function __construct(protected readonly Battle $model) {}

    public function handleSearch(Builder $query, array $params): Builder
    {
        return $query
            ->when(Arr::get($params, 'room_id'), static function (Builder $query, $roomId) {
                $query->where('room_id', $roomId);
            })
            ->when(Arr::get($params, 'room_no'), static function (Builder $query, $roomNo) {
                $query->where('room_no', $roomNo);
            })
            ->when(Arr::get($params, 'owner_id'), static function (Builder $query, $ownerId) {
                $query->where('owner_id', $ownerId);
            })
            ->when(Arr::exists($params, 'status'), static function (Builder $query) use ($params) {
                $query->where('status', Arr::get($params, 'status'));
            });
    }

    public function lockForUpdate(int $id): ?Battle
    {
        // @phpstan-ignore-next-line
        return $this->getQuery()->whereKey($id)->lockForUpdate()->first();
    }

    /**
     * 房間全部對局（後台房間詳情聚合展示，方案 §7.4）。
     *
     * @return Collection
     */
    public function listByRoom(int $roomId): Collection
    {
        return $this->getQuery()
            ->where('room_id', $roomId)
            ->orderBy('id')
            ->get();
    }

    /**
     * 各房間的對局數（後台房間列表行內展示，一次 GROUP BY，方案 §7.4）。
     *
     * @param int[] $roomIds
     * @return array<int, int>
     */
    public function countByRoomIds(array $roomIds): array
    {
        if ($roomIds === []) {
            return [];
        }

        return $this->getQuery()
            ->whereIn('room_id', $roomIds)
            ->groupBy('room_id')
            ->selectRaw('room_id, COUNT(*) AS cnt')
            ->pluck('cnt', 'room_id')
            ->toArray();
    }

    /**
     * 按 ID 批量取對局並以 id 為鍵返回（我的戰績列表一次補齊，避免循環內查庫）。
     *
     * @param int[] $ids
     * @return array<int, array<string, mixed>>
     */
    public function mapByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $map = [];
        $rows = $this->getQuery()
            ->whereIn('id', $ids)
            ->get(['id', 'room_id', 'room_no', 'owner_id', 'status', 'total_rounds', 'tea_fee_total', 'started_at', 'finished_at']);

        foreach ($rows as $row) {
            $map[(int) $row->id] = $row->toArray();
        }

        return $map;
    }
}
