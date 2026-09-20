<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * 房間 ID 入參驗證（詳情/離開/邀請等，方案 §7.2）。
 */

namespace Plugin\Zhh\Scoring\Request\Api;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

class RoomIdRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'room_id' => 'required|integer|min:1',
        ];
    }

    public function attributes(): array
    {
        return [
            'room_id' => '房間ID',
        ];
    }
}
