import { defineStore } from 'pinia'
import { ref } from 'vue'
import request from '@/utils/request' // 确保引入了 request

export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem('token') || '')
  const user = ref(JSON.parse(localStorage.getItem('user') || '{}'))
  const permissions = ref(JSON.parse(localStorage.getItem('permissions') || '[]')) // 👈 新增：持久化存储权限

  function setToken(newToken) {
    token.value = newToken
    localStorage.setItem('token', newToken)
  }

  function setUser(newUser) {
    user.value = newUser
    localStorage.setItem('user', JSON.stringify(newUser))
  }

  // 👇 新增：设置权限的方法
  function setPermissions(newPermissions) {
    permissions.value = newPermissions
    localStorage.setItem('permissions', JSON.stringify(newPermissions))
  }

  function logout() {
    token.value = ''
    user.value = {}
    permissions.value = []
    localStorage.removeItem('token')
    localStorage.removeItem('user')
    localStorage.removeItem('permissions')
  }

  // 👇 关键动作：拉取用户信息和权限
  async function fetchUserInfo() {
    try {
      // 请求后端的 /api/me 接口
      const res = await request.get('/me')
      if (res && res.user) {
        setUser(res.user)
      }
      if (res && res.permissions) {
        setPermissions(res.permissions) // 把权限存进状态库
      }
      return res
    } catch (error) {
      console.error('获取用户信息失败', error)
    }
  }

  return { 
    token, 
    user, 
    permissions, // 👈 导出 permissions
    setToken, 
    setUser, 
    setPermissions, 
    logout, 
    fetchUserInfo // 👈 导出 fetchUserInfo
  }
})