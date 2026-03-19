import request from '@/utils/request'

// 🌟 支持传入 params 对象进行日期搜索
export const getDashboardData = (params) => {
  return request.get('/finance/overview', { params })
}