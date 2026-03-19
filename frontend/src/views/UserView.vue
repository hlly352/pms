<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { getUserList, updateUser, deleteUser, createUser, resetUserPassword } from '@/api/user'
import { getRoles } from '@/api/system'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Edit, Delete, Plus, Key } from '@element-plus/icons-vue'

const users = ref([])
const allRoles = ref([]) 
const dialogVisible = ref(false)
const isEdit = ref(false)

// 🌟 新增：表单引用，用于触发校验
const userFormRef = ref(null)

const form = reactive({
  id: null,
  name: '',
  email: '',
  password: '',
  role_names: [] 
})

// 🌟 新增：Element Plus 表单验证规则 (使用 computed 动态判断)
const rules = computed(() => {
  return {
    name: [{ required: true, message: '请输入用户名', trigger: 'blur' }],
    email: [
      { required: true, message: '请输入邮箱地址', trigger: 'blur' },
      { type: 'email', message: '请输入正确的邮箱格式 (如 admin@qq.com)', trigger: ['blur', 'change'] }
    ],
    // 编辑状态不需要校验密码，新增状态强制校验6位以上
    password: isEdit.value ? [] : [
      { required: true, message: '请设置初始密码', trigger: 'blur' },
      { min: 6, message: '密码长度不能少于 6 个字符', trigger: 'blur' }
    ]
  }
})

// 角色标签颜色字典
const roleColors = {
  'super-admin': 'danger',
  'user': 'info',
  'editor': 'warning'
}

const loadData = async () => {
  users.value = await getUserList()
  allRoles.value = await getRoles()
}

const handleAdd = () => {
  isEdit.value = false
  form.id = null
  form.name = ''
  form.email = ''
  form.password = ''
  form.role_names = []
  dialogVisible.value = true
  // 弹窗打开时清除可能遗留的红色校验提示
  if (userFormRef.value) userFormRef.value.clearValidate()
}

const handleEdit = (row) => {
  isEdit.value = true
  form.id = row.id
  form.name = row.name
  form.email = row.email
  form.password = '' 
  form.role_names = row.roles ? row.roles.map(r => r.name) : []
  dialogVisible.value = true
  if (userFormRef.value) userFormRef.value.clearValidate()
}

// 🌟 修改：提交时先执行前端表单校验
const handleSubmit = async () => {
  if (!userFormRef.value) return
  
  // 触发校验
  await userFormRef.value.validate(async (valid) => {
    if (valid) {
      try {
        if (isEdit.value) {
          await updateUser(form.id, form)
          ElMessage.success('更新成功')
        } else {
          await createUser(form)
          ElMessage.success('用户创建成功')
        }
        dialogVisible.value = false
        loadData() 
      } catch (error) {
        console.error('提交失败:', error)
      }
    } else {
      ElMessage.warning('请检查标红的字段填写是否正确')
    }
  })
}

const handleDelete = (row) => {
  ElMessageBox.confirm('确定删除该用户吗?', '警告', { type: 'warning' })
    .then(async () => {
      await deleteUser(row.id)
      ElMessage.success('删除成功')
      loadData()
    })
}

const handleResetPassword = (row) => {
  ElMessageBox.prompt(`请输入为【${row.name}】设置的新密码：`, '重置密码', {
    confirmButtonText: '确认重置',
    cancelButtonText: '取消',
    inputType: 'password', 
    inputPattern: /^.{6,}$/, 
    inputErrorMessage: '密码长度不能少于 6 个字符'
  }).then(async ({ value }) => {
    await resetUserPassword(row.id, { password: value })
    ElMessage.success(`成功重置【${row.name}】的密码！`)
  }).catch(() => {})
}

onMounted(loadData)
</script>

<template>
  <div class="user-container">
    <div class="header-box">
      <h2>用户权限管理 🛡️</h2>
      <el-button type="success" :icon="Plus" @click="handleAdd">
        新增用户
      </el-button>
    </div>

    <el-table :data="users" border stripe style="margin-top: 20px">
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column prop="name" label="用户名" width="150" />
      <el-table-column prop="email" label="邮箱" width="200" />
      
      <el-table-column label="角色">
        <template #default="scope">
          <el-tag 
            v-for="role in scope.row.roles" 
            :key="role.id"
            :type="roleColors[role.name] || 'primary'"
            style="margin-right: 5px"
          >
            {{ role.name }}
          </el-tag>
        </template>
      </el-table-column>

      <el-table-column label="操作" width="300">
        <template #default="scope">
          <el-button type="primary" size="small" :icon="Edit" @click="handleEdit(scope.row)">配置</el-button>
          <el-button type="warning" size="small" :icon="Key" @click="handleResetPassword(scope.row)">重置密码</el-button>
          <el-button type="danger" size="small" :icon="Delete" @click="handleDelete(scope.row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑用户配置' : '新增用户'" width="500px">
      
      <el-form ref="userFormRef" :model="form" :rules="rules" label-width="80px">
        
        <el-form-item label="用户名" prop="name">
          <el-input v-model="form.name" :disabled="isEdit" placeholder="请输入用户名" />
        </el-form-item>

        <el-form-item label="邮箱" prop="email">
          <el-input v-model="form.email" placeholder="如: admin@qq.com" />
        </el-form-item>
        
        <el-form-item v-if="!isEdit" label="初始密码" prop="password">
          <el-input v-model="form.password" type="password" show-password placeholder="至少输入 6 位字符" />
        </el-form-item>

        <el-form-item label="分配角色">
          <el-select v-model="form.role_names" multiple placeholder="请选择角色" style="width: 100%">
            <el-option v-for="role in allRoles" :key="role.id" :label="role.name" :value="role.name" />
          </el-select>
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
.user-container { padding: 20px; background: #fff; border-radius: 8px; }
.header-box { display: flex; justify-content: space-between; align-items: center; }
.header-box h2 { margin: 0; }
</style>