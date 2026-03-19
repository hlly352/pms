import request from '@/utils/request'

export function getRecordList() {
  return request({ url: '/records', method: 'get' })
}

export function addRecord(content) {
  return request({ url: '/records', method: 'post', data: { content } })
}

export function deleteRecord(id) {
  return request({ url: '/records/' + id, method: 'delete' })
}