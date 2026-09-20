<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/user-center plugin.
 *
 * 會員用戶數據倉庫（方案 §6.2 登錄查/建會員、§6.4 後台列表篩選、§6.5 用戶數統計）。
 */

namespace Plugin\Zhh\UserCenter\Repository;

use App\Repository\IRepository;
use Hyperf\Collection\Arr;
use Hyperf\Database\Model\Builder;
use Plugin\Zhh\UserCenter\Model\MemberUser;

/**
 * Class MemberUserRepository.
 * @extends IRepository<MemberUser>
 */
final class MemberUserRepository extends IRepository
{
    public function __construct(protected readonly MemberUser $model) {}

    public function handleSearch(Builder $query, array $params): Builder
    {
        return $query
            ->when(Arr::get($params, 'app_id'), static function (Builder $query, $appId) {
                $query->where('app_id', $appId);
            })
            ->when(Arr::get($params, 'openid'), static function (Builder $query, $openid) {
                $query->where('openid', $openid);
            })
            ->when(Arr::get($params, 'nickname'), static function (Builder $query, $nickname) {
                $query->where('nickname', 'like', '%' . $nickname . '%');
            })
            ->when(Arr::exists($params, 'status'), static function (Builder $query) use ($params) {
                $query->where('status', Arr::get($params, 'status'));
            })
            ->when(Arr::get($params, 'created_at'), static function (Builder $query, $createdAt) {
                $query->whereBetween('created_at', [
                    $createdAt[0] . ' 00:00:00',
                    $createdAt[1] . ' 23:59:59',
                ]);
            });
    }

    /**
     * 按 (應用, openid) 取會員（同一 openid 不重複建號，方案 §6.2）。
     */
    public function findByOpenid(string $appId, string $openid): ?MemberUser
    {
        // @phpstan-ignore-next-line
        return $this->getQuery()
            ->where('app_id', $appId)
            ->where('openid', $openid)
            ->first();
    }

    /**
     * 批量刪除前取涉及到的應用標識（用於逐應用失效用戶數緩存，方案 §6.5.2）。
     *
     * @param int[] $ids
     * @return string[]
     */
    public function findAppIdsByIds(array $ids): array
    {
        return $this->getQuery()
            ->whereIn('id', $ids)
            ->distinct()
            ->pluck('app_id')
            ->toArray();
    }

    /**
     * 首頁用戶數統計：按 app_id 過濾 + 排除停用會員（方案 §6.5.4 統計口徑）。
     */
    public function countEnabledByApp(string $appId): int
    {
        return $this->getQuery()
            ->where('app_id', $appId)
            ->where('status', 1)
            ->count();
    }

    /**
     * 定時預熱用：一次 GROUP BY 聚合查出各應用人數（方案 §6.5.3，禁止在循環內逐應用 count()）。
     *
     * @param string[] $appIds
     * @return array<string, int|string>
     */
    public function countEnabledGroupByApp(array $appIds): array
    {
        if ($appIds === []) {
            return [];
        }

        return $this->getQuery()
            ->whereIn('app_id', $appIds)
            ->where('status', 1)
            ->groupBy('app_id')
            ->selectRaw('app_id, COUNT(*) AS cnt')
            ->pluck('cnt', 'app_id')
            ->toArray();
    }
}
