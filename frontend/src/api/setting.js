import request from '@/utils/request'

// 获取系统配置
export function getSettings() {
  return request({ url: '/settings', method: 'get' })
}

// 保存系统配置
export function saveSettings(data) {
  return request({ url: '/settings', method: 'post', data })
}