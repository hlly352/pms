<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // 1. 创建笔记本表
        Schema::create('notebooks', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('笔记本名称');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // 2. 插入一个默认笔记本 (防止老数据游离)
        $defaultNotebookId = DB::table('notebooks')->insertGetId([
            'name' => '默认笔记本',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. 修改 recitations 表，添加 notebook_id
        Schema::table('recitations', function (Blueprint $table) use ($defaultNotebookId) {
            $table->unsignedBigInteger('notebook_id')->default($defaultNotebookId)->after('id')->index();
        });
    }

    public function down()
    {
        Schema::table('recitations', function (Blueprint $table) {
            $table->dropColumn('notebook_id');
        });
        Schema::dropIfExists('notebooks');
    }
};