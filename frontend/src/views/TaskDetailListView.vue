<script setup>
import { ref, reactive, onMounted, computed, watch } from 'vue'
import { getAllTaskDetails, updateTaskDetailStatus, updateTaskDetailRemark } from '@/api/task'
import { ElMessage } from 'element-plus'
import { Timer, Check, Close, Search, Refresh, Edit, Warning } from '@element-plus/icons-vue'

const list = ref([])
const loading = ref(false)

// 🌟 修改：将 taskDate 字符串改为 taskDateRange 数组
const queryParams = reactive({
  keyword: '',
  source: '',        // 任务来源
  accountId: '',     // 结算账户
  taskDateRange: [], // 执行日期段
  isDelayed: false   // 是否延期完成
})

// 分页状态管理
const pagination = reactive({
  current: 1,
  size: 20
})

const loadData = async () => {
  loading.value = true
  try {
    list.value = await getAllTaskDetails()
  } finally {
    loading.value = false
  }
}

onMounted(loadData)

// 解析为本地时间
const formatDateTime = (timeStr) => {
  if (!timeStr) return '-'
  const date = new Date(timeStr)
  
  if (isNaN(date.getTime())) return timeStr.substring(0, 16).replace('T', ' ')
  
  const Y = date.getFullYear()
  const M = (date.getMonth() + 1).toString().padStart(2, '0')
  const D = date.getDate().toString().padStart(2, '0')
  const h = date.getHours().toString().padStart(2, '0')
  const m = date.getMinutes().toString().padStart(2, '0')
  
  return `${Y}-${M}-${D} ${h}:${m}`
}

// 判断是否为当天的待办任务
const isTodayPending = (row) => {
  if (row.status === 'completed') return false;
  if (!row.task_time) return false;
  const d = new Date();
  const todayLocal = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
  return row.task_time.substring(0, 10) === todayLocal;
}

// 提取任务的结算账户对象
const getTaskAccount = (row) => {
  if (row.task?.time_account) return row.task.time_account;
  if (row.task?.project_stage_step?.stage?.project?.time_account) return row.task.project_stage_step.stage.project.time_account;
  return null;
}

// 提取任务的来源类型
const getTaskSource = (row) => {
  if (row.task?.source === 'project') return 'project';
  if (row.task?.source === 'recitation') return 'recitation';
  if (row.task?.source === 'reading') return 'reading';
  if (row.task) return 'manual';
  return '';
}

// 动态生成所有可选的结算账户（从列表中提取去重）
const accountOptions = computed(() => {
  const accMap = new Map();
  list.value.forEach(row => {
    const acc = getTaskAccount(row);
    if (acc && !accMap.has(acc.id)) {
      accMap.set(acc.id, acc);
    }
  });
  return Array.from(accMap.values());
})

// 确认完成打卡
const confirmCompleteTask = async (row) => {
  const h = Number(row.temp_hours_h) || 0;
  const m = Number(row.temp_hours_m) || 0;
  const totalHours = Number((h + (m / 60)).toFixed(2));
  
  try {
    await updateTaskDetailStatus(row.id, { 
       status: 'completed', 
       actual_hours: totalHours 
    });
    
    row.status = 'completed';
    row.actual_hours = totalHours;
    row.finished_at = new Date().toISOString();
    
    const accName = getTaskAccount(row)?.name;

    if (accName) {
        ElMessage.success(`🎉 打卡成功！用时 ${totalHours}h，已从【${accName}】自动扣除！`);
    } else {
        ElMessage.success(`🎉 打卡成功！用时 ${totalHours}h (该任务未绑定账户，不扣余额)`);
    }
    
    document.body.click(); 
  } catch (e) {
    console.error('打卡失败', e);
  }
}

// 撤销打卡
const revertTaskStatus = async (row) => {
  try {
    await updateTaskDetailStatus(row.id, { 
       status: 'pending', 
       actual_hours: 0 
    });
    
    const accName = getTaskAccount(row)?.name;

    row.status = 'pending';
    row.actual_hours = 0;
    row.finished_at = null;
    row.temp_hours_h = 0; 
    row.temp_hours_m = 0; 
    
    if (accName) {
        ElMessage.warning(`已撤销打卡，刚才扣除的时间已全额退还至【${accName}】`);
    } else {
        ElMessage.warning('已撤销打卡，时间已归零');
    }
  } catch (e) {
    console.error('撤销失败', e);
  }
}

