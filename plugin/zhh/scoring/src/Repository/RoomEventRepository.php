<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * 房間系統事件數據倉庫（方案 §7.3 過程時間線）。
 */

namespace Plugin\Zhh\Scoring\Repository;

use App\Repository\IRepository;
use Hyperf\Collection\Arr;
use Hyperf\Collection\Collection;
use Hyperf\Database\Model\Builder;
use Plugin\Zhh\Scoring\Model\RoomEvent;
use Plugin\Zhh\Scoring\Repository\Concerns\BatchWrites;

/**
 * Class RoomEventRepository.
 * @extends IRepository<RoomEvent>
 */
final class RoomEventRepository extends IRepository
{
    use BatchWrites;

    public function __construct(protected readonly RoomEvent $model) {}

    public function handleSearch(Builder $query, array $params): Builder
    {
        return $query
            ->when(Arr::get($params, 'room_id'), static function (Builder $query, $roomId) {
                $query->where('room_id', $roomId);
            })
            ->when(Arr::get($params, 'battle_id'), static function (Builder $query, $battleId) {
                $query->where('battle_id', $battleId);
            })
            ->when(Arr::exists($params, 'event_type'), static function (Builder $query) use ($params) {
                $query->where('event_type', Arr::get($params, 'event_type'));
            });
    }

    /**
     * 對局全部系統事件，按時間升序（與流水合併成過程時間線，方案 §7.3）。
     */
    public function listByBattle(int $battleId): Collection
    {
        return $this->getQuery()
            ->where('battle_id', $battleId)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * 房間過程時間線用的事件：本對局事件 + 開局前的房間事件（battle_id 為空，如建房/首次進房）。
     *
     * 開局前寫入的事件 battle_id 為 null，若只按 battle_id 過濾會漏掉，
     * 導致「XXX 進入了房間」在房間頁看不到。
     */
    public function listForRoom(int $roomId, int $battleId): Collection
    {
        return $this->getQuery()
            ->where('room_id', $roomId)
            ->where(static function (Builder $query) use ($battleId) {
                $query->whereNull('battle_id');
                if ($battleId > 0) {
                    $query->orWhere('battle_id', $battleId);
                }
            })
            ->orderBy('created_at')
            ->get();
    }
}
