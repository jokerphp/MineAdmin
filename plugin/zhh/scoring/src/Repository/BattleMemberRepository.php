<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * 對局成員快照數據倉庫（方案 §7.3 快照寫入、§7.3 個人統計聚合）。
 */

namespace Plugin\Zhh\Scoring\Repository;

use App\Repository\IRepository;
use Hyperf\Collection\Arr;
use Hyperf\Collection\Collection;
use Hyperf\Database\Model\Builder;
use Plugin\Zhh\Scoring\Model\BattleMember;
use Plugin\Zhh\Scoring\Repository\Concerns\BatchWrites;

/**
 * Class BattleMemberRepository.
 * @extends IRepository<BattleMember>
 */
final class BattleMemberRepository extends IRepository
{
    use BatchWrites;

    public function __construct(protected readonly BattleMember $model) {}

    public function handleSearch(Builder $query, array $params): Builder
    {
        return $query
            ->when(Arr::get($params, 'battle_id'), static function (Builder $query, $battleId) {
                $query->where('battle_id', $battleId);
            })
            ->when(Arr::get($params, 'user_id'), static function (Builder $query, $userId) {
                $query->where('user_id', $userId);
            })
            ->when(Arr::exists($params, 'settle_type'), static function (Builder $query) use ($params) {
                $query->where('settle_type', Arr::get($params, 'settle_type'));
            });
    }

    /**
     * 對局全部快照（結算結果展示；同一成員可能多行，方案 §5.2）。
     */
    public function listByBattle(int $battleId): Collection
    {
        return $this->getQuery()
            ->where('battle_id', $battleId)
            ->orderBy('rank')
            ->get();
    }

    /**
     * 多個對局的全部快照（後台房間詳情一次取齊，避免循環內查庫，方案 §7.4）。
     *
     * @param int[] $battleIds
     */
    public function listByBattleIds(array $battleIds): Collection
    {
        if ($battleIds === []) {
            return new Collection();
        }

        return $this->getQuery()
            ->whereIn('battle_id', $battleIds)
            ->orderBy('battle_id')
            ->orderBy('rank')
            ->get();
    }

    /**
     * 各成員在本局的最後一次結算時間（分段口徑的邊界，方案 §7.3 v1.5）：一次 GROUP BY。
     *
     * @return array<int, string> [user_id => 最後結算時間]
     */
    public function lastSettledAtByBattle(int $battleId): array
    {
        return $this->getQuery()
            ->where('battle_id', $battleId)
            ->groupBy('user_id')
            ->selectRaw('user_id, MAX(settled_at) AS last_at')
            ->pluck('last_at', 'user_id')
            ->toArray();
    }

    /**
     * 本人是否為該對局參與者（對局詳情/結算結果權限校驗，方案 §7.3）。
     */
    public function isParticipant(int $battleId, int $userId): bool
    {
        return $this->getQuery()
            ->where('battle_id', $battleId)
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * 我的戰績：按結算時間倒序分頁（一次查詢，附加資料由 Service 批量補齊）。
     */
    public function pageByUser(int $userId, int $page, int $size): array
    {
        return $this->page(
            ['user_id' => $userId, 'order_by' => 'settled_at', 'order_by_direction' => 'desc'],
            $page,
            $size
        );
    }

    /**
     * 個人統計：取本人全部結算快照的分數與結果（一次查詢，聚合在內存完成，方案 §7.3）。
     */
    public function statsRows(int $userId): Collection
    {
        return $this->getQuery()
            ->where('user_id', $userId)
            ->get(['total_score', 'result']);
    }

    /**
     * 個人/對局維度的快照分求和（含重入多次快照，方案 §7.3 累計總分口徑）。
     */
    public function sumScoreByUser(int $userId): string
    {
        return (string) ($this->getQuery()->where('user_id', $userId)->sum('total_score') ?? '0.00');
    }
}
