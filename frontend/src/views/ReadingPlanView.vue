<template>
  <div class="app-container">
    <div style="margin-bottom: 20px; display: flex; justify-content: space-between;">
      <h2>阅读计划 📅</h2>
      <el-button type="primary" icon="Plus" @click="openCreate">制定新计划</el-button>
    </div>

    <el-card shadow="never" class="search-card" style="margin-bottom: 20px; border: none; background-color: #f8f9fa;">
      <el-form :inline="true" :model="queryParams" class="search-form" style="display: flex; flex-wrap: wrap; align-items: center;">
        
        <el-form-item label="书名" style="margin-bottom: 0; margin-right: 20px;">
          <el-input v-model="queryParams.book_title" placeholder="搜索计划书名..." clearable style="width: 180px" @keyup.enter="handleSearch" />
        </el-form-item>

        <el-form-item label="分类" style="margin-bottom: 0; margin-right: 20px;">
          <el-select v-model="queryParams.category" placeholder="全部分类" clearable style="width: 140px" @change="handleSearch">
            <el-option v-for="item in categoryOptions" :key="item.value" :label="item.label" :value="item.value" />
          </el-select>
        </el-form-item>

        <el-form-item label="计划排查" style="margin-bottom: 0; margin-right: 20px;">
           <el-checkbox v-model="queryParams.isOverTime">
              <span style="color: #F56C6C; font-weight: bold;">
                <el-icon style="vertical-align: middle;"><Warning /></el-icon> 严重超时
              </span>
           </el-checkbox>
        </el-form-item>
        
        <el-form-item style="margin-bottom: 0; margin-left: auto;">
           <el-button type="primary" :icon="Search" @click="handleSearch">搜索</el-button>
           <el-button @click="resetSearch" :icon="Refresh">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-table v-loading="loading" :data="filteredPlans" border style="width: 100%">
      
      <el-table-column label="封面" width="90" align="center">
        <template #default="{ row }">
          <el-image :src="row.book?.cover_url" style="height: 60px; border-radius: 4px" fit="cover">
            <template #error><div class="image-slot"><el-icon><Picture /></el-icon></div></template>
          </el-image>
        </template>
      </el-table-column>
      
      <el-table-column prop="book.title" label="书名" min-width="140" />

      <el-table-column label="分类" width="100" align="center">
        <template #default="{ row }">
          <el-tag type="info" effect="plain" size="small">{{ row.book?.category || '未分类' }}</el-tag>
        </template>
      </el-table-column>

      <el-table-column label="书籍规格" width="110" align="center">
        <template #default="{ row }">
          <div style="font-size: 13px; color: #606266; line-height: 1.6;">
            <div><span style="font-weight: bold;">{{ row.book?.page_count || 0 }}</span> <span style="font-size: 12px; color: #999;">页</span></div>
            <div><span style="font-weight: bold;">{{ row.book?.word_count ? (row.book.word_count / 10000).toFixed(1) : 0 }}</span> <span style="font-size: 12px; color: #999;">万字</span></div>
          </div>
        </template>
      </el-table-column>

      <el-table-column label="阅读进度" min-width="180" align="center">
        <template #default="{ row }">
          <div style="text-align: left; padding: 0 10px;">
            <div style="font-size: 12px; color: #666; margin-bottom: 5px; display: flex; justify-content: space-between;">
              <span>已读 <strong style="color: #409EFF; font-size: 14px;">{{ row.read_pages || 0 }}</strong> 页</span>
              <span>共 {{ row.book?.page_count || 0 }} 页</span>
            </div>
            <el-progress 
              :percentage="calcProgress(row.read_pages, row.book?.page_count)" 
              :stroke-width="8" 
              :color="customColors"
              :show-text="false"
            />
          </div>
        </template>
      </el-table-column>

      <el-table-column label="耗时对比(h)" width="150" align="center">
        <template #default="{ row }">
          <div style="font-family: Consolas;">
            <span title="实际累计耗时" :style="{ color: row.actual_total_hours > row.planned_total_hours ? '#F56C6C' : '#67C23A', fontWeight: 'bold', fontSize: '15px' }">
              {{ row.actual_total_hours || 0 }}
            </span>
            <span style="color: #909399; font-size: 12px;" title="计划总耗时">
              / {{ row.planned_total_hours || 0 }} 
            </span>
          </div>
        </template>
      </el-table-column>

      <el-table-column label="阅读速度" width="110" align="center">
        <template #default="{ row }">
          <el-tag type="warning" effect="plain" size="small">{{ row.speed?.name || '未知' }}</el-tag>
          <div style="font-size: 11px; color: #999; margin-top: 4px;">{{ row.speed?.speed }} 字/时</div>
        </template>
      </el-table-column>
      
      <el-table-column prop="rule.name" label="阅读规则" width="100" align="center">
        <template #default="{ row }">
          <el-tag type="info" size="small">{{ row.rule?.name || '默认规则' }}</el-tag>
        </template>
      </el-table-column>
      
      <el-table-column label="单次时长" width="90" align="center">
        <template #default="{ row }">
          <span style="font-weight: bold; color: #409EFF">{{ row.daily_minutes }}</span> m
        </template>
      </el-table-column>
      
      <el-table-column label="操作" width="160" align="center" fixed="right">
        <template #default="{ row }">
          <el-button type="info" link :icon="List" @click="handleViewDetails(row)">详情</el-button>
          <el-button type="danger" link :icon="Delete" @click="handleDelete(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <div class="pagination-container" style="margin-top: 20px; display: flex; justify-content: flex-end;">
      <el-pagination 
        v-model:current-page="pagination.current" 
        v-model:page-size="pagination.size" 
        :page-sizes="[10, 20, 50]" 
        background 
        layout="total, sizes, prev, pager, next, jumper" 
        :total="pagination.total" 
        @size-change="handleSizeChange" 
        @current-change="handleCurrentChange"
      />
    </div>

    <el-dialog v-model="dialogVisible" title="制定阅读计划" width="550px" @closed="resetForm">
      <el-form label-width="90px">
        <el-form-item label="选择书籍">
          <el-select v-model="form.book_id" placeholder="请选择未加入计划的书籍" style="width: 100%" filterable>
            <el-option v-for="book in options.books" :key="book.id" :label="book.title" :value="book.id" :disabled="!book.word_count || !book.page_count">
              <span style="float: left">{{ book.title }}</span>
              <span style="float: right; color: #8492a6; font-size: 13px;">
                {{ book.word_count ? (book.word_count/10000).toFixed(1)+'万字' : '缺字数' }} | {{ book.page_count ? book.page_count+'页' : '缺页数' }}
              </span>
            </el-option>
          </el-select>
        </el-form-item>

        <el-form-item label="阅读速度">
          <el-select v-model="form.speed_id" placeholder="选择速度" style="width: 100%">
            <el-option v-for="speed in options.speeds" :key="speed.id" :label="speed.name" :value="speed.id">
               <span style="float: left">{{ speed.name }}</span>
               <span style="float: right; color: #8492a6; font-size: 12px;">{{ speed.speed }}字/时</span>
            </el-option>
          </el-select>
        </el-form-item>

        <el-form-item label="选择规则">
          <el-select v-model="form.rule_id" placeholder="选择排期规则" style="width: 100%">
            <el-option v-for="rule in options.rules" :key="rule.id" :label="rule.name" :value="rule.id">
               <span style="float: left">{{ rule.name }}</span>
               <span style="float: right; color: #8492a6; font-size: 12px; margin-left: 10px;">{{ formatRuleInterval(rule) }}</span>
            </el-option>
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitPlan" :loading="submitLoading">智能生成任务</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="detailVisible" :title="`《${currentBookTitle}》的阅读详情排期`" width="750px">
       <el-table :data="detailList" v-loading="detailLoading" height="450" border stripe>
          <el-table-column label="执行日期" width="120" align="center">
             <template #default="{ row }">
                <span style="font-weight: bold; color: #409EFF">
                   {{ row.task_time ? row.task_time.substring(0, 10) : '-' }}
                </span>
             </template>
          </el-table-column>
          
          <el-table-column prop="remark" label="阅读目标与详情" min-width="250">
             <template #default="{ row }">
                <div v-if="row.isEditing">
                   <el-input 
                     :id="`remark-input-${row.id}`"
                     v-model="row.remark" 
                     size="small" 
                     @blur="handleSaveRemark(row)" 
                     @keyup.enter="handleSaveRemark(row)"
                     placeholder="请输入阅读目标详情..."
                   />
                </div>
                <div v-else @click="handleEditRemark(row)" class="remark-cell">
                   <span v-if="row.remark">{{ row.remark }}</span>
                   <span v-else style="color: #ccc; font-size: 12px;">点击添加详情...</span>
                   <el-icon class="edit-icon" style="margin-left: 5px; color: #409EFF; display: none;"><Edit /></el-icon>
                </div>
             </template>
          </el-table-column>
          
          <el-table-column label="打卡状态" width="140" align="center">
             <template #default="{ row }">
                <el-popover v-if="row.status !== 'completed'" placement="top" width="260" trigger="click">
                     <div style="margin-bottom: 12px; font-size: 13px; color: #606266; font-weight: bold;">
                        <el-icon style="vertical-align: middle;"><Timer /></el-icon> 本次实际耗时
                     </div>
                     <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 15px; font-size: 13px; color: #606266;">
                         <el-input-number v-model="row.temp_hours_h" :min="0" :step="1" :precision="0" size="small" style="width: 85px;" placeholder="0" controls-position="right" /><span>小时</span>
                         <el-input-number v-model="row.temp_hours_m" :min="0" :max="59" :step="5" :precision="0" size="small" style="width: 85px;" placeholder="0" controls-position="right" /><span>分</span>
                     </div>
                     <div style="text-align: right; margin: 0">
                        <el-button size="small" type="primary" @click="confirmCompleteTask(row)">确认完成</el-button>
                     </div>
                     <template #reference>
                        <el-tag type="info" effect="dark" style="cursor: pointer; transition: all 0.3s;" class="hover-tag">待办</el-tag>
                     </template>
                </el-popover>
                
                <div v-else style="display: flex; flex-direction: column; align-items: center; gap: 4px;">
                   <el-popconfirm title="确定要撤销这条打卡吗？" @confirm="revertTaskStatus(row)" width="200">
                      <template #reference>
                         <el-tag type="success" effect="dark" style="cursor: pointer;">已完成</el-tag>
                      </template>
                   </el-popconfirm>
                   <span v-if="row.actual_hours > 0" style="font-size: 12px; color: #67C23A; font-family: Consolas; background: #f0f9eb; padding: 0 6px; border-radius: 4px;">
                      {{ row.actual_hours }}h
                   </span>
                </div>
             </template>
          </el-table-column>
       </el-table>
    </el-dialog>

  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import request from '@/utils/request'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Picture, List, Delete, Edit, Timer, Warning, Refresh, Search } from '@element-plus/icons-vue' 

