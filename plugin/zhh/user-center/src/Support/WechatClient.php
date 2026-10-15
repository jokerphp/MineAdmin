<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/user-center plugin.
 *
 * 微信小程式接口封裝（方案 §6.2）：jscode2session 換取 openid/session_key。
 * session_key 只存 Redis 不落庫（方案 §5.1、§12）。
 */

namespace Plugin\Zhh\UserCenter\Support;

use App\Exception\BusinessException;
use App\Http\Common\ResultCode;
use Hyperf\Filesystem\FilesystemFactory;
use Hyperf\Guzzle\ClientFactory;
use Hyperf\Redis\Redis;
use Plugin\Zhh\UserCenter\Model\MemberApp;
use Ramsey\Uuid\Uuid;

class WechatClient
{
    /**
     * 微信登錄憑證校驗接口
     */
    private const CODE2SESSION_URL = 'https://api.weixin.qq.com/sns/jscode2session';

    /**
     * 應用憑證接口（取 access_token）
     */
    private const ACCESS_TOKEN_URL = 'https://api.weixin.qq.com/cgi-bin/token';

    /**
     * 不限量小程序碼接口（方案 §7.3 房間邀請）
     */
    private const UNLIMITED_QRCODE_URL = 'https://api.weixin.qq.com/wxa/getwxacodeunlimit';

    /**
     * session_key 緩存時長（秒）。假設值：微信未公佈固定有效期，此處按 7 天緩存，過期後由前端重新 uni.login 換取。
     */
    private const SESSION_KEY_TTL = 604800;

    private const SESSION_KEY_PREFIX = 'uc:session_key:';

    /**
     * access_token 緩存時長（秒）：微信有效期 7200，提前 200 秒過期以避開邊界
     */
    private const ACCESS_TOKEN_TTL = 7000;

    private const ACCESS_TOKEN_PREFIX = 'uc:wx_access_token:';

    /**
     * 程序自產資源磁盤（config/autoload/file.php）：root = storage/resources，公開前綴 = {APP_URL}/resources
     */
    private const RESOURCE_DISK = 'resources';

    /**
     * 小程序碼存放子目錄：resources/qrcode/{日期}/{uuid}.png
     */
    private const QRCODE_DIR = 'qrcode';

    public function __construct(
        private readonly ClientFactory $clientFactory,
        private readonly Redis $redis,
        private readonly FilesystemFactory $filesystemFactory
    ) {}

    /**
     * code 換取 openid / session_key / unionid。
     *
     * @param array{openid: string, session_key: string, unionid: string} $result
     */
    public function code2session(MemberApp $app, string $code): array
    {
        $response = $this->clientFactory->create(['timeout' => 5.0])->get(self::CODE2SESSION_URL, [
            'query' => [
                'appid' => $app->wechat_appid,
                'secret' => $app->wechat_secret,
                'js_code' => $code,
                'grant_type' => 'authorization_code',
            ],
        ]);

        $result = json_decode((string) $response->getBody(), true);

        if (! is_array($result) || ! empty($result['errcode']) || empty($result['openid'])) {
            $message = is_array($result) ? (string) ($result['errmsg'] ?? '') : '';
            throw new BusinessException(
                ResultCode::UNPROCESSABLE_ENTITY,
                $message === '' ? '微信登錄失敗' : '微信登錄失敗：' . $message
            );
        }

        return [
            'openid' => (string) $result['openid'],
            'session_key' => (string) ($result['session_key'] ?? ''),
            'unionid' => (string) ($result['unionid'] ?? ''),
        ];
    }

    /**
     * 緩存 session_key（不入庫）。
     */
    public function cacheSessionKey(string $appId, string $openid, string $sessionKey): void
    {
        if ($sessionKey === '') {
            return;
        }

        $this->redis->set($this->sessionKey($appId, $openid), $sessionKey, self::SESSION_KEY_TTL);
    }

    /**
     * 取小程式 access_token（Redis 緩存 7000s；微信有效期 7200s）。
     */
    public function accessToken(MemberApp $app): string
    {
        $key = self::ACCESS_TOKEN_PREFIX . $app->app_id;
        $cached = $this->redis->get($key);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = $this->clientFactory->create(['timeout' => 5.0])->get(self::ACCESS_TOKEN_URL, [
            'query' => [
                'grant_type' => 'client_credential',
                'appid' => $app->wechat_appid,
                'secret' => $app->wechat_secret,
            ],
        ]);

        $result = json_decode((string) $response->getBody(), true);
        if (! is_array($result) || empty($result['access_token'])) {
            $message = is_array($result) ? (string) ($result['errmsg'] ?? '') : '';
            throw new BusinessException(
                ResultCode::UNPROCESSABLE_ENTITY,
                $message === '' ? '獲取微信 access_token 失敗' : '獲取微信 access_token 失敗：' . $message
            );
        }

        $token = (string) $result['access_token'];
        $this->redis->set($key, $token, self::ACCESS_TOKEN_TTL);

        return $token;
    }

    /**
     * 生成不限量小程序碼（方案 §7.3 房間邀請），返回可直接訪問的圖片 URL。
     *
     * 說明：微信 `wxa/getwxacodeunlimit` 返回的是**圖片二進制流**而非 CDN URL，
     * 因此把二進制落地到 resources 磁盤的 `qrcode/{日期}/{uuid}.png`，再返回 `{APP_URL}/resources/...` 訪問地址，
     * 以符合方案「返回 qr_url」的契約（目錄結構見 config/autoload/file.php 與倉庫根目錄 nginx.config）。
     */
    public function getUnlimitedQRCode(MemberApp $app, string $scene, string $page): string
    {
        $response = $this->clientFactory->create(['timeout' => 10.0])->post(self::UNLIMITED_QRCODE_URL, [
            'query' => ['access_token' => $this->accessToken($app)],
            'json' => [
                'scene' => $scene,
                'page' => $page,
                'check_path' => false,
                'env_version' => 'release',
                'width' => 430,
            ],
        ]);

        $body = (string) $response->getBody();

        // 失敗時微信返回 JSON（errcode/errmsg），成功時返回 PNG 二進制
        $error = json_decode($body, true);
        if (is_array($error) && ! empty($error['errcode'])) {
            throw new BusinessException(
                ResultCode::UNPROCESSABLE_ENTITY,
                '生成小程序碼失敗：' . (string) ($error['errmsg'] ?? (string) $error['errcode'])
            );
        }

        if ($body === '') {
            throw new BusinessException(ResultCode::UNPROCESSABLE_ENTITY, '生成小程序碼失敗：微信未返回圖片數據');
        }

        $path = self::QRCODE_DIR . '/' . date('Y-m-d') . '/' . Uuid::uuid4()->toString() . '.png';

        $filesystem = $this->filesystemFactory->get(self::RESOURCE_DISK);
        $filesystem->write($path, $body);

        return $filesystem->publicUrl($path);
    }

    private function sessionKey(string $appId, string $openid): string
    {
        return self::SESSION_KEY_PREFIX . $appId . ':' . $openid;
    }
}
