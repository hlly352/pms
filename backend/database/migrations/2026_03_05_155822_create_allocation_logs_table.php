<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('allocation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->nullable()->constrained('allocation_rules')->nullOnDelete();
            $table->string('rule_name', 50)->comment('当时的规则名称');
            $table->decimal('total_amount', 10, 2)->comment('本次分配的总金额');
            $table->string('remark', 255)->nullable()->comment('分配备注');
            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('allocation_logs');
    }
};
