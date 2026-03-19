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
                  
            // 🌟 新增：在 time_account_id 字段后新增 account_id (财务账户)
            $table->foreignId('account_id')
                  ->nullable()
                  ->comment('绑定的财务账户ID，录入项目支出时默认从此账户扣款')
                  ->after('time_account_id');
        });
    }

    public function down()
    {
        Schema::table('projects', function (Blueprint $table) {
            // 🌟 撤销迁移时，同时删除这两个字段（可以写在一个数组里一起删）
            $table->dropColumn(['time_account_id', 'account_id']);
        });
    }
};