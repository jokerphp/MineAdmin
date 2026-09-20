<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * 流水類型（方案 §5.2 scoring_record.record_type）。
 */

namespace Plugin\Zhh\Scoring\Enum;

final class RecordType
{
    /**
     * 計分（轉賬式零和：付款方 −amount、收款方 +amount）
     */
    public const SCORE = 1;

    /**
     * 支出 / 茶水費
     */
    public const EXPENSE = 2;

    /**
     * 結算清算（淨額轉賬，配套 scoring_settle_transfer）
     */
    public const SETTLE = 3;
}
