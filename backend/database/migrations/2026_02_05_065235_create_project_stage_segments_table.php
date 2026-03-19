<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void
    {
        Schema::create('project_stage_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_stage_id')->constrained('project_stages')->onDelete('cascade');
            $table->integer('weight')->default(0); // 比重
            $table->date('start_date'); // 开始时间
            $table->date('end_date'); // 结束时间
            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_stage_segments');
    }
};
