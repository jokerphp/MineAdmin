<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * 房間成員表（方案 §5.2 scoring_room_member）。
 * score 語義＝當前未清算餘額：計分累加，結算清算時同步增減，結算後歸零。
 */

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

class CreateScoringRoomMemberTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('scoring_room_member', static function (Blueprint $table) {
            $table->comment('計分房間成員表');
            $table->bigIncrements('id')->comment('主鍵');
            $table->bigInteger('room_id')->comment('房間ID');
            $table->bigInteger('user_id')->comment('用戶ID（member_user.id）');
            $table->string('nickname', 30)->default('')->comment('進房時昵稱快照');
            $table->string('avatar', 255)->default('')->comment('進房時頭像快照');
            $table->decimal('score', 10, 2)->default(0)->comment('當前未清算餘額');
            $table->tinyInteger('role')->default(0)->comment('角色:0=普通成員,1=房主');
            $table->tinyInteger('status')->default(0)->comment('狀態:0=在房,1=已退出,2=已單獨結算');
            $table->timestamp('settled_at')->nullable()->comment('單獨結算時間');
            $table->timestamp('joined_at')->nullable()->comment('進房時間（接管順序以此為準）');
            $table->timestamp('left_at')->nullable()->comment('離開時間');
            $table->datetimes();
            $table->unique(['room_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scoring_room_member');
    }
}
