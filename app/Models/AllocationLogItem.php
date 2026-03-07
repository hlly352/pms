<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AllocationLogItem extends Model
{
    // 允许批量赋值的白名单
    protected $fillable = [
        'log_id', 
        'account_id', 
        'account_name', 
        'ratio', 
        'allocated_amount'
    ];

    /**
     * 关联：属于哪一条主日志
     */
    public function log()
    {
        return $this->belongsTo(AllocationLog::class, 'log_id');
    }

    /**
     * 关联：打入了哪个资金账户
     */
    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}