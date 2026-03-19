<script setup>
import { ref, onMounted, nextTick, onUnmounted, computed } from 'vue' // 🌟 引入 computed
import { getTimeDashboardData } from '@/api/time_overview'
import { updateTaskDetailStatus } from '@/api/task' 
import { ElMessage } from 'element-plus'
import * as echarts from 'echarts'
import { 
  Clock, BottomRight, TopRight, Calendar, RefreshLeft, 
  Timer, Aim, List, Check, DataLine 
} from '@element-plus/icons-vue'

const loading = ref(true)
const accounts = ref([])
const totalInflow = ref(0)
const todayTasks = ref([]) 
const outflowStats = ref([]) // 🌟 新增：存储原始消耗分类数据以便计算总计

const searchDateRange = ref([])
const isCustomDate = ref(false) 

const outflowChartRef = ref(null)
const trendChartRef = ref(null) 
let outflowChart = null
let trendChart = null

// 🌟 1. 计算期间账户总消耗
const totalAccountOutflow = computed(() => {
  return outflowStats.value.reduce((sum, item) => sum + item.value, 0)
})

// 🌟 2. 计算当前所有时间池总储备(剩余时间)
const totalPoolBalance = computed(() => {
  return accounts.value.reduce((sum, acc) => sum + Number(acc.balance_hours || 0), 0)
})

const loadData = async (isSilent = false) => {
  if (!isSilent) loading.value = true
  try {
    const params = {}
    if (searchDateRange.value && searchDateRange.value.length === 2) {
      params.start_date = searchDateRange.value[0]
      params.end_date = searchDateRange.value[1]
    }

    const res = await getTimeDashboardData(params)
    const data = res.data || res
    
    searchDateRange.value = data.date_range
    accounts.value = data.accounts
    totalInflow.value = data.total_inflow
    todayTasks.value = data.today_pending_tasks || [] 
    outflowStats.value = data.outflow_stats || [] // 🌟 保存以便计算

    await nextTick()
    renderOutflowChart(data.outflow_stats)
    renderTrendChart(data.trend_data)
  } catch (error) {
    console.error('时间大盘加载失败', error)
  } finally {
    if (!isSilent) loading.value = false
  }
}

const confirmCompleteTask = async (row) => {
  const h = Number(row.temp_hours_h) || 0;
  const m = Number(row.temp_hours_m) || 0;
  const totalHours = Number((h + (m / 60)).toFixed(2));

  try {
    await updateTaskDetailStatus(row.id, {
       status: 'completed',
       actual_hours: totalHours
    })
    
    ElMessage.success(`🎉 打卡成功！用时 ${h}小时 ${m}分钟，已自动处理`);
    document.body.click(); 

    const index = todayTasks.value.indexOf(row)
    if (index > -1) todayTasks.value.splice(index, 1)
    
    loadData(true)
  } catch (e) {
    console.error('打卡失败', e)
  }
}

const handleDateChange = () => {
  if (searchDateRange.value) {
    isCustomDate.value = true
    loadData()
  } else {
    resetToDefault() 
  }
}

const resetToDefault = () => {
  searchDateRange.value = [] 
  isCustomDate.value = false
  loadData()
}

const renderOutflowChart = (data) => {
  if (!outflowChartRef.value) return
  if (!outflowChart) outflowChart = echarts.init(outflowChartRef.value)
  
  outflowChart.setOption({
    tooltip: { trigger: 'item', formatter: '{b}: {c}h ({d}%)' },
    legend: { type: 'scroll', bottom: '0%', left: 'center' },
    series: [{
      name: '期间时间消耗',
      type: 'pie',
      radius: ['15%', '50%'], 
      center: ['50%', '42%'], 
      roseType: 'area', 
      itemStyle: { borderRadius: 6 },
      label: { show: true, formatter: '{b}\n{d}%', lineHeight: 16, color: '#606266' },
      labelLine: { show: true, length: 10, length2: 12, smooth: true },
      data: data?.length > 0 ? data : [{ name: '暂无数据', value: 0 }]
    }]
  })
}

