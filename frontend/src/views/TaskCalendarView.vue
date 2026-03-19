<script setup>
import { ref, watch, onMounted } from 'vue'
import { getCalendarEvents, updateTaskDetailStatus } from '@/api/task'
import request from '@/utils/request' // 🌟 引入 request 用于请求 PDF 二进制流
import { ElMessage, ElMessageBox } from 'element-plus'
import { Check, Search, Refresh, ArrowLeft, ArrowRight, Timer, Printer } from '@element-plus/icons-vue' // 🌟 引入 Printer 图标

const calendarValue = ref(new Date())
const events = ref([])
const loading = ref(false)
const searchQuery = ref('')

// 打卡弹窗相关状态
const checkInDialogVisible = ref(false)
const currentEvent = ref(null)
const checkInForm = ref({ h: 0, m: 0 })

// 辅助：日期格式化 YYYY-MM-DD
const formatDate = (date) => {
  const d = new Date(date)
  return `${d.getFullYear()}-${(d.getMonth()+1).toString().padStart(2, '0')}-${d.getDate().toString().padStart(2, '0')}`
}

// 加载当月数据
const loadEvents = async () => {
  loading.value = true
  try {
    const current = new Date(calendarValue.value)
    const year = current.getFullYear()
    const month = current.getMonth()
    
    // 取前后 40 天范围覆盖，确保跨月视图能显示完整
    const start = formatDate(new Date(year, month - 1, 20)) 
    const end = formatDate(new Date(year, month + 2, 10))

    const res = await getCalendarEvents(start, end)
    events.value = res
  } finally {
    loading.value = false
  }
}

// 切换月份方法
const changeMonth = (type) => {
  const date = new Date(calendarValue.value)
  if (type === 'prev') {
    date.setMonth(date.getMonth() - 1)
  } else if (type === 'next') {
    date.setMonth(date.getMonth() + 1)
  } else if (type === 'today') {
    date.setTime(Date.now())
  }
  calendarValue.value = date 
}

watch(() => calendarValue.value, () => { loadEvents() })
onMounted(loadEvents)

// 筛选某一天的任务
const getEventsForDay = (dateString) => {
  return events.value.filter(item => {
    const isSameDay = item.task_time && item.task_time.substring(0, 10) === dateString
    const taskName = item.task?.name || ''
    const isMatchSearch = taskName.toLowerCase().includes(searchQuery.value.toLowerCase())
    return isSameDay && isMatchSearch
  })
}

// 点击任务处理逻辑
const handleEventClick = (event) => {
  if (event.status === 'completed') {
    ElMessageBox.confirm(
      `确认撤销 "${event.task?.name}" 的完成状态吗？实际耗时将归零。`, 
      '撤销打卡', 
      { confirmButtonText: '确定撤销', cancelButtonText: '取消', type: 'warning' }
    ).then(async () => {
      try {
        await updateTaskDetailStatus(event.id, { status: 'pending', actual_hours: 0 })
        ElMessage.warning('已撤销打卡，时间已归零')
        
        event.status = 'pending'
        event.actual_hours = 0
        event.finished_at = null
      } catch (e) { console.error(e) }
    }).catch(() => {})
  } else {
    currentEvent.value = event
    checkInForm.value = { h: 0, m: 0 } 
    checkInDialogVisible.value = true
  }
}

// 确认提交打卡耗时
const confirmCheckIn = async () => {
  const h = Number(checkInForm.value.h) || 0;
  const m = Number(checkInForm.value.m) || 0;
  const totalHours = Number((h + (m / 60)).toFixed(2));

  try {
    await updateTaskDetailStatus(currentEvent.value.id, {
       status: 'completed',
       actual_hours: totalHours
    })
    
    ElMessage.success(`🎉 打卡成功！用时 ${h}小时 ${m}分钟`)
    
    currentEvent.value.status = 'completed'
    currentEvent.value.actual_hours = totalHours
    currentEvent.value.finished_at = new Date().toISOString()
    
    checkInDialogVisible.value = false
  } catch (e) {
    console.error('打卡失败', e)
  }
}

// 辅助：获取时间 HH:mm
const getTimeStr = (datetime) => {
  if (!datetime) return ''
  const date = new Date(datetime)
  const h = date.getHours().toString().padStart(2, '0')
  const m = date.getMinutes().toString().padStart(2, '0')
  return `${h}:${m}`
}

// ==========================================
// 🌟 新增：打印相关的核心逻辑
// ==========================================

// 打印日清单 (读取当前选中的日期)
// 🌟 打印日清单 (原生浏览器下载，完美绕过跨域)
const printDailyTasks = () => {
  const dateStr = formatDate(calendarValue.value)
  
  // 组装完整的后台接口地址 (请确保这里的 IP 和端口与你的后端一致)
  const url = `/api/print/daily?date=${dateStr}&token=jiatailong`
  
  ElMessage.success('正在请求云端生成日清单 PDF，请稍候...')
  
  // 让浏览器直接在新标签页打开这个带 token 的链接
  // 浏览器会自动识别 PDF 流并展示原生打印预览，CORS 跨域限制对它无效！
  window.open(url, '_blank')
}

// 🌟 打印月清单 (原生浏览器下载，完美绕过跨域)
const printMonthlyTasks = () => {
  const date = new Date(calendarValue.value)
  const monthStr = `${date.getFullYear()}-${(date.getMonth() + 1).toString().padStart(2, '0')}`
  
  // 组装完整的后台接口地址
  const url = `/api/print/monthly?month=${monthStr}&token=jiatailong`
  
  ElMessage.success('正在请求云端生成月清单 PDF，请稍候...')
  
  window.open(url, '_blank')
}
</script>

