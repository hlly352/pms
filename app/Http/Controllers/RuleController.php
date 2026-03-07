<?php

namespace App\Http\Controllers;

use App\Models\Rule;
use Illuminate\Http\Request;

class RuleController extends Controller
{
   public function index(Request $request)
    {
        // 创建查询构建器
        $query = Rule::query();

        // 👇 核心逻辑：如果前端传了 module 参数，就只查该模块的规则
        if ($request->has('module') && !empty($request->input('module'))) {
            $query->where('module', $request->input('module'));
        }

        // 默认按时间倒序
        return $query->latest()->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'type' => 'required|in:fixed,loop',
            'module' => 'required|string', // 👈 增加验证
            'details' => 'required|array',
        ]);

        return Rule::create($request->all());
    }
    
    // update 方法里的 validate 也可以加上 module，这里略

    public function update(Request $request, Rule $rule)
    {
        $request->validate([
            'name' => 'required|string',
            'details' => 'array',
        ]);

        $rule->update($request->all());
        return $rule;
    }

    public function destroy(Rule $rule)
    {
        $rule->delete();
        return response()->noContent();
    }
}