const renderTrendChart = (trendData) => {
  if (!trendChartRef.value) return
  if (!trendChart) trendChart = echarts.init(trendChartRef.value)
  
  const dates = trendData?.dates || []
  const series = trendData?.series || []
  
  trendChart.clear()

  trendChart.setOption({
    tooltip: { 
      trigger: 'axis',
      formatter: function (params) {
        let html = `<div style="font-weight:bold; margin-bottom:5px;">${params[0].name}</div>`;
        params.forEach(item => {
            html += `${item.marker} ${item.seriesName}: <span style="font-weight:bold; color:#409EFF">${item.value} h</span><br/>`;
        });
        return html;
      }
    },
    legend: { top: '0%', type: 'scroll' },
    grid: { top: '15%', left: '3%', right: '3%', bottom: '5%', containLabel: true },
    xAxis: {
      type: 'category',
      boundaryGap: false,
      data: dates,
      axisLabel: { formatter: (val) => val ? String(val).substring(5) : '' } 
    },
    yAxis: { 
      type: 'value',
      name: '消耗 (h)',
      splitLine: { lineStyle: { type: 'dashed', color: '#ebeef5' } }
    },
    series: series.length > 0 ? series : [{ name: '暂无数据', type: 'line', data: [] }]
  }, true)
}

const calcPercentage = (outflow, inflow, balance) => {
  const totalAvailable = (Number(balance) + Number(outflow)) || 1
  const percent = (Number(outflow) / totalAvailable) * 100
  return Math.min(Math.max(percent, 0), 100).toFixed(1)
}

const handleResize = () => {
  outflowChart?.resize()
  trendChart?.resize()
}

onMounted(() => {
  loadData()
  window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
  window.removeEventListener('resize', handleResize)
  outflowChart?.dispose()
  trendChart?.dispose()
})
</script>