// 备注编辑
const handleEditRemark = (row) => {
  row.isEditing = true
  setTimeout(() => {
    const input = document.getElementById(`list-remark-input-${row.id}`)
    if(input) input.focus()
  }, 100)
}

const handleSaveRemark = async (row) => {
  if (!row.isEditing) return
  row.isEditing = false
  try {
    await updateTaskDetailRemark(row.id, row.remark)
    ElMessage.success('备注已更新')
  } catch (e) { console.error(e) }
}

// 核心：交叉过滤与自定义排序
const displayList = computed(() => {
  // 1. 先进行过滤
  let filtered = list.value.filter(item => {
    // 关键字过滤
    if (queryParams.keyword) {
      const lowerKey = queryParams.keyword.toLowerCase()
      const taskName = item.task ? item.task.name.toLowerCase() : ''
      const remark = item.remark ? item.remark.toLowerCase() : ''
      if (!taskName.includes(lowerKey) && !remark.includes(lowerKey)) return false
    }
    
    // 任务来源过滤
    if (queryParams.source && getTaskSource(item) !== queryParams.source) return false
    
    // 结算账户过滤
    if (queryParams.accountId && getTaskAccount(item)?.id !== queryParams.accountId) return false
    
    // 🌟 修改：执行日期范围过滤
    if (queryParams.taskDateRange && queryParams.taskDateRange.length === 2) {
      if (!item.task_time) return false;
      // 截取任务时间的 YYYY-MM-DD 部分进行严格比对
      const taskDateOnly = item.task_time.substring(0, 10);
      const startDate = queryParams.taskDateRange[0];
      const endDate = queryParams.taskDateRange[1];
      
      if (taskDateOnly < startDate || taskDateOnly > endDate) {
        return false;
      }
    }
    
    // 延期完成过滤 (实际完成日期 > 计划执行日期)
    if (queryParams.isDelayed) {
      if (item.status !== 'completed' || !item.finished_at || !item.task_time) return false;
      const finishedDate = item.finished_at.substring(0, 10);
      const plannedDate = item.task_time.substring(0, 10);
      if (finishedDate <= plannedDate) return false;
    }

    return true;
  });

  // 2. 然后进行排序 (待办在前，已完成在后；待办按执行时间升序，已完成按执行时间降序)
  filtered.sort((a, b) => {
    if (a.status === 'pending' && b.status === 'completed') return -1;
    if (a.status === 'completed' && b.status === 'pending') return 1;

    const timeA = new Date(a.task_time || 0).getTime();
    const timeB = new Date(b.task_time || 0).getTime();

    if (a.status === 'pending') {
      return timeA - timeB; // 待办：升序 (越早的任务越在前面)
    } else {
      return timeB - timeA; // 已完成：降序 (刚做完的任务在前面)
    }
  });

  return filtered;
})

// 监听查询参数变化，重置回第一页
watch(queryParams, () => {
  pagination.current = 1
}, { deep: true })

const resetSearch = () => {
  queryParams.keyword = ''
  queryParams.source = ''
  queryParams.accountId = ''
  queryParams.taskDateRange = [] // 🌟 修改：重置为空数组
  queryParams.isDelayed = false
}

// 分页计算
const pagedList = computed(() => {
  const start = (pagination.current - 1) * pagination.size
  const end = start + pagination.size
  return displayList.value.slice(start, end)
})

const handleSizeChange = (val) => { pagination.size = val; pagination.current = 1 }
const handleCurrentChange = (val) => { pagination.current = val }

// 底部汇总
const getSummaries = (param) => {
  const { columns } = param; 
  const sums = [];
  
  columns.forEach((column, index) => {
    if (index === 0) {
      sums[index] = '总计耗时'; 
      return;
    }
    
    if (column.property === 'actual_hours') {
      const values = displayList.value.map(item => Number(item[column.property]) || 0);
      const total = values.reduce((prev, curr) => prev + curr, 0);
      sums[index] = total > 0 ? `${total.toFixed(2)} h` : '0 h';
    } else {
      sums[index] = '';
    }
  });
  
  return sums;
};
</script>

