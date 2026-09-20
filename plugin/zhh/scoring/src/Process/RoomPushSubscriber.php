<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * 房間推送訂閱者（方案 §10.2/§10.4）：一個獨立進程訂閱 Redis 頻道，
 * 把 `Support\RoomPush` 投遞的消息推送給房間內的 WebSocket 連接。
 *
 * 部署注意（假設）：`Hyperf\WebSocketServer\Sender::push()` 只能推送到**本進程**持有的 fd，
 * 因此本進程需要與 ws 服務同進程組（或由 Swoole `sendMessage`/`onPipeMessage` 二次轉發）。
 * 跨進程未覆蓋到時，客戶端仍可依 `score_version` 調 `GET /api/v1/room/info` 校準（方案 §10.5 兜底）。
 */

namespace Plugin\Zhh\Scoring\Process;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Process\AbstractProcess;
use Hyperf\Process\Annotation\Process;
use Hyperf\Redis\Redis;
use Hyperf\WebSocketServer\Sender;
use Plugin\Zhh\Scoring\Support\RoomConnectionStore;
use Plugin\Zhh\Scoring\Support\RoomPush;
use Psr\Container\ContainerInterface;
use Throwable;

#[Process(name: 'ScoringRoomPushSubscriber', nums: 1)]
class RoomPushSubscriber extends AbstractProcess
{
    /**
     * 注意：`Hyperf\Process\AbstractProcess::__construct(ContainerInterface $container)` 必填 1 個參數，
     * 子類覆寫建構子時必須把容器傳給父類，否則啟動時會
     * `ArgumentCountError: Too few arguments to function AbstractProcess::__construct(), 0 passed`。
     * 容器不在此處升格為屬性（父類已有 protected $container），避免屬性重複宣告。
     */
    public function __construct(
        ContainerInterface $container,
        private readonly Redis $redis,
        private readonly Sender $sender,
        private readonly RoomConnectionStore $connectionStore,
        private readonly StdoutLoggerInterface $logger
    ) {
        parent::__construct($container);
    }

    public function handle(): void
    {
        $this->redis->psubscribe([RoomPush::CHANNEL_PREFIX . '*'], function ($pattern, $channel, $payload): void {
            $this->dispatch((string) $channel, (string) $payload);
        });
    }

    /**
     * 把消息推給該房間在本進程內可達的連接（循環內僅推送，不查庫）。
     */
    private function dispatch(string $channel, string $payload): void
    {
        $roomId = (int) substr($channel, strlen(RoomPush::CHANNEL_PREFIX));
        if ($roomId <= 0) {
            return;
        }

        foreach ($this->connectionStore->connections($roomId) as $connection) {
            try {
                $this->sender->push($connection['fd'], $payload);
            } catch (Throwable $e) {
                // fd 已失效：僅記錄日誌，不影響其他連接
                $this->logger->warning('scoring ws push failed: ' . $e->getMessage());
            }
        }
    }
}
