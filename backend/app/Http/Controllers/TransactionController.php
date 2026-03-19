<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Subject;
use App\Models\Account; // 🌟 新增：引入财务账户模型
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; // 🌟 新增：引入 DB 用于事务处理

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        // 🌟 核心升级：同时预加载 'subject'、'project' 和 'account'，让前端能显示出关联账户名
        $query = Transaction::with(['subject', 'project', 'account']);

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        
        if ($request->filled('remark')) {
            $query->where('remark', 'like', '%' . $request->input('remark') . '%');
        }
        
        if ($request->filled('subject_id')) {
            $subjectId = $request->input('subject_id');
            // 找出这个科目，以及它底下的所有子科目 ID
            $subjectIds = Subject::where('id', $subjectId)->orWhere('pid', $subjectId)->pluck('id');
            $query->whereIn('subject_id', $subjectIds);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('transaction_date', [$request->input('start_date'), $request->input('end_date')]);
        }

        $perPage = $request->input('per_page', 10);
        $transactions = $query->orderBy('transaction_date', 'desc')
                              ->orderBy('created_at', 'desc')
                              ->paginate($perPage);

        return response()->json($transactions);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:income,expense',
            'subject_id' => 'required|exists:subjects,id',
            'project_id' => 'nullable|exists:projects,id',
            'account_id' => 'required|exists:accounts,id', // 🌟 新增：强制要求传入并验证资金账户 ID
            'amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'remark' => 'nullable|string'
        ]);

        // 🌟 核心：使用事务保证“流水”与“余额”同时写入成功
        $transaction = DB::transaction(function () use ($validated) {
            // 1. 创建流水记录
            $trx = Transaction::create($validated);

            // 2. 更新账户余额
            $account = Account::findOrFail($validated['account_id']);
            if ($validated['type'] === 'expense') {
                $account->decrement('balance', $validated['amount']); // 支出：扣减余额
            } else {
                $account->increment('balance', $validated['amount']); // 收入：增加余额
            }

            return $trx;
        });

        return response()->json($transaction, 201);
    }

    public function update(Request $request, Transaction $transaction)
    {
        $validated = $request->validate([
            'type' => 'sometimes|required|in:income,expense',
            'subject_id' => 'sometimes|required|exists:subjects,id',
            'project_id' => 'nullable|exists:projects,id',
            'account_id' => 'sometimes|required|exists:accounts,id', // 🌟 新增账户验证
            'amount' => 'sometimes|required|numeric|min:0.01',
            'transaction_date' => 'sometimes|required|date',
            'remark' => 'nullable|string'
        ]);

        // 🌟 核心：非常严谨的“退旧补新”逻辑
        DB::transaction(function () use ($validated, $transaction) {
            
            // 1. 撤销旧记录对旧账户余额的影响 (把钱退回去)
            $oldAccount = Account::find($transaction->account_id);
            if ($oldAccount) {
                if ($transaction->type === 'expense') {
                    $oldAccount->increment('balance', $transaction->amount); // 之前是扣掉的，现在加回来
                } else {
                    $oldAccount->decrement('balance', $transaction->amount); // 之前是加上的，现在扣回来
                }
            }

            // 2. 更新流水记录为最新的数据
            $transaction->update($validated);

            // 3. 将新记录的金额作用于新账户 (可能账户换了，也可能没换)
            $newAccount = Account::find($transaction->account_id);
            if ($newAccount) {
                if ($transaction->type === 'expense') {
                    $newAccount->decrement('balance', $transaction->amount); // 新支出：扣掉
                } else {
                    $newAccount->increment('balance', $transaction->amount); // 新收入：加上
                }
            }
        });

        return response()->json($transaction);
    }

    public function destroy(Transaction $transaction)
    {
        // 🌟 核心：删除记录前，必须把钱退还给对应账户
        DB::transaction(function () use ($transaction) {
            $account = Account::find($transaction->account_id);
            if ($account) {
                if ($transaction->type === 'expense') {
                    $account->increment('balance', $transaction->amount); // 支出被删除了，钱退回账户
                } else {
                    $account->decrement('balance', $transaction->amount); // 收入被删除了，从账户里扣除
                }
            }
            
            // 删除流水
            $transaction->delete();
        });

        return response()->noContent();
    }
}