<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/user-center plugin.
 *
 * 後台會員管理（方案 §6.4、§8.2）。
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
use Hyperf\Swagger\Annotation\Put;
use Mine\Access\Attribute\Permission;
use Mine\Swagger\Attributes\PageResponse;
use Mine\Swagger\Attributes\ResultResponse;
use OpenApi\Attributes\RequestBody;
use Plugin\Zhh\UserCenter\Request\Admin\MemberRequest;
use Plugin\Zhh\UserCenter\Schema\MemberUserSchema;
use Plugin\Zhh\UserCenter\Service\MemberUserService;

#[HyperfServer(name: 'http')]
#[Middleware(middleware: AccessTokenMiddleware::class, priority: 100)]
#[Middleware(middleware: PermissionMiddleware::class, priority: 99)]
#[Middleware(middleware: OperationMiddleware::class, priority: 98)]
final class MemberController extends AbstractController
{
    public function __construct(
        private readonly MemberUserService $memberUserService
    ) {}

    #[Get(
        path: '/admin/user-center/member',
        operationId: 'userCenterMemberPageList',
        summary: '會員列表',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['用戶中心']
    )]
    #[Permission(code: 'userCenter:member:index')]
    #[PageResponse(instance: MemberUserSchema::class)]
    public function pageList(): Result
    {
        return $this->success(
            $this->memberUserService->page($this->getRequestData(), $this->getCurrentPage(), $this->getPageSize())
        );
    }

    #[Get(
        path: '/admin/user-center/member/{id}',
        operationId: 'userCenterMemberDetail',
        summary: '會員詳情',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['用戶中心']
    )]
    #[Permission(code: 'userCenter:member:index')]
    #[ResultResponse(instance: new Result(data: new MemberUserSchema()))]
    public function detail(int $id): Result
    {
        return $this->success($this->memberUserService->detail($id));
    }

    #[Put(
        path: '/admin/user-center/member/{id}',
        operationId: 'userCenterMemberUpdate',
        summary: '更新會員（含停用/啟用）',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['用戶中心']
    )]
    #[Permission(code: 'userCenter:member:update')]
    #[RequestBody(content: new JsonContent(ref: MemberRequest::class, title: '更新會員'))]
    #[ResultResponse(instance: new Result())]
    public function update(int $id, MemberRequest $request): Result
    {
        $this->memberUserService->updateMember($id, $request->validated());

        return $this->success();
    }

    #[Delete(
        path: '/admin/user-center/member',
        operationId: 'userCenterMemberDelete',
        summary: '刪除會員（批量）',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['用戶中心']
    )]
    #[Permission(code: 'userCenter:member:delete')]
    #[ResultResponse(instance: new Result())]
    public function delete(): Result
    {
        $this->memberUserService->deleteMembers($this->getRequestData());

        return $this->success();
    }
}
