<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * 結算清算轉賬方案表（方案 §5.2 scoring_settle_transfer）。
 * 清算方案在結算事務中計算一次並持久化，之後所有展示一律讀本表，保證全員看到同一份結果。
 */

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

class CreateScoringSettleTransferTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('scoring_settle_transfer', static function (Blueprint $table) {
            $table->comment('結算清算轉賬方案表');
            $table->bigIncrements('id')->comment('主鍵');
            $table->bigInteger('room_id')->comment('房間ID');
            $table->bigInteger('battle_id')->comment('對局ID');
            $table->tinyInteger('settle_type')->default(1)->comment('結算類型:1=整局結算,2=單獨結算');
            $table->bigInteger('from_user_id')->comment('付款方（負分者）');
            $table->bigInteger('to_user_id')->comment('收款方（正分者）');
            $table->decimal('amount', 10, 2)->comment('清算金額');
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
        Schema::dropIfExists('scoring_settle_transfer');
    }
}
