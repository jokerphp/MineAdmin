<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/user-center plugin.
 *
 * 應用（小程序/程序）註冊表（方案 §5.1 member_app）：多程序復用的關鍵，
 * 未來對接不同程序（另一小程序/H5/App）只需新增一條記錄，用戶表無需改動。
 */

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

class CreateMemberAppTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('member_app', static function (Blueprint $table) {
            $table->comment('應用（小程序/程序）註冊表');
            $table->bigIncrements('id')->comment('應用ID,主鍵');
            $table->string('app_id', 50)->unique()->comment('應用唯一標識，對外暴露');
            $table->string('app_name', 100)->comment('應用名稱');
            $table->string('app_type', 20)->comment('應用類型:wechat_mini_program/other');
            $table->string('wechat_appid', 64)->default('')->comment('微信小程序appid');
            $table->string('wechat_secret', 128)->default('')->comment('微信小程序secret（密文或環境變量引用）');
            $table->tinyInteger('status')->default(1)->comment('狀態:1=啟用,2=停用');
            $table->string('remark', 255)->default('')->comment('備註');
            $table->datetimes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_app');
    }
}
