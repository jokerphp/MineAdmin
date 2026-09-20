<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/user-center plugin.
 *
 * 會員資料 Swagger 響應 Schema（方案 §6.1）——僅用於接口文檔，實際響應由 Service::formatUser() 組裝。
 */

namespace Plugin\Zhh\UserCenter\Schema;

use Hyperf\Swagger\Annotation as OA;

#[
    OA\Schema(
        description: '會員資料',
        example: '{"id":1,"app_id":"scoring_wx","nickname":"張三","avatar":"https://example.com/a.png","background":"","gender":1,"bio":"","phone":"","status":1,"login_count":3,"last_login_at":"2026-09-18 10:00:00","created_at":"2026-09-18 09:00:00"}'
    )
]
final class MemberUserSchema
{
    #[OA\Property(property: 'id', description: '會員ID', type: 'integer', example: 1)]
    public int $id;

    #[OA\Property(property: 'app_id', description: '所屬應用標識', type: 'string', example: 'scoring_wx')]
    public string $app_id;

    #[OA\Property(property: 'openid', description: '微信openid', type: 'string')]
    public string $openid;

    #[OA\Property(property: 'nickname', description: '昵稱', type: 'string', example: '張三')]
    public string $nickname;

    #[OA\Property(property: 'avatar', description: '頭像URL', type: 'string')]
    public string $avatar;

    #[OA\Property(property: 'background', description: '背景圖URL', type: 'string')]
    public string $background;

    #[OA\Property(property: 'gender', description: '性別:0=未知,1=男,2=女', type: 'integer', example: 0)]
    public int $gender;

    #[OA\Property(property: 'bio', description: '簡介', type: 'string')]
    public string $bio;

    #[OA\Property(property: 'phone', description: '手機號', type: 'string')]
    public string $phone;

    #[OA\Property(property: 'status', description: '狀態:1=正常,2=停用', type: 'integer', example: 1)]
    public int $status;

    #[OA\Property(property: 'login_count', description: '登錄次數', type: 'integer', example: 1)]
    public int $login_count;

    #[OA\Property(property: 'last_login_at', description: '最後登錄時間', type: 'string')]
    public string $last_login_at;

    #[OA\Property(property: 'created_at', description: '註冊時間', type: 'string')]
    public string $created_at;
}
