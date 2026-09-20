<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/user-center plugin.
 *
 * 登錄 / 登出 / 刷新 token（方案 §6.2、§0.2）。
 */

namespace Plugin\Zhh\UserCenter\Controller\Api;

use App\Http\Common\Controller\AbstractController;
use App\Http\Common\Middleware\AccessTokenMiddleware;
use App\Http\Common\Middleware\RefreshTokenMiddleware;
use App\Http\Common\Result;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\Swagger\Annotation\HyperfServer;
use Hyperf\Swagger\Annotation\Post;
use Mine\Jwt\Traits\RequestScopedTokenTrait;
use Mine\Swagger\Attributes\ResultResponse;
use Plugin\Zhh\UserCenter\Request\Api\LoginRequest;
use Plugin\Zhh\UserCenter\Schema\LoginResultSchema;
use Plugin\Zhh\UserCenter\Service\MemberUserService;

#[HyperfServer(name: 'http')]
final class LoginController extends AbstractController
{
    use RequestScopedTokenTrait;

    public function __construct(
        private readonly MemberUserService $memberUserService
    ) {}

    #[Post(
        path: '/api/v1/user/login',
        operationId: 'UserCenterLogin',
        summary: '微信小程式一鍵登錄',
        tags: ['用戶中心']
    )]
    #[ResultResponse(instance: new Result(data: new LoginResultSchema()))]
    public function login(LoginRequest $request): Result
    {
        return $this->success(
            $this->memberUserService->loginWithWechat($request->validated(), $request->ip())
        );
    }

    #[Post(
        path: '/api/v1/user/logout',
        operationId: 'UserCenterLogout',
        summary: '登出',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['用戶中心']
    )]
    #[Middleware(AccessTokenMiddleware::class)]
    #[ResultResponse(instance: new Result())]
    public function logout(): Result
    {
        $this->memberUserService->logout($this->getToken());

        return $this->success();
    }

    #[Post(
        path: '/api/v1/user/refresh',
        operationId: 'UserCenterRefresh',
        summary: '刷新token',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['用戶中心']
    )]
    #[Middleware(RefreshTokenMiddleware::class)]
    #[ResultResponse(instance: new Result(data: new LoginResultSchema()))]
    public function refresh(): Result
    {
        return $this->success($this->memberUserService->refresh($this->getToken()));
    }
}
