<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/user-center plugin.
 *
 * 應用（小程序/程序）Swagger Schema（方案 §6.1、§6.4）。
 */

namespace Plugin\Zhh\UserCenter\Schema;

use Hyperf\Swagger\Annotation as OA;

#[
    OA\Schema(
        description: '應用（小程序）資料',
        example: '{"id":1,"app_id":"scoring_wx","app_name":"打牌記賬-微信小程式","app_type":"wechat_mini_program","wechat_appid":"wx123","status":1,"remark":""}'
    )
]
final class MemberAppSchema
{
    #[OA\Property(property: 'id', description: '應用ID', type: 'integer', example: 1)]
    public int $id;

    #[OA\Property(property: 'app_id', description: '應用唯一標識', type: 'string', example: 'scoring_wx')]
    public string $app_id;

    #[OA\Property(property: 'app_name', description: '應用名稱', type: 'string')]
    public string $app_name;

    #[OA\Property(property: 'app_type', description: '應用類型:wechat_mini_program/other', type: 'string')]
    public string $app_type;

    #[OA\Property(property: 'wechat_appid', description: '微信小程序appid', type: 'string')]
    public string $wechat_appid;

    #[OA\Property(property: 'status', description: '狀態:1=啟用,2=停用', type: 'integer', example: 1)]
    public int $status;

    #[OA\Property(property: 'remark', description: '備註', type: 'string')]
    public string $remark;

    #[OA\Property(property: 'created_at', description: '創建時間', type: 'string')]
    public string $created_at;
}
