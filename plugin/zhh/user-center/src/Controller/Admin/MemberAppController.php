<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/user-center plugin.
 *
 * 後台應用（小程序/程序）管理（方案 §6.4、§8.2）。
 */

namespace Plugin\Zhh\UserCenter\Controller\Admin;

use App\Http\Admin\Controller\AbstractController;
use App\Http\Admin\Middleware\PermissionMiddleware;
use App\Http\Common\Middleware\AccessTokenMiddleware;
use App\Http\Common\Middleware\OperationMiddleware;
use App\Http\Common\Result;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\Swagger\Annotation\Delete;
use Hyperf\Swagger\Annotation\Get;
use Hyperf\Swagger\Annotation\HyperfServer;
use Hyperf\Swagger\Annotation\JsonContent;
use Hyperf\Swagger\Annotation\Post;
use Hyperf\Swagger\Annotation\Put;
use Mine\Access\Attribute\Permission;
use Mine\Swagger\Attributes\PageResponse;
use Mine\Swagger\Attributes\ResultResponse;
use OpenApi\Attributes\RequestBody;
use Plugin\Zhh\UserCenter\Request\Admin\MemberAppRequest;
use Plugin\Zhh\UserCenter\Schema\MemberAppSchema;
use Plugin\Zhh\UserCenter\Service\MemberAppService;

#[HyperfServer(name: 'http')]
#[Middleware(middleware: AccessTokenMiddleware::class, priority: 100)]
#[Middleware(middleware: PermissionMiddleware::class, priority: 99)]
#[Middleware(middleware: OperationMiddleware::class, priority: 98)]
final class MemberAppController extends AbstractController
{
    public function __construct(
        private readonly MemberAppService $memberAppService
    ) {}

    #[Get(
        path: '/admin/user-center/app',
        operationId: 'userCenterAppPageList',
        summary: '應用列表',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['用戶中心']
    )]
    #[Permission(code: 'userCenter:app:index')]
    #[PageResponse(instance: MemberAppSchema::class)]
    public function pageList(): Result
    {
        return $this->success(
            $this->memberAppService->page($this->getRequestData(), $this->getCurrentPage(), $this->getPageSize())
        );
    }

    #[Post(
        path: '/admin/user-center/app',
        operationId: 'userCenterAppCreate',
        summary: '創建應用',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['用戶中心']
    )]
    #[Permission(code: 'userCenter:app:create')]
    #[RequestBody(content: new JsonContent(ref: MemberAppRequest::class, title: '創建應用'))]
    #[ResultResponse(instance: new Result())]
    public function create(MemberAppRequest $request): Result
    {
        $this->memberAppService->createApp($request->validated());

        return $this->success();
    }

    #[Put(
        path: '/admin/user-center/app',
        operationId: 'userCenterAppUpdate',
        summary: '更新應用',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['用戶中心']
    )]
    #[Permission(code: 'userCenter:app:update')]
    #[RequestBody(content: new JsonContent(ref: MemberAppRequest::class, title: '更新應用'))]
    #[ResultResponse(instance: new Result())]
    public function update(MemberAppRequest $request): Result
    {
        $this->memberAppService->updateApp((int) $this->getRequest()->input('id'), $request->validated());

        return $this->success();
    }

    #[Delete(
        path: '/admin/user-center/app',
        operationId: 'userCenterAppDelete',
        summary: '刪除應用（批量）',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['用戶中心']
    )]
    #[Permission(code: 'userCenter:app:delete')]
    #[ResultResponse(instance: new Result())]
    public function delete(): Result
    {
        $this->memberAppService->deleteApps($this->getRequestData());

        return $this->success();
    }
}
