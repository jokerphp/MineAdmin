<?php

declare(strict_types=1);
/**
 * 反代（Nginx → Hyperf 9501）场景下信任本机代理，使 getClientIps() 解析 X-Forwarded-For。
 *
 * 背景：Mine\Support\Request\ClientIpRequestTrait 只有在 setTrustedProxies() 被调用后
 * 才会读取 X-Forwarded-For，否则 getClientIps() 永远返回 TCP 对端地址（127.0.0.1）。
 * 注意：trait 的静态属性按「使用该 trait 的类」各自独立，因此每个请求类都要单独设置。
 */

namespace App\Listener;

use App\Http\Admin\Request\PassportLoginRequest;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BootApplication;
use Mine\Support\Request;
use Mine\Support\Request\ClientIpRequestConstant;
use Plugin\Zhh\UserCenter\Request\Api\LoginRequest;

final class TrustedProxyListener implements ListenerInterface
{
    /**
     * 只信任本机 / 内网反代；公网 IP 不在其中，避免 X-Forwarded-For 被伪造。
     */
    private const TRUSTED_PROXIES = ['127.0.0.1', '::1', 'private_ranges'];

    public function listen(): array
    {
        return [
            BootApplication::class,
        ];
    }

    public function process(object $event): void
    {
        if (! $event instanceof BootApplication) {
            return;
        }

        $classes = [
            Request::class,               // OperationMiddleware 等直接使用
            PassportLoginRequest::class,  // 后台登录
            LoginRequest::class,          // 小程序登录
        ];

        foreach ($classes as $class) {
            if (class_exists($class) && method_exists($class, 'setTrustedProxies')) {
                $class::setTrustedProxies(self::TRUSTED_PROXIES, ClientIpRequestConstant::HEADER_X_FORWARDED_FOR);
            }
        }
    }
}
