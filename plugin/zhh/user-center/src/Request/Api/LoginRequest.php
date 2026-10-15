<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/user-center plugin.
 *
 * 微信登錄請求驗證（方案 §6.2）。用戶端接口無需 Casbin 權限，使用 NoAuthorizeTrait。
 */

namespace Plugin\Zhh\UserCenter\Request\Api;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Collection\Arr;
use Hyperf\Validation\Request\FormRequest;
use Mine\Support\Request\ClientIpRequestTrait;

class LoginRequest extends FormRequest
{
    use ClientIpRequestTrait;
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'app_id' => 'required|string|max:50',
            'code' => 'required|string|max:128',
            'nickname' => 'sometimes|string|max:30',
            'avatar' => 'sometimes|string|max:255',
        ];
    }

    public function attributes(): array
    {
        return [
            'app_id' => '應用標識',
            'code' => '微信登錄憑證',
            'nickname' => '昵稱',
            'avatar' => '頭像',
        ];
    }

    public function ip(): string
    {
        return Arr::first($this->getClientIps(), static fn ($ip) => $ip, '0.0.0.0');
    }
}
