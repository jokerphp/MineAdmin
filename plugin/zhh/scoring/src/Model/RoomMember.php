<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * 房間成員模型（方案 §5.2 scoring_room_member）。
 * score 不做 cast：decimal 欄位由驅動返回字符串，結算金額計算統一用 bcmath（bcadd/bcsub），避免浮點誤差。
 */

namespace Plugin\Zhh\Scoring\Model;

use Carbon\Carbon;
use Hyperf\DbConnection\Model\Model;

/**
 * @property int $id 主鍵
 * @property int $room_id 房間ID
 * @property int $user_id 用戶ID
 * @property string $nickname 進房時昵稱快照
 * @property string $avatar 進房時頭像快照
 * @property string $score 當前未清算餘額
 * @property int $role 角色:0=普通成員,1=房主
 * @property int $status 狀態:0=在房,1=已退出,2=已單獨結算
 * @property null|Carbon $settled_at 單獨結算時間
 * @property null|Carbon $joined_at 進房時間
 * @property null|Carbon $left_at 離開時間
 */
final class RoomMember extends Model
{
    protected ?string $table = 'scoring_room_member';

    /**
     * @var string[]
     */
    protected array $fillable = [
        'id',
        'room_id',
        'user_id',
        'nickname',
        'avatar',
        'score',
        'role',
        'status',
        'settled_at',
        'joined_at',
        'left_at',
        'created_at',
        'updated_at',
    ];

    /**
     * @var array<string, string>
     */
    protected array $casts = [
        'id' => 'integer',
        'room_id' => 'integer',
        'user_id' => 'integer',
        'role' => 'integer',
        'status' => 'integer',
        'settled_at' => 'datetime',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