import { updateTaskDetailStatus, updateTaskDetailRemark } from '@/api/task'

const loading = ref(false)
const plans = ref([])
const options = reactive({ books: [], speeds: [], rules: [] })
const dialogVisible = ref(false)
const submitLoading = ref(false)

const form = reactive({ book_id: null, speed_id: null, rule_id: null })

// 🌟 新增：分页参数
const pagination = reactive({
  current: 1,
  size: 10,
  total: 0
})

const categoryOptions = ref([])
const fetchCategoryOptions = async () => {
  try {
    const res = await request.get('/categories/options')
    categoryOptions.value = res.map(item => ({ label: item.name, value: item.name }))
  } catch (error) {}
}

const queryParams = reactive({
  book_title: '',
  category: '',
  isOverTime: false
})

// 触发查询 (回到第一页)
const handleSearch = () => {
  pagination.current = 1
  loadPlans()
}

const resetSearch = () => {
  queryParams.book_title = ''
  queryParams.category = ''
  queryParams.isOverTime = false
  handleSearch()
}

// 分页事件
const handleSizeChange = (val) => {
  pagination.size = val
  loadPlans()
}
const handleCurrentChange = (val) => {
  pagination.current = val
  loadPlans()
}

// 前端纯计算过滤 (过滤超时项)
const filteredPlans = computed(() => {
  return plans.value.filter(plan => {
    if (queryParams.isOverTime && (plan.actual_total_hours || 0) <= (plan.planned_total_hours || 0)) {
      return false
    }
    return true
  })
})

