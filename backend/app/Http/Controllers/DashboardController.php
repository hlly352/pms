<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Task;
use App\Models\Book;
use App\Models\Record;
use App\Models\Project;     // 👈 记得新增引入 Project 模型
use App\Models\TaskDetail;  // 👈 记得新增引入 TaskDetail 模型

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // 获取当前用户 (如果你的表里有 user_id，可以在后面的查询中加上 ->where('user_id', $user->id) 来隔离数据)
        $user = $request->user();

        return response()->json([
            // 1. 进行中的项目数 (如果你的 Project 表有 status 字段，可以加上过滤，如 ->where('status', 'in_progress')，这里默认统计所有项目)
            'project_count' => Project::count(),
            
            // 2. 当前月待办任务数 (查询 task_details 表中，排期在当月且状态为 pending 的记录)
            'month_todo_count' => TaskDetail::whereYear('task_time', now()->year)
                                            ->whereMonth('task_time', now()->month)
                                            ->where('status', 'pending')
                                            ->count(),
            
            // 3. 阅读中的书籍数 (将之前的 'unread' 状态改为 'reading'，并将键名改为与前端对应的 reading_book_count)
            'reading_book_count' => Book::where('status', 'reading')->count(),
            
            // 4. 统计累计记录了多少条碎碎念
            'record_count' => Record::count(),
        ]);
    }
}