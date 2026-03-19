<?php
namespace App\Http\Controllers;
use App\Models\Notebook;
use Illuminate\Http\Request;

class NotebookController extends Controller
{
    // 获取所有笔记本
    public function index()
    {
        return Notebook::orderBy('created_at', 'asc')->get();
    }

    // 新建笔记本
    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string']);
        return Notebook::create($request->all());
    }
    
    // 修改笔记本
    public function update(Request $request, $id)
    {
         $notebook = Notebook::findOrFail($id);
         $notebook->update($request->only('name'));
         return $notebook;
    }

    // 删除笔记本 (注意：实际业务中可能需要判断下面有没有文档)
    public function destroy($id)
    {
        Notebook::destroy($id);
        return response()->noContent();
    }
}