const detailVisible = ref(false)
const detailList = ref([])
const detailLoading = ref(false)
const currentBookTitle = ref('')

const customColors = [
  { color: '#f56c6c', percentage: 20 }, 
  { color: '#e6a23c', percentage: 40 }, 
  { color: '#5cb87a', percentage: 60 }, 
  { color: '#1989fa', percentage: 80 }, 
  { color: '#6f7ad3', percentage: 100 },
]

const calcProgress = (readPages, totalPages) => {
  if (!totalPages || totalPages <= 0) return 0;
  const percent = Math.round(( (readPages || 0) / totalPages ) * 100);
  return percent > 100 ? 100 : percent; 
}

const formatRuleInterval = (rule) => {
  if (!rule) return ''
  const details = typeof rule.details === 'string' ? JSON.parse(rule.details || '{}') : (rule.details || {})
  
  if (rule.type === '循环' || rule.type === 'cycle' || rule.type === 'loop') {
    const days = details.days || details.repeat_days || []
    if (!days || days.length === 0 || days.length === 7) return '每天'
    return `${days.join(',')}`
  }
  
  let val = details.intervals || (details.items ? details.items.map(item => item.value) : null)
  if (Array.isArray(val)) return val.join('; ')
  if (typeof val === 'string' && val.trim() !== '') return val.replace(/,/g, '; ')
  return '未配置' 
}

