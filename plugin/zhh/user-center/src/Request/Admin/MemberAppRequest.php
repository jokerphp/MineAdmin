<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/user-center plugin.
 *
 * 後台應用 CRUD 驗證（方案 §6.4）。
 */

namespace Plugin\Zhh\UserCenter\Request\Admin;

use App\Http\Common\Request\Traits\HttpMethodTrait;
use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;
use Mine\Swagger\Attributes\FormRequest as FormRequestAnnotation;
use Plugin\Zhh\UserCenter\Schema\MemberAppSchema;

#[FormRequestAnnotation(
    schema: MemberAppSchema::class,
    title: '應用（小程序）管理',
    only: ['app_id', 'app_name', 'app_type', 'wechat_appid', 'wechat_secret', 'status', 'remark']
)]
class MemberAppRequest extends FormRequest
{
    use HttpMethodTrait;
    use NoAuthorizeTrait;

    public function rules(): array
    {
        $uniqueAppId = 'unique:member_app,app_id';
        if ($this->isUpdate()) {
            $uniqueAppId .= ',' . (int) $this->input('id');
        }

        return [
            'app_id' => ['required', 'string', 'max:50', $uniqueAppId],
            'app_name' => 'required|string|max:100',
            'app_type' => 'required|string|max:20',
            'wechat_appid' => 'sometimes|string|max:64',
            'wechat_secret' => 'sometimes|string|max:128',
            'status' => 'sometimes|integer|in:1,2',
            'remark' => 'sometimes|string|max:255',
        ];
    }

    public function attributes(): array
    {
        return [
            'app_id' => '應用標識',
            'app_name' => '應用名稱',
            'app_type' => '應用類型',
            'wechat_appid' => '微信小程序appid',
            'wechat_secret' => '微信小程序secret',
            'status' => '狀態',
            'remark' => '備註',
        ];
    }
}
