<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/user-center plugin.
 *
 * 登錄響應 Swagger Schema（方案 §6.2）。
 */

namespace Plugin\Zhh\UserCenter\Schema;

use Hyperf\Swagger\Annotation as OA;

#[
    OA\Schema(
        description: '登錄成功返回',
        example: '{"token":"eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.xxx","refresh_token":"eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.yyy","expire_at":3600,"user":{"id":1,"nickname":"張三"}}'
    )
]
final class LoginResultSchema
{
    #[OA\Property(property: 'token', description: '訪問令牌（放入 Authorization: Bearer {token}）', type: 'string')]
    public string $token;

    #[OA\Property(property: 'refresh_token', description: '刷新令牌（token 過期後用於調用 /api/v1/user/refresh）', type: 'string')]
    public string $refresh_token;

    #[OA\Property(property: 'expire_at', description: '訪問令牌有效期，單位秒', type: 'integer', example: 3600)]
    public int $expire_at;

    #[OA\Property(property: 'user', ref: '#/components/schemas/MemberUserSchema', description: '會員資料')]
    public MemberUserSchema $user;
}
