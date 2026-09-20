<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * 加入房間驗證（方案 §7.2）。
 */

namespace Plugin\Zhh\Scoring\Request\Api;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

class JoinRoomRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'room_no' => 'required|string|size:6',
        ];
    }

    public function attributes(): array
    {
        return [
            'room_no' => '房號',
        ];
    }
}
