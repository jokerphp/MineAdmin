<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * WebSocket 控制器（方案 §10.3）：連接鑒權、房間訂閱、心跳與取消訂閱。
 * 連接地址：`ws://{host}:9502/room/ws?token=xxx&room_id=123`（方案 §10.5）。
 *
 * 分發機制（已核對 hyperf/websocket-server 源碼）：握手時 `Hyperf\WebSocketServer\Server::onHandShake()`
 * 先跑路由分派，再從 `CoreMiddleware::HANDLER_NAME` 取本類類名存入 FdCollector；
 * 之後 `onMessage` / `onClose` / `deferOnOpen` 才用容器取實例並呼叫對應介面方法。
 * 因此**必須在路由中把 `/room/ws` 指向本類**（見 `config/routes.php` 的 `Router::addServer('ws', ...)`），
 * 否則握手只會印 "WebSocket handshake failed, because the class does not exists."，三個回調都不會被呼叫。
 *
 * 接口注意：`OnOpenInterface` / `OnMessageInterface` / `OnCloseInterface` 位於 `Hyperf\Contract\`
 * （`hyperf/websocket-server` 套件下沒有 Contract 目錄），且三個方法的 `$server` / `$request` / `$frame`
 * 參數**在介面中沒有型別宣告**——實作時加上型別即為縮窄參數型別，會觸發 PHP Fatal error，
 * 故這裡保持無型別並以 PHPDoc 標註，由 `workerId()` 自行容錯取值。
 */

namespace Plugin\Zhh\Scoring\Controller\Api;

use Hyperf\Contract\OnCloseInterface;
use Hyperf\Contract\OnMessageInterface;
use Hyperf\Contract\OnOpenInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\WebSocketServer\Sender;
use Lcobucci\JWT\Token\RegisteredClaims;
use Mine\Jwt\Factory;
use Plugin\Zhh\Scoring\Enum\RoomMemberStatus;
use Plugin\Zhh\Scoring\Repository\RoomMemberRepository;
use Plugin\Zhh\Scoring\Support\RoomConnectionStore;
use Swoole\Http\Request;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;
use Throwable;

class RoomWebSocket implements OnOpenInterface, OnMessageInterface, OnCloseInterface
{
    public function __construct(
        private readonly Factory $jwtFactory,
        private readonly RoomMemberRepository $roomMemberRepository,
        private readonly RoomConnectionStore $connectionStore,
        private readonly Sender $sender,
        private readonly StdoutLoggerInterface $logger
    ) {}

    /**
     * @param Server $server Swoole WebSocket 服務器
     * @param Request $request 握手請求（含 fd 與 query 參數）
     */
    public function onOpen($server, $request): void
    {
        $fd = (int) $request->fd;
        $params = $request->get ?? [];
        $token = (string) ($params['token'] ?? '');
        $roomId = (int) ($params['room_id'] ?? 0);

        $userId = $this->resolveUserId($token);
        if ($userId === 0 || $roomId <= 0 || ! $this->isRoomMember($roomId, $userId)) {
            // 鑒權失敗：直接關閉連接（小程序會依請求層重新登錄）
            $this->sender->disconnect($fd);
            return;
        }

        $this->connectionStore->bind($roomId, $this->workerId($server), $fd);
        $this->send($fd, [
            'type' => 'subscribed',
            'room_id' => $roomId,
            'user_id' => $userId,
            'data' => ['member_count' => $this->connectionStore->count($roomId)],
        ]);
    }

    /**
     * @param Server $server Swoole WebSocket 服務器
     * @param Frame $frame 上行數據幀
     */
    public function onMessage($server, $frame): void
    {
        $payload = json_decode((string) $frame->data, true);
        if (! is_array($payload)) {
            return;
        }

        $action = (string) ($payload['action'] ?? '');
        $roomId = (int) ($payload['room_id'] ?? 0);
        if ($roomId <= 0) {
            return;
        }

        switch ($action) {
            case 'heartbeat':
                $this->connectionStore->touch($roomId, $this->workerId($server), (int) $frame->fd);
                $this->send((int) $frame->fd, ['type' => 'pong', 'room_id' => $roomId, 'data' => []]);
                break;
            case 'subscribe':
                $this->connectionStore->bind($roomId, $this->workerId($server), (int) $frame->fd);
                $this->send((int) $frame->fd, ['type' => 'subscribed', 'room_id' => $roomId, 'data' => []]);
                break;
            case 'unsubscribe':
                $this->connectionStore->unbind($roomId, $this->workerId($server), (int) $frame->fd);
                $this->send((int) $frame->fd, ['type' => 'unsubscribed', 'room_id' => $roomId, 'data' => []]);
                break;
            default:
                $this->logger->debug('scoring ws unknown action: ' . $action);
        }
    }

    /**
     * @param Server $server Swoole WebSocket 服務器
     */
    public function onClose($server, int $fd, int $reactorId): void
    {
        // 經 fd → room 索引精準清理；索引缺失時連接視圖依 TTL 自動過期
        $this->connectionStore->unbindFd($this->workerId($server), $fd);
    }

    /**
     * 從連接 URL 的 token 解析會員 ID（方案 §12：身份一律取自 JWT）。
     */
    private function resolveUserId(string $token): int
    {
        if ($token === '') {
            return 0;
        }

        try {
            $jwt = $this->jwtFactory->get('default');
            // 方法名依 Mine\Jwt\JwtInterface：解析 access token 為 parserAccessToken（無 parserToken）
            $parsed = $jwt->parserAccessToken($token);
            if ($jwt->hasBlackList($parsed)) {
                return 0;
            }

            return (int) $parsed->claims()->get(RegisteredClaims::ID);
        } catch (Throwable $e) {
            $this->logger->debug('scoring ws token invalid: ' . $e->getMessage());

            return 0;
        }
    }

    private function isRoomMember(int $roomId, int $userId): bool
    {
        $member = $this->roomMemberRepository->findByRoomAndUser($roomId, $userId);

        return $member !== null && (int) $member->status !== RoomMemberStatus::LEFT;
    }

    /**
     * 取當前 worker 編號；介面參數允許 Swoole 與 Swow 兩種服務器物件，故容錯取值。
     */
    private function workerId(mixed $server): int
    {
        return (int) ($server->worker_id ?? 0);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function send(int $fd, array $payload): void
    {
        $this->sender->push($fd, json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '{}');
    }
}
