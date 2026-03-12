<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TimeAllocationLogItem extends Model {
    // 🌟 必须要有这行！否则 save/create 会默默拦截字段导致存不进去
    protected $guarded = []; 
    
    public function log() {
        return $this->belongsTo(TimeAllocationLog::class, 'time_allocation_log_id');
    }
}