<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * 結算清算轉賬方案模型（方案 §5.2 scoring_settle_transfer）：全端展示的唯一數據來源。
 */

namespace Plugin\Zhh\Scoring\Model;

use Carbon\Carbon;
use Hyperf\DbConnection\Model\Model;

/**
 * @property int $id 主鍵
 * @property int $room_id 房間ID
 * @property int $battle_id 對局ID
 * @property int $settle_type 結算類型:1=整局結算,2=單獨結算
 * @property int $from_user_id 付款方
 * @property int $to_user_id 收款方
 * @property string $amount 清算金額
 */
final class SettleTransfer extends Model
{
    protected ?string $table = 'scoring_settle_transfer';

    /**
     * @var string[]
     */
    protected array $fillable = [
        'id',
        'room_id',
        'battle_id',
        'settle_type',
        'from_user_id',
        'to_user_id',
        'amount',
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
        'settle_type' => 'integer',
        'from_user_id' => 'integer',
        'to_user_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
