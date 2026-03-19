<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    // 获取列表 (支持按名称搜索)
    public function index(Request $request)
    {
        $query = Category::query();
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }
        // 按 sort 降序，再按 id 降序
        $categories = $query->orderByDesc('sort')->orderByDesc('id')->paginate($request->input('per_page', 10));
        
        return response()->json($categories);
    }

    // 提供给书籍页面用的下拉列表接口 (不分页)
    public function listOptions()
    {
        $options = Category::orderByDesc('sort')->get(['id', 'name']);
        return response()->json($options);
    }

    // 新增
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:categories',
            'description' => 'nullable|string|max:255',
            'sort' => 'nullable|integer'
        ]);
        $validated['sort'] = $validated['sort'] ?? 0;

        Category::create($validated);
        return response()->json(['message' => '分类创建成功']);
    }

    // 更新
    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:categories,name,' . $category->id,
            'description' => 'nullable|string|max:255',
            'sort' => 'nullable|integer'
        ]);

        $category->update($validated);
        return response()->json(['message' => '分类更新成功']);
    }

    // 删除
    public function destroy(Category $category)
    {
        // 可选：在这里可以检查该分类下是否还有书籍，如果有则禁止删除
        $category->delete();
        return response()->json(['message' => '分类删除成功']);
    }
}