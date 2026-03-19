<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    // 列表
    public function index()
    {
        return Permission::latest()->get();
    }

    // 新增
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:permissions,name', // 必须唯一
            // 'label' => 'nullable' // 如果你以后想加中文描述，可以在这里加
        ]);

        // 默认 guard_name 为 web，Spatie 会自动处理
        // 👇 修改后：强制指定 guard_name 为 'web'
        return Permission::create([
            'name' => $request->name, 
            'guard_name' => 'web' 
        ]);
    }

    // 删除
    public function destroy($id)
    {
        $permission = Permission::findOrFail($id);
        $permission->delete();
        return response()->noContent();
    }
}