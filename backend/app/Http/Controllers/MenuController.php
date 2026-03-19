<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Menu;

class MenuController extends Controller
{
    // 获取菜单树（用于管理页面）
    public function index()
    {
        // 简单起见，只取两层，实际项目可用递归
        return Menu::whereNull('parent_id')
                    ->with('children')
                    ->orderBy('sort')
                    ->get();
    }
    
    // 获取当前用户的侧边栏菜单 (动态路由核心)
    public function myMenus(Request $request)
    {
        $user = $request->user();
        
        // 1. 获取所有菜单
        $allMenus = Menu::with('children')->whereNull('parent_id')->orderBy('sort')->get();
        
        // 2. 过滤权限 (如果用户没有该权限，就移除该菜单)
        // 这一步建议在前端做，或者后端过滤好返回
        // 简单版：直接返回所有，前端根据 permission 字段判断显隐
        return $allMenus;
    }

    public function store(Request $request)
    {
        return Menu::create($request->all());
    }

    public function update(Request $request, Menu $menu)
    {
        $menu->update($request->all());
        return $menu;
    }

    public function destroy(Menu $menu)
    {
        // 🔒 系统菜单保护逻辑
        if ($menu->is_system) {
            return response()->json([
                'message' => '系统内置菜单禁止删除！'
            ], 403);
        }

        $menu->delete();
        return response()->noContent();
    }
}