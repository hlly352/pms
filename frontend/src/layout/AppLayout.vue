<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { getMyMenus } from '@/api/system' // 引入菜单接口
import { getSettings } from '@/api/setting' // 引入配置接口
// 引入所有图标，用于动态渲染后端返回的图标字符串
import * as ElementPlusIconsVue from '@element-plus/icons-vue' 

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

// 状态定义
const isCollapse = ref(false)
const siteTitle = ref('MyLife 个人管理') // 默认标题
const menuList = ref([]) // 存储从后端拿到的菜单

// 权限判断辅助函数
const hasPerm = (perm) => {
  // 1. 如果菜单没配权限标识，默认所有人可见
  if (!perm) return true
  // 2. 如果是超级管理员 (我们在登录时给 admin 赋予了 'all')，可见
  if (authStore.permissions.includes('all')) return true
  // 3. 检查当前用户的权限列表是否包含该权限
  return authStore.permissions.includes(perm)
}

// 退出登录
const handleLogout = () => {
  if(confirm('确定要退出登录吗？')) {
    authStore.logout()
    router.push('/login')
  }
}

// 初始化加载
onMounted(async () => {
  // 1. 🚨 关键步骤：先拉取最新的用户信息和权限！
  // 如果不加这行，刷新页面时 Pinia 里的权限可能是空的，导致菜单全部隐藏
  await authStore.fetchUserInfo()

  // 2. 加载系统设置 (比如网站标题)
  try {
    const settingRes = await getSettings()
    if (settingRes && settingRes.site_title) {
      siteTitle.value = settingRes.site_title
    }
  } catch (e) {
    console.error('加载系统设置失败', e)
  }

  // 3. 加载动态菜单
  // (这时候 authStore.permissions 已经有值了，hasPerm 函数才能正常工作)
  try {
    const menuRes = await getMyMenus()
    menuList.value = menuRes
  } catch (e) {
    console.error('加载菜单失败', e)
  }
})
</script>

<template>
  <el-container class="layout-container">
    
    <el-aside :width="isCollapse ? '64px' : '220px'" class="aside">
      <div class="logo">
        <span v-if="!isCollapse">{{ siteTitle }}</span>
        <span v-else>M</span>
      </div>
      
      <el-menu
            :default-active="route.path"
            class="el-menu-vertical"
            :collapse="isCollapse"
            background-color="#304156"
            text-color="#bfcbd9"
            active-text-color="#409EFF"
            router
            >
            <template v-for="menu in menuList" :key="menu.id">
                
                <template v-if="hasPerm(menu.permission)">

                <el-sub-menu 
                    v-if="menu.children && menu.children.length > 0" 
                    :index="String(menu.id)"
                >
                    <template #title>
                    <el-icon v-if="menu.icon"><component :is="menu.icon" /></el-icon>
                    <span>{{ menu.title }}</span>
                    </template>
                    
                    <template v-for="child in menu.children" :key="child.id">
                    <el-menu-item 
                        v-if="hasPerm(child.permission)" 
                        :index="child.path"
                    >
                        {{ child.title }}
                    </el-menu-item>
                    </template>
                </el-sub-menu>

                <el-menu-item v-else :index="menu.path">
                    <el-icon v-if="menu.icon"><component :is="menu.icon" /></el-icon>
                    <template #title>{{ menu.title }}</template>
                </el-menu-item>

                </template>
            </template>
            </el-menu>
    </el-aside>

    <el-container>
      <el-header class="header">
        <div class="header-left">
          <el-icon class="collapse-btn" @click="isCollapse = !isCollapse">
            <component :is="isCollapse ? 'Expand' : 'Fold'" />
          </el-icon>
          <span style="margin-left: 15px; color: #666; font-size: 14px;">
            欢迎回来，{{ authStore.user.name || '管理员' }}
          </span>
        </div>

        <div class="header-right">
          <el-dropdown>
            <span class="el-dropdown-link">
              <el-avatar :size="32" style="background: #409EFF">
                {{ authStore.user.name ? authStore.user.name[0].toUpperCase() : 'A' }}
              </el-avatar>
              <span class="username">{{ authStore.user.name }}</span>
            </span>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item @click="router.push('/settings?tab=security')">个人设置</el-dropdown-item>
                <el-dropdown-item @click="router.push('/settings?tab=basic')">系统设置</el-dropdown-item>
                <el-dropdown-item divided @click="handleLogout">退出登录</el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </div>
      </el-header>

      <el-main class="main">
        <router-view />
      </el-main>
    </el-container>
  </el-container>
</template>

<style scoped>
.layout-container {
  height: 100vh;
}

.aside {
  background-color: #304156;
  color: #fff;
  transition: width 0.3s;
  overflow-x: hidden;
}

.logo {
  height: 60px;
  line-height: 60px;
  text-align: center;
  font-size: 20px;
  font-weight: bold;
  color: #fff;
  background-color: #2b2f3a;
  white-space: nowrap;
  overflow: hidden;
}

.el-menu-vertical {
  border-right: none;
}

.header {
  background-color: #fff;
  border-bottom: 1px solid #dcdfe6;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0 20px;
}

.header-left {
  display: flex;
  align-items: center;
}

.collapse-btn {
  font-size: 24px;
  cursor: pointer;
  color: #555;
}

.header-right {
  display: flex;
  align-items: center;
}

.username {
  margin-left: 8px;
  font-weight: 500;
  cursor: pointer;
}

.main {
  background-color: #f0f2f5;
  padding: 20px;
}

/* 修复折叠动画卡顿 */
.el-menu-vertical:not(.el-menu--collapse) {
  width: 220px;
  min-height: 400px;
}
</style>