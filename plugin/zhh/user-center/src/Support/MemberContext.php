<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/user-center plugin.
 *
 * 當前登錄會員上下文（方案 §12 接口越權）：身份一律取自 JWT 的 uid，不信賴客戶端傳參。
 * 說明：受限於 vendor 未安裝、無法核實自定義 claim 支持情況，app_id 不寫入 claim，
 * 統一由 token 的 uid 反查 member_user 得到（見方案 §6.2 JWT 簽發的落地說明）。
 */

namespace Plugin\Zhh\UserCenter\Support;

use App\Exception\BusinessException;
use App\Http\Common\ResultCode;
use Hyperf\Context\Context;
use Lcobucci\JWT\Token\RegisteredClaims;
use Mine\Jwt\Traits\RequestScopedTokenTrait;
use Plugin\Zhh\UserCenter\Model\MemberUser;
use Plugin\Zhh\UserCenter\Repository\MemberUserRepository;

final class MemberContext
{
    use RequestScopedTokenTrait;

    private const CONTEXT_KEY = 'member_user';

    public function __construct(private readonly MemberUserRepository $memberUserRepository) {}

    /**
     * 當前會員 ID（JWT uid claim）。
     */
    public function id(): int
    {
        return (int) $this->getToken()->claims()->get(RegisteredClaims::ID);
    }

    public function user(): ?MemberUser
    {
        if (Context::has(self::CONTEXT_KEY)) {
            /** @var null|MemberUser $user */
            $user = Context::get(self::CONTEXT_KEY);
            return $user;
        }

        // @phpstan-ignore-next-line
        $user = $this->memberUserRepository->findById($this->id());
        Context::set(self::CONTEXT_KEY, $user);

        return $user;
    }

    /**
     * 取當前會員，不存在或已停用時拋出 401。
     */
    public function userOrFail(): MemberUser
    {
        $user = $this->user();

        if (! $user instanceof MemberUser || (int) $user->status !== 1) {
            throw new BusinessException(ResultCode::UNAUTHORIZED, '會員不存在或已停用');
        }

        return $user;
    }
}
