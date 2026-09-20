<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/user-center plugin.
 *
 * 插件卸載腳本（方案 §4.2）：回滾本插件寫入的菜單/權限與數據表，
 * 計分業務表由 zhh/scoring 插件自行卸載。
 */

namespace Plugin\Zhh\UserCenter;

use App\Model\Permission\Menu;
use Hyperf\Context\ApplicationContext;
use Hyperf\Database\Schema\Schema;
use Hyperf\Logger\LoggerFactory;
use Throwable;

class UninstallScript
{
    /**
     * 本插件在 menu 表註冊的菜單與權限碼（與 Database/Seeders/MenuSeeder.php 保持一致）
     */
    private const MENU_NAMES = [
        'userCenter',
        'userCenter:member',
        'userCenter:member:index',
        'userCenter:member:update',
        'userCenter:member:delete',
        'userCenter:app',
        'userCenter:app:index',
        'userCenter:app:create',
        'userCenter:app:update',
        'userCenter:app:delete',
    ];

    /**
     * MineAdmin 文檔（插件生命週期）約定的卸載入口，返回 true 才視為卸載成功。
     */
    public function handle(): bool
    {
        try {
            $this->cleanMenuPermissions();
            $this->dropTables();
            echo "zhh/user-center 插件卸載完成\n";
            return true;
        } catch (Throwable $e) {
            ApplicationContext::getContainer()
                ->get(LoggerFactory::class)
                ->get('error')
                ->error('卸載 zhh/user-center 失敗：' . $e->getMessage(), ['exception' => $e]);

            return false;
        }
    }

    /**
     * 保留 mine-extension:create 生成的骨架入口，避免與框架實際調用約定（handle / __invoke）不一致導致腳本不被執行。
     */
    public function __invoke(): bool
    {
        return $this->handle();
    }

    /**
     * 清理菜單與權限碼（方案 §8.3）：一次查出 id 後交給 Model::destroy 批量刪除，
     * 使 Menu 模型的刪除事件可以聯動清理角色綁定。
     */
    protected function cleanMenuPermissions(): void
    {
        $menuIds = Menu::getModel()->newQuery()
            ->whereIn('name', self::MENU_NAMES)
            ->pluck('id')
            ->toArray();

        if ($menuIds !== []) {
            Menu::destroy($menuIds);
        }
    }

    protected function dropTables(): void
    {
        // 先刪子表再刪主表，避免殘留引用
        Schema::dropIfExists('member_user');
        Schema::dropIfExists('member_app');

        echo "已刪除數據表：member_user、member_app\n";
    }
}
