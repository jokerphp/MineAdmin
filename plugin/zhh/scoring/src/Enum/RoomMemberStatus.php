<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * 房間成員狀態（方案 §5.2 scoring_room_member.status）。
 */

namespace Plugin\Zhh\Scoring\Enum;

final class RoomMemberStatus
{
    /**
     * 在房
     */
    public const IN = 0;

    /**
     * 已退出
     */
    public const LEFT = 1;

    /**
     * 已單獨結算
     */
    public const SETTLED = 2;
}
