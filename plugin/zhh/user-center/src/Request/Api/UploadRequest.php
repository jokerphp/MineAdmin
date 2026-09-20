<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/user-center plugin.
 *
 * 上傳驗證（方案 §6.3、§12 上傳安全）：mime 白名單 + 2MB 上限。
 */

namespace Plugin\Zhh\UserCenter\Request\Api;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

class UploadRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'file' => 'required|file|image|mimes:jpg,jpeg,png,webp|max:2048',
            'type' => 'required|string|in:avatar,background',
        ];
    }

    public function attributes(): array
    {
        return [
            'file' => '圖片文件',
            'type' => '用途（avatar=頭像,background=背景圖）',
        ];
    }
}
