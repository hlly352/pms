<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>月度任务日历</title>
    <style>
        
        body {
            font-family: 'sun-exta', sans-serif;
            color: #333;
        }
        h2 {
            text-align: center;
            margin-top: 0;
            margin-bottom: 15px;
        }
        /* 极度简化的表格样式 */
        table {
            border-collapse: collapse;
        }
        th {
            background-color: #f8f9fa;
            color: #606266;
            padding: 8px 0;
            font-size: 14px;
        }
        /* 🌟 统计盒子样式 */
        .statistics-box {
            margin-top: 15px;
            padding: 12px 15px;
            background-color: #f4f4f5; /* 极浅的灰底色 */
            border: 1px solid #e4e7ed;
            border-left: 4px solid #67C23A; /* 左侧加一道清新的绿色竖线 */
            font-size: 12px;
            color: #606266;
            /* 防止被 mPDF 分页切断 */
            page-break-inside: avoid; 
        }
        
        .statistics-box strong {
            color: #303133;
            font-size: 13px;
        }

        .stat-item {
            margin-left: 20px;
            display: inline-block;
        }

        .stat-item b {
            color: #409EFF; /* 数字用醒目的蓝色显示 */
            font-size: 14px;
        }
    </style>
</head>
<body>
    <h2>🗓️ {{ $month }} 任务大盘</h2>

    @php
        $groupedTasks = $tasks->groupBy(function($task) {
            return substr($task->task_time, 0, 10);
        });

        $firstDayOfMonth = \Carbon\Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $daysInMonth = $firstDayOfMonth->daysInMonth;
        $startDayOfWeek = $firstDayOfMonth->dayOfWeek; 
        
        $calendar = [];
        $dayCounter = 1;
        
        for ($week = 0; $week < 6; $week++) {
            $weekRow = [];
            for ($dayOfWeek = 0; $dayOfWeek < 7; $dayOfWeek++) {
                if ($week === 0 && $dayOfWeek < $startDayOfWeek) {
                    $weekRow[] = null; 
                } elseif ($dayCounter <= $daysInMonth) {
                    $dateString = $firstDayOfMonth->copy()->addDays($dayCounter - 1)->format('Y-m-d');
                    $weekRow[] = [
                        'day' => str_pad($dayCounter, 2, '0', STR_PAD_LEFT),
                        'tasks' => $groupedTasks->get($dateString, collect())
                    ];
                    $dayCounter++;
                } else {
                    $weekRow[] = null; 
                }
            }
            $calendar[] = $weekRow;
            if ($dayCounter > $daysInMonth) {
                break; 
            }
        }
    @endphp

    <table width="100%" border="1" cellpadding="5" bordercolor="#ebeef5">
        <thead>
            <tr>
                <th width="14%" style="color: #F56C6C;">Sun</th>
                <th width="14%">Mon</th>
                <th width="14%">Tue</th>
                <th width="14%">Wed</th>
                <th width="14%">Thu</th>
                <th width="15%">Fri</th>
                <th width="15%" style="color: #F56C6C;">Sat</th>
            </tr>
        </thead>
        <tbody>
            @foreach($calendar as $weekRow)
                <tr>
                    @foreach($weekRow as $index => $dayData)
                        @if($dayData)
                            <td valign="top" style="height: 90px; {{ ($index === 0 || $index === 6) ? 'background-color: #fafbfc;' : '' }}">
                                <strong style="font-size: 14px;">{{ $dayData['day'] }}</strong><br>
                                
                                @foreach($dayData['tasks'] as $k => $detail)
                                    <span style="color: #409EFF; font-size: 11px; line-height: 1.6;">
                                        @php 
                                            echo ($k + 1);
                                        @endphp
                                        {{ \Illuminate\Support\Str::limit($detail->task->name ?? '未知', 22, '..') }}
                                    </span><br>
                                @endforeach
                            </td>
                        @else
                            <td valign="top" style="background-color: #fafbfc;">&nbsp;</td>
                        @endif
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="statistics-box">
        <strong>📊 {{ $month }} 月度数据复盘：</strong>
        <span class="stat-item">总计任务: <b>{{ $totalCount }}</b> 项</span>
        <span class="stat-item">项目任务: <b>{{ $projectCount }}</b> 项</span>
        <span class="stat-item">阅读任务: <b>{{ $readingCount }}</b> 项</span>
        <span class="stat-item">背诵任务: <b>{{ $recitationCount }}</b> 项</span>
        <span class="stat-item">手动任务: <b>{{ $manualCount }}</b> 项</span>
    </div>
</body>
</html>