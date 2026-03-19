import { useAuthStore } from '@/stores/auth'

export default {
  mounted(el, binding) {
    const { value } = binding // 获取指令的值，比如 'user.delete'
    const authStore = useAuthStore()
    
    // 如果没有传递权限值，就忽略
    if (!value) return

    // 获取当前用户的权限列表 (我们需要先在 store 里存好)
    const permissions = authStore.permissions || []

    // 判断逻辑：
    // 1. 如果是 super-admin (我们假定超管拥有所有权限)
    // 2. 或者权限列表里包含这个权限
    const hasPermission = permissions.includes('all') || permissions.includes(value)

    if (!hasPermission) {
      // 移除 DOM 元素
      el.parentNode && el.parentNode.removeChild(el)
    }
  }
}