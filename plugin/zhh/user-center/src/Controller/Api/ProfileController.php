<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/user-center plugin.
 *
 * 個人資料 / 上傳 / 首頁用戶數（方案 §6.3、§6.5）。
 */

namespace Plugin\Zhh\UserCenter\Controller\Api;

use App\Http\Common\Controller\AbstractController;
use App\Http\Common\Middleware\AccessTokenMiddleware;
use App\Http\Common\Result;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Swagger\Annotation\Get;
use Hyperf\Swagger\Annotation\HyperfServer;
use Hyperf\Swagger\Annotation\JsonContent;
use Hyperf\Swagger\Annotation\Post;
use Hyperf\Swagger\Annotation\Put;
use Mine\Swagger\Attributes\ResultResponse;
use Mine\Upload\UploadInterface;
use OpenApi\Attributes\RequestBody;
use Plugin\Zhh\UserCenter\Repository\MemberAppRepository;
use Plugin\Zhh\UserCenter\Request\Api\UpdateProfileRequest;
use Plugin\Zhh\UserCenter\Request\Api\UploadRequest;
use Plugin\Zhh\UserCenter\Schema\MemberUserSchema;
use Plugin\Zhh\UserCenter\Service\MemberUserService;
use Plugin\Zhh\UserCenter\Support\MemberContext;
use Symfony\Component\Finder\SplFileInfo;

#[HyperfServer(name: 'http')]
final class ProfileController extends AbstractController
{
    public function __construct(
        private readonly MemberUserService $memberUserService,
        private readonly MemberAppRepository $memberAppRepository,
        private readonly MemberContext $memberContext,
        private readonly UploadInterface $upload
    ) {}

    #[Get(
        path: '/api/v1/user/profile',
        operationId: 'UserCenterProfile',
        summary: '查看個人資料',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['用戶中心']
    )]
    #[Middleware(AccessTokenMiddleware::class)]
    #[ResultResponse(instance: new Result(data: new MemberUserSchema()))]
    public function profile(): Result
    {
        return $this->success(
            $this->memberUserService->formatUser($this->memberContext->userOrFail())
        );
    }

    #[Put(
        path: '/api/v1/user/profile',
        operationId: 'UserCenterUpdateProfile',
        summary: '更新個人資料',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['用戶中心']
    )]
    #[RequestBody(content: new JsonContent(ref: UpdateProfileRequest::class, title: '更新個人資料'))]
    #[Middleware(AccessTokenMiddleware::class)]
    #[ResultResponse(instance: new Result(data: new MemberUserSchema()))]
    public function updateProfile(UpdateProfileRequest $request): Result
    {
        return $this->success(
            $this->memberUserService->updateProfile($this->memberContext->userOrFail(), $request->validated())
        );
    }

    #[Post(
        path: '/api/v1/user/upload',
        operationId: 'UserCenterUpload',
        summary: '上傳頭像/背景圖',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['用戶中心']
    )]
    #[Middleware(AccessTokenMiddleware::class)]
    #[ResultResponse(instance: new Result(data: ['url' => 'https://example.com/upload/xxx.png']))]
    public function upload(UploadRequest $request): Result
    {
        $uploadFile = $request->file('file');
        $newTmpPath = sys_get_temp_dir() . '/' . uniqid() . '.' . $uploadFile->getExtension();
        $uploadFile->moveTo($newTmpPath);

        $upload = $this->upload->upload(new SplFileInfo($newTmpPath, '', ''));

        return $this->success(['url' => $upload->getUrl()]);
    }

    #[Get(
        path: '/api/v1/user/user-count',
        operationId: 'UserCenterUserCount',
        summary: '首頁用戶數（公開）',
        tags: ['用戶中心']
    )]
    #[ResultResponse(instance: new Result(data: ['count' => 9094]))]
    public function userCount(RequestInterface $request): Result
    {
        $appId = (string) ($request->input('app_id') ?? '');
        if ($appId === '') {
            $appId = $this->memberAppRepository->defaultAppId();
        }

        if ($appId === '') {
            return $this->success(['count' => 0]);
        }

        return $this->success($this->memberUserService->userCount($appId));
    }
}
