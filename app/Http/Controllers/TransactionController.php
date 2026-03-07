<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Subject;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        // 🌟 核心升级：同时预加载 'subject' 和 'project'，解决表格里项目名显示不出的问题
        $query = Transaction::with(['subject', 'project']);

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
            'project_id' => 'nullable|exists:projects,id', // 🌟 新增：验证项目ID（选填，如果有值必须真实存在）
            'amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'remark' => 'nullable|string'
        ]);

        $transaction = Transaction::create($validated);
        return response()->json($transaction, 201);
    }

    public function update(Request $request, Transaction $transaction)
    {
        $validated = $request->validate([
            'type' => 'sometimes|required|in:income,expense',
            'subject_id' => 'sometimes|required|exists:subjects,id',
            'project_id' => 'nullable|exists:projects,id', // 🌟 新增：验证项目ID
            'amount' => 'sometimes|required|numeric|min:0.01',
            'transaction_date' => 'sometimes|required|date',
            'remark' => 'nullable|string'
        ]);

        $transaction->update($validated);
        return response()->json($transaction);
    }

    public function destroy(Transaction $transaction)
    {
        $transaction->delete();
        return response()->noContent();
    }
}