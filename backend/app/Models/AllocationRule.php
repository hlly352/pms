<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AllocationRule extends Model {
    protected $fillable = ['name', 'remark'];
    public function items() {
        return $this->hasMany(AllocationRuleItem::class, 'rule_id');
    }
}