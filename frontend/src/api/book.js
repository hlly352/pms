import request from '@/utils/request'

// 接收一个 params 参数对象
export function getBookList(params) {
    return request({
      url: '/books',
      method: 'get',
      params: params // axios 会自动把它拼接到 URL 后面，变成 ?title=xx&status=xx
    })
}

export function addBook(data) {
  return request({ url: '/books', method: 'post', data })
}

export function updateBook(id, data) {
  return request({ url: '/books/' + id, method: 'put', data })
}

export function deleteBook(id) {
  return request({ url: '/books/' + id, method: 'delete' })
}