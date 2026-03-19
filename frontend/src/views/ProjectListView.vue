<script setup>
import { ref, reactive, onMounted, computed, watch, nextTick } from 'vue'
import { 
  getProjects, deleteProject, createProject, updateProject,
  getStageSteps, createStageStep, deleteStageStep 
} from '@/api/project'
import { getGoals, getGoalTypes } from '@/api/goal'
import { updateTaskDetailStatus, updateTaskDetailRemark } from '@/api/task'
import request from '@/utils/request' 
import { getTimeAccounts } from '@/api/time_account'
// 🌟 1. 新增：引入获取财务账户的接口 (请确保 @/api/account 路径和方法名正确)
import { getAccounts } from '@/api/account' 
import { ElMessage, ElMessageBox } from 'element-plus'
import { 
  Plus, Delete, Edit, Calendar, List, Timer, View, DataLine, Search, Refresh, Warning, Money, Clock, Collection, Wallet
} from '@element-plus/icons-vue'

// ==========================================
// 1. 状态定义
// ==========================================
const list = ref([])
const loading = ref(false)
const typeList = ref([])   
const allGoals = ref([])   
const filteredGoals = ref([]) 
const timeAccounts = ref([])
// 🌟 2. 新增：存储财务账户列表
const financialAccounts = ref([])

const queryParams = reactive({ 
  name: '', 
  type_id: '', 
  goal_id: '', 
  dateRange: [] // 🌟 新增：日期范围数组
})
const pagination = reactive({ current: 1, size: 10, total: 0 })

const searchFilteredGoals = computed(() => {
  if (!queryParams.type_id) return allGoals.value
  return allGoals.value.filter(g => g.goal_type_id === queryParams.type_id)
})

const dialogVisible = ref(false)
const formLoading = ref(false)
const isEdit = ref(false) 
const originalProjectWeight = ref(0)
const originalGoalId = ref(null)

const stepDialogVisible = ref(false)
const stepLoading = ref(false)
const currentStage = ref({}) 
const currentProject = ref({})
const stepList = ref([]) 
const weekOptions = ['周一', '周二', '周三', '周四', '周五', '周六', '周日']

const stepDetailVisible = ref(false)
const stepDetailLoading = ref(false)
const stepDetailList = ref([])
const currentStepName = ref('')

const customColors = [
  { color: '#f56c6c', percentage: 20 }, 
  { color: '#e6a23c', percentage: 40 }, 
  { color: '#5cb87a', percentage: 60 }, 
  { color: '#1989fa', percentage: 80 }, 
  { color: '#6f7ad3', percentage: 100 },
]

const ganttVisible = ref(false)
const currentProjectForGantt = ref(null)
const ganttData = ref({ projStart: '', projEnd: '', totalDays: 0, stages: [] })

// ==========================================
// 2. 表单与额度计算逻辑
// ==========================================
const getNewForm = () => ({
  id: null, type_id: '', goal_id: '', name: '', expected_result: '', remark: '', 
  goal_weight: 1, 
  time_account_id: null,
  account_id: null, // 🌟 3. 新增：绑定财务账户字段
  planned_budget: undefined, 
  start_date: '', 
  end_date: '',   
  stages: [{ name: '', segments: [{ weight: '', start_date: '', end_date: '' }] }]
})
const form = ref(getNewForm())

const getNewStepForm = () => {
  const now = new Date()
  const hours = String(now.getHours()).padStart(2, '0')
  const minutes = String(now.getMinutes()).padStart(2, '0')
  
  return {
    name: '', 
    description: '', 
    start_date: '', 
    end_date: '', 
    frequency: [], 
    reminder_time: `${hours}:${minutes}`, 
    weight: '',
    planned_hours: undefined, 
    planned_cost: undefined   
  }
}

const getProjectTotalHours = (project) => {
  let total = 0
  if (project.stages && project.stages.length) {
    project.stages.forEach(stage => {
      if (stage.steps && stage.steps.length) {
        stage.steps.forEach(step => {
          total += Number(step.planned_hours) || 0
        })
      }
    })
  }
  return total > 0 ? parseFloat(total.toFixed(1)) : 0
}

const getProjectTotalCost = (project) => {
  let total = 0
  if (project.stages && project.stages.length) {
    project.stages.forEach(stage => {
      if (stage.steps && stage.steps.length) {
        stage.steps.forEach(step => {
          total += Number(step.planned_cost) || 0
        })
      }
    })
  }
  return total > 0 ? parseFloat(total.toFixed(2)) : 0
}
const stepForm = ref(getNewStepForm())

const getRemainingWeight = (g) => {
  let used = g.projects_sum_goal_weight || 0;
  if (isEdit.value && originalGoalId.value === g.id) used -= originalProjectWeight.value;
  return 100 - used;
}

const maxGoalWeight = computed(() => {
  const g = allGoals.value.find(item => item.id === form.value.goal_id)
  return g ? getRemainingWeight(g) : 100
})

const currentGoal = computed(() => allGoals.value.find(g => g.id === form.value.goal_id) || {})

const siblingProjectsTimelines = computed(() => {
  if (!currentGoal.value || !currentGoal.value.id) return [];
  
  let siblings = [];
  if (currentGoal.value.projects && Array.isArray(currentGoal.value.projects)) {
    siblings = currentGoal.value.projects;
  } else {
    siblings = list.value.filter(p => p.goal_id === currentGoal.value.id);
  }

  return siblings
    .filter(p => p.id !== form.value.id)
    .map(p => ({
      name: p.name,
      start: p.start_date || '未定',
      end: p.end_date || '未定'
    }));
});

const currentStageTotalCost = computed(() => {
  return stepList.value.reduce((sum, step) => sum + (Number(step.planned_cost) || 0), 0)
})
const currentStageTotalHours = computed(() => {
  return stepList.value.reduce((sum, step) => sum + (Number(step.planned_hours) || 0), 0)
})

const projectTotalAllocatedCost = computed(() => {
  let total = 0;
  if (currentProject.value?.stages) {
    currentProject.value.stages.forEach(stage => {
      if (stage.steps) {
        stage.steps.forEach(step => {
          if (stage.id !== currentStage.value.id) {
            total += Number(step.planned_cost) || 0;
          }
        });
      }
    });
  }
  return total + currentStageTotalCost.value; 
});

const projectTotalPlannedHours = computed(() => {
  let total = 0;
  if (currentProject.value?.stages) {
    currentProject.value.stages.forEach(stage => {
      if (stage.steps) {
        stage.steps.forEach(step => {
          if (stage.id !== currentStage.value.id) {
            total += Number(step.planned_hours) || 0;
          }
        });
      }
    });
  }
  return total + currentStageTotalHours.value;
});

const projectRemainingBudget = computed(() => {
  const totalBudget = Number(currentProject.value?.planned_budget) || 0;
  return totalBudget - projectTotalAllocatedCost.value;
});

watch(() => form.value.type_id, (newTypeId) => {
  if (!newTypeId) { filteredGoals.value = []; return }
  const currentGoalObj = allGoals.value.find(g => g.id === form.value.goal_id)
  
  if (currentGoalObj && currentGoalObj.goal_type_id !== newTypeId) {
      form.value.goal_id = ''
  }
  
  filteredGoals.value = allGoals.value.filter(g => {
      return g.goal_type_id === newTypeId && (getRemainingWeight(g) > 0 || (isEdit.value && originalGoalId.value === g.id));
  });
})
watch(() => queryParams.type_id, () => { queryParams.goal_id = '' })

