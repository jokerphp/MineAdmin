<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * 房間成員數據倉庫（方案 §7.3 加入/離開/結算）。
 */

namespace Plugin\Zhh\Scoring\Repository;

use App\Repository\IRepository;
use Hyperf\Collection\Arr;
use Hyperf\Collection\Collection;
use Hyperf\Database\Model\Builder;
use Plugin\Zhh\Scoring\Model\RoomMember;
use Plugin\Zhh\Scoring\Repository\Concerns\BatchWrites;

/**
 * Class RoomMemberRepository.
 * @extends IRepository<RoomMember>
 */
final class RoomMemberRepository extends IRepository
{
    use BatchWrites;

    public function __construct(protected readonly RoomMember $model) {}

    public function handleSearch(Builder $query, array $params): Builder
    {
        return $query
            ->when(Arr::get($params, 'room_id'), static function (Builder $query, $roomId) {
                $query->where('room_id', $roomId);
            })
            ->when(Arr::get($params, 'user_id'), static function (Builder $query, $userId) {
                $query->where('user_id', $userId);
            })
            ->when(Arr::exists($params, 'status'), static function (Builder $query) use ($params) {
                $query->where('status', Arr::get($params, 'status'));
            });
    }

    /**
     * 按條件取成員並加行鎖（方案 §7.3：計分/結算需先在事務內鎖定雙方成員行）。
     *
     * @param array<string, mixed> $where
     */
    public function lockForUpdate(array $where): ?RoomMember
    {
        // @phpstan-ignore-next-line
        return $this->getQuery()->where($where)->lockForUpdate()->first();
    }

    public function findByRoomAndUser(int $roomId, int $userId): ?RoomMember
    {
        // @phpstan-ignore-next-line
        return $this->getQuery()->where('room_id', $roomId)->where('user_id', $userId)->first();
    }

    /**
     * 在房成員（status=0），按進房時間排序（房主接管順序以此為準，方案 §7.3）。
     */
    public function activeList(int $roomId): Collection
    {
        return $this->getQuery()
            ->where('room_id', $roomId)
            ->where('status', 0)
            ->orderBy('joined_at')
            ->get();
    }

    public function countActive(int $roomId): int
    {
        return $this->getQuery()->where('room_id', $roomId)->where('status', 0)->count();
    }

    /**
     * 房主接管候選：按進房順序取第一位在房成員（方案 §7.3）。
     */
    public function findNextOwner(int $roomId, int $excludeUserId): ?RoomMember
    {
        // @phpstan-ignore-next-line
        return $this->getQuery()
            ->where('room_id', $roomId)
            ->where('status', 0)
            ->where('user_id', '!=', $excludeUserId)
            ->orderBy('joined_at')
            ->first();
    }

    /**
     * 我參與過的房間：按成員記錄倒序分頁（我的戰績列表＝創建或加入過的房間，方案 §9.6）。
     *
     * 同一房間重入復用同一行，故一條成員記錄對應一個房間，不會重複。
     */
    public function pageByUser(int $userId, int $page, int $size): array
    {
        return $this->page(['user_id' => $userId], $page, $size);
    }

    /**
     * 房間全部成員（含已退出/已結算，後台房間詳情展示，方案 §7.4）。
     */
    public function listByRoom(int $roomId): Collection
    {
        return $this->getQuery()
            ->where('room_id', $roomId)
            ->orderBy('joined_at')
            ->get();
    }

    /**
     * 房間成員昵稱快照映射（對局詳情時間線補昵稱用，一次查詢即可，方案 §7.3）。
     *
     * @return array<int, string> [user_id => nickname]
     */
    public function nicknameMap(int $roomId): array
    {
        return $this->getQuery()
            ->where('room_id', $roomId)
            ->pluck('nickname', 'user_id')
            ->toArray();
    }

    /**
     * 房間成員頭像映射（過程消息流氣泡頭像用，一次查詢，方案 §7.3）。
     *
     * @return array<int, string> [user_id => avatar]
     */
    public function avatarMap(int $roomId): array
    {
        return $this->getQuery()
            ->where('room_id', $roomId)
            ->pluck('avatar', 'user_id')
            ->toArray();
    }

    /**
     * 一次批量標記已結算（方案 §7.3：禁止循環內逐條 updateById）。
     */
    public function batchSettle(int $roomId): int
    {
        return $this->getQuery()
            ->where('room_id', $roomId)
            ->where('status', 0)
            ->update([
                'status' => 2,
                'settled_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * 結算後清除分數：score 語義＝當前未清算餘額，清算落地後歸零（方案 §7.3）。
     */
    public function resetScores(int $roomId): int
    {
        return $this->getQuery()->where('room_id', $roomId)->update(['score' => 0]);
    }
}
