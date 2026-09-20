<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * 計分/支出流水表（方案 §5.2 scoring_record）：對帳與明細的唯一依據，只增不改。
 * amount 恆為正：record_type=1/3 為轉賬額（收款方 +amount、付款方 −amount），record_type=2 為支出額。
 */

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

class CreateScoringRecordTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('scoring_record', static function (Blueprint $table) {
            $table->comment('計分/支出流水表');
            $table->bigIncrements('id')->comment('主鍵');
            $table->bigInteger('room_id')->comment('房間ID');
            $table->string('room_no', 16)->default('')->comment('房號冗余');
            $table->bigInteger('battle_id')->nullable()->comment('所屬對局ID');
            $table->integer('round_no')->nullable()->comment('所屬輪次');
            $table->bigInteger('user_id')->comment('操作人（1/3為付款方，2為支出記錄人）');
            $table->bigInteger('target_user_id')->nullable()->comment('收款方（1/3為轉入分數者）');
            $table->tinyInteger('record_type')->comment('類型:1=計分,2=支出/茶水,3=結算清算');
            $table->decimal('amount', 10, 2)->comment('金額/分數（恆為正）');
            $table->string('remark', 255)->default('')->comment('備註');
            $table->bigInteger('created_by')->default(0)->comment('創建人');
            $table->datetimes();
            $table->index(['battle_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scoring_record');
    }
}
