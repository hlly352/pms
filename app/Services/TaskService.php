<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskDetail;
use Carbon\Carbon;
use Overtrue\ChineseCalendar\Calendar; // 👈 核心引入

class TaskService
{
    /**
     * 创建任务时的立即生成逻辑 (针对：一次性、重复规则)
     */
    public function generateOnCreate(Task $task)
    {
        $dates = [];
        $today = Carbon::now()->startOfDay();

        // 1. 一次性任务
        if ($task->frequency === 'once') {
            if (!empty($task->execution_config['date'])) {
                $dates[] = Carbon::parse($task->execution_config['date']);
            }
        }
        // 2. 重复规则 (按间隔天数累加)
        elseif ($task->frequency === 'repeat' && $task->rule) {
            $items = $task->rule->details['items'] ?? [];
            
            // 之前的倒序逻辑（如果还需要的话保留，不需要则注释）
            // $items = array_reverse($items); 

            $cursor = $today->copy();

            foreach ($items as $item) {
                $daysInterval = intval($item['value']);
                if ($daysInterval > 0) {
                    $cursor->addDays($daysInterval);
                    $dates[] = $cursor->copy();
                }
            }
        }

        // 3. 批量创建详情
        $this->createDetailsBatch($task, $dates);

        // 👇 新增：更新任务的生成时间和截止时间
        if (!empty($dates)) {
            // 取数组中最后一个日期作为截止日期
            // (因为无论是单次还是累加，最后一个肯定是最晚的)
            $lastDate = end($dates);

            $task->update([
                'last_generated_at' => Carbon::now(), // 上次生成时间 = 当前添加时间
                'generate_deadline' => $lastDate->toDateString() // 截止时间 = 最后一次任务详情的日期
            ]);
        }
    }
   
    /**
     * 周期性任务生成逻辑 (点击按钮触发)
     * 逻辑：当前日期 > 截止日期时，生成未来2个月的任务
     */
    public function generateScheduledTasks()
    {
        $now = Carbon::now();

        // 1. 查询符合条件的任务：状态开启 + 是周期性任务 + (截止日期为空 OR 当前日期 > 截止日期)
        $tasks = Task::where('status', true)
            ->whereIn('frequency', ['weekly', 'monthly', 'yearly'])
            // 👇 核心修改：排除掉来源是 'project' 的任务
            ->where(function($query) {
                $query->whereNull('source') // 兼容旧数据
                    ->orWhereNotIn('source', ['project', 'recitation']); // 排除项目管理、背诵管理自动生成的
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('generate_deadline')
                  ->orWhere('generate_deadline', '<', $now);
            })
            ->get();

        $count = 0;
        foreach ($tasks as $task) {
            $count += $this->processPeriodicTask($task);
        }

        return $count;
    }

    /**
     * 处理单个周期性任务
     */
    private function processPeriodicTask(Task $task)
    {
        // 范围：从现在开始，往后找2个月
        $startDate = Carbon::now()->startOfDay();
        $endDate = Carbon::now()->addMonths(2)->endOfDay();
        
        $dates = [];
        $cursor = $startDate->copy();

        // 📅 按天遍历策略：虽然看起来笨，但处理复杂日历（尤其是农历、闰月、大小月）最稳健
        while ($cursor->lte($endDate)) {
            $matched = false;

            // A. 每周
            if ($task->frequency === 'weekly') {
                $targetDays = $task->execution_config['days'] ?? []; // ['Mon', 'Fri']
                if (in_array($cursor->format('D'), $targetDays)) {
                    $matched = true;
                }
            }
            // B. 每月
            elseif ($task->frequency === 'monthly') {
                $targetDates = $task->execution_config['days'] ?? []; // [1, 15, 30]
                if (in_array($cursor->day, $targetDates)) {
                    $matched = true;
                }
            }
            // C. 每年 (含农历处理)
            elseif ($task->frequency === 'yearly') {
                $yearDates = $task->execution_config['year_dates'] ?? [];
                foreach ($yearDates as $conf) {
                    // 检查当前游标日期 $cursor 是否匹配该配置
                    if ($this->isDateMatch($cursor, $conf)) {
                        $matched = true;
                        break;
                    }
                }
            }
            
            if ($matched) {
                $dates[] = $cursor->copy();
            }

            $cursor->addDay();
        }

        // 2. 批量入库
        if (!empty($dates)) {
            $this->createDetailsBatch($task, $dates);
        }

        // 3. 更新任务主表状态
        $task->update([
            'last_generated_at' => Carbon::now(),
            'generate_deadline' => $endDate->toDateString() // 标记生成到了未来第2个月末
        ]);

        return count($dates);
    }

    /**
     * 核心比对函数：判断某一天是否匹配配置
     * @param Carbon $date 当前遍历到的公历日期
     * @param array $config 配置项 ['date' => '12-20', 'is_lunar' => true]
     */
 /**
     * 核心比对函数：判断某一天是否匹配配置
     */
    private function isDateMatch(Carbon $date, $config)
    {
        // 解析配置中的月和日 (格式 "MM-DD")
        $targetMonth = intval(substr($config['date'], 0, 2));
        $targetDay = intval(substr($config['date'], 3, 2));

        if (!empty($config['is_lunar'])) {
            // 🌙 农历处理逻辑
            try {
                // 👇 修正点：先实例化 Calendar 类，再调用方法
                $calendar = new Calendar();
                $lunar = $calendar->solar2lunar($date->year, $date->month, $date->day);
                
                // 比对农历月日是否一致
                return $lunar['lunar_month'] == $targetMonth && $lunar['lunar_day'] == $targetDay;

            } catch (\Exception $e) {
                return false;
            }
        } else {
            // 🌞 公历处理逻辑
            return $date->month == $targetMonth && $date->day == $targetDay;
        }
    }

    /**
     * 批量生成详情入库
     */
    private function createDetailsBatch(Task $task, array $dates)
    {
        // 截取提醒时间 HH:mm
        $timeStr = $task->reminder_time ? substr($task->reminder_time, 0, 5) : '00:00';
        
        foreach ($dates as $index=>$date) {
            // 最终入库的时间 = 遍历到的公历日期 + 设定的提醒时间
            $dateTimeStr = $date->format('Y-m-d') . ' ' . $timeStr;
            
            // firstOrCreate 防止重复生成
            TaskDetail::firstOrCreate([
                'task_id' => $task->id,
                'task_time' => $dateTimeStr
            ], [
                'status' => 'pending',
                'remark' => '第' . ($index + 1) . '次背诵',
            ]);
        }
    }
}