<template>
  <div class="dashboard-container" v-loading="loading">
    
    <div class="header-banner">
      <div class="title-box">
        <el-icon class="title-icon"><Clock /></el-icon>
        <span class="title-text">个人时间流转引擎</span>
      </div>
      
      <div class="filter-box">
        <span class="date-label" :class="{ 'is-custom': isCustomDate }">
          <el-icon style="vertical-align: middle; margin-right: 4px;"><Calendar /></el-icon>
          {{ isCustomDate ? '当前查询区间 :' : '当前专属时间段 :' }}
        </span>
        <el-date-picker
          v-model="searchDateRange"
          type="daterange"
          range-separator="至"
          start-placeholder="开始日期"
          end-placeholder="结束日期"
          value-format="YYYY-MM-DD"
          @change="handleDateChange"
          style="width: 250px; margin-right: 10px;"
          clearable
        />
        <el-button v-if="isCustomDate" type="warning" plain :icon="RefreshLeft" @click="resetToDefault">回到本期</el-button>
      </div>
    </div>

    <el-row :gutter="20" class="main-row">
      
      <el-col :span="9">
        <el-card shadow="hover" class="panel-card center-panel">
          <template #header>
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <div class="panel-title text-primary"><el-icon style="vertical-align: -2px;"><Timer /></el-icon> 时间池储备与消耗</div>
              
              <div style="display: flex; gap: 8px;">
                <div style="font-size: 12px; color: #67C23A; font-weight: bold; background: #f0f9eb; padding: 2px 8px; border-radius: 4px; border: 1px solid #e1f3d8;">
                  注入: +{{ Number(totalInflow).toFixed(1) }} h
                </div>
                <div style="font-size: 12px; color: #409EFF; font-weight: bold; background: #ecf5ff; padding: 2px 8px; border-radius: 4px; border: 1px solid #d9ecff;">
                  剩余储备: {{ Number(totalPoolBalance).toFixed(1) }} h
                </div>
              </div>

            </div>
          </template>
          
          <div class="scroll-area">
            <div v-for="acc in accounts" :key="acc.id" class="acc-item">
              <div class="acc-header">
                <span class="acc-name" :style="{ borderLeftColor: acc.color || '#409EFF' }">{{ acc.name }}</span>
                <span class="acc-balance" :style="{ color: acc.color || '#409EFF' }">{{ Number(acc.balance_hours).toFixed(1) }} h</span>
              </div>
              <div class="acc-stats">
                <div class="stat-block text-success">
                  <el-icon><BottomRight /></el-icon> 注入: +{{ Number(acc.month_inflow).toFixed(1) }}h
                </div>
                <div class="stat-block text-danger">
                  <el-icon><TopRight /></el-icon> 消耗: -{{ Number(acc.month_outflow).toFixed(1) }}h
                </div>
              </div>
              <el-progress 
                :percentage="Number(calcPercentage(acc.month_outflow, acc.month_inflow, acc.balance_hours))" 
                :color="acc.color || '#409EFF'"
                :stroke-width="10"
                style="margin-top: 10px;"
              />
              <div class="progress-label">当前时间池消耗率</div>
            </div>
            <el-empty v-if="accounts.length === 0" description="暂无正常状态的时间账户" />
          </div>
        </el-card>
      </el-col>

      <el-col :span="8">
        <el-card shadow="hover" class="panel-card">
          <template #header>
             <div style="display: flex; justify-content: space-between; align-items: center;">
                <div class="panel-title text-danger"><el-icon style="vertical-align: -2px;"><Aim /></el-icon> 期间时间消耗去向</div>
                <div style="font-size: 12px; color: #F56C6C; font-weight: bold; background: #fef0f0; padding: 2px 8px; border-radius: 4px; border: 1px solid #fde2e2;">
                   账户消耗总计: -{{ Number(totalAccountOutflow).toFixed(1) }} h
                </div>
             </div>
          </template>
          <div ref="outflowChartRef" class="chart-box"></div> 
        </el-card>
      </el-col>

      <el-col :span="7">
        <el-card shadow="hover" class="panel-card">
          <template #header>
             <div style="display: flex; justify-content: space-between; align-items: center;">
                <div class="panel-title" style="color: #E6A23C;"><el-icon style="vertical-align: -2px;"><List /></el-icon> 今日待办任务指引</div>
                <div style="font-size: 12px; color: #E6A23C; font-weight: bold; background: #fdf6ec; padding: 2px 8px; border-radius: 4px; border: 1px solid #faecd8;">
                   期间总消耗: -{{ Number(totalAccountOutflow).toFixed(1) }} h
                </div>
             </div>
          </template>
          
          <div class="scroll-area">
             <div v-for="task in todayTasks" :key="task.id" class="today-task-item">
                <div class="task-time">{{ task.task_time }}</div>
                <div class="task-info">
                   <div class="task-name" :title="task.task_name">{{ task.task_name }}</div>
                   <div class="task-remark" v-if="task.remark" :title="task.remark">{{ task.remark }}</div>
                </div>
                <div class="task-action">
                   <el-popover placement="left" width="260" trigger="click">
                      <div style="margin-bottom: 12px; font-size: 13px; color: #606266; font-weight: bold;">
                         <el-icon style="vertical-align: middle;"><Timer /></el-icon> 本次实际耗时
                      </div>
                      <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 15px; font-size: 13px; color: #606266;">
                          <el-input-number v-model="task.temp_hours_h" :min="0" :step="1" :precision="0" size="small" style="width: 85px;" placeholder="0" controls-position="right" />
                          <span>小时</span>
                          <el-input-number v-model="task.temp_hours_m" :min="0" :max="59" :step="5" :precision="0" size="small" style="width: 85px;" placeholder="0" controls-position="right" />
                          <span>分</span>
                      </div>
                      <div style="text-align: right; margin: 0">
                         <el-button size="small" type="primary" @click="confirmCompleteTask(task)">确认完成</el-button>
                      </div>
                      <template #reference>
                         <el-button type="success" circle size="small" :icon="Check" title="点击完成并记录时间" />
                      </template>
                   </el-popover>
                </div>
             </div>
             <el-empty v-if="todayTasks.length === 0" description="今日暂无待办任务，好好休息吧~" :image-size="80" />
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="20" style="margin-top: 20px;">
      <el-col :span="24">
        <el-card shadow="hover" class="trend-card">
          <template #header><div class="panel-title text-success"><el-icon style="vertical-align: -2px;"><DataLine /></el-icon> 每日时间消耗趋势</div></template>
          <div ref="trendChartRef" class="trend-chart-box"></div>
        </el-card>
      </el-col>
    </el-row>

  </div>