<template>
  <div class="page-container">
    
    <el-card shadow="never" class="search-card">
      <el-form :inline="true" :model="queryParams" class="search-form">
        <el-form-item label="任务关键字">
          <el-input v-model="queryParams.keyword" placeholder="搜索名称/备注..." style="width: 160px" clearable :prefix-icon="Search"/>
        </el-form-item>
        
        <el-form-item label="任务来源">
          <el-select v-model="queryParams.source" clearable placeholder="全部来源" style="width: 130px">
            <el-option label="项目实施" value="project" />
            <el-option label="背诵管理" value="recitation" />
            <el-option label="阅读管理" value="reading" />
            <el-option label="手动创建" value="manual" />
          </el-select>
        </el-form-item>

        <el-form-item label="结算账户">
          <el-select v-model="queryParams.accountId" clearable placeholder="全部账户" style="width: 140px">
            <el-option v-for="acc in accountOptions" :key="acc.id" :label="acc.name" :value="acc.id" />
          </el-select>
        </el-form-item>

        <el-form-item label="执行周期">
          <el-date-picker
            v-model="queryParams.taskDateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始范围"
            end-placeholder="结束范围"
            value-format="YYYY-MM-DD"
            style="width: 240px"
            clearable
          />
        </el-form-item>

        <el-form-item>
          <el-checkbox v-model="queryParams.isDelayed">
            <span style="color: #F56C6C; font-weight: bold; margin-right: 15px;">延期完成任务</span>
          </el-checkbox>
        </el-form-item>

        <el-form-item style="margin-right: 0;">
          <el-button :icon="Refresh" @click="resetSearch">重置条件</el-button>
          <el-button type="primary" :icon="Refresh" @click="loadData" plain>刷新数据</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-table 
      v-loading="loading" 
      :data="pagedList" 
      border 
      stripe 
      style="width: 100%" 
      height="calc(100vh - 210px)"
      show-summary
      :summary-method="getSummaries"
    >
      
      <el-table-column label="所属任务" min-width="120" show-overflow-tooltip>
        <template #default="{ row }">
          <span style="font-weight: 600; color: #303133">{{ row.task ? row.task.name : '已删除' }}</span>
        </template>
      </el-table-column>

      <el-table-column prop="task_time" label="计划执行时间" width="180">
        <template #default="{ row }">
          <div style="display: flex; align-items: center; gap: 6px;">
            <span :style="{ fontFamily: 'Consolas, monospace', fontWeight: 'bold', color: row.status === 'completed' ? '#909399' : '#409EFF' }">
              {{ formatDateTime(row.task_time) }}
            </span>
            <el-tag v-if="isTodayPending(row)" type="danger" size="small" effect="dark" round style="border: none;">今日重点</el-tag>
          </div>
        </template>
      </el-table-column>

      <el-table-column label="任务来源" width="100" align="center">
        <template #default="{ row }">
          <el-tag v-if="row.task?.source === 'project'" type="warning" effect="plain" size="small">项目实施</el-tag>
          <el-tag v-else-if="row.task?.source === 'recitation'" type="success" effect="plain" size="small">背诵管理</el-tag>
          <el-tag v-else-if="row.task?.source === 'reading'" type="info" effect="plain" size="small">阅读管理</el-tag>
          <el-tag v-else-if="row.task" type="info" effect="plain" size="small">手动创建</el-tag>
          <span v-else>-</span>
        </template>
      </el-table-column>

      <el-table-column label="结算账户" width="130" align="center">
        <template #default="{ row }">
          <div 
            v-if="getTaskAccount(row)" 
            style="background: #f3f0ff; color: #7b61ff; border: 1px solid #e5dfff; border-radius: 4px; padding: 2px 8px; font-size: 12px; display: inline-flex; align-items: center; white-space: nowrap;"
          >
            <el-icon style="margin-right: 4px;"><Timer /></el-icon>
            {{ getTaskAccount(row).name }}
          </div>
          <span v-else style="color: #c0c4cc; font-size: 12px;">未绑定</span>
        </template>
      </el-table-column>

      <el-table-column label="状态" width="140" align="center" prop="status">
        <template #default="{ row }">
          
          <el-popover v-if="row.status !== 'completed'" placement="top" width="260" trigger="click">
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
                <el-tag type="info" effect="dark" style="cursor: pointer; transition: all 0.3s; white-space: nowrap;" class="hover-tag">待办执行</el-tag>
             </template>
          </el-popover>

          <div v-else style=" flex-direction: column; align-items: center; gap: 4px;">
             <el-popconfirm title="确定要撤销这条打卡吗？" @confirm="revertTaskStatus(row)" width="200">
                <template #reference>
                  <el-tag type="success" effect="dark" style="cursor: pointer; transition: all 0.3s; white-space: nowrap;" class="hover-tag">已完成</el-tag>
                </template>
             </el-popconfirm>
          </div>

        </template>
      </el-table-column>

      <el-table-column prop="actual_hours" label="实际耗时(h)" width="120" align="center">
        <template #default="{ row }">
          <span v-if="row.actual_hours > 0" style="color: #67C23A; font-weight: bold; font-size: 14px; font-family: Consolas;">
            {{ row.actual_hours }}
          </span>
          <span v-else style="color: #c0c4cc">-</span>
        </template>
      </el-table-column>

      <el-table-column prop="finished_at" label="实际完成时间" width="160">
        <template #default="{ row }">
          <div v-if="row.finished_at" style="display: flex; flex-direction: column; align-items: flex-start; gap: 2px;">
            <span style="color: #67C23A">{{ formatDateTime(row.finished_at) }}</span>
            <span v-if="row.finished_at.substring(0,10) > row.task_time.substring(0,10)" style="font-size: 11px; color: #F56C6C; background: #fef0f0; padding: 0 4px; border-radius: 2px;">
              <el-icon><Warning /></el-icon> 延期完成
            </span>
          </div>
          <span v-else style="color: #909399">-</span>
        </template>
      </el-table-column>

      <el-table-column prop="remark" label="执行备注" min-width="200">
        <template #default="{ row }">
            <div v-if="row.isEditing">
               <el-input 
                 :id="`list-remark-input-${row.id}`"
                 v-model="row.remark" 
                 size="small" 
                 @blur="handleSaveRemark(row)" 
                 @keyup.enter="handleSaveRemark(row)"
                 placeholder="输入备注..."
               />
            </div>
            <div v-else @click="handleEditRemark(row)" class="remark-cell">
               <span v-if="row.remark">{{ row.remark }}</span>
               <span v-else style="color:#ddd; font-size:12px">点击添加备注</span>
               <el-icon class="edit-icon"><Edit /></el-icon>
            </div>
        </template>
      </el-table-column>

    </el-table>

    <div class="pagination-container">
      <el-pagination 
        v-model:current-page="pagination.current" 
        v-model:page-size="pagination.size" 
        :page-sizes="[20, 50, 100, 200]" 
        background 
        layout="total, sizes, prev, pager, next, jumper" 
        :total="displayList.length" 
        @size-change="handleSizeChange" 
        @current-change="handleCurrentChange"
      />
    </div>
  </div>
</template>

<style scoped>
.page-container { padding: 20px; background: #fff; border-radius: 8px; height: calc(100vh - 84px); display: flex; flex-direction: column; }

/* 高级查询卡片样式 */
.search-card { margin-bottom: 15px; border: none; box-shadow: 0 2px 12px 0 rgba(0,0,0,0.05); }
.search-form { display: flex; flex-wrap: wrap; align-items: center; margin-bottom: -15px; }
.search-form .el-form-item { margin-bottom: 15px; margin-right: 20px; }

.remark-cell { cursor: pointer; display: flex; align-items: center; min-height: 24px; }
.remark-cell .edit-icon { margin-left: 5px; color: #409EFF; display: none; }
.remark-cell:hover .edit-icon { display: inline-flex; }
.remark-cell:hover { background-color: #f5f7fa; border-radius: 4px; padding-left: 5px; }

.hover-tag:hover { opacity: 0.8; transform: translateY(-1px); }

.pagination-container {
  margin-top: 15px;
  display: flex;
  justify-content: flex-end;
}

:deep(.el-table__footer-wrapper tbody td.el-table__cell) {
  background-color: #fafafa;
  font-weight: bold;
  color: #67C23A;
}
</style>