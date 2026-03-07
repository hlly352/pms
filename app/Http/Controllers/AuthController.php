<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    // 登录接口
    public function login(Request $request)
    {
        // 1. 验证前端传来的数据格式
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // 2. 尝试去数据库查找用户
        $user = User::where('email', $request->email)->first();

        // 3. 检查用户是否存在，以及密码是否正确 (Hash::check)
        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'code' => 401,
                'msg' => '账号或密码错误', // 安全起见，通常不告诉别人具体是哪个错了
            ]);
        }

        // 4. 关键步骤：颁发令牌 (Token)
        // createToken 方法是 Sanctum 提供的，'my-app-token' 是令牌的名字
        $token = $user->createToken('my-app-token')->plainTextToken;

        // 5. 返回 Token 和用户信息给前端
        return response()->json([
            'code' => 200,
            'msg' => '登录成功',
            'data' => [
                'token' => $token, // 前端要拿着这个钥匙
                'user' => $user
            ]
        ]);
    }

    // 退出登录接口
    public function logout(Request $request)
    {
        // 销毁当前令牌
        $request->user()->currentAccessToken()->delete();
        
        return response()->json([
            'code' => 200,
            'msg' => '退出成功'
        ]);
    }
}