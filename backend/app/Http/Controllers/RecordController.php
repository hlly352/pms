<?php

namespace App\Http\Controllers;

use App\Models\Record;
use Illuminate\Http\Request;

class RecordController extends Controller
{
    // 获取列表：按时间倒序
    public function index()
    {
        return Record::latest()->get();
    }

    // 发布一条新记录
    public function store(Request $request)
    {
        $request->validate(['content' => 'required']);

        $record = new Record();
        $record->content = $request->input('content');
        // type 默认是 'daily'，你也可以通过前端传过来
        $record->type = $request->input('type', 'daily'); 
        $record->save();

        return response()->json($record, 201);
    }

    // 删除记录
    public function destroy(Record $record)
    {
        $record->delete();
        return response()->json(['msg' => '删除成功']);
    }
}