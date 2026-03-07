<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    // 获取所有配置，转成 { key: value } 格式给前端
    public function index()
    {
        // pluck('value', 'key') 会把结果变成 ['site_name' => 'MyLife', 'avatar' => '...'] 这种格式
        // 这样前端用起来非常方便
        return Setting::pluck('value', 'key');
    }

    // 批量保存配置
    public function update(Request $request)
    {
        $data = $request->all();

        foreach ($data as $key => $value) {
            // updateOrCreate: 如果 key 存在就更新 value，不存在就创建
            Setting::updateOrCreate(
                ['key' => $key], // 查找条件
                ['value' => $value] // 更新内容
            );
        }

        return response()->json(['msg' => '设置已保存']);
    }
}