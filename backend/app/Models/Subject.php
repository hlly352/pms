<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
   // 🌟 1. 在白名单中加入 account_id
    protected $fillable = [
        'subject_name', 
        'subject_type', 
        'subject_code',
        'pid', 
        'account_id', // 新增的字段
        'subject_order', 
        'status'
    ];

    // 🌟 2. 新增与账户的关联
    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    // 可选：定义子科目关联关系 (方便以后做更复杂的查询)
    public function children()
    {
        return $this->hasMany(Subject::class, 'pid', 'id');
    }
}