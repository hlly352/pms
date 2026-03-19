<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    // 1. 获取所有科目并组装成树形结构
    public function index(Request $request)
    {
        $query = Subject::orderBy('subject_order', 'asc')->orderBy('id', 'asc');

        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->input('subject_type'));
        }

        $subjects = $query->get()->toArray();
        $tree = $this->buildTree($subjects, 0);

        return response()->json($tree);
    }

    // 2. 新增科目
    public function store(Request $request)
    {
        $validated = $request->validate([
            'pid' => 'required|integer',
            // 🌟 核心修改：允许接收 'transfer' 类型
            'subject_type' => 'required|in:expense,income,transfer', 
            'subject_name' => 'required|string|max:100',
            'subject_code' => 'nullable|string|max:50',
            'account_id' => 'nullable|integer',
            'subject_order' => 'required|integer',
            'status' => 'required|in:0,1',
        ]);

        $subject = Subject::create($validated);

        return response()->json($subject, 201);
    }

    // 3. 更新科目
    public function update(Request $request, Subject $subject)
    {
        $validated = $request->validate([
            'pid' => 'required|integer',
            // 🌟 核心修改：允许接收 'transfer' 类型
            'subject_type' => 'required|in:expense,income,transfer', 
            'subject_name' => 'required|string|max:100',
            'subject_code' => 'nullable|string|max:50',
            'account_id' => 'nullable|integer',
            'subject_order' => 'required|integer',
            'status' => 'required|in:0,1',
        ]);

        if ($validated['pid'] == $subject->id) {
            return response()->json(['message' => '上级科目不能是自己！'], 422);
        }

        $subject->update($validated);

        return response()->json($subject);
    }
    
    // 4. 删除科目
    public function destroy(Subject $subject)
    {
        // 双重保险：后端校验是否存在子科目，存在则拒绝删除
        $hasChildren = Subject::where('pid', $subject->id)->exists();
        if ($hasChildren) {
            return response()->json(['message' => '该科目下存在子科目，无法直接删除！'], 422);
        }

        $subject->delete();

        return response()->noContent();
    }

    // ==========================================
    // 递归辅助方法：将扁平数组转换为树形结构
    // ==========================================
    private function buildTree(array $elements, $parentId = 0)
    {
        $branch = array();

        foreach ($elements as $element) {
            if ($element['pid'] == $parentId) {
                // 递归寻找当前节点的子节点
                $children = $this->buildTree($elements, $element['id']);
                if ($children) {
                    $element['children'] = $children;
                }
                $branch[] = $element;
            }
        }

        return $branch;
    }
}