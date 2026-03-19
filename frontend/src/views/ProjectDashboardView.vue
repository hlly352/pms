<script setup>
import { ref, reactive, computed, onMounted, nextTick, onUnmounted, watch, h } from 'vue'
import { useRouter } from 'vue-router' 
import { getProjects } from '@/api/project'
import { DataLine, Money, Clock, Trophy, View, List, Warning, Search, Filter, Timer, Wallet } from '@element-plus/icons-vue' 
import * as echarts from 'echarts'

const router = useRouter() 

const loading = ref(false)
const projects = ref([]) // 原始全部数据

// 动态计算近3个月的日期
const getRecentMonthsRange = () => {
  const end = new Date();
  end.setMonth(end.getMonth() + 1); // 往后延1个月，看近期的未来
  const start = new Date();
  start.setMonth(start.getMonth() - 3); // 往前推3个月

  const formatDate = (d) => `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  return [formatDate(start), formatDate(end)];
}

// 查询条件表单
const queryParams = reactive({
  keyword: '',
  goal_id: null,
  dateRange: getRecentMonthsRange(), // 🌟 加上默认日期段
  isOverdue: false,   // 超期且未完成
  isOverBudget: false,// 实际花费 > 预算
  isOverTime: false   // 实际耗时 > 计划耗时
})

// 图表实例
const costChartRef = ref(null)
const timeChartRef = ref(null)
let costChartInstance = null
let timeChartInstance = null

// 详情弹窗相关状态
const detailDialogVisible = ref(false)
const currentProject = ref(null)
const activeTab = ref('overview') // 🌟 新增：控制弹窗中的 Tabs 标签页

// =======================
// 核心统计算法
// =======================
const getProjectTotalCost = (project) => {
  let total = 0
  project.stages?.forEach(stage => {
    stage.steps?.forEach(step => {
      total += Number(step.planned_cost) || 0
    })
  })
  return parseFloat(total.toFixed(2))
}

const getProjectTotalHours = (project) => {
  let total = 0
  project.stages?.forEach(stage => {
    stage.steps?.forEach(step => {
      total += Number(step.planned_hours) || 0
    })
  })
  return total > 0 ? parseFloat(total.toFixed(1)) : 0
}

const getCheckInRate = (project) => {
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

const getStageCheckInRate = (stage) => {
  let total = 0
  let completed = 0
  stage.steps?.forEach(step => {
    if (step.task) {
      total += step.task.total_details || 0
      completed += step.task.completed_details || 0
    }
  })
  return total === 0 ? 0 : Math.round((completed / total) * 100)
}

const getStageDateRange = (stage) => {
  if (stage.start_date || stage.end_date) {
    return { start: stage.start_date || '', end: stage.end_date || '' }
  }
  if (stage.segments && stage.segments.length > 0) {
    let min = stage.segments[0].start_date || ''; let max = stage.segments[0].end_date || ''
    stage.segments.forEach(seg => {
      if (seg.start_date && (!min || seg.start_date < min)) min = seg.start_date
      if (seg.end_date && (!max || seg.end_date > max)) max = seg.end_date
    })
    if (min || max) return { start: min, end: max }
  }
  if (stage.steps && stage.steps.length > 0) {
    let minDate = null; let maxDate = null
    stage.steps.forEach(step => {
      if (step.start_date) { const s = new Date(step.start_date).getTime(); if (!minDate || s < minDate) minDate = s }
      if (step.end_date) { const e = new Date(step.end_date).getTime(); if (!maxDate || e > maxDate) maxDate = e }
    })
    const formatDate = (ts) => {
      if (!ts) return ''
      const d = new Date(ts)
      return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
    }
    if (minDate || maxDate) return { start: formatDate(minDate), end: formatDate(maxDate) }
  }
  return { start: '', end: '' }
}

const getProjectDateRange = (project) => {
  const start = project.start_date || '未定'
  const end = project.end_date || '未定'
  return `${start} ~ ${end}`
}

const isProjectOverdue = (project) => {
  if (getCheckInRate(project) >= 100) return false
  if (!project.end_date) return false
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const end = new Date(project.end_date)
  end.setHours(0, 0, 0, 0)
  return today > end
}

// =======================
// 🌟 新增：时间明细树形数据与资金明细数据
// =======================

// 将 项目 -> 阶段 -> 步骤 -> 打卡记录 转换为适用于 el-table 的树形数据
const timeDetailTreeData = computed(() => {
  if (!currentProject.value || !currentProject.value.stages) return []
  
  return currentProject.value.stages.map(stage => {
    let stageActualHours = 0 // 累计该阶段的总耗时

    const steps = (stage.steps || []).map(step => {
      let stepActualHours = 0 // 累计该步骤的总耗时
      
      // 提取有效的已完成打卡记录
      const details = (step.task && step.task.details) ? step.task.details.filter(d => d.status === 'completed' && d.actual_hours > 0).map(d => {
        const h = Number(d.actual_hours) || 0
        stepActualHours += h
        return {
          id: `detail_${d.id}`,
          label: '✅ 实际打卡',
          type: 'detail',
          time: d.finished_at || d.task_time,
          actual_hours: h,
          remark: d.remark || '-'
        }
      }) : []

      stageActualHours += stepActualHours

      return {
        id: `step_${step.id}`,
        label: step.name,
        type: 'step',
        time: `${step.start_date || ''} ~ ${step.end_date || ''}`,
        actual_hours: stepActualHours > 0 ? stepActualHours : 0,
        remark: step.description,
        children: details.length > 0 ? details : null // null 避免展开空箭头
      }
    })

    return {
      id: `stage_${stage.id}`,
      label: stage.name,
      type: 'stage',
      time: `${getStageDateRange(stage).start} ~ ${getStageDateRange(stage).end}`,
      actual_hours: stageActualHours > 0 ? stageActualHours : 0,
      remark: '-',
      children: steps.length > 0 ? steps : null
    }
  })
})

// 直接读取当前项目下的交易流水
const fundDetailData = computed(() => {
  return currentProject.value?.transactions || []
})
// =======================
// 🌟 新增：明细表格的底部合计逻辑
// =======================

// 1. 时间消耗合计 (树形表：只累加最顶层的阶段耗时，防止重复翻倍计算)
const getTimeSummaries = (param) => {
  const { columns, data } = param
  const sums = []
  columns.forEach((column, index) => {
    if (index === 0) { sums[index] = '项目总耗时'; return }
    if (column.label === '实际耗时') {
      // 这里的 data 只有树的最顶层节点（阶段）
      const totalHours = data.reduce((sum, stage) => sum + (Number(stage.actual_hours) || 0), 0)
      sums[index] = h('span', { style: { color: '#E6A23C', fontWeight: 'bold', fontFamily: 'Consolas', fontSize: '15px' } }, `${totalHours.toFixed(2)} h`)
    } else {
      sums[index] = ''
    }
  })
  return sums
}

// 2. 资金消耗合计 (平铺表：支出算增加花费，收入算抵扣花费)
const getFundSummaries = (param) => {
  const { columns, data } = param
  const sums = []
  columns.forEach((column, index) => {
    if (index === 0) { sums[index] = '项目总花费'; return }
    if (column.label === '金额 (元)') {
      const totalCost = data.reduce((sum, item) => {
        const val = Number(item.amount) || 0
        // 如果是支出（expense），项目总花费增加；如果是收入（income），项目总花费抵扣减少
        return sum + (item.type === 'expense' ? val : -val) 
      }, 0)
      sums[index] = h('span', { style: { color: totalCost >= 0 ? '#F56C6C' : '#67C23A', fontWeight: 'bold', fontFamily: 'Consolas', fontSize: '15px' } }, `￥${totalCost.toFixed(2)}`)
    } else {
      sums[index] = ''
    }
  })
  return sums
}

// =======================
// 数据过滤与动态计算
// =======================
const goalOptions = computed(() => {
  const goalsMap = new Map()
  projects.value.forEach(p => {
    if (p.goal && p.goal.id) {
      goalsMap.set(p.goal.id, p.goal.name)
    }
  })
  return Array.from(goalsMap.entries()).map(([id, name]) => ({ id, name }))
})

const filteredProjects = computed(() => {
  return projects.value.filter(p => {
    if (queryParams.keyword && !p.name.toLowerCase().includes(queryParams.keyword.toLowerCase())) return false
    if (queryParams.goal_id && p.goal?.id !== queryParams.goal_id) return false
    if (queryParams.isOverdue && !isProjectOverdue(p)) return false
    if (queryParams.isOverBudget) {
       const plannedBudget = Number(p.planned_budget) || 0
       const actualCost = Number(p.actual_total_cost) || 0
       if (plannedBudget === 0 || actualCost <= plannedBudget) return false
    }
    if (queryParams.isOverTime) {
       const plannedHours = getProjectTotalHours(p)
       const actualHours = Number(p.actual_total_hours) || 0
       if (plannedHours === 0 || actualHours <= plannedHours) return false
    }
    return true
  })
})

const summary = computed(() => {
  let tBudget = 0, tAllocated = 0, tSpent = 0, tPlannedH = 0, tActualH = 0
  filteredProjects.value.forEach(p => {
    tBudget += Number(p.planned_budget) || 0
    tAllocated += getProjectTotalCost(p)
    tSpent += Number(p.actual_total_cost) || 0
    tPlannedH += getProjectTotalHours(p)
    tActualH += Number(p.actual_total_hours) || 0
  })
  return {
    totalProjects: filteredProjects.value.length,
    totalBudget: tBudget,
    totalAllocated: tAllocated,
    totalSpent: tSpent,
    totalPlannedHours: tPlannedH,
    totalActualHours: tActualH
  }
})

const loadData = async () => {
  loading.value = true
  try {
    // 🌟 1. 构造要传给后端的参数
    const params = { 
      per_page: 500, // 大盘还是需要尽量取多一点数据渲染
      name: queryParams.keyword,
      goal_id: queryParams.goal_id
    }

    // 🌟 2. 提取日期范围并传给后端
    if (queryParams.dateRange && queryParams.dateRange.length === 2) {
      params.start_date = queryParams.dateRange[0]
      params.end_date = queryParams.dateRange[1]
    }

    // 🌟 3. 发起请求
    const res = await getProjects(params) 
    projects.value = res.data?.data || res.data || []
  } catch (error) {
    console.error('获取项目数据失败:', error)
  } finally {
    loading.value = false
  }
}

const openProjectDetail = (row) => {
  currentProject.value = row
  activeTab.value = 'overview' // 每次打开弹窗默认显示“项目概览”
  detailDialogVisible.value = true
}

const initCharts = () => {
  if (!costChartInstance) costChartInstance = echarts.init(costChartRef.value)
  if (!timeChartInstance) timeChartInstance = echarts.init(timeChartRef.value)

  const sourceData = filteredProjects.value
  const projectNames = sourceData.map(p => p.name.length > 8 ? p.name.substring(0, 8) + '...' : p.name)
  
  const budgets = sourceData.map(p => Number(p.planned_budget) || 0)
  const allocatedCosts = sourceData.map(p => getProjectTotalCost(p))
  const spents = sourceData.map(p => Number(p.actual_total_cost) || 0)
  
  const plannedHours = sourceData.map(p => getProjectTotalHours(p))
  const actualHours = sourceData.map(p => Number(p.actual_total_hours) || 0)

  costChartInstance.setOption({
    title: { text: '项目资金健康度 (仅展示筛选结果)', textStyle: { fontSize: 14, color: '#606266' } },
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
    legend: { data: ['项目总预算', '步骤已分配', '实际已花费'], top: 0, right: 0 },
    grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
    xAxis: { type: 'value', name: '金额 (元)' },
    yAxis: { type: 'category', data: projectNames, inverse: true },
    series: [
      { name: '项目总预算', type: 'bar', data: budgets, itemStyle: { color: '#67C23A', borderRadius: [0, 4, 4, 0] }, barGap: '10%' },
      { name: '步骤已分配', type: 'bar', data: allocatedCosts, itemStyle: { color: '#E6A23C', borderRadius: [0, 4, 4, 0] } },
      { name: '实际已花费', type: 'bar', data: spents, itemStyle: { color: '#F56C6C', borderRadius: [0, 4, 4, 0] } }
    ]
  }, true) 

  timeChartInstance.setOption({
    title: { text: '项目执行工时对比 (仅展示筛选结果)', textStyle: { fontSize: 14, color: '#606266' } },
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
    legend: { data: ['计划总工时', '实际已耗时'], top: 0, right: 0 },
    grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
    xAxis: { type: 'value', name: '小时 (h)' },
    yAxis: { type: 'category', data: projectNames, inverse: true },
    series: [
      { name: '计划总工时', type: 'bar', data: plannedHours, itemStyle: { color: '#409EFF', borderRadius: [0, 4, 4, 0] } },
      { name: '实际已耗时', type: 'bar', data: actualHours, itemStyle: { color: '#E6A23C', borderRadius: [0, 4, 4, 0] } }
    ]
  }, true)
}

watch(filteredProjects, () => {
  nextTick(() => { initCharts() })
}, { deep: true })

const handleResize = () => {
  costChartInstance?.resize()
  timeChartInstance?.resize()
}

onMounted(() => {
  loadData()
  window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
  window.removeEventListener('resize', handleResize)
  costChartInstance?.dispose()
  timeChartInstance?.dispose()
})

const resetSearch = () => {
  queryParams.keyword = ''
  queryParams.goal_id = null
  queryParams.isOverdue = false
  queryParams.isOverBudget = false
  queryParams.isOverTime = false
}
</script>

<template>
  <div class="dashboard-wrapper" v-loading="loading">

    <el-card shadow="never" class="search-card">
      <el-form :inline="true" :model="queryParams" class="search-form">
        
        <el-form-item label="所属目标">
          <el-select v-model="queryParams.goal_id" placeholder="全部目标" clearable style="width: 150px">
            <el-option v-for="item in goalOptions" :key="item.id" :label="item.name" :value="item.id" />
          </el-select>
        </el-form-item>
        
        <el-form-item label="项目名称">
          <el-input v-model="queryParams.keyword" placeholder="搜索项目名..." clearable :prefix-icon="Search" style="width: 180px" />
        </el-form-item>
        <el-form-item label="项目周期">
          <el-date-picker
            v-model="queryParams.dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始范围"
            end-placeholder="结束范围"
            value-format="YYYY-MM-DD"
            style="width: 240px"
            @change="loadData" 
          />
        </el-form-item>
        <el-form-item label="风险排查">
           <el-checkbox v-model="queryParams.isOverdue" style="margin-right: 15px;">
              <span style="color: #F56C6C; font-weight: bold;">超期未完</span>
           </el-checkbox>
           <el-checkbox v-model="queryParams.isOverBudget" style="margin-right: 15px;">
              <span style="color: #F56C6C; font-weight: bold;">资金透支</span>
           </el-checkbox>
           <el-checkbox v-model="queryParams.isOverTime">
              <span style="color: #E6A23C; font-weight: bold;">工时超标</span>
           </el-checkbox>
        </el-form-item>

        <el-form-item style="margin-right: 0; float: right;">
           <el-button @click="resetSearch" :icon="Filter">重置筛选</el-button>
        </el-form-item>
      </el-form>
    </el-card>
    
    <el-row :gutter="20" class="stat-row">
      <el-col :xs="12" :md="6">
        <el-card shadow="hover" class="data-card border-blue">
          <div class="card-icon bg-blue"><el-icon><DataLine /></el-icon></div>
          <div class="card-info">
            <div class="card-title">筛选项目数</div>
            <div class="card-value">{{ summary.totalProjects }} 个</div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="12" :md="6">
        <el-card shadow="hover" class="data-card border-green">
          <div class="card-icon bg-green"><el-icon><Money /></el-icon></div>
          <div class="card-info">
            <div class="card-title">筛选总预算天花板</div>
            <div class="card-value">￥{{ summary.totalBudget.toFixed(2) }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="12" :md="6">
        <el-card shadow="hover" class="data-card border-red">
          <div class="card-icon bg-red"><el-icon><Money /></el-icon></div>
          <div class="card-info">
            <div class="card-title">当前真实花费 / 已分配</div>
            <div class="card-value" style="font-size: 18px;">
              <span style="color: #F56C6C">￥{{ summary.totalSpent.toFixed(2) }}</span>
              <span style="color: #909399; font-size: 14px; font-weight: normal;"> / ￥{{ summary.totalAllocated.toFixed(2) }}</span>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :xs="12" :md="6">
        <el-card shadow="hover" class="data-card border-orange">
          <div class="card-icon bg-orange"><el-icon><Clock /></el-icon></div>
          <div class="card-info">
            <div class="card-title">当前耗时 / 计划工时</div>
            <div class="card-value" style="font-size: 18px;">
              <span style="color: #E6A23C">{{ summary.totalActualHours.toFixed(1) }}</span> 
              <span style="color: #909399; font-size: 14px; font-weight: normal;"> / {{ summary.totalPlannedHours.toFixed(1) }} h</span>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="20" class="chart-row">
      <el-col :span="12"><el-card shadow="never" class="chart-card"><div ref="costChartRef" class="echarts-box"></div></el-card></el-col>
      <el-col :span="12"><el-card shadow="never" class="chart-card"><div ref="timeChartRef" class="echarts-box"></div></el-card></el-col>
    </el-row>

    <el-card shadow="never" class="table-card">
      <template #header>
        <div style="font-weight: bold; color: #303133; display: flex; align-items: center;">
          <el-icon style="margin-right: 6px; color: #409EFF"><Trophy /></el-icon> 项目执行健康度明细
        </div>
      </template>

      <el-table :data="filteredProjects" border stripe style="width: 100%" height="400">
        
        <el-table-column label="项目档案" min-width="190" fixed>
          <template #default="{ row }">
            <div style="font-weight: bold; color: #303133; font-size: 14px; display: flex; align-items: center; gap: 4px;">
              {{ row.name }}
              <el-tooltip v-if="isProjectOverdue(row)" content="⚠️ 该项目已超期但仍未完成100%打卡" placement="top">
                 <el-icon style="color: #F56C6C; font-size: 16px;"><Warning /></el-icon>
              </el-tooltip>
            </div>
            <div style="font-size: 12px; color: #909399; margin-top: 4px;">
              🎯 目标：{{ row.goal?.name || '未关联' }}
            </div>
            <div style="font-size: 11px; color: #E6A23C; margin-top: 2px; font-family: Consolas;">
              📅 {{ getProjectDateRange(row) }}
            </div>
          </template>
        </el-table-column>
        
        <el-table-column label="完成情况" width="100" align="center">
          <template #default="{ row }">
            <el-progress type="circle" :percentage="getCheckInRate(row)" :width="48" :stroke-width="4" :color="getCheckInRate(row) === 100 ? '#67C23A' : (isProjectOverdue(row) ? '#F56C6C' : '#409EFF')">
              <template #default="{ percentage }"><span style="font-size: 12px; font-family: Consolas;">{{ percentage }}%</span></template>
            </el-progress>
          </template>
        </el-table-column>

        <el-table-column label="资金健康度对比" min-width="260">
          <template #default="{ row }">
            <div class="progress-cell">
              <div class="progress-labels">
                <span title="实际记账花费" style="color: #F56C6C">已花: ￥{{ row.actual_total_cost || 0 }}</span>
                <span title="步骤排期中分出去的钱" style="color: #E6A23C">分配: ￥{{ getProjectTotalCost(row) }}</span>
                <span title="项目总预算" style="color: #67C23A">预算: ￥{{ row.planned_budget || 0 }}</span>
              </div>
              <el-progress :percentage="Math.min(((row.actual_total_cost || 0) / (Math.max(row.planned_budget, 1))) * 100, 100) || 0" :status="((row.actual_total_cost || 0) > (row.planned_budget || 0)) ? 'exception' : 'success'" :stroke-width="8" :show-text="false" />
            </div>
          </template>
        </el-table-column>

        <el-table-column label="工时健康度对比" min-width="200">
          <template #default="{ row }">
            <div class="progress-cell">
              <div class="progress-labels">
                <span style="color: #E6A23C">已耗时: {{ row.actual_total_hours || 0 }} h</span>
                <span style="color: #409EFF">计划: {{ getProjectTotalHours(row) }} h</span>
              </div>
              <el-progress :percentage="Math.min(((row.actual_total_hours || 0) / (getProjectTotalHours(row) || 1)) * 100, 100) || 0" :stroke-width="8" :status="((row.actual_total_hours || 0) > getProjectTotalHours(row)) ? 'exception' : ''" :show-text="false" />
            </div>
          </template>
        </el-table-column>

        <el-table-column label="操作" width="90" align="center" fixed="right">
          <template #default="{ row }"><el-button type="primary" link :icon="View" @click="openProjectDetail(row)">详情</el-button></template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog v-model="detailDialogVisible" :title="`项目全景档案 - ${currentProject?.name}`" width="950px" top="6vh">
      <div v-if="currentProject" class="project-detail-box">
        
        <el-tabs v-model="activeTab" class="custom-tabs">
          
          <el-tab-pane label="📋 项目概览" name="overview">
             <el-descriptions :column="2" border style="margin-top: 10px;">
                <el-descriptions-item label="项目名称"><strong>{{ currentProject.name }}</strong></el-descriptions-item>
                <el-descriptions-item label="所属目标">{{ currentProject.goal?.name || '未关联' }}</el-descriptions-item>
                <el-descriptions-item label="预期结果" :span="2">{{ currentProject.expected_result || '未填写' }}</el-descriptions-item>
                <el-descriptions-item label="起止周期"><span style="font-family: Consolas; font-weight:bold;">{{ getProjectDateRange(currentProject) }}</span></el-descriptions-item>
                <el-descriptions-item label="总打卡完成率"><span style="color: #67C23A; font-weight: bold; font-family: Consolas;">{{ getCheckInRate(currentProject) }}%</span></el-descriptions-item>
                <el-descriptions-item label="项目总预算"><span style="color: #67C23A; font-weight: bold; font-family: Consolas;">￥{{ currentProject.planned_budget || 0 }}</span></el-descriptions-item>
                <el-descriptions-item label="计划总工时"><span style="color: #409EFF; font-weight: bold; font-family: Consolas;">{{ getProjectTotalHours(currentProject) }} h</span></el-descriptions-item>
                <el-descriptions-item label="步骤已分配"><span style="color: #E6A23C; font-family: Consolas;">￥{{ getProjectTotalCost(currentProject) }}</span></el-descriptions-item>
                <el-descriptions-item label="实际总耗时"><span style="color: #E6A23C; font-weight: bold; font-family: Consolas;">{{ currentProject.actual_total_hours || 0 }} h</span></el-descriptions-item>
                <el-descriptions-item label="实际已花费" :span="2"><span style="color: #F56C6C; font-weight: bold; font-size: 16px; font-family: Consolas;">￥{{ currentProject.actual_total_cost || 0 }}</span></el-descriptions-item>
             </el-descriptions>

             <div v-if="currentProject.stages && currentProject.stages.length > 0" class="stages-section">
                <div class="section-title">
                  <el-icon style="margin-right: 6px; color: #409EFF;"><List /></el-icon> 阶段宏观执行明细
                </div>
                <el-table :data="currentProject.stages" border stripe size="small" style="width: 100%">
                  <el-table-column type="index" label="序号" width="60" align="center" />
                  <el-table-column prop="name" label="阶段名称" min-width="120" />
                  <el-table-column label="起止时间" min-width="180" align="center">
                    <template #default="{ row }">
                      <span style="font-size: 12px; color: #606266; font-family: Consolas; font-weight: bold;">
                        {{ getStageDateRange(row).start || '未定' }} ~ {{ getStageDateRange(row).end || '未定' }}
                      </span>
                    </template>
                  </el-table-column>
                  <el-table-column label="权重" width="80" align="center">
                    <template #default="{ row }">{{ row.weight !== undefined ? row.weight : 0 }}%</template>
                  </el-table-column>
                  <el-table-column label="打卡完成率" width="160" align="center">
                    <template #default="{ row }">
                      <el-progress :percentage="getStageCheckInRate(row)" :stroke-width="8" :color="getStageCheckInRate(row) === 100 ? '#67C23A' : '#409EFF'" />
                    </template>
                  </el-table-column>
                </el-table>
             </div>
          </el-tab-pane>

          <el-tab-pane label="⏱️ 时间消耗明细" name="time">
            <el-table 
            :data="timeDetailTreeData" 
            row-key="id" 
            border 
            default-expand-all 
            style="width: 100%; margin-top: 10px;" 
            :tree-props="{children: 'children', hasChildren: 'hasChildren'}"
            show-summary 
            :summary-method="getTimeSummaries" 
          >
              <el-table-column prop="label" label="执行节点 (阶段 / 步骤 / 打卡记录)" min-width="220" show-overflow-tooltip>
                <template #default="{ row }">
                  <span :style="{ fontWeight: row.type === 'stage' ? 'bold' : 'normal', color: row.type === 'detail' ? '#67C23A' : '#303133' }">
                    {{ row.label }}
                  </span>
                </template>
              </el-table-column>
              
              <el-table-column label="节点类型" width="100" align="center">
                <template #default="{ row }">
                  <el-tag v-if="row.type === 'stage'" size="small" type="primary" effect="dark">实施阶段</el-tag>
                  <el-tag v-else-if="row.type === 'step'" size="small" type="warning" effect="plain">具体步骤</el-tag>
                  <el-tag v-else size="small" type="success" effect="plain">打卡记录</el-tag>
                </template>
              </el-table-column>
              
              <el-table-column prop="time" label="发生时间 / 周期" width="190" align="center">
                <template #default="{ row }">
                  <span style="font-family: Consolas; font-size: 12px; color: #909399">{{ row.time || '-' }}</span>
                </template>
              </el-table-column>
              
              <el-table-column label="实际耗时" width="100" align="right">
                <template #default="{ row }">
                  <span v-if="row.actual_hours > 0" style="color: #E6A23C; font-weight: bold; font-family: Consolas;">
                    {{ row.actual_hours }} h
                  </span>
                  <span v-else style="color: #c0c4cc">-</span>
                </template>
              </el-table-column>

              <el-table-column prop="remark" label="详情/备注" min-width="150" show-overflow-tooltip />
            </el-table>
          </el-tab-pane>

          <el-tab-pane label="💰 资金消耗明细" name="funds">
            <el-table 
            :data="fundDetailData" 
            border 
            stripe 
            style="width: 100%; margin-top: 10px;"
            show-summary 
            :summary-method="getFundSummaries" 
          >
               <el-table-column type="index" label="序号" width="60" align="center" />
               <el-table-column prop="transaction_date" label="交易日期" width="120" align="center">
                 <template #default="{ row }"><span style="font-family: Consolas;">{{ row.transaction_date }}</span></template>
               </el-table-column>
               
               <el-table-column label="金额 (元)" width="120" align="right">
                 <template #default="{ row }">
                   <span :style="{ color: row.type === 'expense' ? '#F56C6C' : '#67C23A', fontWeight: 'bold', fontFamily: 'Consolas' }">
                     {{ row.type === 'expense' ? '-' : '+' }}{{ Number(row.amount).toFixed(2) }}
                   </span>
                 </template>
               </el-table-column>
               
               <el-table-column label="扣款账户" width="120" align="center">
                 <template #default="{ row }">
                   <span style="color: #409EFF; font-size: 12px;"><el-icon style="vertical-align: middle;"><Wallet /></el-icon> {{ row.account?.name || '-' }}</span>
                 </template>
               </el-table-column>

               <el-table-column prop="remark" label="用途备注" min-width="180" show-overflow-tooltip />
               
               <template #empty>
                 <el-empty description="该项目暂无真实资金流出记录" :image-size="80" />
               </template>
            </el-table>
          </el-tab-pane>

        </el-tabs>

      </div>
      <template #footer>
        <el-button @click="detailDialogVisible = false" type="primary" plain>关闭档案</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
.dashboard-wrapper { padding: 20px; background-color: #f0f2f5; min-height: calc(100vh - 84px); }

/* 查询卡片样式 */
.search-card { margin-bottom: 20px; border-radius: 8px; border: none; }
.search-form { display: flex; flex-wrap: wrap; align-items: center; }
.search-form .el-form-item { margin-bottom: 0; margin-right: 25px; }

.stat-row { margin-bottom: 20px; }
.data-card { border-radius: 8px; border: none; }
.data-card :deep(.el-card__body) { padding: 15px 20px; display: flex; align-items: center; }
.border-blue { border-bottom: 3px solid #409EFF; }
.border-green { border-bottom: 3px solid #67C23A; }
.border-red { border-bottom: 3px solid #F56C6C; }
.border-orange { border-bottom: 3px solid #E6A23C; }
.card-icon { width: 48px; height: 48px; border-radius: 8px; display: flex; justify-content: center; align-items: center; font-size: 24px; color: #fff; margin-right: 15px; }
.bg-blue { background: #409EFF; }
.bg-green { background: #67C23A; }
.bg-red { background: #F56C6C; }
.bg-orange { background: #E6A23C; }
.card-info { flex: 1; }
.card-title { font-size: 13px; color: #909399; margin-bottom: 5px; }
.card-value { font-size: 22px; font-weight: bold; color: #303133; font-family: Consolas, monospace; }
.chart-row { margin-bottom: 20px; }
.chart-card { border-radius: 8px; }
.echarts-box { width: 100%; height: 350px; }
.table-card { border-radius: 8px; }
.progress-cell { padding: 5px 0; }
.progress-labels { display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 6px; font-family: Consolas, monospace; font-weight: bold; }
.project-detail-box :deep(.el-descriptions__label) { width: 120px; background-color: #fafafa; color: #606266; font-weight: bold; }
.project-detail-box :deep(.el-descriptions__content) { color: #303133; }
.stages-section { margin-top: 25px; }
.section-title { font-size: 14px; font-weight: bold; color: #303133; margin-bottom: 12px; display: flex; align-items: center; padding-left: 5px; border-left: 3px solid #409EFF; }

/* 自定义 Tabs 样式 */
.custom-tabs :deep(.el-tabs__item) { font-size: 15px; font-weight: bold; }
.custom-tabs :deep(.el-tabs__header) { margin-bottom: 10px; }

:deep(.el-table__footer-wrapper tbody td.el-table__cell) {
  background-color: #fafafa;
  font-weight: bold;
}
</style>