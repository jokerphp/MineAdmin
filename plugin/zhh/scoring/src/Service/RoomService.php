<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * 房間業務服務（方案 §7.3）：創建、加入（含單獨結算後重入）、離開、詳情、邀請。
 */

namespace Plugin\Zhh\Scoring\Service;

use App\Exception\BusinessException;
use App\Http\Common\ResultCode;
use App\Service\IService;
use Hyperf\Context\ApplicationContext;
use Hyperf\DbConnection\Db;
use Plugin\Zhh\Scoring\Enum\RoomEventType;
use Plugin\Zhh\Scoring\Enum\RoomMemberStatus;
use Plugin\Zhh\Scoring\Enum\RoomStatus;
use Plugin\Zhh\Scoring\Model\Battle;
use Plugin\Zhh\Scoring\Model\Room;
use Plugin\Zhh\Scoring\Model\RoomMember;
use Plugin\Zhh\Scoring\Repository\BattleRepository;
use Plugin\Zhh\Scoring\Repository\RoomEventRepository;
use Plugin\Zhh\Scoring\Repository\RoomMemberRepository;
use Plugin\Zhh\Scoring\Repository\RoomRepository;
use Plugin\Zhh\Scoring\Support\OwnerTransfer;
use Plugin\Zhh\Scoring\Support\RoomPush;
use Plugin\Zhh\UserCenter\Model\MemberUser;
use Plugin\Zhh\UserCenter\Repository\MemberAppRepository;
use Plugin\Zhh\UserCenter\Repository\MemberUserRepository;
use Plugin\Zhh\UserCenter\Support\WechatClient;

/**
 * @extends IService<Room>
 */
final class RoomService extends IService
{
    /**
     * 房號長度（方案 §5.2：6~8 位）
     */
    private const ROOM_NO_LENGTH = 6;

    /**
     * 每次生成的房號候選數（一次 whereIn 查重，避免循環內查庫）
     */
    private const ROOM_NO_CANDIDATES = 5;

    /**
     * 房號字符集：排除易混淆的 0/O/1/I
     */
    private const ROOM_NO_CHARSET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(
        protected readonly RoomRepository $repository,
        protected readonly RoomMemberRepository $roomMemberRepository,
        protected readonly RoomEventRepository $roomEventRepository,
        protected readonly BattleRepository $battleRepository,
        protected readonly OwnerTransfer $ownerTransfer,
        protected readonly RoomPush $roomPush,
        protected readonly MemberUserRepository $memberUserRepository,
        protected readonly MemberAppRepository $memberAppRepository,
        protected readonly WechatClient $wechatClient
    ) {}

    /**
     * 首次計分時創建對局記錄並回填房間（方案 §7.3 開始對局）：
     * 單獨結算與整局結算都寫入同一個 battle_id，保證對帳完整。
     */
    public function ensureBattle(Room $room, int $ownerId): int
    {
        if ($room->current_battle_id !== null) {
            return (int) $room->current_battle_id;
        }

        /** @var Battle $battle */
        $battle = $this->battleRepository->create([
            'room_id' => (int) $room->id,
            'room_no' => (string) $room->room_no,
            'owner_id' => $ownerId,
            'status' => 0,
            'started_at' => date('Y-m-d H:i:s'),
        ]);

        $this->repository->updateById((int) $room->id, [
            'current_battle_id' => (int) $battle->id,
            'status' => RoomStatus::PLAYING,
        ]);

        return (int) $battle->id;
    }

    /**
     * 創建房間並讓房主自動入房（方案 §7.3 創建房間）。
     *
     * 方法名不可用 `create`：本類繼承 `App\Service\IService`，其 `create(array $data): mixed`
     * 為框架 CRUD 契約，子類以 `int $uid` 覆寫會因參數型別不相容而致命（PHP Fatal error）。
     * 與 `MemberAppService::createApp/updateApp/deleteApps` 的命名慣例一致。
     *
     * @return array{room_id: int, room_no: string}
     */
    public function createRoom(int $uid): array
    {
        return Db::transaction(function () use ($uid) {
            /** @var Room $room */
            $room = $this->repository->create([
                'room_no' => $this->generateUniqueRoomNo(),
                'owner_id' => $uid,
                'status' => RoomStatus::WAITING,
                'current_round' => 0,
                'score_version' => 0,
            ]);

            $profile = $this->memberProfile($uid);
            $this->roomMemberRepository->create([
                'room_id' => (int) $room->id,
                'user_id' => $uid,
                'nickname' => $profile['nickname'],
                'avatar' => $profile['avatar'],
                'score' => 0,
                'role' => 1,
                'status' => RoomMemberStatus::IN,
                'joined_at' => date('Y-m-d H:i:s'),
            ]);

            $this->roomPush->pushRoomUpdate((int) $room->id, 1);

            return ['room_id' => (int) $room->id, 'room_no' => (string) $room->room_no];
        });
    }

