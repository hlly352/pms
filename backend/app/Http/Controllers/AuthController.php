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
        // 1. 🌟 修改：验证前端传来的 username 而不是 email，去掉 email 格式验证
        $request->validate([
            'username' => 'required|string', 
            'password' => 'required',
        ]);

        // 2. 🌟 修改：去数据库查找用户时，用 name 字段去匹配前端传来的 username
        $user = User::where('name', $request->username)->first();

        // 3. 检查用户是否存在，以及密码是否正确 (Hash::check)
        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'code' => 401,
                'msg' => '账号或密码错误', // 安全起见，不告诉具体是账号错还是密码错
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