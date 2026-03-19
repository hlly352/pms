<script setup>
import { ref, onMounted, computed, onUnmounted } from 'vue'
import request from '@/utils/request'
import { getSettings } from '@/api/setting'
import { useAuthStore } from '@/stores/auth'
import { getTodayPendingTasks, updateTaskDetailStatus } from '@/api/task'
import { ElMessage } from 'element-plus'
import { 
  Check, Reading, Memo, ChatDotRound, 
  Calendar, List, Timer // 🌟 新增了 Timer 图标
} from '@element-plus/icons-vue'

const authStore = useAuthStore()

// ===========================
// 1. 状态定义
// ===========================

// 基础状态
const loading = ref(false)
const todayTasks = ref([]) // 今日待办列表
const currentTime = ref(new Date())
const timer = ref(null) // 时钟定时器
const lifeTimerInterval = ref(null) // 人生计时器定时器

// 统计数据
const stats = ref({
  project_count: 0,       // 进行中的项目数
  month_todo_count: 0,    // 本月待办任务数
  reading_book_count: 0,  // 阅读中的书籍数
  record_count: 0         // 记录点滴数
})
const motto = ref('不积跬步，无以至千里')

// 人生计时器数据 (起始时间: 1989-09-10 09:45:00)
const startTime = new Date('1989-09-10T09:45:00')
const lifeTimer = ref({
  years: 0,
  months: 0,
  days: 0,
  hours: '00',
  minutes: '00',
  seconds: '00'
})

// ===========================
// 2. 核心逻辑
// ===========================

// 动态问候语
const greeting = computed(() => {
  const hour = currentTime.value.getHours()
  if (hour < 6) return '夜深了'
  if (hour < 9) return '早上好 🌞'
  if (hour < 12) return '上午好 ☕'
  if (hour < 14) return '中午好 🍚'
  if (hour < 18) return '下午好 🥤'
  return '晚上好 🌙'
})

// 加载统计数据
const loadStats = async () => {
  try {
    const res = await request.get('/dashboard/stats')
    stats.value = res
  } catch (e) { console.error(e) }
}

// 加载个性签名
const loadSettings = async () => {
  try {
    const res = await getSettings()
    if (res && res.user_motto) {
      motto.value = res.user_motto
    }
  } catch (e) { console.error(e) }
}

// 加载今日待办
const loadTodayData = async () => {
  loading.value = true
  try {
    const res = await getTodayPendingTasks()
    todayTasks.value = res
  } finally {
    loading.value = false
  }
}

// 🌟 核心修改：确认完成任务并填报时间 (自动换算小时)
const confirmCompleteTask = async (row) => {
  // 获取填写的小时和分钟，如果没填默认为 0
  const h = Number(row.temp_hours_h) || 0;
  const m = Number(row.temp_hours_m) || 0;
  
  // 核心计算：换算成总小时数，并保留 2 位小数
  const totalHours = Number((h + (m / 60)).toFixed(2));

  try {
    // 调用 API 提交状态和时间对象
    await updateTaskDetailStatus(row.id, {
       status: 'completed',
       actual_hours: totalHours
    })
    
    ElMessage.success(`🎉 太棒了！任务已完成 (用时 ${h}小时 ${m}分钟)`)
    
    // 模拟点击页面空白处，关闭 Popover 气泡框
    document.body.click(); 

    // 从列表中瞬间移除
    const index = todayTasks.value.indexOf(row)
    if (index > -1) {
      todayTasks.value.splice(index, 1)
    }
    
    // 🌟 优化：重新加载顶部的统计数据，让“今日待办”数量瞬间-1
    loadStats()

  } catch (e) {
    console.error(e)
  }
}

// 时间格式化 (解决时区问题)
const formatTime = (timeStr) => {
  if (!timeStr) return '-'
  const date = new Date(timeStr)
  const h = date.getHours().toString().padStart(2, '0')
  const m = date.getMinutes().toString().padStart(2, '0')
  return `${h}:${m}`
}

