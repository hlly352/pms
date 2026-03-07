<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    protected $fillable = [
        'parent_id', 'title', 'icon', 'path', 
        'name', 'component', 'permission', 
        'sort', 'hidden', 
        'is_system' // 👈 系统菜单不能删
    ];
    
    // 建议加上类型转换，保证取出来是 true/false 而不是 1/0
    protected $casts = [
        'is_system' => 'boolean',
        'hidden' => 'boolean',
    ];

    // 关联子菜单
    public function children()
    {
        return $this->hasMany(Menu::class, 'parent_id')->orderBy('sort');
    }

    // 递归获取子菜单
    public function childrenRecursive()
    {
        return $this->children()->with('childrenRecursive');
    }
}