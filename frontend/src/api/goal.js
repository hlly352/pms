import request from '@/utils/request'

// 获取目标列表
export const getGoals = () => request.get('/goals')

// 👇 新增：获取目标类型列表 (用于下拉框)
export const getGoalTypes = () => request.get('/goal-types')

// 创建目标
export const createGoal = (data) => request.post('/goals', data)

// 更新目标
export const updateGoal = (id, data) => request.put(`/goals/${id}`, data)

// 删除目标
export const deleteGoal = (id) => request.delete(`/goals/${id}`)