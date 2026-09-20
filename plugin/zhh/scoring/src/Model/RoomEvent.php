<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * 房間系統事件模型（方案 §5.2 scoring_room_event）：過程時間線還原用，只增不改。
 */

namespace Plugin\Zhh\Scoring\Model;

use Carbon\Carbon;
use Hyperf\DbConnection\Model\Model;

/**
 * @property int $id 主鍵
 * @property int $room_id 房間ID
 * @property string $room_no 房號冗余
 * @property null|int $battle_id 所屬對局ID
 * @property int $event_type 類型:1=進入,2=離開,3=房主接管,4=單獨結算,5=整局結算
 * @property int $user_id 主體
 * @property string $nickname 主體昵稱快照
 * @property null|int $target_user_id 對象
 * @property null|string $target_nickname 對象昵稱快照
 * @property string $remark 備註
 * @property Carbon $created_at 事件時間
 */
final class RoomEvent extends Model
{
    protected ?string $table = 'scoring_room_event';

    /**
     * @var string[]
     */
    protected array $fillable = [
        'id',
        'room_id',
        'room_no',
        'battle_id',
        'event_type',
        'user_id',
        'nickname',
        'target_user_id',
        'target_nickname',
        'remark',
        'created_at',
        'updated_at',
    ];

    /**
     * @var array<string, string>
     */
    protected array $casts = [
        'id' => 'integer',
        'room_id' => 'integer',
        'battle_id' => 'integer',
        'event_type' => 'integer',
        'user_id' => 'integer',
        'target_user_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
