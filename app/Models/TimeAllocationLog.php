<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TimeAllocationLog extends Model {
    protected $guarded = [];

    // 🌟 必须要有这个关联，with('items') 才能生效
    public function items() {
        return $this->hasMany(TimeAllocationLogItem::class, 'time_allocation_log_id');
    }
}