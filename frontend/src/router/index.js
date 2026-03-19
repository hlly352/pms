import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('../views/LoginView.vue')
    },
    {
      // 根路径 /
      path: '/',
      // 使用我们刚才写的布局组件
      component: () => import('../layout/AppLayout.vue'),
      // 只要访问 /，就自动跳到 dashboard
      redirect: '/dashboard',
      // 👇 关键：子路由！这些页面会显示在 AppLayout 的 <router-view /> 里
      children: [
        {
            path: 'dashboard', // 注意：路径不需要 /
            name: 'dashboard',
            component: () => import('../views/DashboardView.vue')
          },
        {
            path: 'tasks', // 注意：不要加斜杠 /
            name: 'tasks',
            component: () => import('../views/TaskView.vue') // 或者是 TaskView.vue
        },
        {
            path: 'task-details', // 访问路径 /task-details
            name: 'TaskDetailList',
            component: () => import('../views/TaskDetailListView.vue'), // 👇 下一步要创建的文件
            meta: { 
              title: '任务详情列表', // 左侧菜单显示的文字
              icon: 'List'        // 菜单图标
            }
          },
          {
            path: 'calendar',
            name: 'TaskCalendar',
            component: () => import('../views/TaskCalendarView.vue'),
            meta: { title: '任务日程表', icon: 'Calendar' }
          },
          {
            path: '/time-allocation',
            name: 'TimeAllocation',
            component: () => import('../views/TimeAllocationView.vue'),
            meta: { 
              title: '时间分配', 
              icon: 'Timer' // 如果你的菜单是动态读取 meta.icon 的，在这里配好
            }
          },
          {
            path: '/time-accounts',
            name: 'TimeAccount',
            component: () => import('@/views/TimeAccountView.vue'),
            meta: { title: '时间账户管理', icon: 'Timer' }
          },
          // 在你的 routes 数组中的合适位置添加：
          {
            path: '/time-overview',         // 浏览器地址栏的访问路径
            name: 'TimeOverview',
            // 这里的路径请替换为你实际存放 TimeOverview.vue 的路径
            component: () => import('@/views/TimeOverview.vue'), 
            meta: { 
              title: '时间大盘', 
              icon: 'DataBoard' // 你的图标
            }
          },
        { 
            path: 'roles', 
            component: () => import('../views/RoleView.vue') 
        },
        { 
            path: 'menus', 
            component: () => import('../views/MenuView.vue') 
        },
        { 
            path: 'permissions', 
            component: () => import('../views/PermissionView.vue') 
        },
        {
          path: 'todo', // 注意：不要加斜杠 /
          name: 'todo',
          component: () => import('../views/TodoView.vue') // 或者是 TaskView.vue
        },
        {
          path: '/users',
          name: 'users',
          component: () => import('../views/UserView.vue')
        },
        {
            path: '/goals',
            name: 'goals',
            component: () => import('../views/GoalView.vue')
        },
        {
            path: '/projects', // 对应数据库菜单的路径 (列表页)
            component: () => import('../views/ProjectListView.vue'),
            meta: { title: '项目计划' }
          },
          {
            path: '/project-dashboard',
            name: 'ProjectDashboard',
            component: () => import('../views/ProjectDashboardView.vue'), // 确保路径正确
            meta: { title: '项目大看板', icon: 'DataBoard' } // 根据你的系统定义meta
          },
          {
            path: '/rules',
            name: 'rules',
            component: () => import('../views/RuleView.vue')
        },
        {
            path: '/recitation', // 浏览器访问路径: /recitation
            name: 'Recitation', // 命名路由，必须唯一
            component: () => import('@/views/RecitationView.vue'), // 懒加载我们刚才写的文件
            meta: { 
              title: '背诵记忆', // 侧边栏显示的文字
              icon: 'Reading',   // 侧边栏图标 (需要确保你引入了这个图标)
              requiresAuth: true // 如果有权限控制
            }
          },
          {
            path: '/recitationtasks',
            name: 'RecitationTasks',
            component: () => import('@/views/RecitationTaskView.vue'), 
            meta: { title: '背诵任务' }
          },
        {
            path: '/records',
            name: 'records',
            component: () => import('../views/RecordView.vue')
        },
        {
          path: '/categories',
          name: 'Categories',
          component: () => import('../views/CategoryView.vue'), // 指向我们马上要建的页面
          meta: { title: '分类管理' }
        },
        {
            path: '/books',
            name: 'Books',
            component: () => import('../views/BookView.vue')
        },
        {
            path: '/reading-speeds',
            name: 'ReadingSpeeds',
            component: () => import('@/views/ReadingSpeedView.vue'),
            meta: { title: '阅读速度管理' }
          },
          {
            path: '/subjects',
            name: 'Subjects',
            component: () => import('../views/SubjectListView.vue'),
            meta: { title: '收支科目管理', icon: 'Connection' }
          },
        {
          path: '/reading-plans',
          name: 'ReadingPlans',
          component: () => import('../views/ReadingPlanView.vue'), // 确保路径指向你刚才创建的 vue 文件
          meta: { title: '阅读计划' }
         },
         {
          path: '/reading-notes',
          name: 'Reading-note',
          component: () => import('../views/ReadingNoteTimelineView.vue'), // 确保路径指向你刚才创建的 vue 文件
          meta: { title: '阅读笔记' }
         },
        {
            path: '/settings',
            name: 'setting',
            component: () => import('../views/SettingView.vue')
        },
        {
          path: '/accounts', // 路由地址
          name: 'Account',
          component: () => import('../views/AccountListView.vue'), // 指向刚才建的文件
          meta: { title: '账户管理' }
        },
        {
          path: '/allocations',
          name: 'Allocation',
          component: () => import('../views/AllocationView.vue'),
          meta: { title: '收入分配中心' }
        },
        {
            path: '/transactions', 
            name: 'Transactions',
            component: () => import('../views/TransactionListView.vue'), // 👈 换成新名字
            meta: { title: '收支记录', icon: 'Coin' }
        },
        {
          path: '/financeoverviews',
          name: 'FinanceOverview',
          component: () => import('../views/FinanceOverView.vue'),
          meta: { title: '财务大盘' }
        }
      ]
    }
  ]
})

// 路由守卫：没登录不许进
router.beforeEach((to, from, next) => {
  const token = localStorage.getItem('token')
  if (to.name !== 'login' && !token) {
    next({ name: 'login' })
  } else {
    next()
  }
})

export default router