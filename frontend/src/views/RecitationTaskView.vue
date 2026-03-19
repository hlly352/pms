<script setup>
import { ref, onMounted } from 'vue'
import request from '@/utils/request'
import { ElMessage } from 'element-plus'
import { Check, Timer, AlarmClock, Reading, Folder, Edit } from '@element-plus/icons-vue'
import { MdPreview } from 'md-editor-v3'
import 'md-editor-v3/lib/style.css'

// 🌟 引入通用的任务状态更新 API
import { updateTaskDetailStatus, updateTaskDetailRemark } from '@/api/task'

const loading = ref(false)
const tasks = ref([])
const showDrawer = ref(false)
const currentTask = ref({}) // 当前正在背诵的任务

// ===========================
// 数据加载
// ===========================
const loadTasks = async () => {
  loading.value = true
  try {
    const res = await request.get('/recitations/today')
    // 🌟 为每个任务附加上打卡所需的状态变量
    tasks.value = res.map(item => ({
      ...item,
      status: item.status || 'pending', // 默认状态
      temp_hours_h: 0,
      temp_hours_m: 30, // 默认 30 分钟
      isEditing: false  // 控制备注是否处于编辑状态
    }))
  } finally {
    loading.value = false
  }
}

// ===========================
// 交互逻辑
// ===========================
const handleStart = (task) => {
  currentTask.value = task
  showDrawer.value = true
}

// 🌟 核心打卡逻辑 (完全参照阅读打卡)
const confirmCompleteTask = async (row) => {
  const h = Number(row.temp_hours_h) || 0;
  const m = Number(row.temp_hours_m) || 0;
  const totalHours = Number((h + (m / 60)).toFixed(2));
  
  try {
    // 调用通用打卡接口 (注意传的是 detail_id)
    await updateTaskDetailStatus(row.detail_id, { 
       status: 'completed', 
       actual_hours: totalHours 
    });
    
    row.status = 'completed';
    ElMessage.success(`🎉 打卡成功！已记录用时 ${h}小时 ${m}分钟`);
    
    // 隐藏气泡框并关闭背诵抽屉
    document.body.click(); 
    showDrawer.value = false;
    
    // 重新加载列表 (后台只会返回 pending 的，所以打卡后会自动从今日列表消失)
    loadTasks(); 
  } catch (e) {
    console.error('打卡失败', e);
  }
}

// 🌟 撤销打卡逻辑
const revertTaskStatus = async (row) => {
  try {
    await updateTaskDetailStatus(row.detail_id, { status: 'pending', actual_hours: 0 });
    row.status = 'pending';
    row.temp_hours_h = 0; 
    row.temp_hours_m = 30; 
    ElMessage.warning('已撤销打卡，阅读进度已回退');
    loadTasks(); 
  } catch (e) {
    console.error('撤销失败', e);
  }
}

// 🌟 阶段备注编辑逻辑
const handleEditRemark = (row) => {
  row.isEditing = true
  setTimeout(() => {
    const input = document.getElementById(`remark-input-${row.detail_id}`)
    if(input) input.focus()
  }, 100)
}

const handleSaveRemark = async (row) => {
  if (!row.isEditing) return
  row.isEditing = false
  try {
    // 你的后端把 detail.remark 映射为了前端的 stage
    await updateTaskDetailRemark(row.detail_id, row.stage)
    ElMessage.success('阶段/备注已更新')
  } catch (e) {
    console.error(e)
  }
}

onMounted(() => {
  loadTasks()
})
</script>

