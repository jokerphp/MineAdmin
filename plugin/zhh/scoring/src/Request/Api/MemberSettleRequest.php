<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * 單獨結算驗證（方案 §7.3）：僅房主或本人可發起。
 */

namespace Plugin\Zhh\Scoring\Request\Api;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

class MemberSettleRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'room_id' => 'required|integer|min:1',
            'target_user_id' => 'required|integer|min:1',
        ];
    }

    public function attributes(): array
    {
        return [
            'room_id' => '房間ID',
            'target_user_id' => '被結算成員用戶ID',
        ];
    }
}
