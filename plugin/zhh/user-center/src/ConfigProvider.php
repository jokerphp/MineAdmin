<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/user-center plugin.
 *
 * 插件啟動配置：把插件 src/ 目錄納入註解掃描（路由、中間件、權限碼、Crontab 均依賴註解）。
 * 全局 config/autoload/annotations.php 只掃描 app 與 kernel，插件路徑必須由本類返回合併（方案 §4.4）。
 */

namespace Plugin\Zhh\UserCenter;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            // 合併到 config/autoload/annotations.php 的 scan.paths
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
        ];
    }
}
