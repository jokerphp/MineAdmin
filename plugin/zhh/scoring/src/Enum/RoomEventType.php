<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * 房間系統事件類型（方案 §5.2 scoring_room_event.event_type）。
 */

namespace Plugin\Zhh\Scoring\Enum;

final class RoomEventType
{
    /**
     * 進入房間
     */
    public const JOIN = 1;

    /**
     * 離開房間
     */
    public const LEAVE = 2;

    /**
     * 房主接管
     */
    public const OWNER_TRANSFER = 3;

    /**
     * 單獨結算
     */
    public const MEMBER_SETTLED = 4;

    /**
     * 整局結算
     */
    public const BATTLE_SETTLED = 5;
}
