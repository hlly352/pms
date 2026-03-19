<script setup>
import { ref, onMounted } from 'vue'
// 引入 API (确保 src/api/rule.js 已创建)
import { getRules, createRule, updateRule, deleteRule } from '@/api/rule'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Delete, Edit, Timer, Connection } from '@element-plus/icons-vue'

// ==========================================
// 1. 状态定义
// ==========================================

const list = ref([])
const loading = ref(false)
const dialogVisible = ref(false)
const formLoading = ref(false)
const isEdit = ref(false)

// 模块字典配置 (用于下拉框和列表回显)
const moduleOptions = [
  { label: '通用规则', value: 'common' },
  { label: '任务管理', value: 'task' },
  { label: '阅读管理', value: 'read' },
  { label: '项目计划', value: 'project' },
  { label: '背诵管理', value: 'recitation' }
]

// 星期选项
const weekOptions = ['周一', '周二', '周三', '周四', '周五', '周六', '周日']

// 表单默认结构
const defaultForm = {
  id: null,
  name: '',
  type: 'fixed',    // 默认固定类型
  module: 'common', // 默认通用模块
  purpose: '',
  remark: '',
  // details 混合存储两种结构，提交时根据 type 取用
  details: {
    items: [{ value: '' }], // 固定类型用
    days: [],               // 循环类型用
    minutes: 30             // 循环类型用
  }
}

// 初始化表单 (深拷贝避免引用问题)
const form = ref(JSON.parse(JSON.stringify(defaultForm)))

// ==========================================
// 2. 数据加载与处理
// ==========================================

// 加载规则列表
const loadData = async () => {
  loading.value = true
  try {
    list.value = await getRules()
  } catch (error) {
    console.error('加载规则失败', error)
  } finally {
    loading.value = false
  }
}

onMounted(loadData)

// 列表页格式化详情显示
const formatDetails = (row) => {
  if (!row.details) return '-'
  
  if (row.type === 'fixed') {
    // 显示前3项，后面用...
    if (Array.isArray(row.details.items)) {
       const values = row.details.items.map(i => i.value).filter(v => v)
       if (values.length === 0) return '无内容'
       return values.join('; ')
    }
  } else {
    // 循环类型
    const days = Array.isArray(row.details.days) ? row.details.days.join('、') : '无日期'
    return `${days} (每 ${row.details.minutes || 0} 分钟)`
  }
  return '格式未知'
}

// ==========================================
// 3. 增删改查逻辑
// ==========================================

// 打开新增弹窗
const handleAdd = () => {
  isEdit.value = false
  form.value = JSON.parse(JSON.stringify(defaultForm))
  dialogVisible.value = true
}

// 打开编辑弹窗
const handleEdit = (row) => {
  isEdit.value = true
  const data = JSON.parse(JSON.stringify(row))
  
  // 数据补全 (防止老数据缺少字段导致报错)
  if (!data.details) data.details = {}
  if (!data.details.items) data.details.items = [{ value: '' }]
  if (!data.details.days) data.details.days = []
  if (data.details.minutes === undefined) data.details.minutes = 30
  
  form.value = data
  dialogVisible.value = true
}

// 提交表单
const handleSubmit = async () => {
  // 1. 基础校验
  if (!form.value.name) return ElMessage.warning('请输入规则名称')
  if (!form.value.module) return ElMessage.warning('请选择适用模块')

  // 2. 类型校验
  if (form.value.type === 'fixed') {
    const validItems = form.value.details.items.filter(i => i.value && i.value.trim() !== '')
    if (validItems.length === 0) return ElMessage.warning('请至少填写一项规则内容')
  } else {
    if (form.value.details.days.length === 0) return ElMessage.warning('请选择重复周期')
    if (!form.value.details.minutes || form.value.details.minutes <= 0) return ElMessage.warning('时长必须大于0')
  }

  formLoading.value = true
  try {
    // 3. 构建提交数据 (清理不必要的数据)
    const submitData = {
      ...form.value,
      details: {} // 重置 details，只填入当前类型需要的
    }

    if (form.value.type === 'fixed') {
      submitData.details = { 
        items: form.value.details.items.filter(i => i.value) // 过滤空行
      }
    } else {
      submitData.details = { 
        days: form.value.details.days, 
        minutes: form.value.details.minutes 
      }
    }

    // 4. 发送请求
    if (isEdit.value) {
      await updateRule(submitData.id, submitData)
      ElMessage.success('更新成功')
    } else {
      await createRule(submitData)
      ElMessage.success('创建成功')
    }
    
    dialogVisible.value = false
    loadData()
  } catch (e) {
    console.error(e)
  } finally {
    formLoading.value = false
  }
}

