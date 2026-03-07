<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    // 🌟 将 'color' 和 'income_ratio' 加入白名单
    protected $fillable = [
        'name',
        'balance',
        'color',        // 🌟 必须加上这一行
        'income_ratio', // 刚才设置比例时新加的，建议也加上
        'icon',
        'color', 
        'remark',
        'status'
    ];
}