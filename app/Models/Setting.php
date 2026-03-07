<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    // 允许 'key'和 'value' 被批量写入
    protected $fillable = ['key', 'value'];
}