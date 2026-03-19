<?php
namespace App\Http\Controllers;

use App\Models\ReadingNote;
use Illuminate\Http\Request;

class ReadingNoteController extends Controller
{
    // 获取感悟列表（支持按书籍筛选）
    // 获取感悟列表（支持按书名、内容、时间段筛选）
    public function index(Request $request)
    {
        $query = ReadingNote::with('book'); // 预加载书籍信息

        // 1. 原有的按书本 ID 查找 (用于书籍详情页抽屉)
        if ($request->filled('book_id')) {
            $query->where('book_id', $request->input('book_id'));
        }

        // 2. 🌟 新增：按书名模糊查找 (跨表查询)
        if ($request->filled('book_title')) {
            $query->whereHas('book', function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->input('book_title') . '%');
            });
        }

        // 3. 🌟 新增：按感悟内容模糊查找
        if ($request->filled('content')) {
            $query->where('content', 'like', '%' . $request->input('content') . '%');
        }

        // 4. 🌟 新增：按时间段查找 (精确到天)
        if ($request->filled('start_date') && $request->filled('end_date')) {
            // 补全时分秒，确保包含结束日期当天的全部记录
            $start = $request->input('start_date') . ' 00:00:00';
            $end = $request->input('end_date') . ' 23:59:59';
            $query->whereBetween('created_at', [$start, $end]);
        }

        // 可以由前端控制每页条数，默认 20
        $perPage = $request->input('per_page', 20);
        $notes = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($notes);
    }
    
    public function store(Request $request)
    {
        $validated = $request->validate([
            'book_id' => 'required|exists:books,id',
            'content' => 'required|string',
        ]);
        $note = ReadingNote::create($validated);
        return response()->json($note, 201);
    }

    public function update(Request $request, ReadingNote $readingNote)
    {
        $validated = $request->validate([
            'content' => 'required|string',
        ]);
        $readingNote->update($validated);
        return response()->json($readingNote);
    }

    public function destroy(ReadingNote $readingNote)
    {
        $readingNote->delete();
        return response()->noContent();
    }
}