// ==========================================
// 3. 数据加载
// ==========================================
const loadData = async (silent = false) => {
  if (!silent) loading.value = true
  try {
    const params = { 
      page: pagination.current, 
      per_page: pagination.size, 
      name: queryParams.name, 
      type_id: queryParams.type_id, 
      goal_id: queryParams.goal_id 
    }

    // 🌟 新增：如果选择了日期段，就把参数喂给后端
    if (queryParams.dateRange && queryParams.dateRange.length === 2) {
      params.start_date = queryParams.dateRange[0]
      params.end_date = queryParams.dateRange[1]
    }
    const res = await getProjects(params)
    
    let finalData = []
    if (res && res.data && Array.isArray(res.data)) {
        finalData = res.data
        pagination.total = res.total || 0
    } else if (res && res.data && res.data.data && Array.isArray(res.data.data)) {
        finalData = res.data.data
        pagination.total = res.data.total || 0
    } else if (Array.isArray(res)) {
        finalData = res
        pagination.total = res.length || 0
    }
    list.value = finalData
  } catch (error) {
    console.error('获取列表失败:', error)
    list.value = [] 
  } finally { 
    if (!silent) loading.value = false 
  }
}

const handleSearch = () => { pagination.current = 1; loadData() }
const resetSearch = () => { 
  queryParams.name = ''; 
  queryParams.type_id = ''; 
  queryParams.goal_id = ''; 
  queryParams.dateRange = []; // 🌟 新增：重置时清空日期
  handleSearch() 
}
const handleSizeChange = (val) => { pagination.size = val; loadData() }
const handleCurrentChange = (val) => { pagination.current = val; loadData() }

const loadOptions = async () => {
  try {
    // 🌟 4. 修改：同时请求时间和财务账户数据
    const [typesRes, goalsRes, timeAccRes, financeAccRes] = await Promise.all([
      getGoalTypes(), 
      getGoals(),
      getTimeAccounts(),
      getAccounts()
    ])
    typeList.value = typesRes || []; 
    allGoals.value = goalsRes || [];
    // 过滤出启用状态的账户 (假设状态字段名为 status 且启用时为 1)
    timeAccounts.value = (timeAccRes?.data || timeAccRes || []).filter(a => a.status === 1);
    financialAccounts.value = (financeAccRes?.data || financeAccRes || []).filter(a => a.status === 1);
  } catch (error) {}
}

const getStageStartEnd = (stage) => {
  let min = stage.segments?.[0]?.start_date || ''
  let max = stage.segments?.[0]?.end_date || ''
  stage.segments?.forEach(seg => {
    if (seg.start_date && seg.start_date < min) min = seg.start_date
    if (seg.end_date && seg.end_date > max) max = seg.end_date
  })
  return { start: min, end: max }
}

const isStageDelayedWithoutSteps = (stage) => {
  if (stage.steps && stage.steps.length > 0) return false
  const { start } = getStageStartEnd(stage)
  if (!start) return false
  const today = new Date(); today.setHours(0, 0, 0, 0)
  const startDate = new Date(start); startDate.setHours(0, 0, 0, 0)
  return today > startDate
}

const getSortedStages = (stages) => {
  if (!stages || !stages.length) return []
  return [...stages].sort((a, b) => {
    const startA = getStageStartEnd(a).start || '9999-12-31'
    const startB = getStageStartEnd(b).start || '9999-12-31'
    return startA < startB ? -1 : (startA > startB ? 1 : 0)
  })
}

const getDuration = (start, end) => {
  if (!start || !end) return '-'
  const days = Math.floor((new Date(end) - new Date(start)) / (1000 * 60 * 60 * 24)) + 1
  return days > 0 ? days : 0
}
const getDiffDays = (start, end) => Math.floor((new Date(end) - new Date(start)) / (1000 * 60 * 60 * 24))

onMounted(() => { loadData(); loadOptions() })

// ==========================================
// 4. 业务逻辑：项目计划 (增删改)
// ==========================================
const handleAdd = () => { isEdit.value = false; originalProjectWeight.value = 0; originalGoalId.value = null; form.value = getNewForm(); dialogVisible.value = true }

const handleEdit = (row) => {
  isEdit.value = true; 
  originalProjectWeight.value = row.goal_weight || 0; 
  originalGoalId.value = row.goal_id
  
  const formData = JSON.parse(JSON.stringify(row))
  if (row.goal && row.goal.goal_type_id) {
      formData.type_id = row.goal.goal_type_id
      filteredGoals.value = allGoals.value.filter(g => g.goal_type_id === formData.type_id);
  } else {
      filteredGoals.value = allGoals.value
  }
  
  form.value = formData; 
  dialogVisible.value = true
}

const handleWeightChange = (val) => {
  const max = maxGoalWeight.value;
  if (val > max) {
     ElMessage.warning(`⚠️ 超限警告：该目标剩余可用占比最多为 ${max}%，已强行调整为最大值！`);
     nextTick(() => {
        form.value.goal_weight = max;
     });
  }
};

const handleSubmit = async () => {
  if (!form.value.goal_id) return ElMessage.warning('请选择关联目标')
  if (!form.value.name) return ElMessage.warning('请填写项目名称')
  if (!form.value.goal_weight) return ElMessage.warning('请设置目标占比')
  
  if (!form.value.start_date || !form.value.end_date) return ElMessage.warning('请设置项目总起止时间！')
  if (form.value.start_date > form.value.end_date) return ElMessage.warning('项目的结束时间不能早于开始时间！')

  const projStart = new Date(form.value.start_date).getTime()
  const projEnd = new Date(form.value.end_date).getTime()

  for (let i = 0; i < form.value.stages.length; i++) {
     const stage = form.value.stages[i];
     for (let j = 0; j < stage.segments.length; j++) {
        const seg = stage.segments[j];
        if (seg.start_date && seg.end_date) {
           const segStart = new Date(seg.start_date).getTime();
           const segEnd = new Date(seg.end_date).getTime();
           
           if (segStart > segEnd) {
              return ElMessage.warning(`第 ${i+1} 阶段的结束时间不能早于开始时间！`);
           }
           if (segStart < projStart || segEnd > projEnd) {
              return ElMessage.warning(`⚠️ 越界警告！\n阶段【${stage.name || i+1}】的排期 (${seg.start_date} ~ ${seg.end_date})\n超出了项目总周期 (${form.value.start_date} ~ ${form.value.end_date})`);
           }
        }
     }
  }

  formLoading.value = true
  try {
    if (isEdit.value) await updateProject(form.value.id, form.value)
    else await createProject(form.value)
    ElMessage.success('操作成功'); dialogVisible.value = false; loadData(); loadOptions() 
  } catch (e) {} finally { formLoading.value = false }
}

