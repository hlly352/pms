import request from '@/utils/request'

export const getReadingNotes = (params) => request.get('/reading-notes', { params })
export const createReadingNote = (data) => request.post('/reading-notes', data)
export const updateReadingNote = (id, data) => request.put(`/reading-notes/${id}`, data)
export const deleteReadingNote = (id) => request.delete(`/reading-notes/${id}`)