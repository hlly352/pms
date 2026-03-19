import request from '@/utils/request'

// 获取项目列表 (🌟 核心修复：接收 params 参数并传递给 axios 的 GET 请求)
export const getProjects = (params) => request.get('/projects', { params })

// 创建项目
export const createProject = (data) => request.post('/projects', data)

// 删除项目
export const deleteProject = (id) => request.delete(`/projects/${id}`)

// 获取详情 (如果以后做编辑功能要用)
export const getProject = (id) => request.get(`/projects/${id}`)

// 更新项目
export const updateProject = (id, data) => request.put(`/projects/${id}`, data)

// 获取某阶段的步骤
export const getStageSteps = (stageId) => request.get('/stage-steps', { params: { stage_id: stageId } })

// 新增步骤
export const createStageStep = (data) => request.post('/stage-steps', data)

// 删除步骤
export const deleteStageStep = (id) => request.delete(`/stage-steps/${id}`)

// 获取某个实施步骤对应的打卡明细
export const getStepTaskDetails = (stepId) => request.get(`/project-stage-steps/${stepId}/task-details`)

// 获取单个项目的全景深层档案 (包含阶段、步骤、任务明细、财务流水)
export const getProjectFullArchive = (id) => request.get(`/projects/${id}/full-archive`)