// 删除规则
const handleDelete = (row) => {
  ElMessageBox.confirm(`确定要删除规则 "${row.name}" 吗？`, '警告', {
    confirmButtonText: '删除',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(async () => {
    await deleteRule(row.id)
    ElMessage.success('删除成功')
    loadData()
  })
}

// ==========================================
// 4. 动态表单操作 (固定列表)
// ==========================================

const addItem = () => {
  form.value.details.items.push({ value: '' })
}

const removeItem = (index) => {
  form.value.details.items.splice(index, 1)
}
</script>

<template>
  <div class="page-container">
    <div class="header-actions">
      <el-button type="primary" :icon="Plus" @click="handleAdd">新增规则</el-button>
    </div>

    <el-table v-loading="loading" :data="list" border stripe>
      
      <el-table-column prop="name" label="规则名称" width="150" show-overflow-tooltip>
        <template #default="{ row }">
          <span style="font-weight: 600">{{ row.name }}</span>
        </template>
      </el-table-column>
      
      <el-table-column prop="module" label="适用模块" width="120" align="center">
        <template #default="{ row }">
          <el-tag effect="plain" type="info">
            {{ moduleOptions.find(m => m.value === row.module)?.label || row.module }}
          </el-tag>
        </template>
      </el-table-column>

      <el-table-column prop="type" label="类型" width="100" align="center">
        <template #default="{ row }">
          <el-tag :type="row.type === 'fixed' ? '' : 'warning'" effect="dark" style="border:none">
            {{ row.type === 'fixed' ? '固定' : '循环' }}
          </el-tag>
        </template>
      </el-table-column>
      
      <el-table-column prop="purpose" label="用处" width="150" show-overflow-tooltip />
      
      <el-table-column label="规则详情" min-width="250" show-overflow-tooltip>
        <template #default="{ row }">
          <span style="font-size: 13px; color: #606266;">{{ formatDetails(row) }}</span>
        </template>
      </el-table-column>
      
      <el-table-column prop="remark" label="备注" show-overflow-tooltip />
      
      <el-table-column label="操作" width="150" align="center" fixed="right">
        <template #default="{ row }">
          <el-button type="primary" link :icon="Edit" @click="handleEdit(row)">编辑</el-button>
          <el-button type="danger" link :icon="Delete" @click="handleDelete(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog 
      v-model="dialogVisible" 
      :title="isEdit ? '编辑规则' : '新增规则'" 
      width="600px"
      destroy-on-close
    >
      <el-form :model="form" label-width="85px" class="rule-form">
        
        <el-form-item label="规则名称" required>
          <el-input v-model="form.name" placeholder="例如：每日背单词" />
        </el-form-item>

        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="适用模块" required>
              <el-select v-model="form.module" placeholder="请选择" style="width: 100%">
                <el-option 
                  v-for="item in moduleOptions" 
                  :key="item.value" 
                  :label="item.label" 
                  :value="item.value" 
                />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="规则类型" required>
              <el-radio-group v-model="form.type">
                <el-radio-button label="fixed">固定</el-radio-button>
                <el-radio-button label="loop">循环</el-radio-button>
              </el-radio-group>
            </el-form-item>
          </el-col>
        </el-row>

        <el-form-item label="规则用处">
          <el-input v-model="form.purpose" placeholder="简述该规则用于什么场景" />
        </el-form-item>

        <template v-if="form.type === 'fixed'">
          <div class="detail-block">
             <div class="detail-header">
               <span>规则条目列表</span>
               <span class="detail-tip">（适合清单、步骤等一次性规则）</span>
             </div>
             <div class="fixed-list">
               <div 
                 v-for="(item, index) in form.details.items" 
                 :key="index" 
                 class="fixed-row"
               >
                  <span class="row-index">{{ index + 1 }}.</span>
                  <el-input v-model="item.value" placeholder="请输入具体规则内容" />
                  <div class="row-actions">
                     <el-button 
                       v-if="index === form.details.items.length - 1" 
                       circle size="small" type="primary" plain :icon="Plus" 
                       @click="addItem" 
                     />
                     <el-button 
                       v-if="form.details.items.length > 1" 
                       circle size="small" type="danger" plain :icon="Delete" 
                       @click="removeItem(index)" 
                     />
                  </div>
               </div>
             </div>
          </div>
        </template>

        <template v-else>
          <div class="detail-block loop-block">
             <div class="detail-header">
               <span>循环设置</span>
               <span class="detail-tip">（适合番茄钟、每日打卡等周期规则）</span>
             </div>
             
             <el-form-item label="重复周期" label-width="80px" style="margin-bottom: 15px;">
                <el-checkbox-group v-model="form.details.days">
                   <el-checkbox-button v-for="day in weekOptions" :key="day" :label="day">
                     {{ day }}
                   </el-checkbox-button>
                </el-checkbox-group>
             </el-form-item>
             
             <el-form-item label="单次时长" label-width="80px">
                <el-input-number v-model="form.details.minutes" :min="1" :step="5" controls-position="right" />
                <span class="unit-text">分钟</span>
             </el-form-item>
          </div>
        </template>

        <el-form-item label="备注说明">
          <el-input v-model="form.remark" type="textarea" rows="2" />
        </el-form-item>

      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmit" :loading="formLoading">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
.page-container {
  padding: 20px;
  background: #fff;
  border-radius: 8px;
  min-height: calc(100vh - 84px);
}
.header-actions {
  margin-bottom: 20px;
}

/* 详情块容器 */
.detail-block {
  background: #f8f9fa;
  padding: 15px;
  border-radius: 6px;
  border: 1px dashed #dcdfe6;
  margin-bottom: 18px;
}
.detail-header {
  font-size: 14px;
  font-weight: bold;
  color: #303133;
  margin-bottom: 12px;
  display: flex;
  align-items: center;
}
.detail-tip {
  font-weight: normal;
  font-size: 12px;
  color: #909399;
  margin-left: 5px;
}

/* 固定类型行样式 */
.fixed-row {
  display: flex;
  align-items: center;
  margin-bottom: 10px;
}
.fixed-row:last-child {
  margin-bottom: 0;
}
.row-index {
  width: 25px;
  text-align: right;
  margin-right: 8px;
  font-weight: bold;
  color: #909399;
  font-size: 14px;
}
.row-actions {
  margin-left: 10px;
  display: flex;
  width: 70px;
  gap: 5px;
}

/* 循环类型样式 */
.loop-block .el-form-item {
  margin-bottom: 0; /* 内部 item 去掉默认 margin，由外部控制 */
}
.unit-text {
  margin-left: 10px;
  color: #606266;
}
</style>