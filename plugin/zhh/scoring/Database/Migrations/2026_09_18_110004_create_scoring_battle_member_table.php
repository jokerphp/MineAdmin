<?php

declare(strict_types=1);
/**
 * This file is part of the zhh/scoring plugin.
 *
 * 對局成員快照表（方案 §5.2 scoring_battle_member）：結算時固化，房間刪除不影響戰績。
 * total_score 為分段口徑（本段成績 = 餘額 + 本人上次結算以來的清算淨流入），多次快照求和即全程總成績。
 * 不設唯一約束：同一成員可有多行快照（單獨結算後重入再結算）。
 */

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;
use Hyperf\DbConnection\Db;

class CreateScoringBattleMemberTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 自愈：MySQL 的 DDL 不會回滾，若上次安裝在「建表成功、加索引失敗」時中斷，
        // 會留下已建好但未被 migrations 表記錄的半成品表，重跑遷移就會撞 1050（table already exists）。
        // 這裡只在該表為空（安裝中斷、尚無業務資料）時重建；一旦有資料則中止，強制人工處理，避免誤刪。
        if (Schema::hasTable('scoring_battle_member')) {
            if (Db::table('scoring_battle_member')->count() > 0) {
                throw new RuntimeException('scoring_battle_member 已存在且含資料，請人工確認後再執行本遷移');
            }

            Schema::drop('scoring_battle_member');
            echo "已清理上次中斷留下的空表 scoring_battle_member\n";
        }

        Schema::create('scoring_battle_member', static function (Blueprint $table) {
            $table->comment('對局成員快照表');
            $table->bigIncrements('id')->comment('主鍵');
            $table->bigInteger('battle_id')->comment('對局ID');
            $table->bigInteger('user_id')->comment('用戶ID');
            $table->string('nickname', 30)->default('')->comment('結算時昵稱快照');
            $table->string('avatar', 255)->default('')->comment('結算時頭像快照');
            $table->decimal('total_score', 10, 2)->default(0)->comment('結算快照分數（分段口徑）');
            $table->integer('rank')->default(0)->comment('結算時相對排名');
            $table->tinyInteger('result')->default(0)->comment('結果:1=勝,2=負,3=平');
            $table->decimal('tea_fee', 10, 2)->default(0)->comment('茶水費');
            $table->tinyInteger('settle_type')->default(1)->comment('結算類型:1=整局結算,2=單獨結算');
            $table->timestamp('settled_at')->nullable()->comment('結算時間');
            $table->datetimes();

            // 索引名必須顯式指定：Hyperf 自動命名規則為
            // {連接前綴}{表名}_{欄位1}_{欄位2}..._{type}，本複合索引會產生
            // `scoring_battle_member_battle_id_user_id_settle_type_settled_at_index`（68 字元，
            // 若 DB_PREFIX=zhh_ 則 72 字元），超過 MySQL 的 64 字元上限（錯誤 1059）。
            $table->index(['battle_id', 'user_id', 'settle_type', 'settled_at'], 'idx_bm_battle_user_settle');
            $table->index(['user_id', 'settled_at'], 'idx_bm_user_settled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scoring_battle_member');
    }
}