// 核心算法：精确计算 年、月、日、时、分、秒
const updateLifeTimer = () => {
  const now = new Date()
  const start = startTime 
  
  let Y = now.getFullYear() - start.getFullYear()
  let M = now.getMonth() - start.getMonth()
  let D = now.getDate() - start.getDate()
  let h = now.getHours() - start.getHours()
  let m = now.getMinutes() - start.getMinutes()
  let s = now.getSeconds() - start.getSeconds()

  if (s < 0) { s += 60; m-- }
  if (m < 0) { m += 60; h-- }
  if (h < 0) { h += 24; D-- }
  
  if (D < 0) {
    const prevMonthLastDay = new Date(now.getFullYear(), now.getMonth(), 0)
    D += prevMonthLastDay.getDate()
    M--
  }
  
  if (M < 0) {
    M += 12
    Y--
  }

  lifeTimer.value = {
    years: Y,
    months: M,
    days: D,
    hours: h.toString().padStart(2, '0'),
    minutes: m.toString().padStart(2, '0'),
    seconds: s.toString().padStart(2, '0')
  }
}

// ===========================
// 3. 生命周期
// ===========================
onMounted(() => {
  loadStats()
  loadSettings()
  loadTodayData()

  timer.value = setInterval(() => {
    currentTime.value = new Date()
  }, 60000)

  updateLifeTimer()
  lifeTimerInterval.value = setInterval(updateLifeTimer, 1000)
})

onUnmounted(() => {
  if (timer.value) clearInterval(timer.value)
  if (lifeTimerInterval.value) clearInterval(lifeTimerInterval.value)
})
</script>

