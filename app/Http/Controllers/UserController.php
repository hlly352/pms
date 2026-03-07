<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    // 获取所有用户列表（带上角色信息）
    public function index()
    {
        // with('role') 叫“预加载”，是为了解决 N+1 查询问题，一次性把角色名字查出来
        // $users = User::with('role')->get();
        // 以前是 with('role')，现在改成 with('roles')
        // 因为 Spatie 支持一个用户有多个角色
        return User::with('roles')->latest()->get();
        
        return response()->json([
            'code' => 200,
            'msg' => '获取成功',
            'data' => $users
        ]);
        
    }
    public function update(Request $request, User $user)
    {
        // 1. 更新基本信息
        $user->update($request->only('name', 'email'));

        // 2. 同步角色 (关键步骤)
        // 前端会传过来一个数组：['super-admin', 'editor']
        if ($request->has('role_names')) {
            $user->syncRoles($request->role_names);
        }

        return $user;
    }
}