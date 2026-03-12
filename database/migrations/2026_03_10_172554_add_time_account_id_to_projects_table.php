<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('projects', function (Blueprint $table) {
            // 在 goal_weight 字段后新增 time_account_id
            $table->foreignId('time_account_id')
                  ->nullable()
                  ->comment('绑定的时间池ID，任务打卡时优先扣除此账户')
                  ->after('goal_weight'); 
        });
    }

    public function down()
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('time_account_id');
        });
    }
};