<template>
  <div class="task-page">
    <div class="header">
      <h2>今日待背诵 ({{ tasks.length }})</h2>
      <el-button type="primary" link @click="loadTasks">刷新</el-button>
    </div>

    <div class="task-list" v-loading="loading">
      <el-empty v-if="tasks.length === 0" description="太棒了，今日背诵任务已全部完成！" />
      
      <el-row :gutter="20">
        <el-col :xs="24" :sm="12" :md="8" v-for="item in tasks" :key="item.detail_id" style="margin-bottom: 20px;">
          <el-card shadow="hover" :class="['task-card', { 'is-overdue': item.is_overdue }]">
            <template #header>
              <div class="card-header">
                <span class="title" :title="item.title">{{ item.title }}</span>
                <el-tag v-if="item.is_overdue" type="danger" size="small" effect="dark">已逾期</el-tag>
                <el-tag v-else type="success" size="small">今日</el-tag>
              </div>
            </template>
            
            <div class="card-body">
              <div class="info-row">
                <el-icon><Folder /></el-icon>
                <span class="info-text">{{ item.path }}</span>
              </div>

              <div class="info-row remark-cell" @click="handleEditRemark(item)">
                <el-icon><AlarmClock /></el-icon>
                <div v-if="item.isEditing" style="margin-left: 8px; flex: 1;">
                   <el-input 
                     :id="`remark-input-${item.detail_id}`"
                     v-model="item.stage" 
                     size="small" 
                     @blur="handleSaveRemark(item)" 
                     @keyup.enter="handleSaveRemark(item)"
                     placeholder="输入阶段备注..."
                   />
                </div>
                <div v-else style="margin-left: 8px; flex: 1; display: flex; align-items: center;">
                   <span class="info-text">{{ item.stage || '点击添加备注...' }}</span>
                   <el-icon class="edit-icon"><Edit /></el-icon>
                </div>
              </div>

              <div class="info-row">
                <el-icon><Timer /></el-icon>
                <span class="info-text">计划日期: {{ item.plan_date }}</span>
              </div>
            </div>

            <div class="card-footer" style="display: flex; gap: 10px;">
              <el-button type="primary" plain style="flex: 1" :icon="Reading" @click="handleStart(item)">
                背诵
              </el-button>

              <el-popover v-if="item.status !== 'completed'" placement="top" width="260" trigger="click">
                 <div style="margin-bottom: 12px; font-size: 13px; color: #606266; font-weight: bold;">
                    <el-icon style="vertical-align: middle;"><Timer /></el-icon> 本次实际耗时
                 </div>
                 <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 15px; font-size: 13px; color: #606266;">
                     <el-input-number v-model="item.temp_hours_h" :min="0" :step="1" :precision="0" size="small" style="width: 85px;" placeholder="0" controls-position="right" /><span>小时</span>
                     <el-input-number v-model="item.temp_hours_m" :min="0" :max="59" :step="5" :precision="0" size="small" style="width: 85px;" placeholder="0" controls-position="right" /><span>分</span>
                 </div>
                 <div style="text-align: right; margin: 0">
                    <el-button size="small" type="primary" @click="confirmCompleteTask(item)">确认完成</el-button>
                 </div>
                 <template #reference>
                    <el-button type="success" :icon="Check">打卡</el-button>
                 </template>
              </el-popover>

              <el-popconfirm v-else title="确定要撤销这条打卡吗？" @confirm="revertTaskStatus(item)" width="200">
                 <template #reference>
                    <el-button type="success" plain :icon="Check">已打卡</el-button>
                 </template>
              </el-popconfirm>
            </div>
          </el-card>
        </el-col>
      </el-row>
    </div>

    <el-drawer v-model="showDrawer" :title="currentTask.title" size="60%" destroy-on-close>
      <div class="preview-container">
        <MdPreview :modelValue="currentTask.doc_content" />
      </div>
      
      <template #footer>
        <div style="flex: auto; display: flex; justify-content: flex-end; gap: 10px;">
          <el-button @click="showDrawer = false">稍后再背</el-button>
          
          <el-popover v-if="currentTask.status !== 'completed'" placement="top" width="260" trigger="click">
             <div style="margin-bottom: 12px; font-size: 13px; color: #606266; font-weight: bold;">
                <el-icon style="vertical-align: middle;"><Timer /></el-icon> 本次实际耗时
             </div>
             <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 15px; font-size: 13px; color: #606266;">
                 <el-input-number v-model="currentTask.temp_hours_h" :min="0" :step="1" :precision="0" size="small" style="width: 85px;" placeholder="0" controls-position="right" /><span>小时</span>
                 <el-input-number v-model="currentTask.temp_hours_m" :min="0" :max="59" :step="5" :precision="0" size="small" style="width: 85px;" placeholder="0" controls-position="right" /><span>分</span>
             </div>
             <div style="text-align: right; margin: 0">
                <el-button size="small" type="primary" @click="confirmCompleteTask(currentTask)">确认完成</el-button>
             </div>
             <template #reference>
                <el-button type="success" :icon="Check">完成打卡</el-button>
             </template>
          </el-popover>
        </div>
      </template>
    </el-drawer>
  </div>
</template>

<style scoped>
.task-page {
  padding: 20px;
  height: calc(100vh - 84px);
  overflow-y: auto;
  background-color: #f5f7fa;
}
.header {
  display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;
}
.task-card {
  height: 100%;
  display: flex; flex-direction: column;
  border-radius: 8px;
  transition: all 0.3s;
}
.task-card:hover { transform: translateY(-5px); }
.is-overdue { border: 1px solid #fde2e2; }
.is-overdue :deep(.el-card__header) { background-color: #fef0f0; }

.card-header { display: flex; justify-content: space-between; align-items: center; }
.title { font-weight: bold; font-size: 16px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 70%; }

.card-body { padding: 10px 0; color: #606266; font-size: 14px; flex: 1; }
.info-row { display: flex; align-items: center; margin-bottom: 8px; }
.info-text { margin-left: 8px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* 🌟 补充备注快捷编辑样式 */
.remark-cell {
  cursor: pointer;
  padding: 4px 0;
  border-radius: 4px;
  transition: background-color 0.2s;
}
.remark-cell .edit-icon {
  margin-left: 5px; 
  color: #409EFF; 
  display: none;
}
.remark-cell:hover .edit-icon {
  display: inline-flex !important;
}
.remark-cell:hover {
  background-color: #ecf5ff; /* 悬停时淡淡的蓝色 */
}

.preview-container { padding: 0 20px; }
</style>