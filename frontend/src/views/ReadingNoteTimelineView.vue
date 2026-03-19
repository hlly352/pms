<script setup>
import { ref, reactive, onMounted } from 'vue'
import { getReadingNotes, deleteReadingNote, updateReadingNote } from '@/api/readingNote'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Edit, Delete, Collection, Search, Refresh } from '@element-plus/icons-vue'

// =======================
// 1. 动态计算默认时间段 (当月)
// =======================
const getDefaultDateRange = () => {
  const now = new Date()
  const year = now.getFullYear()
  const month = now.getMonth()
  
  // 当月第一天
  const startDate = new Date(year, month, 1)
  // 当月最后一天 (下个月的第0天)
  const endDate = new Date(year, month + 1, 0)
  
  const formatDate = (d) => {
    const y = d.getFullYear()
    const m = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    return `${y}-${m}-${day}`
  }
  
  return [formatDate(startDate), formatDate(endDate)]
}

// =======================
// 2. 状态定义与搜索表单
// =======================
const notes = ref([])
const loading = ref(false)
const pagination = reactive({ current: 1, size: 20, total: 0 })

// 搜索条件
const queryParams = reactive({
  book_title: '',
  content: '',
  dateRange: getDefaultDateRange() // 默认挂载当月时间
})

// 编辑弹窗状态
const editDialogVisible = ref(false)
const editForm = ref({ id: null, content: '' })

// =======================
// 3. 数据加载与交互逻辑
// =======================
const buildParams = () => {
  const params = { 
    page: pagination.current, 
    per_page: pagination.size,
    book_title: queryParams.book_title,
    content: queryParams.content
  }
  if (queryParams.dateRange && queryParams.dateRange.length === 2) {
    params.start_date = queryParams.dateRange[0]
    params.end_date = queryParams.dateRange[1]
  }
  return params
}

