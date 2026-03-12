<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('task_account_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('source')->unique()->comment('任务类型(tasks.source)');
            $table->foreignId('time_account_id')->nullable()->comment('绑定的时间池ID');
            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('task_account_mappings');
    }
};