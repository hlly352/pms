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
        Schema::create('allocation_log_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('log_id')->constrained('allocation_logs')->onDelete('cascade');
            $table->foreignId('account_id')->constrained('accounts')->onDelete('cascade');
            $table->string('account_name', 50)->comment('当时的账户名称');
            $table->unsignedInteger('ratio')->comment('当时执行的比例');
            $table->decimal('allocated_amount', 10, 2)->comment('实际分到的金额');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('allocation_log_items');
    }
};
