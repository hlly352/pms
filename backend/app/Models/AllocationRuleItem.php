<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AllocationRuleItem extends Model {
    protected $fillable = ['rule_id', 'account_id', 'ratio'];
    public function account() {
        return $this->belongsTo(Account::class, 'account_id');
    }
}