const loadNotes = async () => {
  loading.value = true
  try {
    const res = await getReadingNotes(buildParams())
    notes.value = res.data?.data || res.data || res || []
    pagination.total = res.data?.total || res.total || 0
  } catch (error) {
    console.error('获取感悟列表失败', error)
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  pagination.current = 1
  loadNotes()
}

const handleReset = () => {
  queryParams.book_title = ''
  queryParams.content = ''
  queryParams.dateRange = getDefaultDateRange() // 恢复为当月
  handleSearch()
}

const handleSizeChange = (val) => {
  pagination.size = val
  loadNotes()
}

const handleCurrentChange = (val) => {
  pagination.current = val
  loadNotes()
}

// =======================
// 4. 增删改操作
// =======================
const handleEdit = (note) => {
  editForm.value = { id: note.id, content: note.content }
  editDialogVisible.value = true
}

const submitEdit = async () => {
  if (!editForm.value.content) return ElMessage.warning('内容不能为空')
  try {
    await updateReadingNote(editForm.value.id, { content: editForm.value.content })
    ElMessage.success('更新成功')
    editDialogVisible.value = false
    loadNotes()
  } catch (error) {}
}

const handleDelete = (id) => {
  ElMessageBox.confirm('确定要删除这条感悟吗？', '提示', { type: 'warning' }).then(async () => {
    try {
      await deleteReadingNote(id)
      ElMessage.success('已删除')
      loadNotes()
    } catch (error) {}
  }).catch(() => {})
}

const formatDate = (dateString) => {
  const d = new Date(dateString)
  return `${d.getFullYear()}年${d.getMonth() + 1}月${d.getDate()}日 ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
}

onMounted(() => loadNotes())
</script>

<template>
  <div class="timeline-container">
    
    <el-card shadow="never" class="search-card">
      <el-form :inline="true" :model="queryParams" class="search-form">
        <el-form-item label="书名">
          <el-input 
            v-model="queryParams.book_title" 
            placeholder="搜索相关书籍..." 
            clearable 
            style="width: 180px"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        
        <el-form-item label="感悟内容">
          <el-input 
            v-model="queryParams.content" 
            placeholder="搜一搜写过的金句..." 
            clearable 
            style="width: 200px"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        
        <el-form-item label="记录时间">
          <el-date-picker 
            v-model="queryParams.dateRange" 
            type="daterange" 
            range-separator="至" 
            start-placeholder="开始日期" 
            end-placeholder="结束日期" 
            value-format="YYYY-MM-DD" 
            :clearable="true" 
            style="width: 260px"
            @change="handleSearch"
          />
        </el-form-item>
        
        <el-form-item>
          <el-button type="primary" :icon="Search" @click="handleSearch">搜索</el-button>
          <el-button :icon="Refresh" @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card shadow="never" v-loading="loading" class="content-card">
      <template #header>
        <div style="font-weight: bold; font-size: 16px; color: #303133;">
          <el-icon style="margin-right: 8px; color: #409EFF"><Collection /></el-icon>读书感悟时间线
        </div>
      </template>

      <el-timeline v-if="notes.length > 0" style="padding-top: 10px;">
        <el-timeline-item 
          v-for="note in notes" 
          :key="note.id" 
          :timestamp="formatDate(note.created_at)" 
          placement="top" 
          color="#409EFF"
        >
          <el-card shadow="hover" class="note-card">
            <div class="book-tag">
              <el-tag size="small" type="success" effect="plain">《{{ note.book?.title || '未知书籍' }}》</el-tag>
            </div>
            <div class="note-content">{{ note.content }}</div>
            <div class="note-actions">
              <el-button type="primary" link :icon="Edit" @click="handleEdit(note)">编辑</el-button>
              <el-button type="danger" link :icon="Delete" @click="handleDelete(note.id)">删除</el-button>
            </div>
          </el-card>
        </el-timeline-item>
      </el-timeline>
      
      <el-empty 
        v-else 
        description="当前时间段内还没有记录感悟哦，或者试试更换搜索条件~" 
      />

      <div class="pagination-container" v-if="pagination.total > 0">
        <el-pagination 
          v-model:current-page="pagination.current" 
          v-model:page-size="pagination.size" 
          :page-sizes="[10, 20, 50]" 
          background 
          layout="total, prev, pager, next" 
          :total="pagination.total" 
          @size-change="handleSizeChange" 
          @current-change="handleCurrentChange"
        />
      </div>
    </el-card>

    <el-dialog v-model="editDialogVisible" title="修改感悟" width="550px">
      <el-input v-model="editForm.content" type="textarea" :rows="8" placeholder="在这里修改你的读书心得..." />
      <template #footer>
        <el-button @click="editDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitEdit">保存修改</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
.timeline-container { 
  padding: 20px; 
  background: #f5f7fa; 
  min-height: calc(100vh - 84px); 
}

/* 搜索卡片样式 */
.search-card { 
  margin-bottom: 20px; 
  border-radius: 8px; 
  border: none; 
}
.search-form { 
  display: flex; 
  flex-wrap: wrap; 
  align-items: center; 
}
.search-form .el-form-item { 
  margin-bottom: 0; 
  margin-right: 20px; 
}

.content-card {
  border-radius: 8px; 
  border: none;
}
.note-card { 
  position: relative; 
  border-radius: 8px; 
  border: 1px solid #ebeef5; 
}
.book-tag { 
  margin-bottom: 12px; 
}
.note-content { 
  font-size: 14px; 
  color: #303133; 
  line-height: 1.8; 
  white-space: pre-wrap; 
  margin-bottom: 15px; 
}
.note-actions { 
  text-align: right; 
  border-top: 1px dashed #ebeef5; 
  padding-top: 10px; 
  margin-top: 10px; 
}
.pagination-container { 
  margin-top: 25px; 
  display: flex; 
  justify-content: flex-end; 
}
</style>