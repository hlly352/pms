<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * 1. 获取所有用户列表（带上角色信息）
     */
    public function index()
    {
        // 预加载 roles，解决 N+1 查询问题
        $users = User::with('roles')->latest()->get();
        
        return response()->json($users);
    }

    /**
     * 2. 新增用户 (🌟 新增)
     */
    public function store(Request $request)
    {
        // 校验输入数据
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'role_names' => 'nullable|array'
        ]);

        // 创建用户并对密码进行哈希加密
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // 分配初始角色
        if ($request->has('role_names')) {
            $user->assignRole($request->role_names);
        }

        return response()->json(['msg' => '用户创建成功', 'data' => $user]);
    }

    /**
     * 3. 更新用户信息与角色
     */
    public function update(Request $request, User $user)
    {
        // 校验邮箱时，排除当前用户自己的邮箱，防止报错
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role_names' => 'nullable|array'
        ]);

        // 1. 更新基本信息
        $user->update($request->only('name', 'email'));

        // 2. 同步角色 (Spatie 提供的方法，会自动添加新角色、移除旧角色)
        if ($request->has('role_names')) {
            $user->syncRoles($request->role_names);
        }

        return response()->json(['msg' => '更新成功', 'data' => $user]);
    }

    /**
     * 4. 重置指定用户的密码 (🌟 新增)
     */
    public function resetPassword(Request $request, User $user)
    {
        $request->validate([
            'password' => 'required|string|min:6',
        ]);

        // 强制更新密码（使用 Hash 加密）
        $user->update([
            'password' => Hash::make($request->password)
        ]);

        return response()->json(['msg' => '密码重置成功']);
    }

    /**
     * 5. 删除用户 (🌟 补全)
     */
    public function destroy(User $user)
    {
        // 可选：在这里可以加一个保护逻辑，防止超级管理员把自己删了
        // if ($user->hasRole('super-admin')) {
        //     return response()->json(['message' => '超级管理员不能被删除'], 403);
        // }

        $user->delete();

        return response()->json(['msg' => '删除成功']);
    }
}