</template>

<style scoped>
.dashboard-container { padding: 20px; background: #f0f2f5; min-height: calc(100vh - 84px); }

.header-banner { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; background: white; padding: 15px 25px; border-radius: 8px; box-shadow: 0 2px 12px 0 rgba(0,0,0,0.05); }
.title-box { display: flex; align-items: center; }
.title-icon { font-size: 24px; color: #7b61ff; margin-right: 10px; }
.title-text { font-size: 20px; font-weight: bold; color: #303133; letter-spacing: 1px; }

.filter-box { display: flex; align-items: center; }
.date-label { font-size: 14px; font-weight: bold; color: #E6A23C; margin-right: 10px; background: #fdf6ec; padding: 6px 12px; border-radius: 4px; border: 1px solid #faecd8; transition: all 0.3s; }
.date-label.is-custom { color: #7b61ff; background: #f3f0ff; border-color: #e5dfff; }

.main-row { display: flex; align-items: stretch; }

.panel-card { border-radius: 10px; height: 500px; display: flex; flex-direction: column; }
.panel-card :deep(.el-card__body) { flex: 1; padding: 15px; display: flex; flex-direction: column; overflow: hidden; }

.trend-card { border-radius: 10px; height: 320px; display: flex; flex-direction: column; }
.trend-card :deep(.el-card__body) { flex: 1; padding: 10px 15px; display: flex; flex-direction: column; overflow: hidden; }
.trend-chart-box { flex: 1; width: 100%; min-height: 230px; }

.panel-title { font-size: 16px; font-weight: bold; text-align: left; }
.text-success { color: #67C23A; }
.text-primary { color: #409EFF; }
.text-danger { color: #F56C6C; }

.chart-box { flex: 1; width: 100%; min-height: 200px; }

.scroll-area { flex: 1; overflow-y: auto; padding-right: 5px; }
.scroll-area::-webkit-scrollbar { width: 6px; }
.scroll-area::-webkit-scrollbar-thumb { background: #dcdfe6; border-radius: 3px; }

.acc-item { background: #f8f9fa; border-radius: 8px; padding: 12px 15px; margin-bottom: 12px; border: 1px solid #ebeef5; transition: all 0.3s; }
.acc-item:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.05); transform: translateY(-2px); }
.acc-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.acc-name { font-size: 15px; font-weight: bold; color: #303133; border-left: 4px solid; padding-left: 8px; }
.acc-balance { font-size: 18px; font-weight: bold; font-family: Consolas; }
.acc-stats { display: flex; justify-content: space-between; font-size: 13px; background: white; padding: 6px 10px; border-radius: 6px; }
.stat-block { display: flex; align-items: center; gap: 4px; font-family: Consolas; font-weight: bold; }
.progress-label { font-size: 12px; color: #909399; text-align: right; margin-top: 4px; }

.today-task-item { display: flex; align-items: center; padding: 10px 12px; margin-bottom: 10px; background: #fdfdfd; border: 1px solid #ebeef5; border-radius: 6px; border-left: 4px solid #E6A23C; transition: all 0.3s; }
.today-task-item:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.05); transform: translateX(2px); border-color: #E6A23C; }
.task-time { font-family: Consolas, monospace; font-weight: bold; color: #E6A23C; width: 45px; flex-shrink: 0; }
.task-info { flex: 1; margin-left: 10px; margin-right: 10px; overflow: hidden; }
.task-name { font-size: 14px; font-weight: bold; color: #303133; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.task-remark { font-size: 12px; color: #909399; margin-top: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.task-action { flex-shrink: 0; }
</style>