const resetForm = () => { form.book_id = null; form.speed_id = null; form.rule_id = null }

// 🌟 修改：支持分页与查询参数的读取
const loadPlans = async () => {
  loading.value = true
  try {
    const res = await request.get('/reading-plans', {
      params: {
        page: pagination.current,
        per_page: pagination.size,
        book_title: queryParams.book_title,
        category: queryParams.category
      }
    })
    
    plans.value = res.data?.data || res.data || res || []
    pagination.total = res.data?.total || res.total || 0
    
  } catch (e) {
    console.error('获取阅读计划失败', e)
  } finally {
    loading.value = false
  }
}

const loadOptions = async () => {
  try {
    const res = await request.get('/reading-plans/options')
    options.books = res.books || res.data?.books || []
    options.speeds = res.speeds || res.data?.speeds || []
    options.rules = res.rules || res.data?.rules || []
  } catch (e) {}
}

const openCreate = () => {
  resetForm(); loadOptions(); dialogVisible.value = true
}

const submitPlan = async () => {
  if (!form.book_id || !form.speed_id || !form.rule_id) return ElMessage.warning('请将选择完整')

  submitLoading.value = true
  try {
    const res = await request.post('/reading-plans', form)
    ElMessage.success(res.message || '任务生成成功')
    dialogVisible.value = false
    loadPlans()
  } catch (error) {
    ElMessage.error(error.response?.data?.message || '生成失败，请重试')
  } finally {
    submitLoading.value = false
  }
}

const handleViewDetails = async (row) => {
  currentBookTitle.value = row.book?.title || '未知'
  detailVisible.value = true
  detailLoading.value = true
  try {
    const res = await request.get(`/reading-plans/${row.id}`)
    detailList.value = res.data || res
  } catch (e) {
    ElMessage.error('获取排期详情失败')
  } finally {
    detailLoading.value = false
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
    loadPlans(); 
  } catch (e) {}
}

const revertTaskStatus = async (row) => {
  try {
    await updateTaskDetailStatus(row.id, { status: 'pending', actual_hours: 0 });
    row.status = 'pending';
    row.actual_hours = 0;
    row.temp_hours_h = 0; row.temp_hours_m = 0; 
    ElMessage.warning('已撤销打卡，阅读进度已回退');
    loadPlans(); 
  } catch (e) {}
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
    ElMessage.success('阅读详情已更新')
    loadPlans() 
  } catch (e) {}
}

const handleDelete = (row) => {
  ElMessageBox.confirm(
    `确定要删除《${row.book?.title}》的阅读计划吗？\n删除后，已生成的每日排期和打卡记录将一并清除，且无法恢复！`, 
    '高危操作警告', 
    { confirmButtonText: '确定删除', cancelButtonText: '取消', type: 'error' }
  ).then(async () => {
    try {
      await request.delete(`/reading-plans/${row.id}`)
      ElMessage.success('彻底清除成功')
      loadPlans()
    } catch (e) {}
  }).catch(() => {})
}

onMounted(() => { 
  loadPlans()
  fetchCategoryOptions()
})
</script>

<style scoped>
.app-container {
  padding: 20px;
  background: #fff;
  border-radius: 8px;
  min-height: calc(100vh - 120px);
}
.image-slot {
  display: flex; justify-content: center; align-items: center;
  width: 100%; height: 100%; background: #f5f7fa; color: #909399; font-size: 20px;
}
.remark-cell {
  cursor: pointer;
  padding: 4px 0;
}
.remark-cell:hover .edit-icon {
  display: inline-flex !important;
}
.remark-cell:hover {
  background-color: #f5f7fa;
  border-radius: 4px;
  padding-left: 5px;
  transition: background-color 0.2s;
}
</style>