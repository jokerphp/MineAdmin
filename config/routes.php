<?php

declare(strict_types=1);
/**
 * This file is part of MineAdmin.
 *
 * @link     https://www.mineadmin.com
 * @document https://doc.mineadmin.com
 * @contact  root@imoi.cn
 * @license  https://github.com/mineadmin/MineAdmin/blob/master/LICENSE
 */
use Hyperf\HttpServer\Router\Router;
use Plugin\Zhh\Scoring\Controller\Api\RoomWebSocket;

Router::get('/', static function () {
    return '';
});

Router::get('/favicon.ico', static function () {
    return '';
});

/*
 * WebSocket 路由（方案 §10.2 / §10.5）：統一 /ws/ 前綴。
 * Nginx 只需一條 `location ^~ /ws/` 指向 9502；之後新增 WS 端點時只在此註冊 /ws/xxx 即可。
 * 例：wss://myzhh.cn/ws/room?token=xxx&room_id=123
 *
 * handler 必須傳「類名字串」：hyperf/websocket-server 的 CoreMiddleware 握手時會先
 * container->has($controller) 校驗（否則拋 Router not exist.），再把類名寫入 HANDLER_NAME，
 * 之後由 FdCollector + 介面分派呼叫 onOpen / onMessage / onClose（不需要 __invoke 方法）。
 *
 * 註：`::class` 不會觸發自動載入，故此處指向插件類不會影響啟動；只有實際建立 WS 連線、
 *      而 zhh/scoring 已被卸載時才會報錯。
 */
Router::addServer('ws', static function () {
    // 統一前綴：新增 WS 端點在此追加 /ws/xxx 即可，Nginx 無需再改
    Router::get('/ws/room', RoomWebSocket::class);
});