const handleDelete = (row) => {
  ElMessageBox.confirm(`确定要删除项目 "${row.name}" 吗？`, '警告', { type: 'warning', confirmButtonText: '确定删除', cancelButtonText: '取消' })
  .then(async () => { await deleteProject(row.id); ElMessage.success('删除成功'); loadData(); loadOptions() }).catch(()=>{})
}
const addStage = () => form.value.stages.push({ name: '', segments: [{ weight: '', start_date: '', end_date: '' }] })
const removeStage = (i) => form.value.stages.splice(i, 1)
const addSegment = (si) => form.value.stages[si].segments.push({ weight: '', start_date: '', end_date: '' })
const removeSegment = (si, gi) => form.value.stages[si].segments.splice(gi, 1)

// ==========================================
// 5. 业务逻辑：实施步骤
// ==========================================
const handleManageSteps = (stage, project) => { 
  currentStage.value = stage; 
  currentProject.value = project || {}; 
  stepDialogVisible.value = true; 
  stepForm.value = getNewStepForm(); 
  loadSteps(stage.id);
}

const loadSteps = async (stageId) => { 
  stepLoading.value = true; 
  try { 
    const res = await getStageSteps(stageId);
    stepList.value = Array.isArray(res) ? res : (Array.isArray(res?.data) ? res.data : []);
  } catch (e) {
    stepList.value = [];
  } finally { 
    stepLoading.value = false; 
  } 
}

const handleAddStep = async () => {
  if (!stepForm.value.name || !stepForm.value.start_date || !stepForm.value.end_date) return ElMessage.warning('请完善信息')

  if (currentProject.value?.start_date && currentProject.value?.end_date) {
      const stepStart = new Date(stepForm.value.start_date).getTime();
      const stepEnd = new Date(stepForm.value.end_date).getTime();
      const pStart = new Date(currentProject.value.start_date).getTime();
      const pEnd = new Date(currentProject.value.end_date).getTime();

      if (stepStart < pStart || stepEnd > pEnd) {
          return ElMessage.warning(`⚠️ 步骤时间越界！该步骤的执行周期不能超出当前项目规定的总周期：${currentProject.value.start_date} ~ ${currentProject.value.end_date}`);
      }
  }

  const newCost = Number(stepForm.value.planned_cost) || 0;
  const projectBudget = Number(currentProject.value?.planned_budget) || 0;

  if (projectBudget > 0 && newCost > 0) {
      const remaining = projectRemainingBudget.value;
      if (newCost > remaining) {
          try {
              await ElMessageBox.confirm(
                  `当前新增预算 <b style="color:#F56C6C">￥${newCost.toFixed(2)}</b> 将导致项目总预算超支 <b style="color:#F56C6C">￥${(newCost - remaining).toFixed(2)}</b>！<br/><br/>是否执意强行添加？`,
                  '⚠️ 预算超支警告',
                  { confirmButtonText: '强行添加', cancelButtonText: '返回修改', type: 'warning', dangerouslyUseHTMLString: true }
              );
          } catch (e) {
              return; 
          }
      }
  }

  try { 
    await createStageStep({ project_stage_id: currentStage.value.id, ...stepForm.value }); 
    ElMessage.success('添加成功'); 
    stepForm.value = getNewStepForm(); 
    loadSteps(currentStage.value.id); 
    loadData(true); 
  } catch (e) { }
}

const handleDeleteStep = async (row) => { try { await deleteStageStep(row.id); ElMessage.success('删除成功'); loadSteps(currentStage.value.id); loadData(true) } catch (e) {} }

const handleViewStepDetails = async (step) => {
  currentStepName.value = step.name; 
  stepDetailVisible.value = true; 
  stepDetailLoading.value = true;
  try { 
    const res = await request.get(`/project-stage-steps/${step.id}/task-details`); 
    stepDetailList.value = Array.isArray(res) ? res : (Array.isArray(res?.data) ? res.data : []);
  } catch (e) {
    stepDetailList.value = [];
  } finally { 
    stepDetailLoading.value = false; 
  }
}

const confirmCompleteTask = async (row) => {
  const h = Number(row.temp_hours_h) || 0;
  const m = Number(row.temp_hours_m) || 0;
  const totalHours = Number((h + (m / 60)).toFixed(2));
  
  try {
    await updateTaskDetailStatus(row.id, { status: 'completed', actual_hours: totalHours });
    row.status = 'completed';
    row.actual_hours = totalHours;
    ElMessage.success(`🎉 打卡成功！已记录用时 ${h}小时 ${m}分钟`);
    document.body.click(); 
    loadData(true); 
  } catch (e) {
    console.error('打卡失败', e);
  }
}

const revertTaskStatus = async (row) => {
  try {
    await updateTaskDetailStatus(row.id, { status: 'pending', actual_hours: 0 });
    row.status = 'pending';
    row.actual_hours = 0;
    row.temp_hours_h = 0; 
    row.temp_hours_m = 0; 
    ElMessage.warning('已撤销打卡，时间已归零');
    loadData(true); 
  } catch (e) {
    console.error('撤销失败', e);
  }
}
const handleEditRemark = (row) => { row.isEditing = true; setTimeout(() => { document.getElementById(`remark-input-${row.id}`)?.focus() }, 100) }
const handleSaveRemark = async (row) => { if (!row.isEditing) return; row.isEditing = false; try { await updateTaskDetailRemark(row.id, row.remark); ElMessage.success('更新成功') } catch (e) {} }

// ==========================================
// 6. 甘特图处理逻辑
// ==========================================
const openGantt = (project) => {
  currentProjectForGantt.value = project
  
  let projStart = project.start_date || '9999-12-31'; 
  let projEnd = project.end_date || '0000-01-01';
  
  if (projStart === '9999-12-31' || projEnd === '0000-01-01') {
    project.stages?.forEach(stage => {
      stage.segments?.forEach(seg => {
        if (seg.start_date && seg.start_date < projStart) projStart = seg.start_date
        if (seg.end_date && seg.end_date > projEnd) projEnd = seg.end_date
      })
    })
  }

  if (projStart === '9999-12-31' || projEnd === '0000-01-01') {
    return ElMessage.warning('该项目暂无有效的排期时间段，无法生成甘特图')
  }

  const totalDays = getDuration(projStart, projEnd)
  const sortedStages = getSortedStages(project.stages)

  const stagesData = sortedStages.map(stage => {
    let totalDetails = 0; let completedDetails = 0
    stage.steps?.forEach(step => {
       if (step.task) {
          totalDetails += step.task.total_details || 0
          completedDetails += step.task.completed_details || 0
       }
    })
    const progress = totalDetails === 0 ? 0 : Math.round((completedDetails / totalDetails) * 100)

    const mappedSegments = (stage.segments || []).filter(seg => seg.start_date && seg.end_date).map(seg => {
       const offsetDays = getDiffDays(projStart, seg.start_date) 
       const durationDays = getDuration(seg.start_date, seg.end_date)   
       let leftPercent = (offsetDays / totalDays) * 100
       let widthPercent = (durationDays / totalDays) * 100
       if(leftPercent < 0) leftPercent = 0
       if(leftPercent + widthPercent > 100) widthPercent = 100 - leftPercent
       return { ...seg, leftPercent, widthPercent }
    })

    return { ...stage, progress, mappedSegments }
  }).filter(s => s.mappedSegments && s.mappedSegments.length > 0) 

  ganttData.value = { projStart, projEnd, totalDays, stages: stagesData }
  ganttVisible.value = true
}
</script>

