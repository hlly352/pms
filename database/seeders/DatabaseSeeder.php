<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Menu; // 👈 引入菜单模型
use App\Models\GoalType;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {

        // 1. 清理缓存
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. 创建核心权限 (View 权限控制菜单显示)
        $permissions = [
            'user.view', 'user.create', 'user.edit', 'user.delete',
            'role.view', 'role.edit',
            'menu.view', 'menu.edit',
            'dashboard.view',
            'permission.view', 'permission.create', 'permission.delete', 
            'dashboard.view'
        ];
        foreach ($permissions as $p) {
            Permission::updateOrCreate(['name' => $p]);
        }

        // 3. 创建角色
        // 超级管理员 (Admin)
        $adminRole = Role::updateOrCreate(['name' => 'super-admin']);
        $adminRole->givePermissionTo(Permission::all()); // 给所有权限

        // 普通用户 (User)
        $userRole = Role::updateOrCreate(['name' => 'user']);
        $userRole->givePermissionTo(['dashboard.view']); // 只给看仪表盘

        // 4. 初始化菜单 (绑定权限)
        // 仪表盘 (所有人可见)
        // 注意：仪表盘已经在 AppLayout 里硬编码了，这里可以存也可以不存，为了统一管理我们存进去
        // 但 AppLayout 逻辑如果是混合的，可以只存动态部分。
        // 这里演示存入管理类菜单：
        
        $menus = [
            [
                'title' => '仪表盘',
                'path' => '/dashboard',
                'icon' => 'Odometer', // 确保前端注册了这个图标
                'permission' => 'dashboard.view', // 确保你也创建了这个权限
                'sort' => 0 ,// 排在最前面
                'is_system' => true
            ],
            [
                'title' => '用户管理',
                'path' => '/users',
                'icon' => 'User',
                'permission' => 'user.view', // 👈 只有拥有 user.view 权限的人才显示
                'sort' => 10 ,
                'is_system' => true
            ],
            [
                'title' => '角色管理',
                'path' => '/roles',
                'icon' => 'Lock',
                'permission' => 'role.view', // 👈 只有 role.view 可见
                'sort' => 20 ,
                'is_system' => true
            ],
            [
                'title' => '菜单管理',
                'path' => '/menus',
                'icon' => 'Menu',
                'permission' => 'menu.view', // 👈 只有 menu.view 可见
                'sort' => 30 ,
                'is_system' => true
            ],
             [
                'title' => '权限管理',
                'path' => '/permissions',
                'icon' => 'Permission',
                'permission' => 'permission.view', // 👈 只有 permission.view 可见
                'sort' => 40 ,
                'is_system' => true
            ]
        ];

        foreach ($menus as $m) {
            Menu::create($m);
        }

        // 5. 创建账号
        // 超级管理员
        $admin = User::updateOrCreate(
            ['email' => 'admin@mylife.com'],
            ['name' => 'Admin', 'password' => Hash::make('123456')]
        );
        $admin->assignRole('super-admin');

        // 测试用户
        $testUser = User::updateOrCreate(
            ['email' => 'test@mylife.com'],
            ['name' => 'TestUser', 'password' => Hash::make('123456')]
        );
        $testUser->assignRole('user');
        // 👇 1. 初始化目标类型 (写死在这里)
        $types = [
            ['name' => 'health',  'title' => '健康', 'color' => '#67C23A'], // 绿
            ['name' => 'ability', 'title' => '能力', 'color' => '#409EFF'], // 蓝
            ['name' => 'finance', 'title' => '财务', 'color' => '#E6A23C'], // 金
            ['name' => 'time',    'title' => '时间', 'color' => '#909399'], // 灰
            ['name' => 'faith',   'title' => '信仰', 'color' => '#F56C6C'], // 红
            ['name' => 'family',  'title' => '亲情', 'color' => '#d4237a'], // 紫
        ];

        foreach ($types as $t) {
            GoalType::firstOrCreate(['name' => $t['name']], $t);
        }
    }
}