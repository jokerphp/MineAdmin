<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * 對局/戰績主表模型（方案 §5.2 scoring_battle）。
 */

namespace Plugin\Zhh\Scoring\Model;

use Carbon\Carbon;
use Hyperf\DbConnection\Model\Model;

/**
 * @property int $id 主鍵
 * @property int $room_id 來源房間ID
 * @property string $room_no 房號冗余
 * @property int $owner_id 房主
 * @property int $status 狀態:0=進行中,1=已結束,2=異常作廢
 * @property int $total_rounds 總輪次
 * @property string $tea_fee_total 茶水費合計
 * @property null|Carbon $started_at 開始時間
 * @property null|Carbon $finished_at 結束時間
 */
final class Battle extends Model
{
    protected ?string $table = 'scoring_battle';

    /**
     * @var string[]
     */
    protected array $fillable = [
        'id',
        'room_id',
        'room_no',
        'owner_id',
        'status',
        'total_rounds',
        'tea_fee_total',
        'started_at',
        'finished_at',
        'created_at',
        'updated_at',
    ];

    /**
     * @var array<string, string>
     */
    protected array $casts = [
        'id' => 'integer',
        'room_id' => 'integer',
        'owner_id' => 'integer',
        'status' => 'integer',
        'total_rounds' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
