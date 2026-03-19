import request from '@/utils/request'

/**
 * 获取收支科目树形列表
 * @returns {Promise} 包含科目树状结构数据的 Promise
 */
// 🌟 修改：支持传入 params (如 { subject_type: 'expense' })
export const getSubjects = (params) => {
    return request.get('/subjects', { params }) 
  }

/**
 * 新增收支科目
 * @param {Object} data - 科目表单数据 (如 pid, subject_name, subject_code 等)
 * @returns {Promise}
 */
export const createSubject = (data) => {
  return request.post('/subjects', data)
}

/**
 * 更新收支科目
 * @param {Number|String} id - 科目 ID
 * @param {Object} data - 科目表单数据
 * @returns {Promise}
 */
export const updateSubject = (id, data) => {
  return request.put(`/subjects/${id}`, data)
}

/**
 * 删除收支科目
 * @param {Number|String} id - 科目 ID
 * @returns {Promise}
 */
export const deleteSubject = (id) => {
  return request.delete(`/subjects/${id}`)
}