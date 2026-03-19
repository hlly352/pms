<script setup>
import { ref, onMounted, computed, reactive } from 'vue'
import { 
  getTasks, createTask, updateTask, deleteTask, 
  getRules, generateTasks, getTaskDetails,
  updateTaskDetailStatus, updateTaskDetailRemark
} from '@/api/task'
import { ElMessage, ElMessageBox } from 'element-plus'
import { 
  Plus, Delete, Edit, AlarmClock, Refresh, List, Search, Remove 
} from '@element-plus/icons-vue'

// ==========================================
// 1. 状态定义
// ==========================================

const list = ref([])
const loading = ref(false)
const dialogVisible = ref(false)
const isEdit = ref(false)
const formLoading = ref(false)

// 🌟 新增：分页状态
const pagination = reactive({
  current: 1,
  size: 10,
  total: 0
})

// 规则列表
const taskRules = ref([])

// 详情弹窗相关
const detailVisible = ref(false)
const detailList = ref([])
const detailLoading = ref(false)
const currentTaskName = ref('')

// 搜索表单数据
const searchForm = reactive({
  name: '',
  content: '',
  frequency: '',
  status: '',          // 任务开启状态
  completion_status: '', // 完成状态 (not_started, in_progress, completed)
  date_range: [] 
})

// 字典配置
const frequencyMap = {
  once: { label: '一次性', type: 'info' },
  repeat: { label: '重复规则', type: 'primary' },
  weekly: { label: '每周', type: 'success' },
  monthly: { label: '每月', type: 'warning' },
  yearly: { label: '每年', type: 'danger' }
}

const weekOptions = [
  { label: '周一', value: 'Mon' }, { label: '周二', value: 'Tue' },
  { label: '周三', value: 'Wed' }, { label: '周四', value: 'Thu' },
  { label: '周五', value: 'Fri' }, { label: '周六', value: 'Sat' },
  { label: '周日', value: 'Sun' }
]

const monthDayOptions = Array.from({ length: 30 }, (_, i) => i + 1)

// ==========================================
// 2. 辅助函数
// ==========================================

const getCurrentTime = () => {
  const now = new Date()
  return `${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}`
}

const getCurrentDate = () => {
  const now = new Date()
  return `${now.getFullYear()}-${(now.getMonth()+1).toString().padStart(2, '0')}-${now.getDate().toString().padStart(2, '0')}`
}

const getCurrentMonthDay = () => {
  const now = new Date()
  return `${(now.getMonth()+1).toString().padStart(2, '0')}-${now.getDate().toString().padStart(2, '0')}`
}

const getNewForm = () => ({
  name: '',
  content: '',
  frequency: 'once',
  rule_id: null,
  reminder_time: getCurrentTime(),
  execution_config: {
    date: getCurrentDate(), 
    days: [], 
    year_dates: [{ date: getCurrentMonthDay(), is_lunar: false }] 
  }
})

const form = ref(getNewForm())

// ==========================================
// 3. 逻辑处理
// ==========================================

// 加载数据
const loadData = async () => {
  loading.value = true
  try {
    const params = {
      page: pagination.current,       // 🌟 传递当前页码
      per_page: pagination.size,      // 🌟 传递每页条数
      name: searchForm.name,
      content: searchForm.content,
      frequency: searchForm.frequency,
      status: searchForm.status,
      completion_status: searchForm.completion_status,
    }
    if (searchForm.date_range && searchForm.date_range.length === 2) {
      params.date_start = searchForm.date_range[0]
      params.date_end = searchForm.date_range[1]
    }

    const [taskRes, ruleRes] = await Promise.all([
      getTasks(params),
      getRules({ module: 'task' })
    ])
    
    // 🌟 兼容 Laravel 的分页返回结构解析
    list.value = taskRes.data?.data || taskRes.data || taskRes
    pagination.total = taskRes.data?.total || taskRes.total || 0
    
    taskRules.value = ruleRes.data?.data || ruleRes.data || ruleRes
  } finally {
    loading.value = false
  }
}

// 🌟 新增：触发搜索时，将页码重置为第一页
const handleSearch = () => {
  pagination.current = 1
  loadData()
}

