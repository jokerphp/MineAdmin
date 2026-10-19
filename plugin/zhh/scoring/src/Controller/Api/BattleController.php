<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * 戰績接口（方案 §7.2）：我的戰績、個人統計、對局詳情、結算結果。
 */

namespace Plugin\Zhh\Scoring\Controller\Api;

use App\Http\Common\Controller\AbstractController;
use App\Http\Common\Middleware\AccessTokenMiddleware;
use App\Http\Common\Result;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Swagger\Annotation\Get;
use Hyperf\Swagger\Annotation\HyperfServer;
use Mine\Swagger\Attributes\ResultResponse;
use Plugin\Zhh\Scoring\Request\Api\RoomIdRequest;
use Plugin\Zhh\Scoring\Service\BattleService;
use Plugin\Zhh\UserCenter\Support\MemberContext;

#[HyperfServer(name: 'http')]
#[Middleware(middleware: AccessTokenMiddleware::class, priority: 100)]
final class BattleController extends AbstractController
{
    public function __construct(
        private readonly BattleService $battleService,
        private readonly MemberContext $memberContext
    ) {}

    #[Get(
        path: '/api/v1/room/feed',
        operationId: 'ScoringRoomFeed',
        summary: '房間過程消息流（房間頁聊天區：系統事件 + 金額流水）',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['計分業務']
    )]
    #[ResultResponse(instance: new Result(data: []))]
    public function roomFeed(RoomIdRequest $request): Result
    {
        return $this->success(
            $this->battleService->roomFeed(
                (int) $this->memberContext->userOrFail()->id,
                (int) $request->input('room_id')
            )
        );
    }

    #[Get(
        path: '/api/v1/room/records',
        operationId: 'ScoringRoomRecords',
        summary: '我的戰績分頁',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['計分業務']
    )]
    #[ResultResponse(instance: new Result(data: ['list' => [], 'total' => 0]))]
    public function records(RequestInterface $request): Result
    {
        return $this->success(
            $this->battleService->myRecords(
                (int) $this->memberContext->userOrFail()->id,
                (int) $request->input('page', 1),
                (int) $request->input('size', 10)
            )
        );
    }

    #[Get(
        path: '/api/v1/room/stats',
        operationId: 'ScoringRoomStats',
        summary: '個人統計（勝/負/勝率/累計分/局數）',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['計分業務']
    )]
    #[ResultResponse(instance: new Result(data: ['total_score' => '0.00', 'win' => 0, 'lose' => 0, 'draw' => 0, 'played' => 0, 'win_rate' => 0.0]))]
    public function stats(): Result
    {
        return $this->success($this->battleService->stats((int) $this->memberContext->userOrFail()->id));
    }

    #[Get(
        path: '/api/v1/room/battle/{id}',
        operationId: 'ScoringRoomBattleDetail',
        summary: '對局詳情（結果 + 過程時間線）',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['計分業務']
    )]
    #[ResultResponse(instance: new Result(data: ['battle' => [], 'ranking' => [], 'transfers' => [], 'records' => []]))]
    public function battle(int $id): Result
    {
        return $this->success($this->battleService->detail((int) $this->memberContext->userOrFail()->id, $id));
    }

    #[Get(
        path: '/api/v1/room/battle/{id}/settle-result',
        operationId: 'ScoringRoomSettleResult',
        summary: '結算結果（全員同一份）',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['計分業務']
    )]
    #[ResultResponse(instance: new Result(data: ['battle' => [], 'ranking' => [], 'transfers' => []]))]
    public function settleResult(int $id): Result
    {
        return $this->success($this->battleService->settleResult((int) $this->memberContext->userOrFail()->id, $id));
    }
}
