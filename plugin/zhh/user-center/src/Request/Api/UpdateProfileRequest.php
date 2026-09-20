<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/user-center plugin.
 *
 * 更新個人資料驗證（規則與前端對齊，方案 §6.3）。
 */

namespace Plugin\Zhh\UserCenter\Request\Api;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;
use Mine\Swagger\Attributes\FormRequest as FormRequestAnnotation;
use Plugin\Zhh\UserCenter\Schema\MemberUserSchema;

#[FormRequestAnnotation(
    schema: MemberUserSchema::class,
    title: '更新個人資料',
    only: ['nickname', 'gender', 'bio', 'avatar', 'background']
)]
class UpdateProfileRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'nickname' => 'sometimes|string|max:30',
            'gender' => 'sometimes|integer|in:0,1,2',
            'bio' => 'sometimes|string|max:255',
            'avatar' => 'sometimes|url|max:255',
            'background' => 'sometimes|url|max:255',
        ];
    }

    public function attributes(): array
    {
        return [
            'nickname' => '昵稱',
            'gender' => '性別',
            'bio' => '簡介',
            'avatar' => '頭像',
            'background' => '背景圖',
        ];
    }
}