<template>
  <div class="page-container">
    <el-card shadow="never" class="search-card">
      <el-form :inline="true" :model="queryParams" class="search-form">
        <el-form-item label="项目名称"><el-input v-model="queryParams.name" placeholder="请输入名称" clearable @keyup.enter="handleSearch" style="width: 180px" /></el-form-item>
        <el-form-item label="目标类型"><el-select v-model="queryParams.type_id" placeholder="全部类型" clearable style="width: 140px" @change="handleSearch"><el-option v-for="t in typeList" :key="t.id" :label="t.title" :value="t.id" /></el-select></el-form-item>
        <el-form-item label="关联目标"><el-select v-model="queryParams.goal_id" placeholder="全部目标" clearable style="width: 160px" @change="handleSearch"><el-option v-for="g in searchFilteredGoals" :key="g.id" :label="g.name" :value="g.id" /></el-select></el-form-item>
        <el-form-item label="项目周期">
          <el-date-picker
            v-model="queryParams.dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始范围"
            end-placeholder="结束范围"
            value-format="YYYY-MM-DD"
            style="width: 240px"
            clearable
            @change="handleSearch" 
          />
        </el-form-item>
        <el-form-item><el-button type="primary" :icon="Search" @click="handleSearch">搜索</el-button><el-button :icon="Refresh" @click="resetSearch">重置</el-button></el-form-item>
      </el-form>
    </el-card>

    <div class="header-actions">
      <el-button type="primary" :icon="Plus" @click="handleAdd">新增项目计划</el-button>
    </div>

    <el-table v-loading="loading" :data="list" border stripe>
      <el-table-column label="领域" width="80" align="center">
        <template #default="{ row }"><el-tag v-if="row.goal && row.goal.goal_type" :color="row.goal.goal_type.color" effect="dark" style="border:none; color: #fff;">{{ row.goal.goal_type.title }}</el-tag><el-tag v-else type="info">未知</el-tag></template>
      </el-table-column>
      
      <el-table-column label="项目名称" min-width="180">
        <template #default="{ row }">
          <div style="font-weight:bold; font-size:15px; color:#303133">{{ row.name }}</div>
          
          <div style="font-size:12px; color:#909399; margin-top:5px; display:flex; align-items:center; gap:6px; flex-wrap: wrap;">
            <span>所属目标：{{ row.goal?.name || '未关联' }}</span>
            <el-tag size="small" type="warning" effect="plain" round v-if="row.goal_weight">占 {{ row.goal_weight }}%</el-tag>
          </div>
          
          <div v-if="row.start_date && row.end_date" style="font-size:12px; color:#E6A23C; margin-top:5px; font-family: Consolas;">
             <el-icon style="vertical-align: -2px;"><Calendar /></el-icon>
             项目总限期：{{ row.start_date }} ~ {{ row.end_date }}
          </div>
          
          <div style="margin-top:8px; display: flex; flex-direction: column; gap: 6px; align-items: flex-start;">
            
            <div v-if="row.time_account" style="background: #f3f0ff; color: #7b61ff; border: 1px solid #e5dfff; border-radius: 12px; padding: 2px 10px; font-size: 12px; display: inline-flex; align-items: center; white-space: nowrap;" title="任务打卡时将扣除此账户的时间">
               <el-icon style="margin-right: 4px;"><Timer /></el-icon>结算账户: {{ row.time_account.name }}
            </div>
            
            <div v-if="row.planned_budget" style="background: #f0f9eb; color: #67C23A; border: 1px solid #e1f3d8; border-radius: 12px; padding: 2px 10px; font-size: 12px; display: inline-flex; align-items: center; white-space: nowrap;">
               <el-icon style="margin-right: 4px;"><Money /></el-icon>总预算: ￥{{ Number(row.planned_budget).toFixed(2) }}
               <span v-if="row.account" style="margin-left: 5px; opacity: 0.85;">(从 {{ row.account.name }} 扣款)</span>
            </div>
            <div v-if="getProjectTotalCost(row) > 0" style="background: #fdf6ec; color: #E6A23C; border: 1px solid #faecd8; border-radius: 12px; padding: 2px 10px; font-size: 12px; display: inline-flex; align-items: center; white-space: nowrap;">
               <el-icon style="margin-right: 4px;"><Money /></el-icon>已分配资金: ￥{{ getProjectTotalCost(row).toFixed(2) }}
            </div>
            <div v-if="getProjectTotalHours(row) > 0" style="background: #ecf5ff; color: #409EFF; border: 1px solid #d9ecff; border-radius: 12px; padding: 2px 10px; font-size: 12px; display: inline-flex; align-items: center; white-space: nowrap;">
               <el-icon style="margin-right: 4px;"><Clock /></el-icon>总计划工时: {{ getProjectTotalHours(row) }}h
            </div>
          </div>
        </template>
      </el-table-column>

      <el-table-column label="阶段排期表及实施步骤" min-width="650">
        <template #default="{ row }">
          <div v-if="row.stages && row.stages.length" class="stage-list-container">
            <div v-for="stage in getSortedStages(row.stages)" :key="stage.id" class="stage-block">
                <div class="stage-row-item">
                  <div class="stage-left-part">
                    <div class="stage-name">
                      <span class="dot"></span>
                      <span>{{ stage.name }}</span>
                      
                      <span class="stage-time" v-if="getStageStartEnd(stage).start || getStageStartEnd(stage).end">
                         <el-icon><Calendar /></el-icon>
                         {{ getStageStartEnd(stage).start || '未定' }} ~ {{ getStageStartEnd(stage).end || '未定' }}
                      </span>

                      <el-tooltip v-if="isStageDelayedWithoutSteps(stage)" content="该阶段已到期开始，但尚未创建任何实施步骤！" placement="top">
                        <el-tag type="danger" effect="dark" round style="cursor: pointer; height: auto; padding: 2px 6px; margin-left: 6px; border: none;">
                          <div style="display: flex; align-items: center; font-size: 12px; line-height: 1;">
                            <el-icon style="margin-right: 2px;"><Warning /></el-icon>
                            <span>缺步骤</span>
                          </div>
                        </el-tag>
                      </el-tooltip>
                    </div>
                  </div>
                  <el-button type="primary" link size="small" :icon="List" @click="handleManageSteps(stage, row)">管理步骤</el-button>
                </div>
                <div v-if="stage.steps && stage.steps.length > 0" class="inline-step-list">
                   <div v-for="(step, sIdx) in stage.steps" :key="step.id" class="inline-step-item">
                      <div class="step-name-box"><span class="step-num">{{ sIdx + 1 }}.</span><span class="step-name" :title="step.description">{{ step.name }}</span></div>
                      
                      <div class="step-meta">
                         <span class="step-date">{{ step.start_date }} ~ {{ step.end_date }}</span>
                         <el-tag v-if="step.frequency && step.frequency.length" size="small" type="info" effect="plain" class="step-freq">{{ step.frequency.join(',') }}</el-tag>
                         <div style="display: flex; gap: 8px; margin-left: 8px; background: #fff; padding: 2px 6px; border-radius: 4px; border: 1px solid #ebeef5;">
                            <span v-if="step.planned_hours" style="color: #409EFF; font-size: 12px; font-family: Consolas, monospace; font-weight: bold;" title="计划投入工时">
                               <el-icon style="vertical-align: -2px;"><Clock /></el-icon> {{ step.planned_hours }}h
                            </span>
                            <span v-if="step.planned_cost" style="color: #67C23A; font-size: 12px; font-family: Consolas, monospace; font-weight: bold;" title="计划投入资金">
                               <el-icon style="vertical-align: -2px;"><Money /></el-icon> ￥{{ step.planned_cost }}
                            </span>
                         </div>
                         <div class="step-progress" v-if="step.task && step.task.total_details > 0"><el-progress :percentage="Math.round((step.task.completed_details / step.task.total_details) * 100)" :stroke-width="8" :color="customColors"/></div>
                         <span v-else-if="!step.task" style="font-size:12px;color:#c0c4cc;margin-left:15px;">无排期</span>
                         <el-button type="primary" link size="small" :icon="View" style="margin-left: 15px;" @click="handleViewStepDetails(step)">排期</el-button>
                      </div>
                   </div>
                </div>
             </div>
          </div>
          <span v-else style="color:#999; font-size:12px">暂无阶段规划</span>
        </template>
      </el-table-column>
      
      <el-table-column prop="expected_result" label="预期结果" width="180" show-overflow-tooltip />
      <el-table-column label="操作" width="220" align="center" fixed="right">
        <template #default="{ row }">
          <el-button type="success" link :icon="DataLine" @click="openGantt(row)">甘特图</el-button>
          <el-button type="primary" link :icon="Edit" @click="handleEdit(row)">编辑</el-button>
          <el-button type="danger" link :icon="Delete" @click="handleDelete(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <div class="pagination-container"><el-pagination v-model:current-page="pagination.current" v-model:page-size="pagination.size" :page-sizes="[10, 20, 50, 100]" background layout="total, sizes, prev, pager, next, jumper" :total="pagination.total" @size-change="handleSizeChange" @current-change="handleCurrentChange"/></div>

    <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑项目计划' : '制定项目计划'" width="900px" destroy-on-close top="5vh">
      <el-form :model="form" label-width="90px" class="custom-form">
        <div class="form-section-box">
          
          <el-row :gutter="15">
            <el-col :span="6"><el-form-item label="目标类型"><el-select v-model="form.type_id" placeholder="筛选" style="width:100%"><el-option v-for="t in typeList" :key="t.id" :label="t.title" :value="t.id" /></el-select></el-form-item></el-col>
            <el-col :span="8"><el-form-item label="关联目标" required><el-select v-model="form.goal_id" placeholder="选择目标" style="width:100%" :disabled="!form.type_id"><el-option v-for="g in filteredGoals" :key="g.id" :label="g.name" :value="g.id"><span style="float: left">{{ g.name }}</span><span style="float: right; color: #8492a6; font-size: 13px;">可用 {{ getRemainingWeight(g) }}%</span></el-option></el-select></el-form-item></el-col>
            <el-col :span="10"><el-form-item label="目标详情"><div class="read-only-info">{{ currentGoal.content || '请先选择目标' }}</div></el-form-item></el-col>
          </el-row>
          
          <el-row :gutter="15">
            <el-col :span="8">
              <el-form-item label="项目名称" required><el-input v-model="form.name" placeholder="名称" /></el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="目标占比" required>
                  <el-input-number v-model="form.goal_weight" :min="1" style="width: 100%" controls-position="right" @change="handleWeightChange" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="预期结果" required><el-input v-model="form.expected_result" placeholder="预期成果" /></el-form-item>
            </el-col>
          </el-row>

          <el-row :gutter="15">
            <el-col :span="8">
              <el-form-item label="项目总预算">
                <el-input-number v-model="form.planned_budget" :min="0" style="width: 100%" placeholder="总金额" controls-position="right" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="支出账户">
                <el-select v-model="form.account_id" placeholder="默认扣款账户" clearable style="width:100%">
                  <el-option v-for="acc in financialAccounts" :key="acc.id" :label="acc.name" :value="acc.id" />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="消耗时间池">
                <el-select v-model="form.time_account_id" placeholder="打卡扣除时间" clearable style="width:100%">
                  <el-option v-for="acc in timeAccounts" :key="acc.id" :label="acc.name" :value="acc.id" />
                </el-select>
              </el-form-item>
            </el-col>
          </el-row>

          <el-row :gutter="15" style="margin-top: 5px;">
            <el-col :span="14">
              <el-form-item label="项目周期" required>
                <div style="display:flex; align-items: center; width: 100%; gap: 10px;">
                    <el-date-picker v-model="form.start_date" type="date" placeholder="项目开始日期" value-format="YYYY-MM-DD" style="flex:1" />
                    <span style="color:#909399; font-weight: bold;">至</span>
                    <el-date-picker v-model="form.end_date" type="date" placeholder="项目结束(Deadline)" value-format="YYYY-MM-DD" style="flex:1" />
                </div>
              </el-form-item>
            </el-col>
            <el-col :span="10">
               <div style="font-size: 12px; color: #E6A23C; line-height: 32px;">
                 <el-icon style="vertical-align: middle;"><Warning /></el-icon>
                 设置后，下方所有阶段的排期均不能超出此范围。
               </div>
            </el-col>
          </el-row>

          <div v-if="form.goal_id" class="goal-timeline-reference">
             <div class="reference-title">
               <el-icon><Calendar /></el-icon> 目标时间参考雷达
             </div>
             <div class="reference-content">
                <div class="goal-time">
                   <el-tag size="small" type="danger" effect="plain" style="border:none; font-weight:bold;">主目标总体周期</el-tag>
                   <span class="time-text">
                      {{ currentGoal.start_date || '未定' }}  ~  {{ currentGoal.end_date || '未定' }}
                   </span>
                </div>
                <div class="sibling-projects" v-if="siblingProjectsTimelines.length > 0">
                   <div class="sibling-title">
                     <el-icon style="vertical-align: -2px; margin-right: 2px;"><Collection /></el-icon>
                     同目标下其他项目的时间线分布：
                   </div>
                   <div class="sibling-list">
                      <div v-for="(sp, idx) in siblingProjectsTimelines" :key="idx" class="sibling-item">
                         <span class="sp-name">{{ sp.name }}</span>
                         <span class="sp-time">{{ sp.start }} ~ {{ sp.end }}</span>
                      </div>
                   </div>
                </div>
                <div v-else class="sibling-projects" style="color: #c0c4cc; font-size: 12px; margin-top: 8px;">
                   该目标下暂无其他项目
                </div>
             </div>
          </div>

        </div>

        <div class="stage-wrapper">
          <div class="stage-item" v-for="(stage, sIndex) in form.stages" :key="sIndex">
            <div class="stage-header">
              <div class="stage-title-input"><span class="index-badge">{{ sIndex + 1 }}</span><el-input v-model="form.stages[sIndex].name" placeholder="阶段名称" style="width: 200px" /></div>
              <el-button type="danger" link v-if="form.stages.length > 1" @click="removeStage(sIndex)">删除阶段</el-button>
            </div>
            <div class="segment-list">
              <div v-for="(segment, gIndex) in stage.segments" :key="gIndex" class="segment-row">
                 <el-row :gutter="10" align="middle">
                    <el-col :span="5"><el-input v-model="form.stages[sIndex].segments[gIndex].weight" placeholder="比重%"><template #prepend>比重</template></el-input></el-col>
                    <el-col :span="8"><el-date-picker v-model="form.stages[sIndex].segments[gIndex].start_date" type="date" placeholder="阶段开始" value-format="YYYY-MM-DD" style="width:100%" /></el-col>
                    <el-col :span="8"><el-date-picker v-model="form.stages[sIndex].segments[gIndex].end_date" type="date" placeholder="阶段结束" value-format="YYYY-MM-DD" style="width:100%" /></el-col>
                    <el-col :span="3" style="text-align:right">
                       <el-button circle :icon="Plus" size="small" @click="addSegment(sIndex)" v-if="gIndex === stage.segments.length -1" type="primary" plain />
                       <el-button circle :icon="Delete" size="small" @click="removeSegment(sIndex, gIndex)" v-if="stage.segments.length > 1" type="danger" plain />
                    </el-col>
                 </el-row>
              </div>
            </div>
          </div>
        </div>
        <div class="add-stage-bar" @click="addStage"><el-icon><Plus /></el-icon> 添加新阶段</div>
        <el-form-item label="备注说明" style="margin-top:20px"><el-input v-model="form.remark" type="textarea" rows="2" /></el-form-item>
      </el-form>
      <template #footer><el-button @click="dialogVisible = false">取消</el-button><el-button type="primary" @click="handleSubmit" :loading="formLoading">{{ isEdit ? '更新保存' : '确认创建' }}</el-button></template>
    </el-dialog>

    <el-dialog v-model="stepDialogVisible" :title="`实施步骤 - ${currentStage.name}`" width="1150px" top="5vh">
      <div class="step-manager">
        <div class="project-macro-banner" style="display: flex; flex-wrap: wrap; gap: 30px; background: #f8f9fa; padding: 12px 20px; border-radius: 6px; border-left: 4px solid #409EFF; margin-bottom: 15px; box-shadow: 0 1px 4px rgba(0,0,0,0.02);">
            <div style="flex-basis: 100%; border-bottom: 1px dashed #dcdfe6; padding-bottom: 8px; margin-bottom: -10px;">
               <span style="color: #909399; font-size: 13px;">项目总限期：</span>
               <strong style="color: #E6A23C; font-size: 15px; font-family: Consolas;">
                  {{ currentProject.start_date || '未定' }} ~ {{ currentProject.end_date || '未定' }}
               </strong>
            </div>
            <div>
               <span style="color: #909399; font-size: 13px;">项目总预算：</span>
               <strong style="color: #67C23A; font-size: 16px; font-family: Consolas;">￥{{ Number(currentProject.planned_budget || 0).toFixed(2) }}</strong>
            </div>
            <div>
               <span style="color: #909399; font-size: 13px;">全项目已分配：</span>
               <strong style="color: #E6A23C; font-size: 16px; font-family: Consolas;">￥{{ projectTotalAllocatedCost.toFixed(2) }}</strong>
            </div>
            <div>
               <span style="color: #909399; font-size: 13px;">剩余可用资金：</span>
               <strong :style="{ color: projectRemainingBudget >= 0 ? '#409EFF' : '#F56C6C', fontSize: '16px', fontFamily: 'Consolas' }">
                  ￥{{ projectRemainingBudget.toFixed(2) }}
               </strong>
            </div>
            <div style="margin-left: auto;">
               <span style="color: #909399; font-size: 13px;">项目总计划时间：</span>
               <strong style="color: #409EFF; font-size: 16px; font-family: Consolas;">{{ projectTotalPlannedHours.toFixed(1) }} h</strong>
            </div>
        </div>
        <el-table :data="stepList" border height="330" v-loading="stepLoading">
           <el-table-column prop="name" label="步骤名称" width="150" />
           <el-table-column prop="description" label="步骤详情" min-width="180" show-overflow-tooltip />
           <el-table-column label="开始日期" width="110" prop="start_date" align="center" />
           <el-table-column label="结束日期" width="110" prop="end_date" align="center" />
           <el-table-column label="计划工时(h)" width="100" align="center">
              <template #default="{ row }"><span v-if="row.planned_hours" style="color: #409EFF; font-weight: bold;">{{ row.planned_hours }}</span><span v-else style="color: #c0c4cc">-</span></template>
           </el-table-column>
           <el-table-column label="计划预算(元)" width="100" align="right">
              <template #default="{ row }"><span v-if="row.planned_cost" style="color: #67C23A; font-weight: bold;">￥{{ row.planned_cost }}</span><span v-else style="color: #c0c4cc">-</span></template>
           </el-table-column>
           <el-table-column prop="weight" label="权重%" width="70" align="center" />
           <el-table-column label="操作" width="80" align="center" fixed="right"><template #default="{ row }"><el-button type="danger" link @click="handleDeleteStep(row)">删除</el-button></template></el-table-column>
        </el-table>

        <div class="step-add-panel">
          <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
              <div style="display: flex; align-items: center; gap: 15px;">
                 <div><el-icon><Edit /></el-icon> 规划新步骤</div>
                 <div style="font-size: 12px; font-weight: normal; color: #e6a23c;" v-if="currentStage.segments && currentStage.segments.length">
                    <el-icon style="vertical-align: middle; margin-right: 2px;"><Calendar /></el-icon>阶段可用时间：
                    <span v-for="(seg, idx) in currentStage.segments" :key="idx" style="margin-left: 5px; font-family: Consolas, monospace; background: #fdf6ec; padding: 2px 6px; border-radius: 4px; border: 1px solid #faecd8;">
                       {{ seg.start_date }} ~ {{ seg.end_date }}
                    </span>
                 </div>
              </div>
              <div style="font-size: 13px; font-weight: normal; color: #606266; display: flex; align-items: center; gap: 15px;">
                 <span v-if="currentProject?.planned_budget" style="background: #f0f9eb; padding: 3px 8px; border-radius: 4px; border: 1px solid #e1f3d8; font-size: 12px;">
                     <el-icon style="vertical-align: -2px; color: #67C23A;"><Money /></el-icon> 
                     项目总预算: <strong style="color:#67C23A; font-size: 13px;">￥{{ currentProject.planned_budget }}</strong>
                  </span>
                 <span><el-icon style="vertical-align: -2px;"><Clock /></el-icon> 本阶段已排: <strong style="color:#409EFF; font-size: 14px;">{{ currentStageTotalHours }} h</strong></span>
                 <span><el-icon style="vertical-align: -2px;"><Money /></el-icon> 本阶段已排: <strong style="color:#67C23A; font-size: 14px;">￥{{ currentStageTotalCost }}</strong></span>
              </div>
           </div>
           
           <div class="panel-form">
              <el-row :gutter="10" align="middle" style="margin-bottom: 15px;">
                 <el-col :span="4"><el-input v-model="stepForm.name" placeholder="步骤名称" /></el-col>
                 <el-col :span="6"><el-input v-model="stepForm.description" placeholder="步骤详情描述" /></el-col>
                 <el-col :span="4"><el-date-picker v-model="stepForm.start_date" type="date" placeholder="开始日期" value-format="YYYY-MM-DD" style="width:100%" /></el-col>
                 <el-col :span="4"><el-date-picker v-model="stepForm.end_date" type="date" placeholder="结束日期" value-format="YYYY-MM-DD" style="width:100%" /></el-col>
                 <el-col :span="6"><el-select v-model="stepForm.frequency" multiple collapse-tags placeholder="频率(选填)" style="width:100%"><el-option v-for="d in weekOptions" :key="d" :label="d" :value="d" /></el-select></el-col>
              </el-row>
              <el-row :gutter="10" align="middle">
                 <el-col :span="4"><el-input v-model="stepForm.weight" placeholder="完成比重"><template #append>%</template></el-input></el-col>
                 <el-col :span="4"><el-input-number v-model="stepForm.planned_hours" :min="0" placeholder="工时(小时)" style="width:100%" controls-position="right" /></el-col>
                 <el-col :span="4"><el-input-number v-model="stepForm.planned_cost" :min="0" placeholder="预算资金(元)" style="width:100%" controls-position="right" /></el-col>
                 <el-col :span="7">
                    <span style="font-size:12px; color:#666; margin-right:5px; margin-left: 10px;"><el-icon style="vertical-align:middle"><Timer/></el-icon> 提醒时间:</span>
                    <el-time-picker v-model="stepForm.reminder_time" placeholder="选择时间" format="HH:mm" value-format="HH:mm" size="small" style="width:120px; cursor: pointer;" />
                 </el-col>
                 <el-col :span="5" style="text-align: right;"><el-button type="primary" :icon="Plus" @click="handleAddStep" style="width:100%">确认添加</el-button></el-col>
              </el-row>
           </div>
        </div>
      </div>
    </el-dialog>

    <el-dialog v-model="stepDetailVisible" :title="`【${currentStepName}】打卡排期详情`" width="750px">
       <el-table :data="stepDetailList" v-loading="stepDetailLoading" height="450" border stripe>
          <el-table-column label="执行时间" width="160" align="center"><template #default="{ row }"><span style="font-weight: bold; color: #409EFF">{{ row.task_time ? row.task_time.substring(0, 16) : '-' }}</span></template></el-table-column>
          <el-table-column prop="remark" label="排期详情与备注" min-width="250"><template #default="{ row }"><div v-if="row.isEditing"><el-input :id="`remark-input-${row.id}`" v-model="row.remark" size="small" @blur="handleSaveRemark(row)" @keyup.enter="handleSaveRemark(row)" placeholder="请输入备注..."/></div><div v-else @click="handleEditRemark(row)" class="remark-cell"><span v-if="row.remark">{{ row.remark }}</span><span v-else style="color: #ccc; font-size: 12px;">点击添加备注...</span><el-icon class="edit-icon" style="margin-left: 5px; color: #409EFF; display: none;"><Edit /></el-icon></div></template></el-table-column>
          <el-table-column label="打卡状态" width="140" align="center">
             <template #default="{ row }">
              <el-popover v-if="row.status !== 'completed'" placement="top" width="260" trigger="click">
                   <div style="margin-bottom: 12px; font-size: 13px; color: #606266; font-weight: bold;"><el-icon style="vertical-align: middle;"><Timer /></el-icon> 本次实际耗时</div>
                   <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 15px; font-size: 13px; color: #606266;">
                       <el-input-number v-model="row.temp_hours_h" :min="0" :step="1" :precision="0" size="small" style="width: 85px;" placeholder="0" controls-position="right" /><span>小时</span>
                       <el-input-number v-model="row.temp_hours_m" :min="0" :max="59" :step="5" :precision="0" size="small" style="width: 85px;" placeholder="0" controls-position="right" /><span>分</span>
                   </div>
                   <div style="text-align: right; margin: 0"><el-button size="small" type="primary" @click="confirmCompleteTask(row)">确认完成</el-button></div>
                   <template #reference><el-tag type="info" effect="dark" style="cursor: pointer; transition: all 0.3s;" class="hover-tag">待办</el-tag></template>
                </el-popover>
                <div v-else style="display: flex; flex-direction: column; align-items: center; gap: 4px;">
                   <el-popconfirm title="确定要撤销这条打卡吗？" @confirm="revertTaskStatus(row)" width="200">
                      <template #reference><el-tag type="success" effect="dark" style="cursor: pointer;">已完成</el-tag></template>
                   </el-popconfirm>
                   <span v-if="row.actual_hours > 0" style="font-size: 12px; color: #67C23A; font-family: Consolas; background: #f0f9eb; padding: 0 6px; border-radius: 4px;">{{ row.actual_hours }}h</span>
                </div>
             </template>
          </el-table-column>
       </el-table>
    </el-dialog>

    <el-dialog v-model="ganttVisible" :title="`项目整体进度视图：${currentProjectForGantt?.name}`" width="850px" destroy-on-close>
       <div class="gantt-container" v-if="ganttData.stages && ganttData.stages.length">
          <div class="gantt-header">
             <span class="date-node"><el-icon><Calendar /></el-icon> {{ ganttData.projStart }} (启动)</span>
             <span class="total-duration">总跨度：<strong>{{ ganttData.totalDays }}</strong> 天</span>
             <span class="date-node"><el-icon><Calendar /></el-icon> {{ ganttData.projEnd }} (收尾)</span>
          </div>
          <div class="gantt-body">
             <div class="gantt-row" v-for="stage in ganttData.stages" :key="stage.id">
                <div class="gantt-label">
                   <div class="stage-name" :title="stage.name">{{ stage.name }}</div>
                   <div class="stage-metrics"><span :class="{'progress-text': true, 'is-done': stage.progress === 100}">完成度 {{ stage.progress }}%</span></div>
                </div>
                <div class="gantt-track">
                   <div class="gantt-bar-capsule" v-for="(seg, idx) in stage.mappedSegments" :key="idx" :style="{ left: seg.leftPercent + '%', width: seg.widthPercent + '%' }">
                      <div class="gantt-bar-fill" :style="{ width: stage.progress + '%', backgroundColor: stage.progress === 100 ? '#67c23a' : '#409EFF' }"></div>
                      <div class="bar-tooltip">
                         <div class="tip-date">{{ seg.start_date }} 至 {{ seg.end_date }}</div>
                         <div class="tip-prog">整体进度：{{ stage.progress }}%</div>
                      </div>
                   </div>
                </div>
             </div>
          </div>
       </div>
    </el-dialog>
  </div>
