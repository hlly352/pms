import request from '@/utils/request'

export const getRules = () => request.get('/rules')
export const createRule = (data) => request.post('/rules', data)
export const updateRule = (id, data) => request.put(`/rules/${id}`, data)
export const deleteRule = (id) => request.delete(`/rules/${id}`)