<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/user-center plugin.
 *
 * 應用註冊表數據倉庫（方案 §6.2 校驗應用合法性、§6.5.3 預熱取啟用應用列表）。
 */

namespace Plugin\Zhh\UserCenter\Repository;

use App\Repository\IRepository;
use Hyperf\Collection\Arr;
use Hyperf\Database\Model\Builder;
use Plugin\Zhh\UserCenter\Model\MemberApp;

/**
 * Class MemberAppRepository.
 * @extends IRepository<MemberApp>
 */
final class MemberAppRepository extends IRepository
{
    public function __construct(protected readonly MemberApp $model) {}

    public function handleSearch(Builder $query, array $params): Builder
    {
        return $query
            ->when(Arr::get($params, 'app_id'), static function (Builder $query, $appId) {
                $query->where('app_id', $appId);
            })
            ->when(Arr::get($params, 'app_name'), static function (Builder $query, $appName) {
                $query->where('app_name', 'like', '%' . $appName . '%');
            })
            ->when(Arr::exists($params, 'status'), static function (Builder $query) use ($params) {
                $query->where('status', Arr::get($params, 'status'));
            });
    }

    /**
     * 按應用標識取啟用中的應用（登錄時校驗應用合法性，方案 §6.2）。
     */
    public function findEnabledByAppId(string $appId): ?MemberApp
    {
        // @phpstan-ignore-next-line
        return $this->getQuery()
            ->where('app_id', $appId)
            ->where('status', 1)
            ->first();
    }

    /**
     * 系統默認應用標識（首頁用戶數接口未傳 app_id 時使用，方案 §6.5）。
     */
    public function defaultAppId(): string
    {
        return (string) $this->getQuery()
            ->where('status', 1)
            ->orderBy('id')
            ->value('app_id');
    }

    /**
     * 啟用中的應用標識列表（用戶數緩存定時預熱用，方案 §6.5.3）。
     *
     * @return string[]
     */
    public function enabledAppIds(): array
    {
        return $this->getQuery()
            ->where('status', 1)
            ->pluck('app_id')
            ->toArray();
    }
}
