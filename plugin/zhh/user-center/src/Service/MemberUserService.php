<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/user-center plugin.
 *
 * 會員業務服務（方案 §6.2 登錄、§6.3 個人資料、§6.5 首頁用戶數）。
 */

namespace Plugin\Zhh\UserCenter\Service;

use App\Exception\BusinessException;
use App\Http\Common\ResultCode;
use App\Service\IService;
use Hyperf\Collection\Arr;
use Lcobucci\JWT\Token\RegisteredClaims;
use Lcobucci\JWT\UnencryptedToken;
use Mine\Jwt\Factory;
use Mine\Jwt\JwtInterface;
use Plugin\Zhh\UserCenter\Model\MemberApp;
use Plugin\Zhh\UserCenter\Model\MemberUser;
use Plugin\Zhh\UserCenter\Repository\MemberAppRepository;
use Plugin\Zhh\UserCenter\Repository\MemberUserRepository;
use Plugin\Zhh\UserCenter\Support\UserCountCache;
use Plugin\Zhh\UserCenter\Support\WechatClient;

/**
 * @extends IService<MemberUser>
 */
final class MemberUserService extends IService
{
    /**
     * 登錄時可寫入的會員字段（方案 §6.2 請求體只有 nickname / avatar）
     */
    private const LOGIN_PROFILE_FIELDS = ['nickname', 'avatar'];

    /**
     * 編輯資料頁可更新字段（方案 §6.3）
     */
    private const PROFILE_FIELDS = ['nickname', 'gender', 'bio', 'avatar', 'background'];

    public function __construct(
        protected readonly MemberUserRepository $repository,
        protected readonly MemberAppRepository $memberAppRepository,
        protected readonly WechatClient $wechatClient,
        protected readonly UserCountCache $userCountCache,
        protected readonly Factory $jwtFactory
    ) {}

    /**
     * 微信一鍵登錄：首次自動註冊、再次登錄復用同一帳號（方案 §6.2）。
     *
     * @param array<string, mixed> $params
     * @return array{token: string, refresh_token: string, expire_at: int, user: array<string, mixed>}
     */
    public function loginWithWechat(array $params, string $ip = ''): array
    {
        [$app, $session] = $this->exchangeSession($params);

        $user = $this->repository->findByOpenid($app->app_id, $session['openid']);

        if ($user === null) {
            // @phpstan-ignore-next-line
            $user = $this->repository->create([
                'app_id' => $app->app_id,
                'openid' => $session['openid'],
                'unionid' => $session['unionid'],
                'nickname' => (string) ($params['nickname'] ?? ''),
                'avatar' => (string) ($params['avatar'] ?? ''),
            ]);
            // 新增會員：首頁用戶數緩存即時失效（方案 §6.5.2）
            $this->clearUserCountCache($app->app_id);
        }

        return $this->issueTokens($this->touchLogin($user, $ip));
    }

    /**
     * 微信靜默登錄（冷啟動自動登錄）：本地登錄態丟失（用戶刪除小程序 / 長期未進入致 token 失效）時，
     * 用 code 換 openid 判斷該會員是否已使用過本小程序。
     *
     * 已註冊：直接復用原帳號簽發令牌，前端無需再填資料；未註冊：**不建號**，返回 registered=false 交回前端走原有登錄流程。
     *
     * @param array<string, mixed> $params
     * @return array{registered: bool, token?: string, refresh_token?: string, expire_at?: int, user?: array<string, mixed>}
     */
    public function silentLogin(array $params, string $ip = ''): array
    {
        [$app, $session] = $this->exchangeSession($params);

        $user = $this->repository->findByOpenid($app->app_id, $session['openid']);
        if ($user === null) {
            return ['registered' => false];
        }

        return ['registered' => true] + $this->issueTokens($this->touchLogin($user, $ip));
    }

    /**
     * 登出：token 加入黑名單（方案 §6.2）。
     */
    public function logout(UnencryptedToken $token): void
    {
        $this->getJwt()->addBlackList($token);
    }

    /**
     * 刷新 token（沿用 MineAdmin 的做法：舊 token 進黑名單，簽發新的一對）。
     *
     * @return array{token: string, refresh_token: string, expire_at: int}
     */
    public function refresh(UnencryptedToken $token): array
    {
        $jwt = $this->getJwt();
        $jwt->addBlackList($token);

        return [
            'token' => $jwt->builderAccessToken((string) $token->claims()->get(RegisteredClaims::ID))->toString(),
            'refresh_token' => $jwt->builderRefreshToken((string) $token->claims()->get(RegisteredClaims::ID))->toString(),
            'expire_at' => (int) $jwt->getConfig('ttl', 0),
        ];
    }

