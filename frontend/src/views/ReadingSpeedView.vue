<script setup>
import { ref, reactive, onMounted } from 'vue'
import request from '@/utils/request'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Edit, Delete, Timer, Odometer } from '@element-plus/icons-vue'

// 状态定义
const loading = ref(false)
const tableData = ref([])
const showDialog = ref(false)
const isEdit = ref(false)
const formRef = ref(null)

// 表单数据
const form = reactive({
  id: null,
  name: '',
  speed: 10000 // 默认值
})

// 表单校验规则
const rules = {
  name: [{ required: true, message: '请输入规则名称', trigger: 'blur' }],
  speed: [{ required: true, message: '请输入阅读速度', trigger: 'blur' }]
}

// 加载数据
const loadData = async () => {
  loading.value = true
  try {
    const res = await request.get('/reading-speeds')
    tableData.value = res
  } finally {
    loading.value = false
  }
}

// 打开新增弹窗
const handleAdd = () => {
  isEdit.value = false
  form.id = null
  form.name = ''
  form.speed = 10000
  showDialog.value = true
}

// 打开编辑弹窗
const handleEdit = (row) => {
  isEdit.value = true
  form.id = row.id
  form.name = row.name
  form.speed = row.speed
  showDialog.value = true
}

// 提交表单
const handleSubmit = async () => {
  if (!formRef.value) return
  await formRef.value.validate(async (valid) => {
    if (valid) {
      if (isEdit.value) {
        await request.put(`/reading-speeds/${form.id}`, form)
        ElMessage.success('更新成功')
      } else {
        await request.post('/reading-speeds', form)
        ElMessage.success('创建成功')
      }
      showDialog.value = false
      loadData()
    }
  })
}

// 删除
const handleDelete = (id) => {
  ElMessageBox.confirm('确认删除该阅读速度配置吗？', '警告', {
    type: 'warning'
  }).then(async () => {
    await request.delete(`/reading-speeds/${id}`)
    ElMessage.success('删除成功')
    loadData()
  })
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="page-container">
    <div class="header">
      <h2>阅读速度管理</h2>
      <el-button type="primary" :icon="Plus" @click="handleAdd">新建速度规则</el-button>
    </div>

    <el-card shadow="never">
      <el-table :data="tableData" v-loading="loading" stripe style="width: 100%">
        <el-table-column prop="name" label="名称" min-width="150">
          <template #default="{ row }">
            <span style="font-weight: bold">{{ row.name }}</span>
          </template>
        </el-table-column>
        
        <el-table-column prop="speed" label="速度 (字/小时)" min-width="180">
          <template #default="{ row }">
            <div class="speed-badge">
              <el-icon class="speed-icon"><Speed /></el-icon>
              <span class="speed-value">{{ row.speed.toLocaleString() }}</span>
              <span class="speed-unit">字/h</span>
            </div>
          </template>
        </el-table-column>
        
        <el-table-column prop="created_at" label="创建时间" width="180">
          <template #default="{ row }">
            {{ new Date(row.created_at).toLocaleString() }}
          </template>
        </el-table-column>

        <el-table-column label="操作" width="150" align="right">
          <template #default="{ row }">
            <el-button link type="primary" :icon="Edit" @click="handleEdit(row)">编辑</el-button>
            <el-button link type="danger" :icon="Delete" @click="handleDelete(row.id)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog
      v-model="showDialog"
      :title="isEdit ? '编辑速度规则' : '新建速度规则'"
      width="500px"
    >
      <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
        <el-form-item label="名称" prop="name">
          <el-input v-model="form.name" placeholder="例如：小说、技术文档、英文原著" />
        </el-form-item>
        
        <el-form-item label="阅读速度" prop="speed">
          <el-input-number 
            v-model="form.speed" 
            :step="1000" 
            :min="1" 
            style="width: 100%" 
            controls-position="right"
          />
          <div class="tips">单位：每小时阅读字数 (Words Per Hour)</div>
        </el-form-item>
      </el-form>
      
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="showDialog = false">取消</el-button>
          <el-button type="primary" @click="handleSubmit">确认</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
.page-container { padding: 20px; }
.header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.tips { font-size: 12px; color: #909399; margin-top: 5px; }
/* ✅ 新增：速度徽章样式 */
.speed-badge {
  display: inline-flex;
  align-items: center;
  /* 柔和的蓝绿色渐变背景 */
  background: linear-gradient(135deg, #e0f7fa 0%, #b2ebf2 100%);
  color: #00838f; /* 深青色文字，对比度好 */
  padding: 6px 12px;
  border-radius: 24px; /* 这种圆角看起来更现代 */
  box-shadow: 0 2px 6px rgba(0, 131, 143, 0.15); /* 轻微的彩色阴影 */
  transition: all 0.3s ease;
  cursor: default;
}

.speed-badge:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 10px rgba(0, 131, 143, 0.25);
}

.speed-icon {
  font-size: 20px;
  margin-right: 8px;
  color: #00acc1; /* 图标颜色稍微亮一点 */
}

.speed-value {
  font-weight: 700; /* 数字加粗 */
  font-size: 18px;
  font-family: 'Roboto Mono', Consolas, monospace; /* 可选：用等宽字体显示数字更有科技感 */
  letter-spacing: 0.5px;
}

.speed-unit {
  font-size: 13px;
  margin-left: 4px;
  opacity: 0.7; /* 单位颜色淡一点，突出数字 */
  align-self: flex-end;
  margin-bottom: 3px;
  font-weight: normal;
}
</style>