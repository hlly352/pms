import axios from 'axios'
import { ElMessage } from 'element-plus'

const service = axios.create({
  baseURL: '/api', // 你的后端地址
  timeout: 5000
})

// 1. 请求拦截器 (关键修复点)
service.interceptors.request.use(
  (config) => {
    // 👇 关键：必须在【函数内部】获取 Token
    // 这样每次请求发出去之前，都会去 localStorage 拿最新的
    const token = localStorage.getItem('token')
    
    if (token) {
      // 这里的格式必须是 'Bearer ' + token (注意有空格)
      config.headers['Authorization'] = `Bearer ${token}`
    }
    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

// 2. 响应拦截器
service.interceptors.response.use(
  (response) => {
    const res = response.data
    // 假设后端没有 code 或者 code === 200 算成功
    // 根据你的后端实际返回结构调整，如果是直接返回数据：
    return res
  },
  (error) => {
    // 统一处理错误
    if (error.response && error.response.status === 401) {
      ElMessage.error('登录已过期，请重新登录')
      // 可选：自动跳转到登录页
      // window.location.href = '/login'
    } else {
      ElMessage.error(error.message || '请求失败')
    }
    return Promise.reject(error)
  }
)

export default service
