<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/user-center plugin.
 *
 * 後台更新會員驗證（方案 §6.4）。
 */

namespace Plugin\Zhh\UserCenter\Request\Admin;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;
use Mine\Swagger\Attributes\FormRequest as FormRequestAnnotation;
use Plugin\Zhh\UserCenter\Schema\MemberUserSchema;

#[FormRequestAnnotation(
    schema: MemberUserSchema::class,
    title: '更新會員',
    only: ['nickname', 'avatar', 'gender', 'bio', 'phone', 'status', 'remark']
)]
class MemberRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'nickname' => 'sometimes|string|max:30',
            'avatar' => 'sometimes|string|max:255',
            'gender' => 'sometimes|integer|in:0,1,2',
            'bio' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'status' => 'sometimes|integer|in:1,2',
            'remark' => 'sometimes|string|max:255',
        ];
    }

    public function attributes(): array
    {
        return [
            'nickname' => '昵稱',
            'avatar' => '頭像',
            'gender' => '性別',
            'bio' => '簡介',
            'phone' => '手機號',
            'status' => '狀態',
            'remark' => '備註',
        ];
    }
}
