<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * 後台房間管理（方案 §7.4）：房間列表 / 房間詳情（聚合）/ 強制關閉。
 */

namespace Plugin\Zhh\Scoring\Controller\Admin;

use App\Http\Admin\Controller\AbstractController;
use App\Http\Admin\Middleware\PermissionMiddleware;
use App\Http\Common\Middleware\AccessTokenMiddleware;
use App\Http\Common\Middleware\OperationMiddleware;
use App\Http\Common\Result;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\Swagger\Annotation\Delete;
use Hyperf\Swagger\Annotation\Get;
use Hyperf\Swagger\Annotation\HyperfServer;
use Mine\Access\Attribute\Permission;
use Mine\Swagger\Attributes\PageResponse;
use Mine\Swagger\Attributes\ResultResponse;
use Plugin\Zhh\Scoring\Schema\RoomSchema;
use Plugin\Zhh\Scoring\Service\AdminRoomService;

#[HyperfServer(name: 'http')]
#[Middleware(middleware: AccessTokenMiddleware::class, priority: 100)]
#[Middleware(middleware: PermissionMiddleware::class, priority: 99)]
#[Middleware(middleware: OperationMiddleware::class, priority: 98)]
final class RoomController extends AbstractController
{
    public function __construct(
        private readonly AdminRoomService $adminRoomService
    ) {}

    #[Get(
        path: '/admin/scoring/room',
        operationId: 'scoringRoomPageList',
        summary: '房間列表（含對局數/流水數）',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['計分業務']
    )]
    #[Permission(code: 'scoring:room:index')]
    #[PageResponse(instance: RoomSchema::class)]
    public function pageList(): Result
    {
        return $this->success(
            $this->adminRoomService->pageWithCounters(
                $this->getRequestData(),
                $this->getCurrentPage(),
                $this->getPageSize()
            )
        );
    }

    #[Get(
        path: '/admin/scoring/room/{id}',
        operationId: 'scoringRoomDetail',
        summary: '房間詳情（成員/流水/對局快照聚合）',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['計分業務']
    )]
    #[Permission(code: 'scoring:room:index')]
    #[ResultResponse(instance: new Result(data: ['room' => [], 'members' => [], 'records' => [], 'battles' => [], 'battle_members' => [], 'transfers' => []]))]
    public function detail(int $id): Result
    {
        return $this->success($this->adminRoomService->detail($id));
    }

    #[Delete(
        path: '/admin/scoring/room',
        operationId: 'scoringRoomDelete',
        summary: '強制關閉房間（批量）',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['計分業務']
    )]
    #[Permission(code: 'scoring:room:delete')]
    #[ResultResponse(instance: new Result())]
    public function delete(): Result
    {
        $this->adminRoomService->forceClose($this->getRequestData());

        return $this->success();
    }
}
