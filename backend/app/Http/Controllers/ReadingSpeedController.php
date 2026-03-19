<?php

namespace App\Http\Controllers;

use App\Models\ReadingSpeed;
use Illuminate\Http\Request;

class ReadingSpeedController extends Controller
{
    // 获取列表
    public function index()
    {
        return ReadingSpeed::orderBy('created_at', 'desc')->get();
    }

    // 新增
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:50',
            'speed' => 'required|integer|min:1',
        ]);

        return ReadingSpeed::create($data);
    }

    // 更新
    public function update(Request $request, $id)
    {
        $speed = ReadingSpeed::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:50',
            'speed' => 'required|integer|min:1',
        ]);

        $speed->update($data);
        return $speed;
    }

    // 删除
    public function destroy($id)
    {
        ReadingSpeed::destroy($id);
        return response()->noContent();
    }
}