<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/user-center plugin.
 *
 * 應用（小程序/程序）註冊表模型（方案 §5.1 member_app）。
 */

namespace Plugin\Zhh\UserCenter\Model;

use Carbon\Carbon;
use Hyperf\DbConnection\Model\Model;

/**
 * @property int $id 應用ID,主鍵
 * @property string $app_id 應用唯一標識
 * @property string $app_name 應用名稱
 * @property string $app_type 應用類型
 * @property string $wechat_appid 微信小程序appid
 * @property string $wechat_secret 微信小程序secret
 * @property int $status 狀態:1=啟用,2=停用
 * @property string $remark 備註
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class MemberApp extends Model
{
    /**
     * The table associated with the model.
     */
    protected ?string $table = 'member_app';

    /**
     * 隱藏字段：微信 secret 不對外返回（方案 §12 敏感配置）。
     *
     * @var string[]
     */
    protected array $hidden = ['wechat_secret'];

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected array $fillable = [
        'id',
        'app_id',
        'app_name',
        'app_type',
        'wechat_appid',
        'wechat_secret',
        'status',
        'remark',
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected array $casts = [
        'id' => 'integer',
        'status' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
