<script setup>
import { ref, onMounted } from 'vue'
import { getRoles, getAllPermissions, createRole, updateRole, deleteRole } from '@/api/system'
import { ElMessage } from 'element-plus'

const roles = ref([])
const permissions = ref([])
const dialogVisible = ref(false)
const form = ref({ id: null, name: '', permissions: [] })
const isEdit = ref(false)

// 加载数据
const loadData = async () => {
  roles.value = await getRoles()
  permissions.value = await getAllPermissions() // 加载所有权限供勾选
}

const handleEdit = (row) => {
  isEdit.value = true
  form.value = {
    id: row.id,
    name: row.name,
    // 提取该角色已有的权限名
    permissions: row.permissions.map(p => p.name) 
  }
  dialogVisible.value = true
}

const handleCreate = () => {
  isEdit.value = false
  form.value = { id: null, name: '', permissions: [] }
  dialogVisible.value = true
}

const handleSubmit = async () => {
  if (isEdit.value) {
    await updateRole(form.value.id, form.value)
  } else {
    await createRole(form.value)
  }
  ElMessage.success('操作成功')
  dialogVisible.value = false
  loadData()
}

onMounted(loadData)
</script>

<template>
  <div class="role-container">
    <div class="header">
      <el-button type="primary" @click="handleCreate">新增角色</el-button>
    </div>

    <el-table :data="roles" border style="margin-top:20px">
      <el-table-column prop="name" label="角色名称" />
      <el-table-column label="拥有的权限">
        <template #default="{ row }">
          <el-tag v-for="p in row.permissions" :key="p.id" style="margin-right:5px">
            {{ p.name }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="200">
        <template #default="{ row }">
          <el-button size="small" @click="handleEdit(row)">编辑权限</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑角色' : '新建角色'">
      <el-form :model="form" label-width="80px">
        <el-form-item label="角色名称">
          <el-input v-model="form.name" />
        </el-form-item>
        <el-form-item label="权限分配">
          <el-checkbox-group v-model="form.permissions">
            <el-checkbox 
              v-for="p in permissions" 
              :key="p.id" 
              :label="p.name"
            >
              {{ p.name }}
            </el-checkbox>
          </el-checkbox-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmit">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
.role-container { padding: 20px; background: #fff; }
</style>