<template>
  <div class="dashboard-container">
    
    <el-card class="welcome-card" shadow="never">
      <div class="welcome-content">
        <div class="avatar-box">
           <el-avatar :size="64" style="background:#409EFF; font-size:24px; font-weight:bold;">
             {{ authStore.user.name ? authStore.user.name[0].toUpperCase() : 'A' }}
           </el-avatar>
        </div>
        <div class="info-box">
          <h2 class="title">
            {{ greeting }}，{{ authStore.user.name || '管理员' }}
            
            <div class="life-timer-box">
              <span class="timer-label">已运行</span>
              
              <span class="timer-group">
                <span class="timer-num">{{ lifeTimer.years }}</span><span class="timer-unit">年</span>
                <span class="timer-num">{{ lifeTimer.months }}</span><span class="timer-unit">月</span>
                <span class="timer-num">{{ lifeTimer.days }}</span><span class="timer-unit">日</span>
              </span>
              
              <span class="timer-sep-line">|</span>

              <span class="timer-group">
                <span class="timer-digit">{{ lifeTimer.hours }}</span><span class="timer-unit">时</span>
                <span class="timer-digit">{{ lifeTimer.minutes }}</span><span class="timer-unit">分</span>
                <span class="timer-digit sec-animate">{{ lifeTimer.seconds }}</span><span class="timer-unit">秒</span>
              </span>
            </div>

          </h2>
          <p class="motto">“ {{ motto }} ”</p>
        </div>
      </div>
    </el-card>

    <el-row :gutter="20" style="margin-top: 20px;">
      
      <el-col :xs="12" :sm="12" :md="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-icon bg-blue">
            <el-icon><DataLine /></el-icon>
          </div>
          <div class="stat-info">
            <div class="label">进行中的项目</div>
            <div class="value">{{ stats.project_count || 0 }}</div>
          </div>
        </el-card>
      </el-col>

      <el-col :xs="12" :sm="12" :md="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-icon bg-green">
            <el-icon><Memo /></el-icon>
          </div>
          <div class="stat-info">
            <div class="label">今日 / 本月待办</div>
            <div class="value">
              <span style="color: #409EFF">{{ todayTasks.length }}</span>
              <span style="font-size: 14px; color: #909399; font-weight: normal; margin: 0 4px;">/</span>
              <span>{{ stats.month_todo_count || 0 }}</span>
            </div>
          </div>
        </el-card>
      </el-col>

      <el-col :xs="12" :sm="12" :md="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-icon bg-orange">
            <el-icon><Reading /></el-icon>
          </div>
          <div class="stat-info">
            <div class="label">阅读中的书籍</div>
            <div class="value">{{ stats.reading_book_count || 0 }}</div>
          </div>
        </el-card>
      </el-col>

      <el-col :xs="12" :sm="12" :md="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-icon bg-purple">
            <el-icon><ChatDotRound /></el-icon>
          </div>
          <div class="stat-info">
            <div class="label">记录点滴</div>
            <div class="value">{{ stats.record_count || 0 }}</div>
          </div>
        </el-card>
      </el-col>
    </el-row>
    
    <el-row :gutter="20" style="margin-top: 20px;">
      
      <el-col :xs="24" :md="16" style="margin-bottom: 20px;">
        <el-card shadow="never" class="list-card">
          <template #header>
            <div class="card-header">
              <span>📅 今日待办任务</span>
              <el-button text type="primary" @click="loadTodayData">刷新</el-button>
            </div>
          </template>

          <el-table 
            :data="todayTasks" 
            v-loading="loading" 
            style="width: 100%" 
            :show-header="false"
            empty-text="今天任务已全部完成，享受生活吧！🎈"
          >
            <el-table-column width="80">
              <template #default="{ row }">
                <span class="task-time">{{ formatTime(row.task_time) }}</span>
              </template>
            </el-table-column>

            <el-table-column min-width="200">
              <template #default="{ row }">
                 <div class="task-content">
                    <div class="task-main">
                      <span class="task-name">{{ row.task ? row.task.name : '未知任务' }}</span>
                      <el-tag 
                        v-if="row.task?.source === 'project'" 
                        type="warning" 
                        size="small" 
                        effect="plain" 
                        class="ml-2"
                      >项目</el-tag>
                    </div>
                    <div class="task-sub" v-if="row.task?.content">
                      {{ row.task.content }}
                    </div>
                 </div>
              </template>
            </el-table-column>

            <el-table-column width="100" align="right">
              <template #default="{ row }">
                 
                 <el-popover placement="left" width="260" trigger="click">
                    <div style="margin-bottom: 12px; font-size: 13px; color: #606266; font-weight: bold;">
                       <el-icon style="vertical-align: middle;"><Timer /></el-icon> 本次实际耗时
                    </div>
                    
                    <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 15px; font-size: 13px; color: #606266;">
                        <el-input-number v-model="row.temp_hours_h" :min="0" :step="1" :precision="0" size="small" style="width: 85px;" placeholder="0" controls-position="right" />
                        <span>小时</span>
                        <el-input-number v-model="row.temp_hours_m" :min="0" :max="59" :step="5" :precision="0" size="small" style="width: 85px;" placeholder="0" controls-position="right" />
                        <span>分</span>
                    </div>
                    
                    <div style="text-align: right; margin: 0">
                       <el-button size="small" type="primary" @click="confirmCompleteTask(row)">确认完成</el-button>
                    </div>
                    
                    <template #reference>
                       <el-button 
                         type="success" 
                         circle 
                         size="small" 
                         :icon="Check" 
                         title="点击完成并记录时间"
                       />
                    </template>
                 </el-popover>

              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-col>

      <el-col :xs="24" :md="8">
        <el-card shadow="never" class="list-card">
          <template #header>
            <div class="card-header">
              <span>📆 日历</span>
            </div>
          </template>
          <el-calendar class="mini-calendar" />
        </el-card>
      </el-col>
    </el-row>

  </div>
</template>

