<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * 對局/戰績主表（方案 §5.2 scoring_battle）。
 */

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

class CreateScoringBattleTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('scoring_battle', static function (Blueprint $table) {
            $table->comment('對局/戰績主表');
            $table->bigIncrements('id')->comment('主鍵');
            $table->bigInteger('room_id')->comment('來源房間ID');
            $table->string('room_no', 16)->default('')->comment('房號冗余');
            $table->bigInteger('owner_id')->comment('房主（member_user.id）');
            $table->tinyInteger('status')->default(0)->comment('狀態:0=進行中,1=已結束,2=異常作廢');
            $table->integer('total_rounds')->default(0)->comment('總輪次（整局結算時回填）');
            $table->decimal('tea_fee_total', 10, 2)->default(0)->comment('茶水費合計（整局結算時回填）');
            $table->timestamp('started_at')->nullable()->comment('開始時間（首筆計分時記）');
            $table->timestamp('finished_at')->nullable()->comment('結束時間（整局結算時記）');
            $table->datetimes();
            $table->index(['room_id']);
            $table->index(['owner_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scoring_battle');
    }
}