    /**
     * 加入房間（方案 §7.3 加入房間）：
     * 首次加入建行；已單獨結算/已退出者復用原行並把分數歸零重新累計。
     *
     * @return array{room_id: int, room_no: string, rejoined: bool}
     */
    public function join(int $uid, string $roomNo): array
    {
        return Db::transaction(function () use ($uid, $roomNo) {
            $found = $this->repository->findByRoomNo($roomNo);
            if (! $found instanceof Room) {
                throw new BusinessException(ResultCode::NOT_FOUND, '房間不存在');
            }

            // 行鎖：與計分/結算互斥（方案 §7.3 併發處理）
            /** @var Room $room */
            $room = $this->repository->lockForUpdate((int) $found->id);
            if ((int) $room->status >= RoomStatus::SETTLED) {
                throw new BusinessException(ResultCode::UNPROCESSABLE_ENTITY, '該房間已結束');
            }

            $member = $this->roomMemberRepository->lockForUpdate([
                'room_id' => (int) $room->id,
                'user_id' => $uid,
            ]);

            if ($member instanceof RoomMember && (int) $member->status === RoomMemberStatus::IN) {
                return ['room_id' => (int) $room->id, 'room_no' => (string) $room->room_no, 'rejoined' => false];
            }

            if ($this->roomMemberRepository->countActive((int) $room->id) >= (int) $room->member_limit) {
                throw new BusinessException(ResultCode::UNPROCESSABLE_ENTITY, '房間人數已滿');
            }

            $profile = $this->memberProfile($uid);

            if (! $member instanceof RoomMember) {
                $this->roomMemberRepository->create([
                    'room_id' => (int) $room->id,
                    'user_id' => $uid,
                    'nickname' => $profile['nickname'],
                    'avatar' => $profile['avatar'],
                    'score' => 0,
                    'role' => 0,
                    'status' => RoomMemberStatus::IN,
                    'joined_at' => date('Y-m-d H:i:s'),
                ]);
                $this->writeEvent($room, RoomEventType::JOIN, $uid, $profile['nickname']);
                $this->roomPush->pushRoomUpdate((int) $room->id, $this->roomMemberRepository->countActive((int) $room->id));

                return ['room_id' => (int) $room->id, 'room_no' => (string) $room->room_no, 'rejoined' => false];
            }

            // 重入：status 2（已單獨結算）/ 1（已退出）→ 復用該行，分數歸零、重新排隊接管順序（方案 §7.3 重入規則）
            $this->roomMemberRepository->updateById($member->id, [
                'nickname' => $profile['nickname'],
                'avatar' => $profile['avatar'],
                'score' => 0,
                'status' => RoomMemberStatus::IN,
                'settled_at' => null,
                'left_at' => null,
                'joined_at' => date('Y-m-d H:i:s'),
            ]);
            $this->writeEvent($room, RoomEventType::JOIN, $uid, $profile['nickname'], '重新加入');
            $this->roomPush->pushRoomUpdate((int) $room->id, $this->roomMemberRepository->countActive((int) $room->id));

            return ['room_id' => (int) $room->id, 'room_no' => (string) $room->room_no, 'rejoined' => true];
        });
    }

    /**
     * 離開房間（方案 §7.3 離開房間與房主接管）：
     * 有未清算餘額時不允許直接離場（須「結算並離開」）；房主離開則自動移交。
     */
    public function leave(int $uid, int $roomId): void
    {
        Db::transaction(function () use ($uid, $roomId) {
            /** @var null|Room $room */
            $room = $this->repository->lockForUpdate($roomId);
            if (! $room instanceof Room) {
                throw new BusinessException(ResultCode::NOT_FOUND, '房間不存在');
            }

            $member = $this->roomMemberRepository->lockForUpdate([
                'room_id' => $roomId,
                'user_id' => $uid,
            ]);
            if (! $member instanceof RoomMember || (int) $member->status !== RoomMemberStatus::IN) {
                throw new BusinessException(ResultCode::UNPROCESSABLE_ENTITY, '您不在該房間或已結算');
            }

            // 離房約束（方案 §7.3 v1.3）：尚有未清算餘額不得直接離場，避免在房池零和被破壞
            if (bccomp((string) $member->score, '0', 2) !== 0) {
                throw new BusinessException(ResultCode::UNPROCESSABLE_ENTITY, '尚有未清算餘額，請使用「結算並離開」');
            }

            $this->roomMemberRepository->updateById($member->id, [
                'status' => RoomMemberStatus::LEFT,
                'left_at' => date('Y-m-d H:i:s'),
            ]);
            $this->writeEvent($room, RoomEventType::LEAVE, $uid, (string) $member->nickname);
            $this->roomPush->pushRoomUpdate(
                (int) $room->id,
                $this->roomMemberRepository->countActive((int) $room->id)
            );

            if ((int) $room->owner_id === $uid) {
                $this->transferOwnerOrSettle($room, $uid);
            }
        });
    }