<style scoped>
/* 1. 欢迎卡片样式 */
.welcome-card {
  background: linear-gradient(135deg, #ffffff 0%, #f0f2f5 100%);
  border: none;
}
.welcome-content { display: flex; align-items: center; }
.info-box { margin-left: 20px; flex: 1; }
.title { margin: 0; font-size: 20px; color: #303133; display: flex; align-items: center; flex-wrap: wrap; }
.motto { margin: 10px 0 0; color: #909399; font-style: italic; font-size: 14px; }

/* 计时器容器 */
.life-timer-box {
  display: inline-flex;
  align-items: center;
  margin-left: 15px;
  padding: 4px 15px;
  background: rgba(255, 255, 255, 0.6);
  border-radius: 50px;
  border: 1px solid rgba(64, 158, 255, 0.2);
  font-family: 'Consolas', 'Monaco', monospace;
  font-size: 13px;
  vertical-align: middle;
  box-shadow: 0 4px 12px rgba(64, 158, 255, 0.1);
  backdrop-filter: blur(8px);
  white-space: nowrap;
}

.timer-label { color: #909399; margin-right: 8px; font-size: 12px; font-weight: normal; }
.timer-group { display: flex; align-items: baseline; }
.timer-sep-line { margin: 0 8px; color: #DCDFE6; font-size: 10px; }

.timer-num, .timer-digit {
  font-weight: 800;
  font-size: 15px;
  background: linear-gradient(120deg, #409EFF, #67C23A, #E6A23C, #F56C6C, #409EFF);
  background-size: 300% 100%;
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
  animation: rainbow-flow 6s linear infinite;
  font-family: 'Helvetica Neue', Arial, sans-serif;
}

.timer-unit { font-size: 12px; color: #606266; margin: 0 2px 0 1px; font-weight: normal; }

.sec-animate {
  display: inline-block;
  min-width: 18px;
  text-align: center;
  animation: pop-up 1s cubic-bezier(0.175, 0.885, 0.32, 1.275) infinite;
}

@media screen and (max-width: 900px) {
  .life-timer-box {
    display: flex;
    margin-left: 0;
    margin-top: 10px;
    width: fit-content;
    flex-wrap: wrap;
    justify-content: center;
    border-radius: 12px;
  }
  .timer-sep-line { display: none; }
  .timer-group { margin: 2px 5px; }
}

@keyframes rainbow-flow { 0% { background-position: 100% 0; } 100% { background-position: 0 0; } }
@keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
@keyframes pop-up { 0% { transform: scale(1); } 50% { transform: scale(1.15); } 100% { transform: scale(1); } }

/* 3. 统计卡片样式 */
.stat-card { border: none; cursor: pointer; transition: transform 0.2s; margin-bottom: 10px; }
.stat-card:hover { transform: translateY(-5px); }
.stat-card :deep(.el-card__body) { display: flex; align-items: center; padding: 20px; }
.stat-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; justify-content: center; align-items: center; font-size: 24px; color: white; margin-right: 15px; }

.bg-blue { background: linear-gradient(135deg, #409EFF, #79bbff); box-shadow: 0 4px 10px rgba(64, 158, 255, 0.3); }
.bg-green { background: linear-gradient(135deg, #67C23A, #95d475); box-shadow: 0 4px 10px rgba(103, 194, 58, 0.3); }
.bg-orange { background: linear-gradient(135deg, #E6A23C, #f3d19e); box-shadow: 0 4px 10px rgba(230, 162, 60, 0.3); }
.bg-purple { background: linear-gradient(135deg, #909399, #b1b3b8); box-shadow: 0 4px 10px rgba(144, 147, 153, 0.3); }

.stat-info .label { font-size: 14px; color: #909399; }
.stat-info .value { font-size: 24px; font-weight: bold; color: #303133; margin-top: 5px; }

/* 4. 列表与日历样式 */
.list-card { border: none; height: 100%; }
.card-header { display: flex; justify-content: space-between; align-items: center; font-weight: bold; color: #303133; }

.task-time { font-family: Consolas, monospace; font-weight: bold; color: #409EFF; font-size: 16px; }
.task-content { display: flex; flex-direction: column; justify-content: center; }
.task-name { font-size: 15px; color: #303133; font-weight: 500; }
.task-sub { font-size: 12px; color: #999; margin-top: 4px; }
.ml-2 { margin-left: 8px; }

/* 迷你日历优化 */
.mini-calendar { --el-calendar-cell-width: 30px; }
.mini-calendar :deep(.el-calendar-table .el-calendar-day) { height: 35px; padding: 0; display: flex; align-items: center; justify-content: center; }
.mini-calendar :deep(.el-calendar__header) { padding: 10px; font-size: 12px; }

/* 移动端适配 */
@media screen and (max-width: 768px) {
  .life-timer-box { display: flex; margin-left: 0; margin-top: 10px; width: fit-content; }
  .info-box { margin-left: 10px; }
}
</style>