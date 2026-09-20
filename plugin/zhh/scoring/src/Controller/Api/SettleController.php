<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * 結算接口（方案 §7.2）：整局結算（房主）/ 單獨結算（本人或房主）。
 */

namespace Plugin\Zhh\Scoring\Controller\Api;

use App\Http\Common\Controller\AbstractController;
use App\Http\Common\Middleware\AccessTokenMiddleware;
use App\Http\Common\Result;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\Swagger\Annotation\HyperfServer;
use Hyperf\Swagger\Annotation\JsonContent;
use Hyperf\Swagger\Annotation\Post;
use Mine\Swagger\Attributes\ResultResponse;
use OpenApi\Attributes\RequestBody;
use Plugin\Zhh\Scoring\Request\Api\MemberSettleRequest;
use Plugin\Zhh\Scoring\Request\Api\RoomIdRequest;
use Plugin\Zhh\Scoring\Service\SettleService;
use Plugin\Zhh\UserCenter\Support\MemberContext;

#[HyperfServer(name: 'http')]
#[Middleware(middleware: AccessTokenMiddleware::class, priority: 100)]
final class SettleController extends AbstractController
{
    public function __construct(
        private readonly SettleService $settleService,
        private readonly MemberContext $memberContext
    ) {}

    #[Post(
        path: '/api/v1/room/settle',
        operationId: 'ScoringRoomSettle',
        summary: '整局結算（僅房主）',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['計分業務']
    )]
    #[RequestBody(content: new JsonContent(ref: RoomIdRequest::class, title: '整局結算'))]
    #[ResultResponse(instance: new Result(data: ['battle_id' => 1, 'idempotent' => false, 'settle_type' => 1, 'transfers' => []]))]
    public function settle(RoomIdRequest $request): Result
    {
        return $this->success(
            $this->settleService->settle(
                (int) $this->memberContext->userOrFail()->id,
                (int) $request->input('room_id')
            )
        );
    }

    #[Post(
        path: '/api/v1/room/member/settle',
        operationId: 'ScoringRoomMemberSettle',
        summary: '單獨結算成員',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['計分業務']
    )]
    #[RequestBody(content: new JsonContent(ref: MemberSettleRequest::class, title: '單獨結算'))]
    #[ResultResponse(instance: new Result(data: ['battle_id' => 1, 'idempotent' => false, 'settle_type' => 2, 'transfers' => []]))]
    public function settleMember(MemberSettleRequest $request): Result
    {
        return $this->success(
            $this->settleService->settleMember(
                (int) $this->memberContext->userOrFail()->id,
                $request->validated()
            )
        );
    }
}
