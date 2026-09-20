<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * 對局成員快照模型（方案 §5.2 scoring_battle_member）。
 * total_score 為分段口徑，多次快照求和即該成員全程總成績。
 */

namespace Plugin\Zhh\Scoring\Model;

use Carbon\Carbon;
use Hyperf\DbConnection\Model\Model;

/**
 * @property int $id 主鍵
 * @property int $battle_id 對局ID
 * @property int $user_id 用戶ID
 * @property string $nickname 結算時昵稱快照
 * @property string $avatar 結算時頭像快照
 * @property string $total_score 結算快照分數
 * @property int $rank 結算時相對排名
 * @property int $result 結果:1=勝,2=負,3=平
 * @property string $tea_fee 茶水費
 * @property int $settle_type 結算類型:1=整局結算,2=單獨結算
 * @property null|Carbon $settled_at 結算時間
 */
final class BattleMember extends Model
{
    protected ?string $table = 'scoring_battle_member';

    /**
     * @var string[]
     */
    protected array $fillable = [
        'id',
        'battle_id',
        'user_id',
        'nickname',
        'avatar',
        'total_score',
        'rank',
        'result',
        'tea_fee',
        'settle_type',
        'settled_at',
        'created_at',
        'updated_at',
    ];

    /**
     * @var array<string, string>
     */
    protected array $casts = [
        'id' => 'integer',
        'battle_id' => 'integer',
        'user_id' => 'integer',
        'rank' => 'integer',
        'result' => 'integer',
        'settle_type' => 'integer',
        'settled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
