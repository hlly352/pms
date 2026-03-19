<script setup>
import { ref, onMounted } from 'vue'
import { getPermissionList, createPermission, deletePermission } from '@/api/system'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Delete, Plus } from '@element-plus/icons-vue'

const list = ref([])
const dialogVisible = ref(false)
const form = ref({ name: '' })

// 加载列表
const loadData = async () => {
  list.value = await getPermissionList()
}

// 提交新增
const handleSubmit = async () => {
  if (!form.value.name) return ElMessage.warning('请输入权限标识')
  
  try {
    await createPermission(form.value)
    ElMessage.success('添加成功')
    dialogVisible.value = false
    form.value.name = '' // 重置表单
    loadData() // 刷新列表
  } catch (e) {
    // 错误由拦截器处理
  }
}

// 删除权限
const handleDelete = (row) => {
  ElMessageBox.confirm(`确定要删除 "${row.name}" 吗？这可能会影响已分配该权限的角色！`, '警告', {
    type: 'warning'
  }).then(async () => {
    await deletePermission(row.id)
    ElMessage.success('删除成功')
    loadData()
  })
}

onMounted(loadData)
</script>

<template>
  <div class="page-container">
    <div class="header">
      <el-button type="primary" :icon="Plus" @click="dialogVisible = true">新增权限</el-button>
    </div>

    <el-table :data="list" border stripe style="margin-top: 20px">
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column prop="name" label="权限标识 (Code)" />
      <el-table-column prop="created_at" label="创建时间" />
      <el-table-column label="操作" width="120">
        <template #default="{ row }">
          <el-button 
            type="danger" 
            size="small" 
            :icon="Delete" 
            @click="handleDelete(row)"
          >
            删除
          </el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog v-model="dialogVisible" title="新增权限标识" width="400px">
      <el-form :model="form" @keyup.enter="handleSubmit">
        <el-form-item label="标识名称">
          <el-input v-model="form.name" placeholder="例：task.view (建议格式：模块.动作)" />
          <div class="tips">建议使用英文点号分隔，如 user.edit, order.delete</div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmit">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
.page-container { padding: 20px; background: #fff; border-radius: 8px; }
.tips { font-size: 12px; color: #999; margin-top: 5px; }
</style>