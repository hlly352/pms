<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AllocationLog extends Model
{
    // 允许批量赋值的白名单
    protected $fillable = [
        'rule_id', 
        'rule_name', 
        'total_amount', 
        'remark'
    ];

    /**
     * 关联：一条分配日志拥有多条明细
     */
    public function items()
    {
        return $this->hasMany(AllocationLogItem::class, 'log_id');
    }

    /**
     * 关联：这条日志使用了哪个规则 (注意：由于规则可能被删，所以配置了 nullable)
     */
    public function rule()
    {
        return $this->belongsTo(AllocationRule::class, 'rule_id');
    }
}