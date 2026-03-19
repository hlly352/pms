import request from '@/utils/request' // 假设你的 axios 封装在这里，如果路径不同请自行调整

// 获取所有账户
export const getAccounts = () => {
  return request.get('/accounts')
}

// 新增账户
export const createAccount = (data) => {
  return request.post('/accounts', data)
}

// 更新账户
export const updateAccount = (id, data) => {
  return request.put(`/accounts/${id}`, data)
}

// 删除账户
export const deleteAccount = (id) => {
  return request.delete(`/accounts/${id}`)
}

// 🌟 新增：获取指定账户的分配入账记录
export const getAccountAllocations = (id) => {
    return request.get(`/accounts/${id}/allocations`)
  }

