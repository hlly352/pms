import request from '@/utils/request'

// 登录接口
export function login(data) {
  return request({
    url: '/login', // 对应你后端 routes/api.php 里的 Route::post('/login', ...)
    method: 'post',
    data
  })
}

// 获取当前用户信息 (刚才 LoginView 用到的 fetchUserInfo 会间接用到它，或者 store 里用)
export function getInfo() {
  return request({
    url: '/me',
    method: 'get'
  })
}

// 退出登录
export function logout() {
  return request({
    url: '/logout',
    method: 'post'
  })
}