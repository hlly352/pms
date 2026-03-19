import request from '@/utils/request'

// 1. 获取用户列表
export function getUserList() {
  return request({
    url: '/users',
    method: 'get'
  })
}

// 2. 获取角色列表 (以后做动态下拉框用)
export function getRoleList() {
  return request({
    url: '/roles',
    method: 'get'
  })
}

// 3. 更新用户
export function updateUser(id, data) {
  return request({
    url: '/users/' + id,
    method: 'put',
    data
  })
}

// 4. 删除用户 (就是缺了这个！)
export function deleteUser(id) {
  return request({
    url: '/users/' + id,
    method: 'delete'
  })
}

// 创建用户
export function createUser(data) {
  return request({
    url: '/users', // 根据你的后端实际路由修改
    method: 'post',
    data
  })
}

// 重置用户密码
export function resetUserPassword(id, data) {
  return request({
    url: `/users/${id}/reset-password`, // 根据你的后端实际路由修改
    method: 'patch', // 也可以是 put 或 post
    data
  })
}

// 修改当前登录用户邮箱
export function updateEmail(data) {
  return request({ url: '/user/email', method: 'put', data })
}

// 修改当前登录用户密码
export function updatePassword(data) {
  return request({ url: '/user/password', method: 'put', data })
}