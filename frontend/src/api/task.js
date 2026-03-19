import request from '@/utils/request'

export const getTasks = (params) => request.get('/tasks', { params })
export const createTask = (data) => request.post('/tasks', data)
export const updateTask = (id, data) => request.put(`/tasks/${id}`, data)
export const deleteTask = (id) => request.delete(`/tasks/${id}`)

// 还需要复用获取规则的接口 (用于下拉框)
export const getRules = (params) => request.get('/rules', { params })

export const generateTasks = () => request.post('/tasks/generate') // 一键生成
export const getTaskDetails = (id) => request.get(`/tasks/${id}/details`) // 获取详情
// 👇 新增：获取所有任务详情
export const getAllTaskDetails = () => request.get('/all-task-details')
// 👇 新增：更新任务详情状态
export function updateTaskDetailStatus(id, data) {
    return request.put(`/task-details/${id}/status`, data)
  }
// 👇 新增：更新任务详情备注
export const updateTaskDetailRemark = (id, remark) => request.put(`/task-details/${id}/remark`, { remark })
// 👇 新增：获取日历事件
export const getCalendarEvents = (start, end) => request.get('/calendar-events', { params: { start, end } })
// 👇 新增：获取今日待办
export const getTodayPendingTasks = () => request.get('/dashboard/today-pending')