</template>

<style scoped>
.page-container { padding: 20px; background: #fff; border-radius: 8px; min-height: calc(100vh - 84px); }
.search-card { margin-bottom: 20px; border: 1px solid #ebeef5; }
.search-form .el-form-item { margin-bottom: 0; margin-right: 20px; }
.pagination-container { margin-top: 20px; display: flex; justify-content: flex-end; }
.header-actions { margin-bottom: 20px; }
.form-section-box { background: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
.read-only-info { font-size: 13px; color: #606266; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* 时间参考雷达面板 CSS */
.goal-timeline-reference { background-color: #fff; border: 1px solid #e4e7ed; border-radius: 6px; padding: 12px 18px; margin-top: 15px; box-shadow: inset 0 2px 6px rgba(0,0,0,0.02); }
.reference-title { font-size: 13px; font-weight: bold; color: #303133; margin-bottom: 12px; display: flex; align-items: center; gap: 6px; border-bottom: 1px dashed #ebeef5; padding-bottom: 8px; }
.goal-time { margin-bottom: 12px; display: flex; align-items: center; gap: 10px; }
.time-text { font-family: Consolas, monospace; color: #F56C6C; font-weight: bold; font-size: 14px; }
.sibling-title { font-size: 12px; color: #909399; margin-bottom: 8px; }
.sibling-list { display: flex; flex-direction: column; gap: 6px; }
.sibling-item { font-size: 12px; display: flex; justify-content: space-between; background: #f4f4f5; padding: 6px 12px; border-radius: 4px; color: #606266; border-left: 3px solid #E6A23C; }
.sp-name { font-weight: bold; color: #303133; }
.sp-time { font-family: Consolas, monospace; color: #E6A23C; }

.stage-wrapper { max-height: 400px; overflow-y: auto; padding-right: 5px; }
.stage-item { border: 1px solid #e4e7ed; border-radius: 4px; margin-bottom: 15px; overflow: hidden; }
.stage-header { background: #fafafa; padding: 8px 15px; border-bottom: 1px solid #ebeef5; display: flex; justify-content: space-between; align-items: center; }
.stage-title-input { display: flex; align-items: center; }
.index-badge { background: #909399; color: #fff; width: 20px; height: 20px; border-radius: 50%; text-align: center; line-height: 20px; font-size: 12px; margin-right: 10px; }
.segment-list { padding: 10px 15px; }
.segment-row { margin-bottom: 8px; }
.add-stage-bar { border: 1px dashed #dcdfe6; text-align: center; padding: 10px; border-radius: 4px; cursor: pointer; color: #409EFF; transition: all 0.3s; }
.add-stage-bar:hover { border-color: #409EFF; background: #f0f9eb; }
.stage-list-container { display: flex; flex-direction: column; gap: 8px; }
.stage-row-item { display: flex; align-items: center; justify-content: space-between; background: #fdfdfd; padding: 4px 8px; border-radius: 4px; border: 1px dashed #ebeef5; }
.stage-left-part { display: flex; align-items: center; gap: 15px; }
.stage-name { font-weight: 600; color: #303133; display: flex; align-items: center; min-width: 110px; }
.dot { width: 6px; height: 6px; background: #409EFF; border-radius: 50%; margin-right: 6px; display: inline-block; }
.stage-time { color: #909399; font-family: 'Consolas', monospace; display: flex; align-items: center; font-size: 12px; margin-left: 10px; font-weight: normal; }
.stage-time .el-icon { margin-right: 4px; }
.stage-block { display: flex; flex-direction: column; gap: 5px; margin-bottom: 8px; }
.inline-step-list { margin-left: 8px; padding-left: 15px; border-left: 2px solid #ebeef5; display: flex; flex-direction: column; gap: 6px; margin-top: 2px; }
.inline-step-item { display: flex; justify-content: space-between; align-items: center; background: #f9fbff; padding: 4px 10px; border-radius: 4px; border: 1px solid #f0f4ff; }
.step-name-box { display: flex; align-items: center; gap: 5px; font-size: 13px; color: #606266; }
.step-num { color: #909399; font-weight: bold; }
.step-meta { display: flex; align-items: center; gap: 10px; }
.step-date { font-size: 12px; color: #909399; font-family: Consolas, monospace; }
.step-freq { zoom: 0.85; }
.step-progress { width: 120px; margin-left: 15px; display: flex; align-items: center; }
:deep(.step-progress .el-progress) { width: 100%; }
.remark-cell { cursor: pointer; padding: 4px 0; }
.remark-cell:hover .edit-icon { display: inline-flex !important; }
.remark-cell:hover { background-color: #f5f7fa; border-radius: 4px; padding-left: 5px; transition: background-color 0.2s; }
.step-manager { display: flex; flex-direction: column; gap: 15px; }
.step-add-panel { border: 1px solid #e4e7ed; border-radius: 4px; background: #f9fafe; }
.panel-header { padding: 8px 15px; border-bottom: 1px solid #ebeef5; font-weight: bold; color: #409EFF; font-size: 14px; display: flex; align-items: center; gap: 5px; }
.panel-form { padding: 15px; }
.gantt-container { background: #fff; padding: 20px 25px; border-radius: 8px; }
.gantt-header { display: flex; justify-content: space-between; align-items: center; color: #606266; font-size: 13px; border-bottom: 2px solid #e4e7ed; padding-bottom: 12px; margin-bottom: 25px; }
.date-node { font-weight: bold; font-family: Consolas, monospace; background: #f4f4f5; padding: 4px 10px; border-radius: 6px; display: flex; align-items: center; gap: 5px; color: #303133; }
.total-duration { font-size: 13px; color: #909399; letter-spacing: 1px; }
.gantt-body { display: flex; flex-direction: column; gap: 15px; }
.gantt-row { display: flex; align-items: center; padding: 10px 0; border-radius: 8px; transition: background-color 0.3s ease; }
.gantt-row:hover { background-color: #fafafa; }
.gantt-label { width: 140px; flex-shrink: 0; padding-right: 15px; display: flex; flex-direction: column; }
.gantt-label .stage-name { font-size: 14px; font-weight: bold; color: #303133; margin-bottom: 6px; }
.stage-metrics { display: flex; align-items: center; }
.progress-text { font-size: 12px; color: #e6a23c; font-weight: bold; }
.progress-text.is-done { color: #67c23a; }
.gantt-track { flex: 1; height: 26px; background-image: linear-gradient(90deg, #f0f2f5 1px, transparent 1px); background-size: 5% 100%; background-color: #fcfcfd; border-radius: 13px; position: relative; box-shadow: inset 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #ebeef5; }
.gantt-bar-capsule { position: absolute; top: 2px; height: 20px; background: #c3cbd3; border-radius: 10px; overflow: hidden; transition: all 0.3s; cursor: pointer; box-shadow: 0 2px 4px rgba(64, 158, 255, 0.1); }
.gantt-bar-capsule:hover { transform: translateY(-1px); box-shadow: 0 4px 8px rgba(64, 158, 255, 0.2); z-index: 2; }
.gantt-bar-fill { height: 100%; border-radius: 10px; transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1); position: relative; }
.gantt-bar-fill:not([style*="background-color: rgb(103, 194, 58)"]) { background-image: linear-gradient(45deg, rgba(255, 255, 255, 0.2) 25%, transparent 25%, transparent 50%, rgba(255, 255, 255, 0.2) 50%, rgba(255, 255, 255, 0.2) 75%, transparent 75%, transparent); background-size: 20px 20px; animation: progress-stripes 1s linear infinite; }
@keyframes progress-stripes { from { background-position: 20px 0; } to { background-position: 0 0; } }
.bar-tooltip { position: absolute; bottom: 30px; left: 50%; transform: translateX(-50%); background: rgba(48, 49, 51, 0.9); color: #fff; padding: 8px 12px; font-size: 12px; border-radius: 6px; white-space: nowrap; opacity: 0; pointer-events: none; transition: all 0.2s cubic-bezier(0.23, 1, 0.32, 1); z-index: 10; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
.bar-tooltip::after { content: ''; position: absolute; top: 100%; left: 50%; margin-left: -5px; border-width: 5px; border-style: solid; border-color: rgba(48, 49, 51, 0.9) transparent transparent transparent; }
.gantt-bar-capsule:hover .bar-tooltip { opacity: 1; bottom: 26px; }
.tip-date { font-weight: bold; margin-bottom: 4px; font-family: Consolas, monospace; color: #a0cfff;}
.tip-prog { color: #e6a23c; }
</style>