<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * 房間系統事件表（方案 §5.2 scoring_room_event）：非金額類事件（進/離房、房主接管、單獨結算、整局結算），
 * 供過程時間線與系統消息還原；金額類事件走 scoring_record。
 */

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

class CreateScoringRoomEventTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('scoring_room_event', static function (Blueprint $table) {
            $table->comment('房間系統事件表');
            $table->bigIncrements('id')->comment('主鍵');
            $table->bigInteger('room_id')->comment('房間ID');
            $table->string('room_no', 16)->default('')->comment('房號冗余');
            $table->bigInteger('battle_id')->nullable()->comment('所屬對局ID（對局開始前為空）');
            $table->tinyInteger('event_type')->comment('類型:1=進入,2=離開,3=房主接管,4=單獨結算,5=整局結算');
            $table->bigInteger('user_id')->comment('主體（進/離者、被接管者、被結算者）');
            $table->string('nickname', 30)->default('')->comment('主體昵稱快照');
            $table->bigInteger('target_user_id')->nullable()->comment('對象（接管=新房主）');
            $table->string('target_nickname', 30)->nullable()->comment('對象昵稱快照');
            $table->string('remark', 255)->default('')->comment('備註');
            $table->datetimes();
            $table->index(['battle_id', 'created_at']);
            $table->index(['room_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scoring_room_event');
    }
}
