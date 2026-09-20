<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/user-center plugin.
 *
 * 首頁用戶數緩存定時預熱（方案 §6.5.3）：每日低峰重算並 SET（不 DEL，避免空窗期回源）。
 */

namespace Plugin\Zhh\UserCenter\Crontab;

use Hyperf\Crontab\Annotation\Crontab;
use Plugin\Zhh\UserCenter\Repository\MemberAppRepository;
use Plugin\Zhh\UserCenter\Repository\MemberUserRepository;
use Plugin\Zhh\UserCenter\Support\UserCountCache;

#[Crontab(
    name: 'UserCountWarmup',
    rule: '0 3 * * *',
    callback: 'execute',
    memo: '首頁用戶數緩存預熱',
    singleton: true,
    onOneServer: true
)]
final class UserCountWarmupCrontab
{
    public function __construct(
        private readonly MemberAppRepository $memberAppRepository,
        private readonly MemberUserRepository $memberUserRepository,
        private readonly UserCountCache $userCountCache
    ) {}

    public function execute(): void
    {
        $appIds = $this->memberAppRepository->enabledAppIds();
        if ($appIds === []) {
            return;
        }

        // 一次 GROUP BY 聚合查出各應用人數（方案 §1.3：禁止在循環內操作數據庫）
        $counts = $this->memberUserRepository->countEnabledGroupByApp($appIds);

        // 循環體內僅寫 Redis
        foreach ($appIds as $appId) {
            $this->userCountCache->put($appId, (int) ($counts[$appId] ?? 0));
        }
    }
}
