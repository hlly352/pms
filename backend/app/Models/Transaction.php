<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    // 🌟 1. 将 'category' 换成 'subject_id'
    protected $fillable = [
        'type', 
        'subject_id', 
        'amount', 
        'transaction_date', 
        'project_id',
        'account_id',
        'remark'
    ];

    // 🌟 2. 定义与 Subject 表的关联关系
    public function subject()
    {
        // 一笔流水属于一个科目
        return $this->belongsTo(Subject::class, 'subject_id', 'id');
    }
    // 🌟 关联项目模型
    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
    // 🌟 补上资金账户的关联
    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}