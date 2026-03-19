<script setup>
import { ref, onMounted, nextTick, onUnmounted, computed } from 'vue'
import { useRouter } from 'vue-router' // 🌟 引入路由用于跳转
import { getDashboardData } from '@/api/financeoverview' 
import * as echarts from 'echarts'
import { 
  DataAnalysis, BottomRight, TopRight, Calendar, 
  RefreshLeft, List, DataLine // 🌟 引入了 List 和 DataLine 图标
} from '@element-plus/icons-vue'

const router = useRouter() // 🌟 初始化路由
const loading = ref(true)
const accounts = ref([])
const totalIncome = ref(0)
const totalExpense = ref(0)
// 🌟 新增：计算期间余额 (结余)
const periodBalance = computed(() => {
  return totalIncome.value - totalExpense.value
})

// 时间绑定的状态
const searchDateRange = ref([])
const isCustomDate = ref(false) 

// 图表引用
const incomeChartRef = ref(null)
const expenseChartRef = ref(null)
const trendChartRef = ref(null) // 🌟 新增：折线图引用
let incomeChart = null
let expenseChart = null
let trendChart = null

const loadData = async () => {
  loading.value = true
  try {
    const params = {}
    if (searchDateRange.value && searchDateRange.value.length === 2) {
      params.start_date = searchDateRange.value[0]
      params.end_date = searchDateRange.value[1]
    }

    const res = await getDashboardData(params)
    const data = res.data || res
    
    searchDateRange.value = data.date_range
    accounts.value = data.accounts
    
    // 🌟 新增：利用 reduce 快速累加图表数据，得到总收入和总支出
    totalIncome.value = (data.income_stats || []).reduce((sum, item) => sum + item.value, 0)
    totalExpense.value = (data.expense_stats || []).reduce((sum, item) => sum + item.value, 0)
    
    await nextTick()
    renderIncomeChart(data.income_stats)
    renderExpenseChart(data.expense_stats)
    renderTrendChart(data.trend_data) 
  } catch (error) {
    console.error('看板加载失败', error)
  } finally {
    loading.value = false
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

// 🌟 修改：跳转到收支记录页面时，带上时间参数
const goToTransactions = () => {
  const queryParams = {}
  // 如果当前有时间筛选，就把起始和结束日期塞进 URL 参数里
  if (searchDateRange.value && searchDateRange.value.length === 2) {
    queryParams.start_date = searchDateRange.value[0]
    queryParams.end_date = searchDateRange.value[1]
  }
  
  router.push({ 
    path: '/transactions', // 替换为你实际的路由
    query: queryParams    // 生成类似 /transaction?start_date=2026-02-25&end_date=2026-03-24
  }) 
}

// 渲染收入图表 (左侧 - 环形图)
const renderIncomeChart = (data) => {
  if (!incomeChartRef.value) return
  if (!incomeChart) incomeChart = echarts.init(incomeChartRef.value)
  
  incomeChart.setOption({
    tooltip: { trigger: 'item', formatter: '{b}: ￥{c} ({d}%)' },
    legend: { bottom: '0%', left: 'center' },
    series: [{
      name: '期间收入',
      type: 'pie',
      radius: ['40%', '60%'],
      center: ['50%', '45%'],
      avoidLabelOverlap: false,
      itemStyle: { borderRadius: 10, borderColor: '#fff', borderWidth: 2 },
      label: { show: false, position: 'center' },
      emphasis: { label: { show: true, fontSize: 18, fontWeight: 'bold', formatter: '{b}\n{c}' } },
      labelLine: { show: false },
      data: data?.length > 0 ? data : [{ name: '暂无数据', value: 0 }]
    }]
  })
}

// 渲染支出图表 (右侧 - 玫瑰图)
const renderExpenseChart = (data) => {
  if (!expenseChartRef.value) return
  if (!expenseChart) expenseChart = echarts.init(expenseChartRef.value)
  
  expenseChart.setOption({
    tooltip: { trigger: 'item', formatter: '{b}: ￥{c} ({d}%)' },
    legend: { type: 'scroll', bottom: '0%', left: 'center' },
    series: [{
      name: '期间支出',
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

// 🌟 渲染每日消耗趋势折线图
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
            html += `${item.marker} ${item.seriesName}: <span style="font-weight:bold; color:#F56C6C">￥${item.value}</span><br/>`;
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
      axisLabel: { formatter: (val) => val ? String(val).substring(5) : '' } // 仅显示MM-DD
    },
    yAxis: { 
      type: 'value',
      name: '支出 (￥)',
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
  incomeChart?.resize()
  expenseChart?.resize()
  trendChart?.resize()
}

onMounted(() => {
  loadData()
  window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
  window.removeEventListener('resize', handleResize)
  incomeChart?.dispose()
  expenseChart?.dispose()
  trendChart?.dispose()
})
</script>

<template>
  <div class="dashboard-container" v-loading="loading">
    
    <div class="header-banner">
      <div class="title-box">
        <el-icon class="title-icon"><DataAnalysis /></el-icon>
        <span class="title-text">个人财富流转引擎</span>
      </div>
      
      <div class="filter-box">
        <el-button 
          type="primary" 
          plain 
          :icon="List" 
          @click="goToTransactions"
          style="margin-right: 20px;"
        >
          收支流水记录
        </el-button>

        <span class="date-label" :class="{ 'is-custom': isCustomDate }">
          <el-icon style="vertical-align: middle; margin-right: 4px;"><Calendar /></el-icon>
          {{ isCustomDate ? '当前查询区间 :' : '当前专属账期 :' }}
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
      <el-col :span="7">
        <el-card shadow="hover" class="panel-card">
          <template #header>
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <div class="panel-title text-success">💰 期间收入源</div>
              <div style="font-size: 13px; color: #67C23A; font-weight: bold; background: #f0f9eb; padding: 2px 8px; border-radius: 4px; border: 1px solid #e1f3d8;">
                总计: +￥{{ Number(totalIncome).toFixed(2) }}
              </div>
            </div>
          </template>
          <div ref="incomeChartRef" class="chart-box"></div>
        </el-card>
      </el-col>

      <el-col :span="10">
        <el-card shadow="hover" class="panel-card center-panel">
          <template #header>
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <div class="panel-title text-primary">🏦 账户蓄水池与期间消耗</div>
              <div 
                :style="periodBalance >= 0 
                  ? 'color: #409EFF; background: #ecf5ff; border: 1px solid #d9ecff;' 
                  : 'color: #F56C6C; background: #fef0f0; border: 1px solid #fde2e2;'"
                style="font-size: 13px; font-weight: bold; padding: 2px 8px; border-radius: 4px;"
              >
                期间结余: {{ periodBalance > 0 ? '+' : '' }}￥{{ Number(periodBalance).toFixed(2) }}
              </div>
            </div>
          </template>
          
          <div class="account-scroll-area">
            <div v-for="acc in accounts" :key="acc.id" class="acc-item">
              <div class="acc-header">
                <span class="acc-name" :style="{ borderLeftColor: acc.color }">{{ acc.name }}</span>
                <span class="acc-balance" :style="{ color: acc.color }">￥{{ Number(acc.balance).toFixed(2) }}</span>
              </div>
              
              <div class="acc-stats">
                <div class="stat-block text-success">
                  <el-icon><BottomRight /></el-icon> 流入: +{{ Number(acc.month_inflow).toFixed(2) }}
                </div>
                <div class="stat-block text-danger">
                  <el-icon><TopRight /></el-icon> 支出: -{{ Number(acc.month_outflow).toFixed(2) }}
                </div>
              </div>

              <el-progress 
                :percentage="Number(calcPercentage(acc.month_outflow, acc.month_inflow, acc.balance))" 
                :color="acc.color"
                :stroke-width="10"
                style="margin-top: 10px;"
              />
              <div class="progress-label">当前资金池消耗率</div>
            </div>
            <el-empty v-if="accounts.length === 0" description="暂无正常状态的账户" />
          </div>
        </el-card>
      </el-col>

      <el-col :span="7">
        <el-card shadow="hover" class="panel-card">
          <template #header>
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <div class="panel-title text-danger">💸 期间支出去向</div>
              <div style="font-size: 13px; color: #F56C6C; font-weight: bold; background: #fef0f0; padding: 2px 8px; border-radius: 4px; border: 1px solid #fde2e2;">
                总计: -￥{{ Number(totalExpense).toFixed(2) }}
              </div>
            </div>
          </template>
          <div ref="expenseChartRef" class="chart-box"></div> 
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="20" style="margin-top: 20px;">
      <el-col :span="24">
        <el-card shadow="hover" class="trend-card">
          <template #header><div class="panel-title text-success"><el-icon style="vertical-align: -2px;"><DataLine /></el-icon> 每日资金支出趋势</div></template>
          <div ref="trendChartRef" class="trend-chart-box"></div>
        </el-card>
      </el-col>
    </el-row>

  </div>
</template>

<style scoped>
.dashboard-container { padding: 20px; background: #f0f2f5; min-height: calc(100vh - 84px); }

/* 顶部横幅 */
.header-banner { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; background: white; padding: 15px 25px; border-radius: 8px; box-shadow: 0 2px 12px 0 rgba(0,0,0,0.05); }
.title-box { display: flex; align-items: center; }
.title-icon { font-size: 24px; color: #409EFF; margin-right: 10px; }
.title-text { font-size: 20px; font-weight: bold; color: #303133; letter-spacing: 1px; }

.filter-box { display: flex; align-items: center; }
.date-label { font-size: 14px; font-weight: bold; color: #E6A23C; margin-right: 10px; background: #fdf6ec; padding: 6px 12px; border-radius: 4px; border: 1px solid #faecd8; transition: all 0.3s; }
.date-label.is-custom { color: #409EFF; background: #ecf5ff; border-color: #d9ecff; }

.main-row { display: flex; align-items: stretch; }

/* 🌟 将三个模块的高度缩小，固定为 500px */
.panel-card { border-radius: 10px; height: 500px; display: flex; flex-direction: column; }
.panel-card :deep(.el-card__body) { flex: 1; padding: 15px; display: flex; flex-direction: column; overflow: hidden; }

/* 🌟 折线图的卡片高度 */
.trend-card { border-radius: 10px; height: 320px; display: flex; flex-direction: column; }
.trend-card :deep(.el-card__body) { flex: 1; padding: 10px 15px; display: flex; flex-direction: column; overflow: hidden; }
.trend-chart-box { flex: 1; width: 100%; min-height: 230px; }

.panel-title { font-size: 16px; font-weight: bold; text-align: left; }
.text-success { color: #67C23A; }
.text-primary { color: #409EFF; }
.text-danger { color: #F56C6C; }

.chart-box { flex: 1; width: 100%; min-height: 200px; }

/* 中间账户滚动区 */
.account-scroll-area { flex: 1; overflow-y: auto; padding-right: 5px; }
.account-scroll-area::-webkit-scrollbar { width: 6px; }
.account-scroll-area::-webkit-scrollbar-thumb { background: #dcdfe6; border-radius: 3px; }

/* 账户独立卡片 */
.acc-item { background: #f8f9fa; border-radius: 8px; padding: 12px 15px; margin-bottom: 12px; border: 1px solid #ebeef5; transition: all 0.3s; }
.acc-item:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.05); transform: translateY(-2px); }
.acc-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.acc-name { font-size: 15px; font-weight: bold; color: #303133; border-left: 4px solid #409EFF; padding-left: 8px; }
.acc-balance { font-size: 18px; font-weight: bold; font-family: Consolas; }
.acc-stats { display: flex; justify-content: space-between; font-size: 13px; background: white; padding: 6px 10px; border-radius: 6px; }
.stat-block { display: flex; align-items: center; gap: 4px; }
.progress-label { font-size: 12px; color: #909399; text-align: right; margin-top: 4px; }
</style>