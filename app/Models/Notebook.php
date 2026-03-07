<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notebook extends Model
{
    // 允许批量赋值的字段
    // 或者使用 protected $guarded = []; 解除所有限制
    protected $fillable = ['name', 'description'];

    /**
     * 关联背诵文档/文件夹
     * 一个笔记本包含多个背诵条目
     */
    public function recitations()
    {
        return $this->hasMany(Recitation::class);
    }
}