    /**
     * 更新個人資料，返回最新會員資料（方案 §6.3）。
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function updateProfile(MemberUser $user, array $data): array
    {
        $fields = Arr::only($data, self::PROFILE_FIELDS);

        if ($fields !== []) {
            // @phpstan-ignore-next-line
            $user = $this->repository->saveById($user->id, $fields) ?? $user;
        }

        return $this->formatUser($user);
    }

    /**
     * 對外輸出的會員資料（首頁/我的/編輯資料共用同一份結構，方案 §6.2、§6.3）。
     *
     * @return array<string, mixed>
     */
    public function formatUser(MemberUser $user): array
    {
        return [
            'id' => (int) $user->id,
            'app_id' => (string) $user->app_id,
            'nickname' => (string) $user->nickname,
            'avatar' => (string) $user->avatar,
            'background' => (string) $user->background,
            'gender' => (int) $user->gender,
            'bio' => (string) $user->bio,
            'phone' => (string) $user->phone,
            'created_at' => $user->created_at?->toDateTimeString(),
        ];
    }

    /**
     * 首頁用戶數：讀路徑只讀緩存，未命中才用單飛鎖回源一次（方案 §6.5.3）。
     *
     * @return array{count: int}
     */
    public function userCount(string $appId): array
    {
        $cached = $this->userCountCache->get($appId);
        if ($cached !== false) {
            return ['count' => $cached];
        }

        $count = $this->userCountCache->stale($appId);

        if ($this->userCountCache->lock($appId)) {
            try {
                $count = $this->repository->countEnabledByApp($appId);
                $this->userCountCache->put($appId, $count);
            } finally {
                $this->userCountCache->unlock($appId);
            }
        }

        return ['count' => $count];
    }

    /**
     * 會員增刪改後即時失效用戶數緩存（方案 §6.5.2 寫操作即時失效）。
     */
    public function clearUserCountCache(string $appId): void
    {
        $this->userCountCache->forget($appId);
    }

    /**
     * 後台：會員詳情（方案 §6.4）。
     *
     * @return array<string, mixed>
     */
    public function detail(int $id): array
    {
        $member = $this->repository->findById($id);
        if (! $member instanceof MemberUser) {
            throw new BusinessException(ResultCode::NOT_FOUND, '會員不存在');
        }

        return $member->toArray();
    }

    /**
     * 後台：更新會員（含停用/啟用，方案 §6.4）。
     *
     * @param array<string, mixed> $data
     */
    public function updateMember(int $id, array $data): void
    {
        $member = $this->repository->findById($id);
        if (! $member instanceof MemberUser) {
            throw new BusinessException(ResultCode::NOT_FOUND, '會員不存在');
        }

        if ($data !== []) {
            $this->repository->updateById($id, $data);
        }

        // 停用/啟用會改變用戶數統計口徑 → 即時失效（方案 §6.5.2）
        if (array_key_exists('status', $data)) {
            $this->clearUserCountCache((string) $member->app_id);
        }
    }

    /**
     * 後台：批量刪除會員（軟刪除），返回刪除條數（方案 §6.4）。
     *
     * @param int[] $ids
     */
    public function deleteMembers(array $ids): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return 0;
        }

        $appIds = $this->repository->findAppIdsByIds($ids);
        $count = $this->repository->deleteById($ids);

        // 刪除會員會改變用戶數 → 即時失效（方案 §6.5.2）。循環內僅操作 Redis，無數據庫操作。
        foreach ($appIds as $appId) {
            $this->clearUserCountCache($appId);
        }

        return $count;
    }

    /**
     * 校驗應用合法性 + 用微信 code 換取會話，並緩存 session_key（不入庫，方案 §6.2）。
     *
     * @param array<string, mixed> $params
     * @return array{0: MemberApp, 1: array{openid: string, session_key: string, unionid: string}}
     */
    private function exchangeSession(array $params): array
    {
        $app = $this->memberAppRepository->findEnabledByAppId((string) $params['app_id']);
        if ($app === null) {
            throw new BusinessException(ResultCode::UNPROCESSABLE_ENTITY, '應用不存在或已停用');
        }

        $session = $this->wechatClient->code2session($app, (string) $params['code']);
        $this->wechatClient->cacheSessionKey($app->app_id, $session['openid'], $session['session_key']);

        return [$app, $session];
    }

    /**
     * 記錄本次登錄（次數 / 時間 / IP）。
     */
    private function touchLogin(MemberUser $user, string $ip): MemberUser
    {
        // @phpstan-ignore-next-line
        return $this->repository->saveById($user->id, [
            'login_count' => (int) $user->login_count + 1,
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => $ip,
        ]) ?? $user;
    }

    /**
     * 簽發令牌（access + refresh）並附帶會員資料。
     *
     * @return array{token: string, refresh_token: string, expire_at: int, user: array<string, mixed>}
     */
    private function issueTokens(MemberUser $user): array
    {
        $jwt = $this->getJwt();

        return [
            'token' => $jwt->builderAccessToken((string) $user->id)->toString(),
            'refresh_token' => $jwt->builderRefreshToken((string) $user->id)->toString(),
            'expire_at' => (int) $jwt->getConfig('ttl', 0),
            'user' => $this->formatUser($user),
        ];
    }

    private function getJwt(): JwtInterface
    {
        return $this->jwtFactory->get('default');
    }
}
