// 文件路径：src/api/time_account.js
import request from '@/utils/request'

// 获取所有时间账户列表
export function getTimeAccounts(params) {
  return request.get('/time-accounts', { params })
}

// 创建时间账户
export function createTimeAccount(data) {
  return request.post('/time-accounts', data)
}

// 更新时间账户
export function updateTimeAccount(id, data) {
  return request.put(`/time-accounts/${id}`, data)
}

// 删除时间账户
export function deleteTimeAccount(id) {
  return request.delete(`/time-accounts/${id}`)
}

// 获取某个时间账户的分配入账记录
export function getTimeAccountAllocations(id) {
  return request.get(`/time-accounts/${id}/allocations`)
}