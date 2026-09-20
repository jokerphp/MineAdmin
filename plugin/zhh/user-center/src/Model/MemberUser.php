<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/user-center plugin.
 *
 * 會員用戶模型（方案 §5.1 member_user）：
 * 以 (app_id, openid) 唯一定位同一應用下的同一微信用戶，多應用共用本表。
 */

namespace Plugin\Zhh\UserCenter\Model;

use Carbon\Carbon;
use Hyperf\Database\Model\SoftDeletes;
use Hyperf\DbConnection\Model\Model;

/**
 * @property int $id 用戶ID,主鍵
 * @property string $app_id 所屬應用標識
 * @property string $openid 微信openid
 * @property string $unionid 微信unionid
 * @property string $nickname 昵稱
 * @property string $avatar 頭像
 * @property string $background 背景圖
 * @property int $gender 性別:0=未知,1=男,2=女
 * @property string $bio 簡介
 * @property string $phone 手機號
 * @property int $status 狀態:1=正常,2=停用
 * @property int $login_count 登錄次數
 * @property null|Carbon $last_login_at 最後登錄時間
 * @property string $last_login_ip 最後登錄IP
 * @property string $remark 備註
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property null|Carbon $deleted_at
 */
final class MemberUser extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected ?string $table = 'member_user';

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected array $fillable = [
        'id',
        'app_id',
        'openid',
        'unionid',
        'nickname',
        'avatar',
        'background',
        'gender',
        'bio',
        'phone',
        'status',
        'login_count',
        'last_login_at',
        'last_login_ip',
        'remark',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected array $casts = [
        'id' => 'integer',
        'gender' => 'integer',
        'status' => 'integer',
        'login_count' => 'integer',
        'last_login_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
