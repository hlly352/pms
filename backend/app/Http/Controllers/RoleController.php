<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    // 获取角色列表
    public function index()
    {
        return Role::with('permissions')->get();
    }

    // 获取所有可用权限 (供前端勾选)
    public function permissions()
    {
        return Permission::all();
    }

    // 创建角色
    public function store(Request $request)
    {
        $role = Role::create(['name' => $request->name, 'guard_name' => 'web']);
        // 同步权限
        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }
        return $role;
    }

    // 更新角色及其权限
    public function update(Request $request, Role $role)
    {
        $role->update(['name' => $request->name]);
        if ($request->has('permissions')) {
            // 前端传过来的是权限名数组 ['user.view', 'task.view']
            $role->syncPermissions($request->permissions);
        }
        return $role;
    }
    
    public function destroy(Role $role)
    {
        $role->delete();
        return response()->noContent();
    }
}