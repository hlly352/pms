import request from '@/utils/request'

// 角色相关
export const getRoles = () => request.get('/roles')
export const getAllPermissions = () => request.get('/permissions')
export const createRole = (data) => request.post('/roles', data)
export const updateRole = (id, data) => request.put(`/roles/${id}`, data)
export const deleteRole = (id) => request.delete(`/roles/${id}`)

// 菜单相关
export const getMenus = () => request.get('/menus')
export const getMyMenus = () => request.get('/my-menus')
export const createMenu = (data) => request.post('/menus', data)
export const updateMenu = (id, data) => request.put(`/menus/${id}`, data)
export const deleteMenu = (id) => request.delete(`/menus/${id}`)
// 权限管理相关
export const getPermissionList = () => request.get('/permissions')
export const createPermission = (data) => request.post('/permissions', data)
export const deletePermission = (id) => request.delete(`/permissions/${id}`)