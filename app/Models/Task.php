<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'execution_config' => 'array',
        'status' => 'boolean',
        // 🛑 删除下面这一行 (或者是改为 'string')
        // 'reminder_time' => 'datetime:H:i', 
    ];
    /**
     * ✅ 新增：关联到背诵文档
     */
    public function recitation()
    {
        return $this->belongsTo(Recitation::class);
    }
    public function rule()
    {
        return $this->belongsTo(Rule::class);
    }
    
    public function details()
    {
        return $this->hasMany(TaskDetail::class);
    }
}