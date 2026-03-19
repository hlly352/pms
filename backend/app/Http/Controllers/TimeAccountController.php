<?php
namespace App\Http\Controllers;

use App\Models\TimeAccount;
use App\Models\TimeAllocationLogItem;
use Illuminate\Http\Request;

class TimeAccountController extends Controller
{
    // 获取所有时间账户
    public function index()
    {
        return response()->json(TimeAccount::orderBy('id', 'desc')->get());
    }

    // 新建时间账户
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'balance_hours' => 'numeric',
            'color' => 'nullable|string',
            'remark' => 'nullable|string',
            'status' => 'integer'
        ]);
        return response()->json(TimeAccount::create($data));
    }

    // 更新时间账户
    public function update(Request $request, $id)
    {
        $account = TimeAccount::findOrFail($id);
        $account->update($request->all());
        return response()->json($account);
    }

    // 删除时间账户
    public function destroy($id)
    {
        $account = TimeAccount::findOrFail($id);
        if ($account->balance_hours > 0) {
            return response()->json(['message' => '账户有余额，禁止删除'], 400);
        }
        $account->delete();
        return response()->json(['message' => '删除成功']);
    }

    // 获取某个账户的历史入账记录
    public function allocations($id)
    {
        $logs = TimeAllocationLogItem::where('time_account_id', $id)
            ->with('log')
            ->orderBy('id', 'desc')
            ->get();
        return response()->json($logs);
    }
}