// 重置搜索
const handleResetSearch = () => {
  searchForm.name = ''
  searchForm.content = ''
  searchForm.frequency = ''
  searchForm.status = ''
  searchForm.completion_status = ''
  searchForm.date_range = []
  handleSearch() // 🌟 走统一的搜索逻辑(重置页码)
}

// 🌟 新增：分页事件处理
const handleSizeChange = (val) => {
  pagination.size = val
  pagination.current = 1 // 改变每页大小时，默认回到第一页
  loadData()
}

const handleCurrentChange = (val) => {
  pagination.current = val
  loadData()
}

onMounted(loadData)

const selectedRule = computed(() => {
  if (!form.value.rule_id) return null
  return taskRules.value.find(r => r.id === form.value.rule_id)
})

// 解析规则详情文字
const getRuleDetailText = (rule) => {
  if (!rule || !rule.details) return '暂无详情'
  
  let details = rule.details
  if (typeof details === 'string') {
    try { details = JSON.parse(details) } catch (e) { details = {} }
  }

  if (rule.type === 'fixed' || rule.type === '固定') {
    let val = details.intervals || (details.items ? details.items.map(i => i.value) : null)
    if (Array.isArray(val)) return val.join('；')
    if (typeof val === 'string') return val.replace(/,/g, '；')
    return '无具体条目'
  } else {
    const daysArr = details.days || details.repeat_days || []
    let daysText = '每天'
    if (daysArr.length > 0 && daysArr.length < 7) {
       daysText = daysArr.join('、') 
    }
    const mins = details.minutes || details.duration || 0
    return mins ? `${daysText} (${mins}分钟)` : daysText
  }
}

// 格式化执行时间列
const formatExecution = (row) => {
  const conf = row.execution_config || {}
  switch (row.frequency) {
    case 'once': 
      return `日期: ${conf.date}`
    case 'repeat': 
      return row.rule ? `规则: ${row.rule.name} [${getRuleDetailText(row.rule)}]` : '规则失效'
    case 'weekly': 
      return `每周: ${conf.days?.join('、')}`
    case 'monthly': 
      return `每月: ${conf.days?.join('号、')}号`
    case 'yearly': 
      return conf.year_dates?.map(d => 
        `${d.date} (${d.is_lunar ? '农历' : '公历'})`
      ).join('; ')
    default: 
      return '-'
  }
}

// ==========================================
// 4. 交互操作
// ==========================================

const handleAutoGenerate = async () => {
  try {
    const res = await generateTasks()
    ElMessage.success(res.message || '生成完成')
    loadData()
  } catch (e) { console.error(e) }
}

const formatDateTime = (timeStr) => {
  if (!timeStr) return '-'
  const date = new Date(timeStr)
  const Y = date.getFullYear()
  const M = (date.getMonth() + 1).toString().padStart(2, '0')
  const D = date.getDate().toString().padStart(2, '0')
  const h = date.getHours().toString().padStart(2, '0')
  const m = date.getMinutes().toString().padStart(2, '0')
  return `${Y}-${M}-${D} ${h}:${m}`
}

const handleViewDetails = async (row) => {
  currentTaskName.value = row.name
  detailVisible.value = true
  detailLoading.value = true
  try {
    detailList.value = await getTaskDetails(row.id)
  } finally {
    detailLoading.value = false
  }
}

const handleToggleDetailStatus = async (row) => {
  const newStatus = row.status === 'completed' ? 'pending' : 'completed'
  const newText = newStatus === 'completed' ? '已完成' : '待办'
  try {
    const oldStatus = row.status
    row.status = newStatus
    row.finished_at = newStatus === 'completed' ? formatDateTime(new Date().toISOString()) : null
    await updateTaskDetailStatus(row.id, newStatus)
    ElMessage.success(`状态已更新为：${newText}`)
  } catch (e) {
    row.status = newStatus === 'completed' ? 'pending' : 'completed'
    console.error(e)
  }
}

const handleEditRemark = (row) => {
  row.isEditing = true
  setTimeout(() => {
    const input = document.getElementById(`remark-input-${row.id}`)
    if(input) input.focus()
  }, 100)
}

const handleSaveRemark = async (row) => {
  if (!row.isEditing) return
  row.isEditing = false
  try {
    await updateTaskDetailRemark(row.id, row.remark)
    ElMessage.success('备注已更新')
  } catch (e) {
    console.error(e)
    ElMessage.error('更新失败')
  }
}

