<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/user-center plugin.
 *
 * 插件安裝腳本（方案 §4.2）：按官方規範「不使用 ConfigProvider 的 publish」，
 * 數據庫遷移與初始化數據統一在安裝腳本中執行（Database/Migrations、Database/Seeders 均不會自動執行）。
 */

namespace Plugin\Zhh\UserCenter;

use Hyperf\Contract\ApplicationInterface;
use Hyperf\Context\ApplicationContext;
use Hyperf\Database\Seeders\Seed;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

class InstallScript
{
    /**
     * MineAdmin 文檔（插件生命週期）約定的安裝入口，返回 true 才會生成 install.lock。
     */
    public function handle(): bool
    {
        if (! $this->checkEnvironment()) {
            echo "安裝失敗：環境不滿足要求\n";
            return false;
        }

        if (! $this->runMigrations()) {
            echo "安裝失敗：數據庫遷移未通過\n";
            return false;
        }

        if (! $this->seedData()) {
            echo "安裝失敗：初始化數據未通過\n";
            return false;
        }

        echo "zhh/user-center 插件安裝完成\n";
        return true;
    }

    /**
     * 保留 mine-extension:create 生成的骨架入口，避免與框架實際調用約定（handle / __invoke）不一致導致腳本不被執行。
     */
    public function __invoke(): bool
    {
        return $this->handle();
    }

    protected function checkEnvironment(): bool
    {
        if (version_compare(PHP_VERSION, '8.2.0', '<')) {
            echo '需要 PHP >= 8.2，當前版本：' . PHP_VERSION . "\n";
            return false;
        }

        return true;
    }

    /**
     * 執行插件自帶的遷移文件（Database/Migrations/*.php）。
     *
     * 注：這些遷移不會被框架自動執行，必須在此顯式觸發（官方文檔給出的方式為控制台 migrate --path）。
     */
    protected function runMigrations(): bool
    {
        $migrationPath = dirname(__DIR__) . '/Database/Migrations';
        if (! is_dir($migrationPath)) {
            return true;
        }

        /** @var Application $application 容器綁定的是 Hyperf\Framework\Application（繼承 Symfony Console Application） */
        $application = ApplicationContext::getContainer()->get(ApplicationInterface::class);
        $application->setAutoExit(false);

        $output = new BufferedOutput();
        $exitCode = $application->run(new ArrayInput([
            'command' => 'migrate',
            '--path' => $migrationPath,
        ]), $output);

        if ($exitCode !== 0) {
            echo $output->fetch();
            return false;
        }

        echo "數據庫遷移完成\n";
        return true;
    }

    /**
     * 執行插件自帶的 Seeder（Database/Seeders/*.php，菜單與權限初始化，方案 §8.3）。
     * 目錄為空時直接跳過，不報錯。
     *
     * 【為何不用 `db:seed --path=...`】SeedCommand 會把框架預設 seeder 目錄
     * （BASE_PATH/databases/seeders）一併納入執行，其中：
     *   - menu_seeder_20240926.php 首行就是 Menu::truncate()（清空整張 menu 表）
     *   - user_seeder_20240926.php 會 User::truncate() + Role::truncate()
     * 造成的實際後果是：每次安裝插件都會清空菜單（含其他插件已註冊的菜單）。
     * 因此改為直接呼叫 Hyperf 的 Seed 服務，只掃描本插件目錄，完全不碰 app 自帶 seeder。
     */
    protected function seedData(): bool
    {
        $seederPath = dirname(__DIR__) . '/Database/Seeders';
        if (! is_dir($seederPath) || glob($seederPath . '/*.php') === []) {
            return true;
        }

        try {
            ApplicationContext::getContainer()->get(Seed::class)->run([$seederPath]);
        } catch (Throwable $e) {
            echo '初始化數據失敗：' . $e->getMessage() . "\n";
            return false;
        }

        echo "初始化數據完成\n";
        return true;
    }
}
