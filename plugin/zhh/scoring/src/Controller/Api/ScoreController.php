<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * 計分 / 支出接口（方案 §7.2）。
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
use Plugin\Zhh\Scoring\Request\Api\ExpenseRequest;
use Plugin\Zhh\Scoring\Request\Api\RecordRequest;
use Plugin\Zhh\Scoring\Service\ScoreService;
use Plugin\Zhh\UserCenter\Support\MemberContext;

#[HyperfServer(name: 'http')]
#[Middleware(middleware: AccessTokenMiddleware::class, priority: 100)]
final class ScoreController extends AbstractController
{
    public function __construct(
        private readonly ScoreService $scoreService,
        private readonly MemberContext $memberContext
    ) {}

    #[Post(
        path: '/api/v1/room/record',
        operationId: 'ScoringRoomRecord',
        summary: '新增計分（轉賬式零和）',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['計分業務']
    )]
    #[RequestBody(content: new JsonContent(ref: RecordRequest::class, title: '新增計分'))]
    #[ResultResponse(instance: new Result())]
    public function record(RecordRequest $request): Result
    {
        $this->scoreService->add((int) $this->memberContext->userOrFail()->id, $request->validated());

        return $this->success();
    }

    #[Post(
        path: '/api/v1/room/expense',
        operationId: 'ScoringRoomExpense',
        summary: '新增支出（茶水費）',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['計分業務']
    )]
    #[RequestBody(content: new JsonContent(ref: ExpenseRequest::class, title: '新增支出'))]
    #[ResultResponse(instance: new Result())]
    public function expense(ExpenseRequest $request): Result
    {
        $this->scoreService->expense((int) $this->memberContext->userOrFail()->id, $request->validated());

        return $this->success();
    }
}
