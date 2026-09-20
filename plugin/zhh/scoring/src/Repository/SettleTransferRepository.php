<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * 結算清算轉賬方案數據倉庫（方案 §7.3）：全端展示統一讀本表。
 */

namespace Plugin\Zhh\Scoring\Repository;

use App\Repository\IRepository;
use Hyperf\Collection\Arr;
use Hyperf\Collection\Collection;
use Hyperf\Database\Model\Builder;
use Plugin\Zhh\Scoring\Model\SettleTransfer;
use Plugin\Zhh\Scoring\Repository\Concerns\BatchWrites;

/**
 * Class SettleTransferRepository.
 * @extends IRepository<SettleTransfer>
 */
final class SettleTransferRepository extends IRepository
{
    use BatchWrites;

    public function __construct(
        protected readonly SettleTransfer $model,
        protected readonly BattleMemberRepository $battleMemberRepository
    ) {}

    public function handleSearch(Builder $query, array $params): Builder
    {
        return $query
            ->when(Arr::get($params, 'room_id'), static function (Builder $query, $roomId) {
                $query->where('room_id', $roomId);
            })
            ->when(Arr::get($params, 'battle_id'), static function (Builder $query, $battleId) {
                $query->where('battle_id', $battleId);
            })
            ->when(Arr::exists($params, 'settle_type'), static function (Builder $query) use ($params) {
                $query->where('settle_type', Arr::get($params, 'settle_type'));
            });
    }

    /**
     * 房間全部清算轉賬（後台房間詳情聚合展示，方案 §7.4）。
     */
    public function listByRoom(int $roomId): Collection
    {
        return $this->getQuery()
            ->where('room_id', $roomId)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * 對局清算方案，按時間升序（結算結果頁，方案 §7.3）。
     */
    public function listByBattle(int $battleId): Collection
    {
        return $this->getQuery()
            ->where('battle_id', $battleId)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * 本段清算淨流入（方案 §7.3 v1.5「快照分與排名的修正」）：[user_id => 本段已收 − 本段已付]。
     *
     * 分段口徑：只計「晚於本人上次 settled_at」的清算轉賬（從未結算者全量計入），
     * 避免重入者第二次快照與已固化快照雙計。
     *
     * 共 2 條 SQL（轉賬一次 + 最後結算時間一次），聚合在內存完成。
     *
     * @return array<int, string>
     */
    public function netInByUser(int $battleId): array
    {
        $transfers = $this->getQuery()
            ->where('battle_id', $battleId)
            ->get(['from_user_id', 'to_user_id', 'amount', 'created_at']);

        if ($transfers->isEmpty()) {
            return [];
        }

        $lastSettled = $this->battleMemberRepository->lastSettledAtByBattle($battleId);
        $net = [];

        foreach ($transfers as $transfer) {
            $fromId = (int) $transfer->from_user_id;
            $toId = (int) $transfer->to_user_id;
            $amount = (string) $transfer->amount;
            $at = (string) $transfer->created_at->toDateTimeString();

            // 收款方：只有晚於其上次結算時間的清算才計入本段
            if (! isset($lastSettled[$toId]) || $at > (string) $lastSettled[$toId]) {
                $net[$toId] = bcadd($net[$toId] ?? '0', $amount, 2);
            }

            if (! isset($lastSettled[$fromId]) || $at > (string) $lastSettled[$fromId]) {
                $net[$fromId] = bcsub($net[$fromId] ?? '0', $amount, 2);
            }
        }

        return $net;
    }
}