const handleAdd = () => {
  isEdit.value = false
  form.value = getNewForm()
  dialogVisible.value = true
}

const handleEdit = (row) => {
  isEdit.value = true
  const data = JSON.parse(JSON.stringify(row))
  if (!data.execution_config) data.execution_config = {}
  if (!data.execution_config.year_dates) data.execution_config.year_dates = [{ date: '', is_lunar: false }]
  if (data.reminder_time && data.reminder_time.length > 5) {
    data.reminder_time = data.reminder_time.substring(0, 5)
  }
  form.value = data
  dialogVisible.value = true
}

const handleSubmit = async () => {
  if (!form.value.name) return ElMessage.warning('请输入任务名称')
  if (form.value.frequency === 'repeat' && !form.value.rule_id) return ElMessage.warning('请选择重复规则')
  if (form.value.frequency === 'once' && !form.value.execution_config.date) return ElMessage.warning('请选择执行日期')

  formLoading.value = true
  try {
    if (isEdit.value) {
      await updateTask(form.value.id, form.value)
      ElMessage.success('更新成功')
    } else {
      await createTask(form.value)
      ElMessage.success('创建成功')
    }
    dialogVisible.value = false
    loadData()
  } catch (e) { console.error(e) } 
  finally { formLoading.value = false }
}

const handleDelete = (row) => {
  ElMessageBox.confirm(
    `确定要删除任务 "${row.name}" 吗？\n\n警告：删除后，该任务下所有已生成的【执行详情】和【历史记录】都将一并被永久删除，无法恢复！`, 
    '高风险操作警告', { confirmButtonText: '确定删除', cancelButtonText: '我再想想', type: 'warning', distinguishCancelAndClose: true }
  ).then(async () => {
    try {
      await deleteTask(row.id)
      ElMessage.success('删除成功')
      loadData()
    } catch (e) {}
  }).catch(() => {})
}

const handleStatusChange = async (row) => {
  const originalStatus = !row.status 
  const originalDeadline = row.generate_deadline 
  const actionText = row.status ? '启用' : '禁用'
  const payload = { status: row.status }
  
  if (row.status === true) {
    const today = getCurrentDate() 
    if (!row.generate_deadline || row.generate_deadline < today) {
       row.generate_deadline = today
       payload.generate_deadline = today
       ElMessage.info('检测到生成截止日期已过期，已自动重置为今天')
    }
  }
  try {
    await updateTask(row.id, payload)
    ElMessage.success(`任务已${actionText}`)
  } catch (e) {
    row.status = originalStatus
    row.generate_deadline = originalDeadline
    console.error(e)
  }
}

const addYearDate = () => {
  form.value.execution_config.year_dates.push({ date: getCurrentMonthDay(), is_lunar: false })
}
const removeYearDate = (index) => {
  form.value.execution_config.year_dates.splice(index, 1)
}
</script>

