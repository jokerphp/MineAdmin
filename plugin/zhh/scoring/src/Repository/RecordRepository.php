<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * 計分/支出流水數據倉庫（方案 §7.3、§7.4 對帳）。
 */

namespace Plugin\Zhh\Scoring\Repository;

use App\Repository\IRepository;
use Hyperf\Collection\Arr;
use Hyperf\Collection\Collection;
use Hyperf\Database\Model\Builder;
use Plugin\Zhh\Scoring\Enum\RecordType;
use Plugin\Zhh\Scoring\Model\Record;
use Plugin\Zhh\Scoring\Repository\Concerns\BatchWrites;

/**
 * Class RecordRepository.
 * @extends IRepository<Record>
 */
final class RecordRepository extends IRepository
{
    use BatchWrites;

    public function __construct(protected readonly Record $model) {}

    public function handleSearch(Builder $query, array $params): Builder
    {
        return $query
            ->when(Arr::get($params, 'room_id'), static function (Builder $query, $roomId) {
                $query->where('room_id', $roomId);
            })
            ->when(Arr::get($params, 'battle_id'), static function (Builder $query, $battleId) {
                $query->where('battle_id', $battleId);
            })
            ->when(Arr::exists($params, 'record_type'), static function (Builder $query) use ($params) {
                $query->where('record_type', Arr::get($params, 'record_type'));
            });
    }

    /**
     * 對局全部流水，按時間升序（對局詳情過程還原用，方案 §7.3）。
     */
    public function listByBattle(int $battleId): Collection
    {
        return $this->getQuery()
            ->where('battle_id', $battleId)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * 各房間的流水數（後台房間列表行內展示，一次 GROUP BY，方案 §7.4）。
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
     * 房間全部流水，按時間升序（後台房間詳情聚合展示，方案 §7.4）。
     */
    public function listByRoom(int $roomId): Collection
    {
        return $this->getQuery()
            ->where('room_id', $roomId)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * 各成員茶水費（record_type=2 合計）：一次 GROUP BY，禁止循環內逐人 count/sum（方案 §1.3）。
     *
     * @return array<int, string> [user_id => 茶水費]
     */
    public function teaFeeByUser(int $battleId): array
    {
        return $this->getQuery()
            ->where('battle_id', $battleId)
            ->where('record_type', RecordType::EXPENSE)
            ->groupBy('user_id')
            ->selectRaw('user_id, SUM(amount) AS total')
            ->pluck('total', 'user_id')
            ->toArray();
    }

    /**
     * 對局茶水費合計（回填 scoring_battle.tea_fee_total，方案 §7.3）。
     */
    public function teaFeeTotalByBattle(int $battleId): string
    {
        $total = $this->getQuery()
            ->where('battle_id', $battleId)
            ->where('record_type', RecordType::EXPENSE)
            ->sum('amount');

        return (string) ($total ?? '0.00');
    }
}
