<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/user-center plugin.
 *
 * 會員用戶表（方案 §5.1 member_user）。
 * 微信 session_key 不入庫，存 Redis（uc:session_key:{app_id}:{openid}），降低泄露風險。
 */

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

class CreateMemberUserTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('member_user', static function (Blueprint $table) {
            $table->comment('會員用戶表');
            $table->bigIncrements('id')->comment('用戶ID,主鍵');
            $table->string('app_id', 50)->comment('所屬應用標識');
            $table->string('openid', 64)->comment('微信openid');
            $table->string('unionid', 64)->default('')->comment('微信unionid');
            $table->string('nickname', 30)->default('')->comment('昵稱');
            $table->string('avatar', 255)->default('')->comment('頭像');
            $table->string('background', 255)->default('')->comment('背景圖');
            $table->tinyInteger('gender')->default(0)->comment('性別:0=未知,1=男,2=女');
            $table->string('bio', 255)->default('')->comment('簡介');
            $table->string('phone', 20)->default('')->comment('手機號');
            $table->tinyInteger('status')->default(1)->comment('狀態:1=正常,2=停用');
            $table->integer('login_count')->default(0)->comment('登錄次數');
            $table->timestamp('last_login_at')->nullable()->comment('最後登錄時間');
            $table->string('last_login_ip', 45)->default('')->comment('最後登錄IP');
            $table->string('remark', 255)->default('')->comment('備註');
            $table->datetimes();
            $table->softDeletes();
            $table->unique(['app_id', 'openid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_user');
    }
}
