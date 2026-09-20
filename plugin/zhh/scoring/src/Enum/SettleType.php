<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * 結算類型（方案 §5.2 scoring_battle_member.settle_type / scoring_settle_transfer.settle_type）。
 */

namespace Plugin\Zhh\Scoring\Enum;

final class SettleType
{
    /**
     * 整局結算
     */
    public const BATTLE = 1;

    /**
     * 單獨結算
     */
    public const MEMBER = 2;
}
