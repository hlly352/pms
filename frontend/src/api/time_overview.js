import request from '@/utils/request' // 引入你项目中封装好的 axios 实例

/**
 * 获取时间大盘看板数据 (包含时间池余额、期间注入源、期间消耗去向)
 * @param {Object} params 包含 start_date 和 end_date 等查询参数
 * @returns {Promise}
 */
export function getTimeDashboardData(params) {
  return request({
    url: '/time-dashboard', // 对应的后端路由地址
    method: 'get',
    params: params
  })
}