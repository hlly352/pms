<script setup>
import { ref, onMounted } from 'vue'
import { getMenus, createMenu, updateMenu, deleteMenu } from '@/api/system'
import { ElMessage, ElMessageBox } from 'element-plus'

const menus = ref([])
const dialogVisible = ref(false)
const form = ref({ parent_id: null, title: '', path: '', icon: '', permission: '', sort: 0 })
const isEdit = ref(false)

const loadData = async () => {
  menus.value = await getMenus()
}

const handleAdd = (parentId = null) => {
  isEdit.value = false
  form.value = { parent_id: parentId, title: '', path: '', icon: '', permission: '', sort: 0 }
  dialogVisible.value = true
}

const handleSubmit = async () => {
  if (isEdit.value) {
    await updateMenu(form.value.id, form.value)
  } else {
    await createMenu(form.value)
  }
  dialogVisible.value = false
  loadData()
}
const handleDelete = (row) => {
  ElMessageBox.confirm(`确定要删除 "${row.title}" 吗？如果有子菜单也会一并删除！`, '警告', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning',
  }).then(async () => {
    try {
      await deleteMenu(row.id)
      ElMessage.success('删除成功')
      loadData() // 刷新列表
    } catch (error) {
      console.error(error)
    }
  })
}
onMounted(loadData)
</script>

<template>
  <div class="menu-container">
    <el-button type="primary" @click="handleAdd(null)">新增一级菜单</el-button>
    
    <el-table 
      :data="menus" 
      border 
      row-key="id" 
      default-expand-all
      style="margin-top: 20px"
    >
      <el-table-column prop="title" label="菜单名称" width="200" />
      <el-table-column prop="icon" label="图标" width="100" />
      <el-table-column prop="path" label="路由路径" />
      <el-table-column prop="permission" label="权限标识" />
      <el-table-column prop="sort" label="排序" width="80" />
      <el-table-column label="操作" width="220">
        <template #default="{ row }">
          <el-button 
            size="small" 
            type="primary" 
            link 
            @click="handleAdd(row.id)"
          >
            添加子菜单
          </el-button>
          
          <el-button 
            size="small" 
            link 
            type="primary"
            @click="isEdit=true;form={...row};dialogVisible=true"
          >
            编辑
          </el-button>

          <el-button 
            v-if="!row.is_system" 
            size="small" 
            link 
            type="danger" 
            @click="handleDelete(row)"
          >
            删除
          </el-button>
          
          <el-tag v-else type="info" size="small" style="margin-left:10px">
             <el-icon><Lock /></el-icon> 系统
          </el-tag>

        </template>
      </el-table-column>
    </el-table>

    <el-dialog v-model="dialogVisible" title="菜单编辑">
      <el-form :model="form" label-width="100px">
        <el-form-item label="标题">
          <el-input v-model="form.title" placeholder="如：用户管理" />
        </el-form-item>
        <el-form-item label="路径">
          <el-input v-model="form.path" placeholder="如：/users" />
        </el-form-item>
        <el-form-item label="图标">
          <el-input v-model="form.icon" placeholder="Element Plus Icon" />
        </el-form-item>
        <el-form-item label="绑定权限">
          <el-input v-model="form.permission" placeholder="如：user.view" />
          <span style="font-size:12px;color:#999">如果不填，则所有人可见。填了则需要拥有该权限。</span>
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="form.sort" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="handleSubmit">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>
<style scoped> .menu-container { padding: 20px; background: #fff; } </style>