<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * 計分驗證（方案 §7.3）：amount 恆為正，轉賬方向由「操作人（付款方）→ target_user_id（收款方）」決定。
 */

namespace Plugin\Zhh\Scoring\Request\Api;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

class RecordRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'room_id' => 'required|integer|min:1',
            'target_user_id' => 'required|integer|min:1',
            'amount' => 'required|numeric|min:0.01',
            'remark' => 'sometimes|string|max:255',
        ];
    }

    public function attributes(): array
    {
        return [
            'room_id' => '房間ID',
            'target_user_id' => '收款方用戶ID',
            'amount' => '分值',
            'remark' => '備註',
        ];
    }
}
