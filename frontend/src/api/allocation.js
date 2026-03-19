import request from '@/utils/request'

// 获取大盘统计信息 (资金池)
export const getStats = () => {
  return request.get('/allocations/stats')
}

// 获取所有分配规则
export const getRules = () => {
  return request.get('/allocations/rules')
}

// 保存分配规则 (新增或编辑)
export const saveRule = (data) => {
  return request.post('/allocations/rules', data)
}

// 执行一次资金分配 🚀
export const executeAllocation = (data) => {
  return request.post('/allocations/execute', data)
}

// 🌟 升级：支持传参搜索
export const getLogs = (params) => {
    return request.get('/allocations/logs', { params })
  }
  
  // 🌟 新增：删除日志
  export const deleteLog = (id) => {
    return request.delete(`/allocations/logs/${id}`)
  }