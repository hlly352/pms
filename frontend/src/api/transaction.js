import request from '@/utils/request'

export const getTransactions = (params) => {
  return request.get('/transactions', { params })
}

export const createTransaction = (data) => {
  return request.post('/transactions', data)
}

export const updateTransaction = (id, data) => {
  return request.put(`/transactions/${id}`, data)
}

export const deleteTransaction = (id) => {
  return request.delete(`/transactions/${id}`)
}