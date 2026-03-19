<script setup>
import { ref, onMounted, computed } from 'vue'
import { getGoals, getGoalTypes, createGoal, updateGoal, deleteGoal } from '@/api/goal'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Edit, Delete, Timer, Calendar, List, Warning, Money, Clock } from '@element-plus/icons-vue' // 🌟 补全图标

const list = ref([])
const typeList = ref([]) 
const loading = ref(false)
const dialogVisible = ref(false)
const isEdit = ref(false)

const projectListVisible = ref(false)
const currentGoalForProjects = ref(null)
const currentGoalProjects = ref([])

// 计算【单个项目】的完成进度
const getProjectCompletionProgress = (project) => {
  let total = 0
  let completed = 0
  project.stages?.forEach(stage => {
    stage.steps?.forEach(step => {
      if (step.task) {
        total += step.task.total_details || 0
        completed += step.task.completed_details || 0
      }
    })
  })
  return total === 0 ? 0 : Math.round((completed / total) * 100)
}

// 🌟 新增：计算整个目标的【总预算】 (累加名下所有项目的预算)
const getGoalTotalBudget = (goal) => {
  let total = 0
  if (goal.projects && goal.projects.length) {
    goal.projects.forEach(p => { total += Number(p.planned_budget) || 0 })
  }
  return parseFloat(total.toFixed(2))
}

// 🌟 新增：计算整个目标的【实际花费】
const getGoalTotalSpent = (goal) => {
  let total = 0
  if (goal.projects && goal.projects.length) {
    goal.projects.forEach(p => { total += Number(p.actual_total_cost) || 0 })
  }
  return parseFloat(total.toFixed(2))
}

// 🌟 新增：计算单个项目的所有步骤计划工时总和
const getProjectTotalPlannedHours = (project) => {
  let total = 0
  project.stages?.forEach(stage => {
    stage.steps?.forEach(step => { total += Number(step.planned_hours) || 0 })
  })
  return parseFloat(total.toFixed(1))
}

// 🌟 新增：计算整个目标的【总计划工时】
const getGoalTotalPlannedHours = (goal) => {
  let total = 0
  if (goal.projects && goal.projects.length) {
    goal.projects.forEach(p => { total += getProjectTotalPlannedHours(p) })
  }
  return parseFloat(total.toFixed(1))
}

// 🌟 新增：计算整个目标的【实际耗时】
const getGoalTotalActualHours = (goal) => {
  let total = 0
  if (goal.projects && goal.projects.length) {
    goal.projects.forEach(p => { total += Number(p.actual_total_hours) || 0 })
  }
  return parseFloat(total.toFixed(1))
}

const handleViewProjects = (row) => {
  currentGoalForProjects.value = row
  currentGoalProjects.value = row.projects || []
  projectListVisible.value = true
}

const isGoalDelayedWithoutProjects = (goal) => {
  if (goal.projects && goal.projects.length > 0) return false
  if (!goal.start_date) return false
  const today = new Date(); today.setHours(0, 0, 0, 0)
  const startDate = new Date(goal.start_date); startDate.setHours(0, 0, 0, 0)
  return today > startDate
}

const defaultForm = { goal_type_id: '', name: '', content: '', start_date: '', end_date: '', progress: 0, status: 'pending' }
const form = ref({ ...defaultForm })

const statusMap = {
  pending: { label: '未开始', type: 'info' },
  in_progress: { label: '进行中', type: 'primary' },
  completed: { label: '已完成', type: 'success' }
}

const loadData = async () => {
  loading.value = true
  try {
    const [goalsRes, typesRes] = await Promise.all([getGoals(), getGoalTypes()])
    list.value = goalsRes
    typeList.value = typesRes 
  } catch (error) {
    console.error('加载数据失败', error)
  } finally {
    loading.value = false
  }
}

const handleAdd = () => {
  isEdit.value = false
  form.value = { ...defaultForm }
  const today = new Date().toISOString().split('T')[0]
  form.value.start_date = today
  dialogVisible.value = true
}

const handleEdit = (row) => {
  isEdit.value = true
  form.value = { ...row }
  dialogVisible.value = true
}

const handleSubmit = async () => {
  if(!form.value.goal_type_id) return ElMessage.warning('请选择目标类型')
  if(!form.value.name) return ElMessage.warning('请填写目标名称')
  if(!form.value.start_date || !form.value.end_date) return ElMessage.warning('请设置完整的起止时间')

  try {
    if (isEdit.value) { await updateGoal(form.value.id, form.value); ElMessage.success('更新成功') } 
    else { await createGoal(form.value); ElMessage.success('创建成功') }
    dialogVisible.value = false
    loadData() 
  } catch (e) {}
}

