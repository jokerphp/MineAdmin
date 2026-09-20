<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * 房間接口（方案 §7.2）：創建/加入/詳情/離開/邀請。
 */

namespace Plugin\Zhh\Scoring\Controller\Api;

use App\Http\Common\Controller\AbstractController;
use App\Http\Common\Middleware\AccessTokenMiddleware;
use App\Http\Common\Result;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\Swagger\Annotation\Get;
use Hyperf\Swagger\Annotation\HyperfServer;
use Hyperf\Swagger\Annotation\JsonContent;
use Hyperf\Swagger\Annotation\Post;
use Mine\Swagger\Attributes\ResultResponse;
use OpenApi\Attributes\RequestBody;
use Plugin\Zhh\Scoring\Request\Api\JoinRoomRequest;
use Plugin\Zhh\Scoring\Request\Api\RoomIdRequest;
use Plugin\Zhh\Scoring\Service\RoomService;
use Plugin\Zhh\UserCenter\Support\MemberContext;

#[HyperfServer(name: 'http')]
#[Middleware(middleware: AccessTokenMiddleware::class, priority: 100)]
final class RoomController extends AbstractController
{
    public function __construct(
        private readonly RoomService $roomService,
        private readonly MemberContext $memberContext
    ) {}

    #[Post(
        path: '/api/v1/room/create',
        operationId: 'ScoringRoomCreate',
        summary: '創建房間',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['計分業務']
    )]
    #[ResultResponse(instance: new Result(data: ['room_id' => 1, 'room_no' => 'AB23CD']))]
    public function create(): Result
    {
        return $this->success($this->roomService->createRoom((int) $this->memberContext->userOrFail()->id));
    }

    #[Post(
        path: '/api/v1/room/join',
        operationId: 'ScoringRoomJoin',
        summary: '加入房間',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['計分業務']
    )]
    #[RequestBody(content: new JsonContent(ref: JoinRoomRequest::class, title: '加入房間'))]
    #[ResultResponse(instance: new Result(data: ['room_id' => 1, 'room_no' => 'AB23CD', 'rejoined' => false]))]
    public function join(JoinRoomRequest $request): Result
    {
        return $this->success(
            $this->roomService->join((int) $this->memberContext->userOrFail()->id, (string) $request->input('room_no'))
        );
    }

    #[Get(
        path: '/api/v1/room/info',
        operationId: 'ScoringRoomInfo',
        summary: '房間詳情',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['計分業務']
    )]
    #[ResultResponse(instance: new Result(data: ['room' => [], 'members' => []]))]
    public function info(RoomIdRequest $request): Result
    {
        return $this->success(
            $this->roomService->info((int) $this->memberContext->userOrFail()->id, (int) $request->input('room_id'))
        );
    }

    #[Post(
        path: '/api/v1/room/leave',
        operationId: 'ScoringRoomLeave',
        summary: '離開房間（房主自動移交）',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['計分業務']
    )]
    #[RequestBody(content: new JsonContent(ref: RoomIdRequest::class, title: '離開房間'))]
    #[ResultResponse(instance: new Result())]
    public function leave(RoomIdRequest $request): Result
    {
        $this->roomService->leave((int) $this->memberContext->userOrFail()->id, (int) $request->input('room_id'));

        return $this->success();
    }

    #[Get(
        path: '/api/v1/room/invite',
        operationId: 'ScoringRoomInvite',
        summary: '房間邀請（房號 + 小程序碼）',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['計分業務']
    )]
    #[ResultResponse(instance: new Result(data: ['room_no' => 'AB23CD', 'qr_url' => 'https://example.com/qr.png', 'share_title' => '來打牌記賬一起記分（房號 AB23CD）']))]
    public function invite(RoomIdRequest $request): Result
    {
        return $this->success(
            $this->roomService->invite((int) $this->memberContext->userOrFail()->id, (int) $request->input('room_id'))
        );
    }
}