    /**
     * 房間詳情（方案 §7.2：成員 + 當前分數 + 輪次 + 版本號）。
     *
     * @return array<string, mixed>
     */
    public function info(int $uid, int $roomId): array
    {
        $room = $this->repository->findById($roomId);
        if (! $room instanceof Room) {
            throw new BusinessException(ResultCode::NOT_FOUND, '房間不存在');
        }

        $this->ensureMember($roomId, $uid);

        $members = [];
        foreach ($this->roomMemberRepository->activeList($roomId) as $member) {
            $members[] = [
                'user_id' => (int) $member->user_id,
                'nickname' => (string) $member->nickname,
                'avatar' => (string) $member->avatar,
                'score' => (string) $member->score,
                'role' => (int) $member->role,
                'status' => (int) $member->status,
                'joined_at' => $member->joined_at?->toDateTimeString(),
            ];
        }

        return [
            'room' => [
                'id' => (int) $room->id,
                'room_no' => (string) $room->room_no,
                'owner_id' => (int) $room->owner_id,
                'status' => (int) $room->status,
                'member_limit' => (int) $room->member_limit,
                'current_round' => (int) $room->current_round,
                'current_battle_id' => $room->current_battle_id === null ? null : (int) $room->current_battle_id,
                'score_version' => (int) $room->score_version,
            ],
            'members' => $members,
        ];
    }

    /**
     * 房間邀請（方案 §7.3 房間邀請）：房號 + 小程序碼圖 + 分享文案。
     *
     * @return array{room_no: string, qr_url: string, share_title: string}
     */
    public function invite(int $uid, int $roomId): array
    {
        $room = $this->repository->findById($roomId);
        if (! $room instanceof Room) {
            throw new BusinessException(ResultCode::NOT_FOUND, '房間不存在');
        }

        // 權限：僅房間成員可查看邀請碼（方案 §7.3）
        $this->ensureMember($roomId, $uid);

        $member = $this->memberUserRepository->findById($uid);
        $appId = $member instanceof MemberUser ? (string) $member->app_id : '';
        $app = $appId === '' ? null : $this->memberAppRepository->findEnabledByAppId($appId);
        if ($app === null) {
            throw new BusinessException(ResultCode::UNPROCESSABLE_ENTITY, '應用不存在或已停用');
        }

        $roomNo = (string) $room->room_no;

        return [
            'room_no' => $roomNo,
            'qr_url' => $this->wechatClient->getUnlimitedQRCode($app, 'r=' . $roomNo, 'pages/index/index'),
            'share_title' => '來打牌記賬一起記分（房號 ' . $roomNo . '）',
        ];
    }

    /**
     * 校驗當前用戶是該房間的有效成員（status=0 在房 / 2 已單獨結算）。
     */
    public function ensureMember(int $roomId, int $uid): RoomMember
    {
        $member = $this->roomMemberRepository->findByRoomAndUser($roomId, $uid);
        if (! $member instanceof RoomMember || (int) $member->status === RoomMemberStatus::LEFT) {
            throw new BusinessException(ResultCode::FORBIDDEN, '您不是該房間成員');
        }

        return $member;
    }

    /**
     * 房主接管；無可接管成員時觸發整局結算（方案 §7.3 接管規則）。
     *
     * SettleService 經容器延遲解析：RoomService（離開）與 SettleService（單獨結算）互相需要，
     * 構造注入會形成循環依賴，故延遲到調用點再取。
     */
    private function transferOwnerOrSettle(Room $room, int $ownerId): void
    {
        if ($this->ownerTransfer->transfer($room, $ownerId) !== null) {
            return;
        }

        // 此時 room.owner_id 仍為原房主，settle 的房主校驗可通過（方案 §7.3 已註明）
        ApplicationContext::getContainer()->get(SettleService::class)->settle($ownerId, (int) $room->id);
    }

    /**
     * @return array{nickname: string, avatar: string}
     */
    private function memberProfile(int $uid): array
    {
        $member = $this->memberUserRepository->findById($uid);

        return [
            'nickname' => $member instanceof MemberUser ? (string) $member->nickname : '',
            'avatar' => $member instanceof MemberUser ? (string) $member->avatar : '',
        ];
    }

    /**
     * 生成未被佔用的房號：一次生成多個候選 + 一次 whereIn 查重（禁止循環內查庫，方案 §1.3）。
     */
    private function generateUniqueRoomNo(): string
    {
        $candidates = [];
        for ($i = 0; $i < self::ROOM_NO_CANDIDATES; ++$i) {
            $candidates[] = $this->randomRoomNo();
        }

        $free = array_values(array_diff($candidates, $this->repository->existingRoomNos($candidates)));
        if ($free === []) {
            throw new BusinessException(ResultCode::FAIL, '房號生成失敗，請重試');
        }

        return $free[0];
    }

    private function randomRoomNo(): string
    {
        $charset = self::ROOM_NO_CHARSET;
        $max = strlen($charset) - 1;
        $roomNo = '';
        for ($i = 0; $i < self::ROOM_NO_LENGTH; ++$i) {
            $roomNo .= $charset[random_int(0, $max)];
        }

        return $roomNo;
    }

    private function writeEvent(Room $room, int $eventType, int $userId, string $nickname, string $remark = ''): void
    {
        $this->roomEventRepository->create([
            'room_id' => (int) $room->id,
            'room_no' => (string) $room->room_no,
            'battle_id' => $room->current_battle_id,
            'event_type' => $eventType,
            'user_id' => $userId,
            'nickname' => $nickname,
            'remark' => $remark,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
