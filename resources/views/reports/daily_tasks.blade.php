<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>今日任务清单</title>
    <style>
        body {
            font-family: 'sun-exta', sans-serif; 
            padding: 20px;
            color: #333;
        }
        
        h2 { 
            text-align: center; 
            border-bottom: 2px solid #333; 
            padding-bottom: 15px; 
            margin-bottom: 25px; 
        }

        /* 日清单使用隐形表格布局，确保复选框和多行文字完美对齐 */
        table {
            width: 100%;
            border-collapse: collapse;
        }
        td {
            padding: 12px 0;
            border-bottom: 1px dashed #ccc; /* 经典的虚线分割 */
            vertical-align: top;
            line-height: 1.6;
        }
        tr:last-child td {
            border-bottom: none;
        }

        /* 专门装复选框的单元格 */
        .td-checkbox {
            width: 30px;
            text-align: left;
            padding-top: 14px; /* 微调复选框的垂直居中 */
        }
        
        /* 方块复选框 */
        .checkbox {
            display: inline-block; 
            width: 16px; 
            height: 16px; 
            border: 2px solid #333; 
        }

        .task-time { color: #888; font-size: 14px; margin-right: 8px; font-family: monospace; }
        .task-title { font-weight: bold; font-size: 15px; color: #000; }
        .task-remark { font-size: 14px; color: #444; }
    </style>
</head>
<body>
    <h2>📋 任务清单 ({{ $date }})</h2>
    
    @if($todayTasks->isEmpty() && $overdueTasks->isEmpty())
        <p style="text-align: center; margin-top: 50px; color: #888;">今天没有安排任务，好好休息吧！🍻</p>
    @else
        <table>
            @foreach($overdueTasks as $detail)
                <tr>
                    <td class="td-checkbox">
                        <span class="checkbox"></span> 
                    </td>
                    <td>
                        <span style="color: #F56C6C; font-weight: bold; font-size: 12px; margin-right: 5px;">[昨日遗留]</span>
                        <span class="task-time">[{{ substr($detail->task_time, 11, 5) }}]</span>
                        <span class="task-title">{{ $detail->task->name ?? '未知任务' }}</span> : 
                        <span class="task-remark">{{ $detail->remark }}</span>
                    </td>
                </tr>
            @endforeach

            @foreach($todayTasks as $detail)
                <tr>
                    <td class="td-checkbox">
                        <span class="checkbox"></span> 
                    </td>
                    <td>
                        <span class="task-time">[{{ substr($detail->task_time, 11, 5) }}]</span>
                        <span class="task-title">{{ $detail->task->name ?? '未知任务' }}</span> : 
                        <span class="task-remark">{{ $detail->remark }}</span>
                    </td>
                </tr>
            @endforeach
        </table>
    @endif
</body>
</html>