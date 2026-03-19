<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    // 1. 获取所有账户列表
    public function index()
    {
        $accounts = Account::orderBy('id', 'asc')->get();
        return response()->json($accounts);
    }

    // 2. 新增账户
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'balance' => 'nullable|numeric', // 🌟 修复：去掉了 default:0
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:20',
            'remark' => 'nullable|string|max:255',
            'status' => 'required|in:0,1',
        ]);

        // 如果前端没传余额，默认给 0
        $validated['balance'] = $validated['balance'] ?? 0;

        $account = Account::create($validated);

        return response()->json($account, 201);
    }

    // 3. 更新账户资料
    public function update(Request $request, Account $account)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'balance' => 'nullable|numeric', // 🌟 修复：同样修改这里
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:20',
            'remark' => 'nullable|string|max:255',
            'status' => 'required|in:0,1',
        ]);

        $account->update($validated);

        return response()->json($account);
    }

    // 4. 删除账户
    public function destroy(Account $account)
    {
        // 🛡️ 财务系统铁律：账户里还有钱，绝对不能删！
        if ($account->balance != 0) {
            return response()->json(['message' => '该账户余额不为零，为保证财务安全，禁止直接删除！'], 422);
        }

        $account->delete();

        return response()->noContent();
    }
    // ==========================================
    // 🌟 新增：获取某个账户的历史入账分配记录
    // ==========================================
    public function allocations($id)
    {
        // 从分配明细表中，查出属于这个账户的记录，并关联主日志以获取规则名称
        $records = \App\Models\AllocationLogItem::with('log')
            ->where('account_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($records);
    }
}