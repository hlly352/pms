<?php

namespace App\Http\Controllers;

use App\Models\AllocationRule;
use App\Models\AllocationRuleItem;
use App\Models\AllocationLog;
use App\Models\AllocationLogItem;
use App\Models\Account;
use App\Models\Transaction; // 你的收支流水表
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AllocationController extends Controller
{
    // ==========================================
    // 1. 获取分配面板的统计数据 (核心：计算资金池)
    // ==========================================
    public function getDashboardStats()
    {
        // 查出历史所有收入的总和
        $totalIncome = Transaction::where('type', 'income')->sum('amount');
        // 查出历史已经分配过的总和
        $totalAllocated = AllocationLog::sum('total_amount');
        
        return response()->json([
            'total_income' => $totalIncome,
            'total_allocated' => $totalAllocated,
            'unallocated_pool' => max(0, $totalIncome - $totalAllocated) // 待分配资金池
        ]);
    }

    // ==========================================
    // 2. 规则引擎 CRUD
    // ==========================================
    public function getRules()
    {
        // 连同明细和对应的账户信息一起查出来
        return response()->json(AllocationRule::with('items.account')->get());
    }

    public function saveRule(Request $request)
    {
        $validated = $request->validate([
            'id' => 'nullable|integer',
            'name' => 'required|string|max:50',
            'remark' => 'nullable|string',
            'items' => 'required|array',
            'items.*.account_id' => 'required|exists:accounts,id',
            'items.*.ratio' => 'required|integer|min:1|max:100',
        ]);

        // 校验比例是否为 100%
        $totalRatio = collect($validated['items'])->sum('ratio');
        if ($totalRatio !== 100) {
            return response()->json(['message' => '规则的比例总和必须刚好等于 100%！'], 422);
        }

        DB::transaction(function () use ($validated) {
            $rule = AllocationRule::updateOrCreate(
                ['id' => $validated['id'] ?? null],
                ['name' => $validated['name'], 'remark' => $validated['remark']]
            );

            // 清理旧的明细，重新插入新的明细
            $rule->items()->delete();
            $rule->items()->createMany($validated['items']);
        });

        return response()->json(['message' => '规则保存成功']);
    }

    // ==========================================
    // 3. 🚀 核心执行引擎：执行分配并打钱！
    // ==========================================
    public function executeAllocation(Request $request)
    {
        $request->validate([
            'rule_id' => 'required|exists:allocation_rules,id',
            'amount' => 'required|numeric|min:0.01',
            'remark' => 'nullable|string'
        ]);

        $amountToAllocate = $request->input('amount');
        $rule = AllocationRule::with('items.account')->findOrFail($request->input('rule_id'));

        // 开启数据库事务，确保钱和日志同时成功
        DB::transaction(function () use ($amountToAllocate, $rule, $request) {
            
            // 1. 写主日志
            $log = AllocationLog::create([
                'rule_id' => $rule->id,
                'rule_name' => $rule->name,
                'total_amount' => $amountToAllocate,
                'remark' => $request->input('remark')
            ]);

            $checkTotal = 0;

            // 2. 遍历规则切分蛋糕
            foreach ($rule->items as $item) {
                // 计算该账户应得金额 (保留两位小数)
                $allocatedValue = round($amountToAllocate * ($item->ratio / 100), 2);
                $checkTotal += $allocatedValue;

                if ($allocatedValue > 0) {
                    // 写明细日志 (保存当时账户的名字快照防篡改)
                    AllocationLogItem::create([
                        'log_id' => $log->id,
                        'account_id' => $item->account_id,
                        'account_name' => $item->account->name,
                        'ratio' => $item->ratio,
                        'allocated_amount' => $allocatedValue
                    ]);

                    // 💥 最重要的一步：给对应账户的余额打钱！
                    $item->account->increment('balance', $allocatedValue);
                }
            }

            // 3. 精度修复 (处理 100 块分给 3 个人造成的 33.33 * 3 = 99.99 丢失 1 分钱的问题)
            $diff = round($amountToAllocate - $checkTotal, 2);
            if ($diff != 0) {
                // 把丢失或多出的 1 分钱强行补偿给第一个有分配额度的账户
                $firstLogItem = AllocationLogItem::where('log_id', $log->id)->first();
                if ($firstLogItem) {
                    $firstLogItem->increment('allocated_amount', $diff);
                    Account::where('id', $firstLogItem->account_id)->increment('balance', $diff);
                }
            }
        });

        return response()->json(['message' => '资金分配执行成功！']);
    }

    // ==========================================
    // 4. 获取历史分配日志 (🌟升级：支持多条件搜索)
    // ==========================================
    public function getLogs(Request $request)
    {
        $query = AllocationLog::with('items')->orderBy('created_at', 'desc');

        // 按规则查找
        if ($request->filled('rule_id')) {
            $query->where('rule_id', $request->input('rule_id'));
        }

        // 按执行日期段查找
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [
                $request->input('start_date') . ' 00:00:00',
                $request->input('end_date') . ' 23:59:59'
            ]);
        }

        return response()->json($query->get());
    }

    // ==========================================
    // 5. 🌟 修改：删除日志并彻底清理明细，撤回资金
    // ==========================================
    public function deleteLog($id)
    {
        $log = AllocationLog::with('items.account')->findOrFail($id);

        // 开启事务，保证操作万无一失
        DB::transaction(function () use ($log) {
            // 1. 遍历当时的分配明细，从各大账户里把钱扣除回来
            foreach ($log->items as $item) {
                if ($item->account) {
                    $item->account->decrement('balance', $item->allocated_amount);
                }
            }
            
            // 2. 🌟 核心修复：显式地将这条日志名下的所有“明细记录”全部删除，斩草除根！
            $log->items()->delete(); 
            
            // 3. 最后删除主日志
            $log->delete();
        });

        return response()->json(['message' => '日志删除成功，资金已安全退回，明细已清理！']);
    }
}