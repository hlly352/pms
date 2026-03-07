<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\TaskController; 
use App\Http\Controllers\GoalController; 
use App\Http\Controllers\RecordController;
use App\Http\Controllers\BookController; 
use App\Http\Controllers\SettingController; 
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RecitationController;
use App\Http\Controllers\NotebookController;
use App\Http\Controllers\ReadingSpeedController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ReadingPlanController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AllocationController;
use App\Http\Controllers\FinanceOverviewController;
use App\Http\Controllers\ReadingNoteController;



// 1. 公开路由：任何人都能访问（登录接口肯定不能锁，锁了就进不来了）
Route::post('/login', [AuthController::class, 'login']);

// 2. 受保护路由：必须带 Token 才能进！
// 'auth:sanctum' 就是 Laravel 的门禁系统
Route::middleware('auth:sanctum')->group(function () {
    
    // 获取当前登录用户的信息 (测试用)
    Route::get('/me', function (Request $request) {
        return $request->user();
    });

    // 我们的用户列表接口，现在安全了
    Route::get('/users', [UserController::class, 'index']);
    
    // 退出登录接口
    Route::post('/logout', [AuthController::class, 'logout']);

    // API 资源路由：这一行代码顶替了 index, store, update, destroy 四行
    Route::apiResource('tasks', TaskController::class);

    // ... 
    Route::apiResource('goals', \App\Http\Controllers\GoalController::class);
    // ...
    Route::apiResource('records', RecordController::class);
    // ...
    Route::apiResource('books', BookController::class);
    // 获取所有设置
    Route::get('/settings', [SettingController::class, 'index']);
    // 保存所有设置 (这里用 POST 或 PUT 都可以)
    Route::post('/settings', [SettingController::class, 'update']);
    // ...
    Route::get('/dashboard/stats', [DashboardController::class, 'index']);
    // 角色管理
    Route::apiResource('permissions', PermissionController::class);
    Route::apiResource('roles', RoleController::class);
    
    // 菜单管理
    Route::get('/my-menus', [MenuController::class, 'myMenus']); // 获取侧边栏
    Route::apiResource('menus', MenuController::class);
    // 👇 加这个路由，注意要放在 apiResource 上面，避免冲突
    Route::get('/goal-types', [App\Http\Controllers\GoalController::class, 'getTypes']);
    
    Route::apiResource('goals', App\Http\Controllers\GoalController::class);

    Route::apiResource('projects', ProjectController::class);
    Route::get('/stage-steps', [App\Http\Controllers\ProjectStageStepController::class, 'index']);
    Route::post('/stage-steps', [App\Http\Controllers\ProjectStageStepController::class, 'store']);
    Route::delete('/stage-steps/{id}', [App\Http\Controllers\ProjectStageStepController::class, 'destroy']);
    Route::get('/project-stage-steps/{id}/task-details', [App\Http\Controllers\ProjectStageStepController::class, 'getTaskDetails']);
    Route::apiResource('rules', \App\Http\Controllers\RuleController::class);

    Route::post('/tasks/generate', [TaskController::class, 'generateBatch']); // 一键生成
    Route::get('/tasks/{id}/details', [TaskController::class, 'getDetails']); // 获取详情
    // 👇 新增：获取所有详情
    Route::get('/all-task-details', [\App\Http\Controllers\TaskController::class, 'getAllDetails']);
    // 👇 新增：修改详情状态
    Route::put('/task-details/{id}/status', [\App\Http\Controllers\TaskController::class, 'updateDetailStatus']);
    // 👇 新增：修改备注
    Route::put('/task-details/{id}/remark', [\App\Http\Controllers\TaskController::class, 'updateDetailRemark']);
    // 👇 新增：日历数据接口
    Route::get('/calendar-events', [\App\Http\Controllers\TaskController::class, 'getCalendarEvents']);
    // 👇 新增：仪表盘今日待办
    Route::get('/dashboard/today-pending', [\App\Http\Controllers\TaskController::class, 'getTodayPending']);
    // 必须放在get('/recitations/{id}之前。它发现 /recitations/{id} 这个规则也能匹配 /recitations/today。
    //于是它认为 {id} = "today"。
    //它调用 show($id) 方法，尝试执行 Recitation::findOrFail('today')。
    Route::get('/recitations/today', [RecitationController::class, 'todayTasks']); 
    // 获取列表（目录树）
    Route::get('/recitations', [RecitationController::class, 'index']);
    // 获取详情
    Route::get('/recitations/{id}', [RecitationController::class, 'show']);
    // 新增
    Route::post('/recitations', [RecitationController::class, 'store']);
    
    // ✅✅✅ 缺少的就是这一行！请添加它：
    Route::put('/recitations/{id}', [RecitationController::class, 'update']);
    
    // 删除
    Route::delete('/recitations/{id}', [RecitationController::class, 'destroy']);
    Route::get('/notebooks', [NotebookController::class, 'index']);
    Route::post('/notebooks', [NotebookController::class, 'store']);
    Route::put('/notebooks/{id}', [NotebookController::class, 'update']);
    Route::delete('/notebooks/{id}', [NotebookController::class, 'destroy']);
    Route::post('/recitations/check/{id}', [RecitationController::class, 'completeTaskDetail']);
    Route::apiResource('reading-speeds', ReadingSpeedController::class);
    Route::post('/upload/image', [UploadController::class, 'uploadImage']);
    //书籍分类
    // 供其他模块（如下拉框）使用的数据接口
    Route::get('/categories/options', [CategoryController::class, 'listOptions']);
    // 书籍分类标准 CRUD 接口
    Route::apiResource('categories', CategoryController::class);
    //阅读计划
    Route::get('/reading-plans/options', [ReadingPlanController::class, 'getOptions']);
    Route::apiResource('reading-plans', ReadingPlanController::class);
    Route::apiResource('reading-notes', ReadingNoteController::class);
    // 收支科目管理路由
    Route::apiResource('subjects', SubjectController::class);
    //收支记录
    Route::apiResource('transactions', TransactionController::class);

    // 🌟 新增：获取单个账户的分配入账记录
    Route::get('accounts/{id}/allocations', [AccountController::class, 'allocations']);
    // 账户(资金池)管理路由
    Route::apiResource('accounts', AccountController::class);

    Route::get('allocations/stats', [AllocationController::class, 'getDashboardStats']);
    Route::get('allocations/rules', [AllocationController::class, 'getRules']);
    Route::post('allocations/rules', [AllocationController::class, 'saveRule']);
    Route::post('allocations/execute', [AllocationController::class, 'executeAllocation']);
    Route::get('allocations/logs', [AllocationController::class, 'getLogs']);
    // 🌟 新增：删除日志(资金撤回)接口
    Route::delete('allocations/logs/{id}', [AllocationController::class, 'deleteLog']);

    // 🌟 财务专属的独立大盘路由
    Route::get('finance/overview', [FinanceOverviewController::class, 'getOverview']);
        
});
// routes/api.php
Route::middleware('auth:sanctum')->get('/me', function (Request $request) {
    $user = $request->user();
    return [
        'user' => $user,
        'roles' => $user->getRoleNames(),
        'permissions' => $user->getAllPermissions()->pluck('name'), // 👈 前端就是靠这个拿到权限的
    ];
});