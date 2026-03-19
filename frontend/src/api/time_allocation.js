import request from '@/utils/request'

// 获取时间分配大盘的统计数据 (总获取、已分配等)
export function getTimeStats() {
  return request.get('/time-allocations/stats')
}

// 获取时间分配规则列表
export function getTimeRules() {
  return request.get('/time-allocations/rules')
}

// 保存或更新分配规则
export function saveTimeRule(data) {
  return request.post('/time-allocations/rules', data)
}

// 🚀 核心：执行一次时间分配 (把时间充入账户)
export function executeTimeAllocation(data) {
  return request.post('/time-allocations/execute', data)
}

// 获取时间分配的历史日志 (支持传时间段和规则ID过滤)
export function getTimeLogs(params) {
  return request.get('/time-allocations/logs', { params })
}

// 撤回(删除)一条分配日志
export function deleteTimeLog(id) {
  return request.delete(`/time-allocations/logs/${id}`)
}

// 切换规则状态
export function toggleTimeRuleStatus(id, isActive) {
    return request.put(`/time-allocations/rules/${id}/status`, { is_active: isActive })
  }