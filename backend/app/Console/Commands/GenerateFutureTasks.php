<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\TaskService; // 引入你的 TaskService

class GenerateFutureTasks extends Command
{
    // 🌟 这是你在终端里运行的命令名称
    protected $signature = 'task:generate-future';

    // 命令的描述
    protected $description = '每天凌晨自动生成未来的周期性任务';

    /**
     * 🌟 核心逻辑：这里注入 TaskService 并调用生成方法
     */
    public function handle(TaskService $taskService)
    {
        $this->info('开始检查并自动生成未来任务...');
        
        // 调用你现有的业务逻辑
        $count = $taskService->generateScheduledTasks();
        
        $this->info("执行完毕！本次成功生成 {$count} 条任务详情。");
    }
}