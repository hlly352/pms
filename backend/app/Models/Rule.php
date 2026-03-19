<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rule extends Model
{
    protected $guarded = [];
    protected $fillable = [
        'name', 'type', 'module', 'purpose', 'details', 'remark' // 👈 加上 module
    ];

    // 自动将 details 字段转为数组/对象
    protected $casts = [
        'details' => 'array',
    ];
}