<template>
  <div class="calendar-page" v-loading="loading">
    <el-calendar v-model="calendarValue">
      
      <template #header="{ date }">
        <div class="calendar-header">
           <div class="header-left">
              <span class="month-title">{{ date }} 日程表</span>
              
              <el-button-group style="margin-left: 20px;">
                <el-button size="small" :icon="ArrowLeft" @click="changeMonth('prev')">上个月</el-button>
                <el-button size="small" @click="changeMonth('today')">今天</el-button>
                <el-button size="small" @click="changeMonth('next')">下个月<el-icon class="el-icon--right"><ArrowRight /></el-icon></el-button>
              </el-button-group>
           </div>
           
           <div class="header-right">
              <el-button-group style="margin-right: 15px;">
                 <el-button size="small" type="primary" plain :icon="Printer" @click="printDailyTasks">打印日清单</el-button>
                 <el-button size="small" type="success" plain :icon="Printer" @click="printMonthlyTasks">打印月清单</el-button>
              </el-button-group>

              <el-input 
                v-model="searchQuery" 
                placeholder="搜索任务名称..." 
                size="small" 
                clearable
                :prefix-icon="Search"
                style="width: 200px; margin-right: 15px;"
              />
              <el-button size="small" :icon="Refresh" @click="loadEvents">刷新数据</el-button>
           </div>
        </div>
      </template>

      <template #date-cell="{ data }">
        <div class="date-cell-content">
          <div class="day-number">{{ data.day.split('-').slice(2).join('') }}</div>
          
          <div class="events-container">
            <div 
              v-for="event in getEventsForDay(data.day)" 
              :key="event.id"
              class="event-item"
              :class="{ 'is-completed': event.status === 'completed' }"
              @click.stop="handleEventClick(event)"
            >
              <span class="event-time">{{ getTimeStr(event.task_time) }}</span>
              <span class="event-title" :title="event.task?.name">{{ event.task?.name }}</span>
              
              <span v-if="event.status === 'completed' && event.actual_hours > 0" class="actual-hours-badge">
                 {{ event.actual_hours }}h
              </span>
              <el-icon class="status-icon" v-if="event.status === 'completed'"><Check /></el-icon>
            </div>
          </div>
        </div>
      </template>
    </el-calendar>

    <el-dialog v-model="checkInDialogVisible" :title="`任务打卡 - ${currentEvent?.task?.name}`" width="350px" top="30vh">
       <div style="margin-bottom: 15px; font-size: 14px; color: #606266; font-weight: bold;">
          <el-icon style="vertical-align: middle; margin-right: 4px;"><Timer /></el-icon> 本次实际耗时
       </div>
       
       <div style="display: flex; gap: 10px; align-items: center; justify-content: center; margin-bottom: 10px;">
           <el-input-number v-model="checkInForm.h" :min="0" :step="1" :precision="0" style="width: 110px;" placeholder="0" controls-position="right" />
           <span style="font-size: 14px; color: #606266;">小时</span>
           
           <el-input-number v-model="checkInForm.m" :min="0" :max="59" :step="5" :precision="0" style="width: 110px;" placeholder="0" controls-position="right" />
           <span style="font-size: 14px; color: #606266;">分</span>
       </div>
       
       <template #footer>
         <span class="dialog-footer">
           <el-button @click="checkInDialogVisible = false">暂不打卡</el-button>
           <el-button type="primary" @click="confirmCheckIn">确认完成</el-button>
         </span>
       </template>
    </el-dialog>

  </div>
</template>

<style scoped>
.calendar-page {
  background: #fff;
  padding: 20px;
  border-radius: 8px;
  height: calc(100vh - 84px);
  overflow-y: auto;
}

.calendar-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
}
.header-left, .header-right {
  display: flex;
  align-items: center;
}
.month-title {
  font-weight: bold;
  font-size: 16px;
  color: #303133;
}

:deep(.el-calendar-table .el-calendar-day) {
  height: 140px; 
  padding: 5px;
  position: relative;
}

.date-cell-content {
  height: 100%;
  display: flex;
  flex-direction: column;
}

.day-number {
  font-weight: bold;
  margin-bottom: 5px;
  font-size: 14px;
}

.events-container {
  flex: 1;
  overflow-y: auto; 
  display: flex;
  flex-direction: column;
  gap: 3px;
}
.events-container::-webkit-scrollbar { width: 0; }

.event-item {
  font-size: 12px;
  padding: 3px 6px;
  border-radius: 4px;
  background-color: #ecf5ff; 
  color: #409eff;
  border-left: 3px solid #409eff;
  cursor: pointer;
  display: flex;
  align-items: center;
  transition: all 0.2s;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.event-item:hover {
  filter: brightness(0.95);
  transform: translateX(1px);
}

.event-item.is-completed {
  background-color: #f0f9eb; 
  color: #67c23a;
  border-left-color: #67c23a;
  opacity: 0.85;
}

.event-item.is-completed .event-title {
  text-decoration: line-through; 
}

.event-time {
  margin-right: 4px;
  font-family: Consolas, monospace;
  font-weight: bold;
  font-size: 11px;
  opacity: 0.8;
}

.event-title {
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
}

.actual-hours-badge {
  font-family: Consolas, monospace;
  font-size: 11px;
  font-weight: bold;
  background: #e1f3d8;
  color: #67C23A;
  padding: 0 4px;
  border-radius: 4px;
  margin-right: 4px;
}

.status-icon {
  margin-left: 2px;
  font-size: 12px;
}
</style>
