<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * 支出（茶水費）驗證（方案 §7.2）。
 */

namespace Plugin\Zhh\Scoring\Request\Api;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

class ExpenseRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'room_id' => 'required|integer|min:1',
            'amount' => 'required|numeric|min:0.01',
            'remark' => 'sometimes|string|max:255',
        ];
    }

    public function attributes(): array
    {
        return [
            'room_id' => '房間ID',
            'amount' => '支出金額',
            'remark' => '備註',
        ];
    }
}
