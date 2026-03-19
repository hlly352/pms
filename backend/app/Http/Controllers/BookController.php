<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\Task;
use App\Models\TaskDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class BookController extends Controller
{
    public function index(Request $request)
    {
        // 1. 建立基础查询，默认按时间倒序
        $query = Book::latest();

        // 2. 如果传了 'title' (书名)，就进行模糊搜索
        if ($request->filled('title')) {
            $query->where('title', 'like', '%' . $request->title . '%');
        }

        // 3. 如果传了 'status' (状态)，就精确匹配
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // 4. 如果传了 'rating' (评分)，就精确匹配
        if ($request->filled('rating')) {
            $query->where('rating', $request->rating);
        }
        
        // 5. 按分类精确搜索！
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // 6. 执行查询获取书籍列表
        $books = $query->get();

        // ==========================================
        // 🌟 核心新增：遍历每本书，智能计算真实已读页数
        // ==========================================
        foreach ($books as $book) {
            $readPages = 0;
            
            // 步骤 A：精准找到这本书对应的阅读主任务
            $task = Task::where('source', 'reading')
                        ->where('name', "阅读《{$book->title}》")
                        ->first();

            if ($task) {
                // 步骤 B：查出该任务下【已完成】的所有子任务
                $completedDetails = TaskDetail::where('task_id', $task->id)
                                              ->where('status', 'completed') 
                                              ->get();

                // 步骤 C：遍历已完成的任务，利用正则提取页码并累加
                foreach ($completedDetails as $detail) {
                    // 提取格式如："📖 第 1 页至第 46 页" 中的数字
                    if (preg_match('/第\s*(\d+)\s*页至第\s*(\d+)\s*页/', $detail->remark, $matches)) {
                        $startPage = (int)$matches[1];
                        $endPage = (int)$matches[2];
                        
                        // 累加本次完成的页数
                        $readPages += ($endPage - $startPage + 1);
                    }
                }
            }
            
            // 步骤 D：如果书本状态被手动标记为“读过”(finished)，强行将已读页数拉满
            if ($book->status === 'finished') {
                $readPages = $book->page_count ?: $readPages;
            }

            // 步骤 E：将动态计算出来的页数挂载到对象上，传给前端
            $book->read_pages = $readPages;
        }

        return response()->json($books);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required',
            'rating' => 'integer|min:0|max:5'
        ]);

        $book = Book::create($request->all());
        return response()->json($book, 201);
    }

    public function update(Request $request, Book $book)
    {
        $book->update($request->all());
        return response()->json($book);
    }

    // 删除书籍及其级联数据
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $book = Book::findOrFail($id);

            // 1. 查找由这本书生成的“阅读主任务”
            $task = Task::where('source', 'reading')
                        ->where('name', "阅读《{$book->title}》")
                        ->first();

            // 2. 如果存在任务，级联删除任务详情和主任务
            if ($task) {
                TaskDetail::where('task_id', $task->id)->delete();
                $task->delete();
            }

            // 3. 查找并删除关联的“阅读计划”
            $plan = ReadingPlan::where('book_id', $book->id)->first();
            if ($plan) {
                $plan->delete();
            }

            // 4. 最后删除书籍本身
            $book->delete();

            DB::commit();
            return response()->json([
                'message' => '书籍已删除，关联的阅读计划与排期任务已自动清理'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => '删除失败: ' . $e->getMessage()
            ], 500);
        }
    }
}