const handleDelete = (row) => {
  ElMessageBox.confirm(`确定要放弃目标 "${row.name}" 吗？`, '警告', { confirmButtonText: '确定删除', cancelButtonText: '取消', type: 'warning' })
  .then(async () => { await deleteGoal(row.id); ElMessage.success('已删除'); loadData() })
}

const customColors = [
  { color: '#f56c6c', percentage: 20 }, 
  { color: '#e6a23c', percentage: 40 }, 
  { color: '#5cb87a', percentage: 60 }, 
  { color: '#1989fa', percentage: 80 }, 
  { color: '#6f7ad3', percentage: 100 },
]

const getCompletionProgress = (goal) => {
  let total = 0
  let completed = 0
  if (goal.projects && goal.projects.length) {
    goal.projects.forEach(project => {
      project.stages?.forEach(stage => {
        stage.steps?.forEach(step => {
          if (step.task) {
            total += step.task.total_details || 0
            completed += step.task.completed_details || 0
          }
        })
      })
    })
  }
  return total === 0 ? 0 : Math.round((completed / total) * 100)
}

onMounted(loadData)
</script>

<template>
  <div class="page-container">
    <div class="header-actions">
      <el-button type="primary" :icon="Plus" size="large" @click="handleAdd">
        制定新目标
      </el-button>
    </div>

    <el-table v-loading="loading" :data="list" style="width: 100%" border stripe>
      <el-table-column label="领域" width="80" align="center">
        <template #default="{ row }">
          <el-tag v-if="row.goal_type" :color="row.goal_type.color" effect="dark" style="border:none; color: #fff; font-weight: 600;">{{ row.goal_type.title }}</el-tag>
          <el-tag v-else type="info">未知</el-tag>
        </template>
      </el-table-column>

      <el-table-column label="目标详情" min-width="200">
        <template #default="{ row }">
          <div style="display: flex; align-items: center; gap: 10px;">
            <div style="font-weight:bold; font-size:15px; color:#303133">{{ row.name }}</div>
            <el-tooltip v-if="isGoalDelayedWithoutProjects(row)" content="目标已到期开始，但尚未创建任何关联项目来执行！" placement="top">
              <el-tag type="danger" effect="dark" round style="cursor: pointer; height: auto; padding: 4px 10px;">
                <div style="display: flex; align-items: center; font-size: 13px; line-height: 1.5;">
                  <el-icon style="margin-right: 4px; font-size: 15px;"><Warning /></el-icon>
                  <span>缺项目规划</span>
                </div>
              </el-tag>
            </el-tooltip>
          </div>
          <div style="font-size:12px; color:#909399; margin-top:5px">{{ row.content || '暂无具体内容' }}</div>
        </template>
      </el-table-column>

      <el-table-column label="执行周期" width="190">
        <template #default="{ row }">
          <div class="date-range">
            <el-icon><Calendar /></el-icon>
            <span>{{ row.start_date }}</span>
            <span class="separator">至</span>
            <span>{{ row.end_date }}</span>
          </div>
        </template>
      </el-table-column>

      <el-table-column label="资源投入大盘 (资金 / 工时)" min-width="260">
        <template #default="{ row }">
           <div style="display: flex; flex-direction: column; gap: 8px;">
              <div style="display: flex; align-items: center; justify-content: space-between; font-size: 12px; font-family: Consolas;">
                 <span style="color: #67C23A;"><el-icon style="vertical-align: -2px;"><Money /></el-icon> 预算: ￥{{ getGoalTotalBudget(row) }}</span>
                 <span style="color: #F56C6C;">已花: ￥{{ getGoalTotalSpent(row) }}</span>
              </div>
              <el-progress 
                 :percentage="Math.min((getGoalTotalSpent(row) / (getGoalTotalBudget(row) || 1)) * 100, 100) || 0" 
                 :status="getGoalTotalSpent(row) > getGoalTotalBudget(row) ? 'exception' : 'success'"
                 :stroke-width="6" 
                 :show-text="false" 
              />
              
              <div style="display: flex; align-items: center; justify-content: space-between; font-size: 12px; font-family: Consolas; margin-top: 4px;">
                 <span style="color: #409EFF;"><el-icon style="vertical-align: -2px;"><Clock /></el-icon> 计划: {{ getGoalTotalPlannedHours(row) }} h</span>
                 <span style="color: #E6A23C;">耗时: {{ getGoalTotalActualHours(row) }} h</span>
              </div>
           </div>
        </template>
      </el-table-column>

      <el-table-column label="计划进度" width="130" align="center">
        <template #default="{ row }">
          <div style="display: flex; flex-direction: column; gap: 5px;">
             <el-progress :percentage="row.projects_sum_goal_weight || 0" :stroke-width="8" color="#e6a23c" />
             <span style="font-size: 12px; color: #909399;">项目额度</span>
          </div>
        </template>
      </el-table-column>

      <el-table-column label="完成进度" width="130" align="center">
        <template #default="{ row }">
          <div style="display: flex; flex-direction: column; gap: 5px;">
             <el-progress :percentage="getCompletionProgress(row)" :stroke-width="8" :color="customColors" />
             <span style="font-size: 12px; color: #909399;">打卡率</span>
          </div>
        </template>
      </el-table-column>

      <el-table-column label="状态" width="80" align="center">
        <template #default="{ row }">
          <el-tag :type="statusMap[row.status]?.type" effect="plain">{{ statusMap[row.status]?.label }}</el-tag>
        </template>
      </el-table-column>

      <el-table-column label="操作" width="220" align="center" fixed="right">
        <template #default="{ row }">
          <el-button type="success" link :icon="List" @click="handleViewProjects(row)">项目清单</el-button>
          <el-button type="primary" link :icon="Edit" @click="handleEdit(row)">编辑</el-button>
          <el-button type="danger" link :icon="Delete" @click="handleDelete(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑目标进度' : '✨ 设立新目标'" width="550px" destroy-on-close>
      <el-form :model="form" label-width="85px" class="custom-form">
        <el-form-item label="目标领域" required>
          <el-select v-model="form.goal_type_id" placeholder="请选择生活领域" style="width: 100%">
            <el-option v-for="item in typeList" :key="item.id" :label="item.title" :value="item.id">
              <div class="type-option">
                <span :style="{ background: item.color }" class="color-dot"></span>
                <span>{{ item.title }}</span>
              </div>
            </el-option>
          </el-select>
        </el-form-item>

        <el-form-item label="目标名称" required>
          <el-input v-model="form.name" placeholder="例如：每周去健身房3次 / 读完5本书" maxlength="50" show-word-limit />
        </el-form-item>

        <el-form-item label="具体内容">
          <el-input v-model="form.content" type="textarea" rows="4" placeholder="详细描述执行计划、奖励机制或具体步骤..." />
        </el-form-item>

        <el-form-item label="起止时间" required>
          <div class="date-inputs">
            <el-date-picker v-model="form.start_date" type="date" placeholder="开始日期" value-format="YYYY-MM-DD" style="width: 48%" />
            <span class="date-split">-</span>
            <el-date-picker v-model="form.end_date" type="date" placeholder="结束日期" value-format="YYYY-MM-DD" style="width: 48%" />
          </div>
        </el-form-item>
      </el-form>

      <template #footer>
        <span class="dialog-footer">
          <el-button @click="dialogVisible = false">取消</el-button>
          <el-button type="primary" @click="handleSubmit" :loading="loading">{{ isEdit ? '更新进度' : '立即创建' }}</el-button>
        </span>
      </template>
    </el-dialog>

    <el-dialog v-model="projectListVisible" :title="`【${currentGoalForProjects?.name}】下的项目清单`" width="850px">
      <el-table :data="currentGoalProjects" border stripe height="400px">
        <el-table-column prop="name" label="项目名称" min-width="150" fixed>
           <template #default="{ row }">
              <div style="font-weight: bold; color: #303133;">{{ row.name }}</div>
              <div style="font-size: 11px; color: #E6A23C; margin-top: 4px; font-family: Consolas;">
                 <el-icon style="vertical-align: -1px;"><Calendar /></el-icon>
                 {{ row.start_date || '未定' }} ~ {{ row.end_date || '未定' }}
              </div>
           </template>
        </el-table-column>
        
        <el-table-column label="占用目标额度" width="110" align="center">
          <template #default="{ row }">
            <el-tag size="small" type="warning" effect="plain">占 {{ row.goal_weight }}%</el-tag>
          </template>
        </el-table-column>
        
        <el-table-column label="项目实际打卡进度" width="160" align="center">
          <template #default="{ row }">
            <el-progress :percentage="getProjectCompletionProgress(row)" :stroke-width="8" :color="customColors" />
          </template>
        </el-table-column>
        
        <el-table-column prop="expected_result" label="预期结果" min-width="180" show-overflow-tooltip />
      </el-table>
      
      <template #footer>
        <el-button @click="projectListVisible = false">关闭</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
.page-container { padding: 20px; background: #fff; min-height: calc(100vh - 84px); border-radius: 8px; }
.header-actions { margin-bottom: 20px; }
.goal-title { font-weight: 600; font-size: 15px; color: #303133; }
.goal-content { font-size: 12px; color: #909399; margin-top: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.date-range { display: flex; align-items: center; color: #606266; font-size: 13px; }
.date-range .el-icon { margin-right: 5px; color: #909399; }
.separator { margin: 0 8px; color: #C0C4CC; }
.type-option { display: flex; align-items: center; }
.color-dot { width: 10px; height: 10px; border-radius: 50%; margin-right: 8px; display: inline-block; }
.date-inputs { display: flex; justify-content: space-between; align-items: center; width: 100%; }
.slider-block { width: 100%; padding-right: 10px; }
</style>