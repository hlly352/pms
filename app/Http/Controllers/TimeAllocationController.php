<?php
namespace App\Http\Controllers;

use App\Models\TimeAccount;
use App\Models\TimeRule;
use App\Models\TimeRuleItem;
use App\Models\TimeAllocationLog;
use App\Models\TimeAllocationLogItem;
use App\Models\TaskDetail; // 用于统计消耗
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TimeAllocationController extends Controller
{
    // 获取大盘统计数据
    // 获取大盘统计数据 (彻底移除 time_pools)
    public function stats()
    {
        // 1. 历史总获取时间 = 所有的自动化发放日志的时间总和
        $totalGenerated = TimeAllocationLog::sum('total_hours');
        
        // 2. 当前账户总余额 = 所有时间账户里的时间加起来
        $currentTotalBalance = TimeAccount::sum('balance_hours');
        
        // 3. 历史总消耗 = 任务打卡消耗掉的时间
        $totalConsumed = TaskDetail::where('status', 'completed')->sum('actual_hours');

        return response()->json([
            'total_generated' => $totalGenerated ?: 0,
            'current_balance' => $currentTotalBalance ?: 0, // 替代原来的待分配池
            'total_consumed'  => $totalConsumed ?: 0,
        ]);
    }

    // 获取规则列表
    public function rules()
    {
        return response()->json(TimeRule::with('items.account')->orderBy('id', 'desc')->get());
    }

    // 保存分配规则
    // 保存分配规则
    public function saveRule(Request $request)
    {
        $data = $request->validate([
            'id' => 'nullable|integer',
            'name' => 'required|string',
            'remark' => 'nullable|string',
            'items' => 'required|array'
        ]);

        return DB::transaction(function () use ($data) {
            // 1. 更新或创建规则主表
            $rule = TimeRule::updateOrCreate(
                ['id' => $data['id']],
                ['name' => $data['name'], 'remark' => $data['remark'] ?? '']
            );

            // 2. 清除旧的明细配置
            $rule->items()->delete(); 

            // 3. 循环插入新的明细配置
            foreach ($data['items'] as $item) {
                // 🌟 关键修改：过滤条件从 ratio > 0 改为 allocate_hours > 0
                if (isset($item['allocate_hours']) && $item['allocate_hours'] > 0) {
                    TimeRuleItem::create([
                        'time_rule_id' => $rule->id,
                        'time_account_id' => $item['account_id'],
                        // 🌟 存入 JSON 格式的执行周期 (前端传过来的是类似 [1,2,3] 的数组)
                        'days_of_week' => json_encode($item['days_of_week'] ?? []), 
                        // 🌟 存入时长
                        'allocate_hours' => $item['allocate_hours'],
                       
                    ]);
                }
            }
            return response()->json(['message' => '自动化规则保存成功']);
        });
    }

    // 🚀 执行时间分配
    public function execute(Request $request)
    {
        $request->validate([
            'rule_id' => 'required|exists:time_rules,id',
            'hours' => 'required|numeric|min:0.01',
        ]);

        return DB::transaction(function () use ($request) {
            $rule = TimeRule::with('items.account')->findOrFail($request->rule_id);
            $hours = $request->hours;

            // 1. 创建分配总日志
            $log = TimeAllocationLog::create([
                'time_rule_id' => $rule->id,
                'rule_name' => $rule->name,
                'total_hours' => $hours,
                'remark' => $request->remark,
            ]);

            // 2. 遍历规则切分，入账并写明细
            foreach ($rule->items as $item) {
                if ($item->account) {
                    $allocated = round(($hours * $item->ratio) / 100, 2);
                    
                    // 增加账户余额
                    $item->account->increment('balance_hours', $allocated);

                    // 写入明细
                    TimeAllocationLogItem::create([
                        'time_allocation_log_id' => $log->id,
                        'time_account_id' => $item->account_id,
                        'account_name' => $item->account->name,
                        'ratio' => $item->ratio,
                        'allocated_hours' => $allocated,
                    ]);
                }
            }

            // 3. 扣除系统的待分配池
            DB::table('time_pools')->where('id', 1)->decrement('unallocated_hours', $hours);

            return response()->json(['message' => '分配成功']);
        });
    }

    // 获取日志
    // 获取日志
    public function logs(Request $request)
    {
        // 🌟 核心：必须要有 with('items')，否则查不出来详情！
        $query = TimeAllocationLog::with('items')->orderBy('id', 'desc');

        if ($request->rule_id) {
            $query->where('time_rule_id', $request->rule_id);
        }
        if ($request->start_date && $request->end_date) {
            $query->whereBetween('created_at', [$request->start_date . ' 00:00:00', $request->end_date . ' 23:59:59']);
        }

        return response()->json($query->get());
    }

    // 撤回(删除)日志，回退时间
    public function deleteLog($id)
    {
        return DB::transaction(function () use ($id) {
            $log = TimeAllocationLog::findOrFail($id);

            // 1. 获取所有明细
            $items = TimeAllocationLogItem::where('time_allocation_log_id', $id)->get();

            // 2. 遍历明细，把时间从账户里精准扣回来
            if ($items->count() > 0) {
                foreach ($items as $item) {
                    $account = TimeAccount::find($item->time_account_id);
                    if ($account) {
                        $account->balance_hours -= $item->allocated_hours;
                        $account->save();
                    }
                }
            }
            // 🌟 4. 终极防漏：手动显式删除所有关联的明细数据！
            TimeAllocationLogItem::where('time_allocation_log_id', $id)->delete();

            // 5. 删除主记录
            $log->delete();

            return response()->json(['message' => '撤回成功，时间已扣除且明细已彻底清理！']);
        });
    }
    /**
     * 🌟 核心引擎：执行每日自动化时间发放
     */
    
    public function runDailyAutoAllocation()
    {
        return DB::transaction(function () {
            $todayDayOfWeek = now()->dayOfWeek;
            $todayDateStr = now()->toDateString();
            
            // 🌟 1. 获取所有已启用的规则
            $rules = TimeRule::where('is_active', 1)->with('items.account')->get();
            
            // 🌟 2. 提取当前生效的规则名称 (例如："日常工作安排")
            $activeRuleNames = $rules->pluck('name')->implode('、');
            // 拼接成新的日志名称，必须包含"自动化发放"字眼以通过后面的防重复校验
            $logRuleName = $activeRuleNames ? "🤖 自动化发放：{$activeRuleNames}" : "🤖 系统每日自动化发放";

            // 3. 检查防重
            $hasRun = TimeAllocationLog::where('rule_name', 'LIKE', '%自动化发放%')
                        ->whereDate('created_at', $todayDateStr)
                        ->exists();
            if ($hasRun) {
                return response()->json(['message' => '今日自动化时间已发放过，无需重复执行。']);
            }

            $totalHoursGeneratedToday = 0;
            $logItems = [];

            foreach ($rules as $rule) {
                foreach ($rule->items as $item) {
                    $targetDays = is_string($item->days_of_week) ? json_decode($item->days_of_week, true) : $item->days_of_week;
                    
                    if (is_array($targetDays) && in_array($todayDayOfWeek, $targetDays)) {
                        if ($item->account) {
                            $hours = $item->allocate_hours;
                            
                            // 账户充值
                            $item->account->increment('balance_hours', $hours);
                            $totalHoursGeneratedToday += $hours;
                            
                            // 收集明细
                            $logItems[] = [
                                'time_account_id' => $item->account->id, 
                                'account_name' => $item->account->name,
                                'allocated_hours' => $hours,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                    }
                }
            }

            // 4. 写入数据库
            if (count($logItems) > 0) {
                // 🌟 使用刚刚拼接好的动态名称创建主记录
                $log = TimeAllocationLog::create([
                    'rule_name' => $logRuleName,
                    'total_hours' => $totalHoursGeneratedToday,
                    'remark' => "基于您设定的周期规则自动注入时间",
                ]);

                // 强制写入明细
                $insertData = [];
                foreach ($logItems as $item) {
                    $item['time_allocation_log_id'] = $log->id;
                    $insertData[] = $item;
                }
                TimeAllocationLogItem::insert($insertData);
            }

            return response()->json([
                'message' => '今日自动化发放完成！', 
                'generated_hours' => $totalHoursGeneratedToday
            ]);
        });
    }
    // 🌟 新增：切换规则的启用/停用状态
    // 🌟 切换规则的启用/停用状态 (增加排他性互斥逻辑)
    public function toggleStatus(Request $request, $id)
    {
        $rule = TimeRule::findOrFail($id);
        
        // 🌟 核心排他逻辑：如果当前操作是“开启”这个规则
        if ($request->is_active == 1) {
            // 就把数据库里除了自己之外的【所有其他规则】，强行批量设置为停用 (0)
            TimeRule::where('id', '!=', $id)->update(['is_active' => 0]);
        }
        
        // 更新自己的状态
        $rule->is_active = $request->is_active;
        $rule->save();

        return response()->json(['message' => '规则状态已切换']);
    }
    // 1. 获取所有的任务类型及其绑定关系
    public function getTaskMappings()
    {
        // 从 tasks 表自动抓取所有不重复的 source，并且排除 'project'
        $sources = DB::table('tasks')
            ->whereNotNull('source')
            ->where('source', '!=', 'project')
            ->distinct()
            ->pluck('source');

        // 查询已经保存的绑定记录
        $mappings = DB::table('task_account_mappings')->get()->keyBy('source');

        // 拼接返回给前端
        $result = $sources->map(function ($source) use ($mappings) {
            return [
                'source' => $source,
                'time_account_id' => isset($mappings[$source]) ? $mappings[$source]->time_account_id : null
            ];
        });

        return response()->json($result);
    }

    // 2. 保存/更新绑定关系
    public function saveTaskMapping(Request $request)
    {
        DB::table('task_account_mappings')->updateOrInsert(
            ['source' => $request->source],
            [
                'time_account_id' => $request->time_account_id, 
                'updated_at' => now()
            ]
        );
        return response()->json(['message' => '绑定成功']);
    }
}