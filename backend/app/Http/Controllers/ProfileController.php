<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    // 修改邮箱
    public function updateEmail(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            // 验证必须是邮箱格式，且在 users 表中唯一（排除当前用户自己）
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
        ], [
            'email.unique' => '该邮箱已被其他账号使用。'
        ]);

        $user->update([
            'email' => $request->email
        ]);

        return response()->json(['msg' => '邮箱更新成功']);
    }

    // 修改密码
    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'old_password' => 'required',
            'new_password' => 'required|min:6|confirmed', // confirmed 自动要求 new_password_confirmation 匹配
        ], [
            'new_password.confirmed' => '两次输入的新密码不一致。',
            'new_password.min' => '新密码不能少于 6 个字符。',
        ]);

        // 验证原密码是否正确
        if (!Hash::check($request->old_password, $user->password)) {
            return response()->json(['message' => '原密码输入错误'], 422); // 返回 422 验证错误状态码
        }

        // 验证通过，保存新密码
        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        return response()->json(['msg' => '密码修改成功']);
    }
}