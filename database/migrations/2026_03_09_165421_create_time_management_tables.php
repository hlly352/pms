<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // 1. 时间账户表 (如：学习池、娱乐池)
        Schema::create('time_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('账户名称');
            $table->decimal('balance_hours', 10, 2)->default(0)->comment('当前时间余额(小时)');
            $table->string('color')->default('#7b61ff')->comment('主题色');
            $table->string('remark')->nullable()->comment('用途说明');
            $table->tinyInteger('status')->default(1)->comment('1:正常 0:停用');
            $table->timestamps();
        });

        // 2. 时间分配规则主表
        Schema::create('time_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('规则名称');
            $table->string('remark')->nullable()->comment('规则说明');
            // 🌟 直接在这里加上规则启停开关
            $table->boolean('is_active')->default(false)->comment('是否启用此规则'); 
            $table->timestamps();
        });

        // 3. 时间分配规则明细表
        Schema::create('time_rule_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('time_rule_id')->constrained('time_rules')->onDelete('cascade');
            $table->foreignId('time_account_id')->constrained('time_accounts')->onDelete('cascade');
            $table->json('days_of_week')->comment('触发星期几:0=周日,1-6=周一至六');
            $table->decimal('allocate_hours', 10, 2)->comment('每次分配时长(小时)');
            $table->timestamps();
        });

        // 4. 时间分配(充值)日志主表
        Schema::create('time_allocation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('time_rule_id')->nullable()->comment('使用的规则ID');
            $table->string('rule_name')->comment('规则名称快照');
            $table->decimal('total_hours', 10, 2)->comment('本次分配总时长');
            $table->string('remark')->nullable()->comment('分配备注');
            $table->timestamps();
        });

        // 5. 时间分配日志明细表 (具体流向)
        Schema::create('time_allocation_log_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('time_allocation_log_id')->constrained('time_allocation_logs')->onDelete('cascade');
            $table->foreignId('time_account_id')->comment('当时入账的账户ID');
            $table->string('account_name')->comment('账户名称快照');
            $table->decimal('allocated_hours', 10, 2)->comment('实际入账时长');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('time_allocation_log_items');
        Schema::dropIfExists('time_allocation_logs');
        Schema::dropIfExists('time_rule_items');
        Schema::dropIfExists('time_rules');
        Schema::dropIfExists('time_accounts');
    }
};