<template>
  <div class="page-container">
    
    <el-card shadow="never" class="search-card">
      <el-form :model="searchForm" inline size="default" class="search-form" @keyup.enter="handleSearch">
        <el-form-item label="名称">
          <el-input v-model="searchForm.name" placeholder="任务名称" clearable style="width: 150px" />
        </el-form-item>
        <el-form-item label="内容">
          <el-input v-model="searchForm.content" placeholder="任务内容" clearable style="width: 150px" />
        </el-form-item>
        <el-form-item label="频率">
          <el-select v-model="searchForm.frequency" placeholder="全部" clearable style="width: 120px" @change="handleSearch">
            <el-option v-for="(val, key) in frequencyMap" :key="key" :label="val.label" :value="key" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="全部" clearable style="width: 100px" @change="handleSearch">
            <el-option label="开启" value="true" />
            <el-option label="关闭" value="false" />
          </el-select>
        </el-form-item>

        <el-form-item label="完成状态">
          <el-select v-model="searchForm.completion_status" placeholder="全部" clearable style="width: 100px" @change="handleSearch">
            <el-option label="未开始" value="not_started" />
            <el-option label="进行中" value="in_progress" />
            <el-option label="完成" value="completed" />
          </el-select>
        </el-form-item>

        <el-form-item label="添加日期">
          <el-date-picker
            v-model="searchForm.date_range"
            type="daterange"
            range-separator="至"
            start-placeholder="开始"
            end-placeholder="结束"
            value-format="YYYY-MM-DD"
            style="width: 240px"
            @change="handleSearch"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
          <el-button :icon="Remove" @click="handleResetSearch">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <div class="header-actions">
      <el-button type="primary" :icon="Plus" @click="handleAdd">新建任务</el-button>
      <el-button type="success" :icon="Refresh" @click="handleAutoGenerate" plain>
        自动生成未来任务
      </el-button>
    </div>

    <el-table v-loading="loading" :data="list" border stripe>
      <el-table-column prop="name" label="任务名称" min-width="120" show-overflow-tooltip fixed="left" />
      
      <el-table-column prop="content" label="任务内容" min-width="150" show-overflow-tooltip>
        <template #default="{ row }">
          <span style="color: #606266">{{ row.content || '-' }}</span>
        </template>
      </el-table-column>
      <el-table-column prop="source" label="任务来源" width="100" align="center">
        <template #default="{ row }">
          <el-tag 
            v-if="row.source === 'project'" 
            type="warning" 
            effect="plain" 
            size="small"
          >
            项目实施
          </el-tag>
          <el-tag 
            v-else-if="row.source === 'recitation'" 
            type="success" 
            effect="plain" 
            size="small"
          >
            背诵管理
          </el-tag>
          <el-tag 
            v-else-if="row.source === 'reading'" 
            type="normal" 
            effect="plain" 
            size="small"
          >
            阅读管理
          </el-tag>
          <el-tag 
            v-else 
            type="info" 
            effect="plain" 
            size="small"
          >
            手动创建
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="结算账户" width="130" align="center">
        <template #default="{ row }">
          <div 
            v-if="row.time_account" 
            style="background: #f3f0ff; color: #7b61ff; border: 1px solid #e5dfff; border-radius: 4px; padding: 2px 8px; font-size: 12px; display: inline-flex; align-items: center; white-space: nowrap;"
            title="打卡完成时将扣除此账户的时间"
          >
            <el-icon style="margin-right: 4px;"><Timer /></el-icon>
            {{ row.time_account.name }}
          </div>
          <div 
            v-else-if="row.project_stage_step?.stage?.project?.time_account" 
            style="background: #f3f0ff; color: #7b61ff; border: 1px solid #e5dfff; border-radius: 4px; padding: 2px 8px; font-size: 12px; display: inline-flex; align-items: center; white-space: nowrap;"
          >
            <el-icon style="margin-right: 4px;"><Timer /></el-icon>
            {{ row.project_stage_step.stage.project.time_account.name }}
          </div>
          <span v-else style="color: #c0c4cc; font-size: 12px;">未绑定</span>
        </template>
      </el-table-column>
      <el-table-column label="生成状态" width="160">
        <template #default="{ row }">
           <div style="font-size:12px; color:#666; line-height: 1.5;">
              <div>截止: {{ row.generate_deadline || (row.execution_config?.end_date ? row.execution_config.end_date : '未生成') }}</div>
              <div>上次: {{ row.last_generated_at ? new Date(row.last_generated_at).toLocaleString() : '-' }}</div>
           </div>
        </template>
      </el-table-column>

      <el-table-column prop="frequency" label="频率" width="80" align="center">
        <template #default="{ row }">
          <el-tag :type="frequencyMap[row.frequency]?.type" effect="plain" size="small">
            {{ frequencyMap[row.frequency]?.label }}
          </el-tag>
        </template>
      </el-table-column>

      <el-table-column label="执行时间" min-width="220" show-overflow-tooltip>
        <template #default="{ row }">
          <span style="font-size:13px; color:#606266">{{ formatExecution(row) }}</span>
        </template>
      </el-table-column>

      <el-table-column prop="reminder_time" label="提醒" width="70" align="center">
        <template #default="{ row }">
          {{ row.reminder_time ? row.reminder_time.substring(0, 5) : '-' }}
        </template>
      </el-table-column>

      <el-table-column label="状态" width="70" align="center">
        <template #default="{ row }">
          <el-switch
            v-model="row.status"
            :active-value="true"
            :inactive-value="false"
            inline-prompt
            active-text="开"
            inactive-text="关"
            size="small"
            @change="handleStatusChange(row)"
          />
        </template>
      </el-table-column>

      <el-table-column prop="created_at" label="添加日期" width="110" align="center">
        <template #default="{ row }">
          <span style="font-size: 12px; color: #909399">
            {{ row.created_at ? row.created_at.substring(0, 10) : '-' }}
          </span>
        </template>
      </el-table-column>

      <el-table-column label="操作" width="220" align="center" fixed="right">
        <template #default="{ row }">
          <el-button type="info" link :icon="List" @click="handleViewDetails(row)">详情</el-button>
          <el-button type="primary" link :icon="Edit" @click="handleEdit(row)">编辑</el-button>
          <el-button type="danger" link :icon="Delete" @click="handleDelete(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <div class="pagination-container">
      <el-pagination
        v-model:current-page="pagination.current"
        v-model:page-size="pagination.size"
        :page-sizes="[10, 20, 50, 100]"
        background
        layout="total, sizes, prev, pager, next, jumper"
        :total="pagination.total"
        @size-change="handleSizeChange"
        @current-change="handleCurrentChange"
      />
    </div>

    <el-dialog 
      v-model="dialogVisible" 
      :title="isEdit ? '编辑任务' : '新建任务'" 
      width="650px"
      destroy-on-close
    >
      <el-form :model="form" label-width="85px">
        <el-form-item label="任务名称" required>
          <el-input v-model="form.name" placeholder="例如：缴纳房租" />
        </el-form-item>
        
        <el-form-item label="任务内容">
          <el-input v-model="form.content" type="textarea" placeholder="备注详情..." />
        </el-form-item>

        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="任务频率" required>
              <el-select v-model="form.frequency" style="width:100%">
                <el-option v-for="(val, key) in frequencyMap" :key="key" :label="val.label" :value="key" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="提醒时间">
              <el-time-picker 
                v-model="form.reminder_time" 
                format="HH:mm"
                value-format="HH:mm" 
                placeholder="00:00" 
                style="width:100%" 
              />
            </el-form-item>
          </el-col>
        </el-row>

        <div class="execution-block">
          <template v-if="form.frequency === 'once'">
             <el-form-item label="执行日期" required>
               <el-date-picker v-model="form.execution_config.date" type="date" value-format="YYYY-MM-DD" placeholder="选择日期" style="width: 100%" />
             </el-form-item>
          </template>

          <template v-else-if="form.frequency === 'repeat'">
             <el-form-item label="选择规则" required>
               <el-select v-model="form.rule_id" placeholder="请选择【任务管理】下的规则" style="width: 100%">
                 <el-option v-for="rule in taskRules" :key="rule.id" :label="rule.name" :value="rule.id" />
               </el-select>
               <div v-if="selectedRule" class="rule-preview-box">
                  <div class="rule-p-header">
                    <el-tag size="small" :type="selectedRule.type === 'fixed' ? '' : 'warning'" effect="dark" style="border:none">
                      {{ selectedRule.type === 'fixed' || selectedRule.type === '固定' ? '固定' : '循环' }}
                    </el-tag>
                    <span class="rule-p-title">{{ selectedRule.name }}</span>
                  </div>
                  <div class="rule-p-content">{{ getRuleDetailText(selectedRule) }}</div>
               </div>
               <div class="tip-text" v-if="taskRules.length === 0">暂无可用规则，请去规则管理添加。</div>
             </el-form-item>
          </template>

          <template v-else-if="form.frequency === 'weekly'">
             <el-form-item label="执行日">
                <el-checkbox-group v-model="form.execution_config.days">
                   <el-checkbox-button v-for="d in weekOptions" :key="d.value" :label="d.value">{{ d.label }}</el-checkbox-button>
                </el-checkbox-group>
             </el-form-item>
          </template>

          <template v-else-if="form.frequency === 'monthly'">
             <el-form-item label="执行日">
                <el-select v-model="form.execution_config.days" multiple collapse-tags placeholder="选择每月几号" style="width:100%">
                  <el-option v-for="d in monthDayOptions" :key="d" :label="d + ' 号'" :value="d" />
                </el-select>
             </el-form-item>
          </template>

          <template v-else-if="form.frequency === 'yearly'">
             <el-form-item label="日期列表" style="margin-bottom:0">
                <div v-for="(item, idx) in form.execution_config.year_dates" :key="idx" class="year-row">
                   <el-date-picker v-model="item.date" type="date" format="MM-DD" value-format="MM-DD" placeholder="月-日" style="width: 160px" />
                   <el-switch v-model="item.is_lunar" active-text="农历" inactive-text="公历" style="margin: 0 15px" />
                   <el-button v-if="form.execution_config.year_dates.length > 1" circle size="small" type="danger" :icon="Delete" @click="removeYearDate(idx)" />
                   <el-button v-if="idx === form.execution_config.year_dates.length - 1" circle size="small" type="primary" :icon="Plus" @click="addYearDate" />
                </div>
             </el-form-item>
          </template>
        </div>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmit" :loading="formLoading">保存</el-button>
      </template>
    </el-dialog>

    <el-dialog 
      v-model="detailVisible" 
      :title="`任务详情: ${currentTaskName}`" 
      width="850px"
    >
       <el-table :data="detailList" v-loading="detailLoading" height="400" border stripe>
          <el-table-column label="任务执行时间" min-width="160">
             <template #default="{ row }">
                <span style="font-family: Consolas, monospace; font-weight: bold; color: #409EFF">
                   {{ formatDateTime(row.task_time) }}
                </span>
             </template>
          </el-table-column>
          
          <el-table-column prop="status" label="状态" width="100" align="center">
             <template #default="{ row }">
                <el-tag 
                  :type="row.status === 'completed' ? 'success' : 'info'"
                  style="cursor: pointer; user-select: none;" 
                  @click="handleToggleDetailStatus(row)"
                  effect="dark"
                >
                   {{ row.status === 'completed' ? '已完成' : '待办' }}
                </el-tag>
             </template>
          </el-table-column>

          <el-table-column prop="finished_at" label="完成时间" width="150">
             <template #default="{ row }">
                {{ formatDateTime(row.finished_at) }}
             </template>
          </el-table-column>

          <el-table-column prop="remark" label="备注" min-width="200">
             <template #default="{ row }">
                <div v-if="row.isEditing">
                   <el-input 
                     :id="`remark-input-${row.id}`"
                     v-model="row.remark" 
                     size="small" 
                     @blur="handleSaveRemark(row)" 
                     @keyup.enter="handleSaveRemark(row)"
                     placeholder="请输入备注"
                   />
                </div>
                <div v-else @click="handleEditRemark(row)" class="remark-cell">
                   <span v-if="row.remark">{{ row.remark }}</span>
                   <span v-else style="color: #ccc; font-size: 12px;">点击添加备注...</span>
                   <el-icon class="edit-icon" style="margin-left: 5px; color: #409EFF; display: none;"><Edit /></el-icon>
                </div>
             </template>
          </el-table-column>
       </el-table>
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
.search-card {
  margin-bottom: 20px;
  border: 1px solid #ebeef5;
}
.search-form .el-form-item {
  margin-bottom: 0;
  margin-right: 15px;
}
.header-actions { margin-bottom: 20px; }
.execution-block {
  background: #f8f9fa;
  padding: 15px 15px 1px 15px;
  border-radius: 6px;
  border: 1px dashed #dcdfe6;
  margin-top: 10px;
}
.year-row { display: flex; align-items: center; margin-bottom: 10px; }
.tip-text { font-size: 12px; color: #e6a23c; margin-top: 5px; }
.rule-preview-box {
  margin-top: 10px;
  background-color: #fdfdfd;
  border: 1px solid #e4e7ed;
  border-radius: 4px;
  padding: 10px;
  width: 100%;
}
.rule-p-header {
  border-bottom: 1px dashed #ebeef5;
  padding-bottom: 5px;
  margin-bottom: 5px;
  display: flex;
  align-items: center;
}
.rule-p-title { font-weight: bold; font-size: 13px; color: #303133; margin-left: 8px; }
.rule-p-content { font-size: 12px; color: #606266; line-height: 1.6; word-break: break-all; }
.remark-cell:hover .edit-icon { display: inline-flex !important; }
.remark-cell:hover { background-color: #f5f7fa; border-radius: 4px; padding-left: 5px; transition: background-color 0.2s; }

/* 🌟 新增：分页容器样式 */
.pagination-container {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}
</style>