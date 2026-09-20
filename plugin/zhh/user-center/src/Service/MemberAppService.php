<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/user-center plugin.
 *
 * 應用（小程序/程序）管理服務（方案 §6.4）。
 */

namespace Plugin\Zhh\UserCenter\Service;

use App\Exception\BusinessException;
use App\Http\Common\ResultCode;
use App\Service\IService;
use Plugin\Zhh\UserCenter\Model\MemberApp;
use Plugin\Zhh\UserCenter\Repository\MemberAppRepository;

/**
 * @extends IService<MemberApp>
 */
final class MemberAppService extends IService
{
    public function __construct(
        protected readonly MemberAppRepository $repository
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function createApp(array $data): void
    {
        $this->repository->create($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateApp(int $id, array $data): void
    {
        if (! $this->repository->existsById($id)) {
            throw new BusinessException(ResultCode::NOT_FOUND, '應用不存在');
        }

        if ($data !== []) {
            $this->repository->updateById($id, $data);
        }
    }

    /**
     * @param int[] $ids
     */
    public function deleteApps(array $ids): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return 0;
        }

        return $this->repository->deleteById($ids);
    }
}
