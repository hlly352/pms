<?php

namespace App\Observers;

use App\Models\Transaction;
use App\Models\Subject;
use App\Models\Account;
use Illuminate\Support\Facades\Log;

class TransactionObserver
{
    /**
     * 1. 监听：新增了一笔流水 (扣钱)
     */
    public function created(Transaction $transaction)
    {
        if ($transaction->type === 'expense') {
            $this->updateAccountBalance($transaction->subject_id, -$transaction->amount);
        }
    }

    /**
     * 2. 监听：修改了一笔流水 (多退少补)
     */
    public function updated(Transaction $transaction)
    {
        // if ($transaction->type === 'expense') {
        //     $oldSubjectId = $transaction->getOriginal('subject_id');
        //     $newSubjectId = $transaction->subject_id;
            
        //     $oldAmount = $transaction->getOriginal('amount');
        //     $newAmount = $transaction->amount;

        //     if ($oldSubjectId == $newSubjectId) {
        //         // 科目没变，只是改了金额
        //         $diff = $oldAmount - $newAmount; 
        //         $this->updateAccountBalance($newSubjectId, $diff);
        //     } else {
        //         // 换了科目：旧科目的钱退回，新科目重新扣
        //         $this->updateAccountBalance($oldSubjectId, $oldAmount);
        //         $this->updateAccountBalance($newSubjectId, -$newAmount);
        //     }
        // }
    }

    /**
     * 3. 监听：删除了一笔流水 (退钱)
     */
    public function deleted(Transaction $transaction)
    {
        // if ($transaction->type === 'expense') {
        //     $this->updateAccountBalance($transaction->subject_id, $transaction->amount);
        // }
    }

    /**
     * 🚀 核心执行器：严格基于 pid 寻找主科目并扣款
     */
    /**
     * 🚀 核心执行器：严格基于 pid 寻找主科目并扣款
     */
    private function updateAccountBalance($subjectId, $amountChange)
    {
        // if ($amountChange == 0) return;

        // $subject = Subject::find($subjectId);
        
        // if (!$subject) {
        //     Log::warning("自动扣款失败：未找到流水对应的科目 ID: {$subjectId}");
        //     return;
        // }

        // 🌟 核心修复：严格使用 pid！如果 pid 大于 0，说明它是子科目，就去查它的主科目
        // $mainSubject = ($subject->pid > 0) ? Subject::find($subject->pid) : $subject;

        // 如果找到了主科目，并且主科目绑定了 account_id，执行扣款！
        // if ($mainSubject && $mainSubject->account_id) {
            
        //     Log::info("自动扣款执行：科目[{$mainSubject->subject_name}] -> 触发账户ID[{$mainSubject->account_id}] 余额变动: {$amountChange}");
            
        //     Account::where('id', $mainSubject->account_id)->increment('balance', $amountChange);
            
        // } else {
            // 🌟 修复 PHP 语法报错：将表达式提取出来，或者用字符串拼接
            // $subjectName = $mainSubject ? $mainSubject->subject_name : '未知';
            // Log::info("自动扣款跳过：主科目[{$subjectName}] 未绑定扣款账户。");
        // }
    }
}