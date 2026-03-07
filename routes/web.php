<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/mi', function () {
    // ⚠️ 请把这里的 'old_finance_table' 替换为你旧收支表在数据库里的真实表名！
    $oldTableName = 'finances'; 

    // 获取旧表所有数据 (假设 status=1 是有效数据，如果你想全部迁移可以去掉 where)
    $oldItems = DB::table($oldTableName)->where('status', '1')->get();

    $count = 0;
    $insertData = [];

    foreach ($oldItems as $item) {
        // 1. 转换收支类型：'O' -> 'expense' (支出)，'I' -> 'income' (收入)
        $type = 'expense'; 
        if (strtoupper($item->type) === 'I') {
            $type = 'income';
        } elseif (strtoupper($item->type) === 'O') {
            $type = 'expense';
        }

        // 2. 组装新格式的数据
        $insertData[] = [
            // 'id' => $item->financeid, // 流水号通常不需要保留旧的，让数据库重新自增即可。如果你非要保留，取消这行注释
            'type' => $type,
            'subject_id' => $item->min_cate, // 🌟 核心：直接关联我们上一步保留下来的科目 ID
            'amount' => $item->amount,
            'transaction_date' => $item->dodate,
            'remark' => $item->remark ?: '', // 如果是 null 则转为空字符串
            'created_at' => $item->created_at ?? now(),
            'updated_at' => $item->updated_at ?? now(),
        ];

        $count++;

        // 3. 性能优化：每 500 条批量插入一次，防止老数据太多撑爆内存
        if (count($insertData) >= 500) {
            DB::table('transactions')->insert($insertData);
            $insertData = []; // 清空数组，准备装下一批
        }
    }

    // 将最后不足 500 条的剩余数据插入
    if (!empty($insertData)) {
        DB::table('transactions')->insert($insertData);
    }

    return "🎉 账单明细迁移成功！共完美转换并迁移了 {$count} 条收支记录。快去前端页面看看那霸气的图表吧！";
});