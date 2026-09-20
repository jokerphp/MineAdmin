<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * 計分/支出流水模型（方案 §5.2 scoring_record）：只增不改，作為對帳依據（方案 §12 刪除保護）。
 */

namespace Plugin\Zhh\Scoring\Model;

use Carbon\Carbon;
use Hyperf\DbConnection\Model\Model;

/**
 * @property int $id 主鍵
 * @property int $room_id 房間ID
 * @property string $room_no 房號冗余
 * @property null|int $battle_id 所屬對局ID
 * @property null|int $round_no 所屬輪次
 * @property int $user_id 操作人（1/3為付款方）
 * @property null|int $target_user_id 收款方
 * @property int $record_type 類型:1=計分,2=支出/茶水,3=結算清算
 * @property string $amount 金額/分數（恆為正）
 * @property string $remark 備註
 * @property int $created_by 創建人
 */
final class Record extends Model
{
    protected ?string $table = 'scoring_record';

    /**
     * @var string[]
     */
    protected array $fillable = [
        'id',
        'room_id',
        'room_no',
        'battle_id',
        'round_no',
        'user_id',
        'target_user_id',
        'record_type',
        'amount',
        'remark',
        'created_by',
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
        'round_no' => 'integer',
        'user_id' => 'integer',
        'target_user_id' => 'integer',
        'record_type' => 'integer',
        'created_by' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
