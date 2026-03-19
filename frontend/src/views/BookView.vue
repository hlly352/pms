<script setup>
import { ref, reactive, onMounted } from 'vue'
import { getBookList, addBook, updateBook, deleteBook } from '@/api/book'
import { getReadingNotes, createReadingNote } from '@/api/readingNote' 
import request from '@/utils/request' 
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Delete, Edit, Search, Refresh, EditPen } from '@element-plus/icons-vue'

const books = ref([])
const dialogVisible = ref(false)
const isEdit = ref(false)

const categoryOptions = ref([])

// ==========================================
// 1. 阅读进度计算与样式逻辑 
// ==========================================
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

// ==========================================
// 2. 读书感悟抽屉相关的状态与方法
// ==========================================
const notesDrawerVisible = ref(false)
const currentBook = ref(null)
const bookNotes = ref([])
const newNoteContent = ref('')
const notesLoading = ref(false)
const noteSubmitting = ref(false)

const openBookNotes = async (book) => {
  currentBook.value = book
  newNoteContent.value = '' 
  notesDrawerVisible.value = true
  fetchBookNotes(book.id)
}

const fetchBookNotes = async (bookId) => {
  notesLoading.value = true
  try {
    const res = await getReadingNotes({ book_id: bookId, per_page: 100 })
    bookNotes.value = res.data?.data || res.data || res || []
  } catch (error) {
    bookNotes.value = []
  } finally {
    notesLoading.value = false
  }
}

const submitNewNote = async () => {
  if (!newNoteContent.value.trim()) return ElMessage.warning('写点什么再发布吧')
  noteSubmitting.value = true
  try {
    await createReadingNote({ book_id: currentBook.value.id, content: newNoteContent.value })
    ElMessage.success('灵感记录成功！')
    newNoteContent.value = '' 
    fetchBookNotes(currentBook.value.id) 
  } finally {
    noteSubmitting.value = false
  }
}

// ==========================================
// 3. 数据加载与表单逻辑
// ==========================================
const fetchCategoryOptions = async () => {
  try {
    const res = await request.get('/categories/options')
    categoryOptions.value = res.map(item => ({ label: item.name, value: item.name }))
  } catch (error) {}
}

const searchForm = reactive({ title: '', category: '', status: '', rating: null })

const loadData = async () => {
  try {
    const res = await getBookList(searchForm)
    books.value = res
  } catch (error) {}
}

const handleReset = () => {
  searchForm.title = ''; searchForm.category = ''; searchForm.status = ''; searchForm.rating = null
  loadData() 
}

const form = reactive({
  id: null, title: '', author: '', category: '', cover_url: '',
  word_count: null, page_count: null, rating: 0, status: 'unread'
})

const openCreate = () => {
  isEdit.value = false
  form.id = null; form.title = ''; form.author = ''; form.category = ''; form.word_count = null; form.page_count = null
  // 🌟 新增时封面设为空，等待用户上传
  form.cover_url = '' 
  form.rating = 0; form.status = 'unread'
  dialogVisible.value = true
}

const openEdit = (item) => {
  isEdit.value = true
  Object.assign(form, item)
  dialogVisible.value = true
}

const handleSubmit = async () => {
  if (!form.title) return ElMessage.warning('书名必填')
  try {
    if (isEdit.value) { await updateBook(form.id, form); ElMessage.success('更新成功') } 
    else { await addBook(form); ElMessage.success('添加成功') }
    dialogVisible.value = false
    loadData()
  } catch (error) {}
}

const handleDelete = (book) => { 
  ElMessageBox.confirm(`确定要删除《${book.title}》吗？\n警告：删除后，该书绑定的【阅读计划】、【读书感悟】等相关数据都将被永久清除！`, '高危操作警告', { confirmButtonText: '确定删除', cancelButtonText: '取消', type: 'error' })
  .then(async () => { await deleteBook(book.id); ElMessage.success('已删除'); loadData() }).catch(() => {})
}

// ==========================================
// 🌟 4. 七牛云图片上传逻辑
// ==========================================
const coverUploading = ref(false)

const beforeCoverUpload = (file) => {
  const isImage = file.type.startsWith('image/')
  const isLt5M = file.size / 1024 / 1024 < 5
  if (!isImage) ElMessage.error('只能上传图片文件！')
  if (!isLt5M) ElMessage.error('图片大小不能超过 5MB！')
  return isImage && isLt5M
}

const handleCoverUpload = async (options) => {
  const formData = new FormData()
  formData.append('file', options.file)
  coverUploading.value = true
  try {
    // 注意：这里的 URL 根据你的路由进行修改，这里假设是 /upload/image
    const res = await request.post('/upload/image', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    // 你的后端返回的是 { url: '...', alt: '...' }
    form.cover_url = res.url
    ElMessage.success('封面上传成功！')
  } catch (error) {
    console.error(error)
    ElMessage.error('封面上传失败，请重试')
  } finally {
    coverUploading.value = false
  }
}

const statusMap = {
  unread: { text: '想读', type: 'info' },
  reading: { text: '在读', type: 'primary' },
  finished: { text: '读过', type: 'success' }
}

onMounted(() => { fetchCategoryOptions(); loadData() })
</script>

<template>
  <div class="book-container">
    <div class="header">
      <h2>我的书架 📚</h2>
      <el-button type="primary" :icon="Plus" @click="openCreate">添加书籍</el-button>
    </div>

    <el-card class="search-box" shadow="never">
      <el-form :inline="true" :model="searchForm" class="demo-form-inline">
        <el-form-item label="书名"><el-input v-model="searchForm.title" placeholder="搜索书名..." clearable @keyup.enter="loadData" /></el-form-item>
        <el-form-item label="分类"><el-select v-model="searchForm.category" placeholder="全部分类" clearable style="width: 120px" @change="loadData"><el-option v-for="item in categoryOptions" :key="item.value" :label="item.label" :value="item.value" /></el-select></el-form-item>
        <el-form-item label="状态"><el-select v-model="searchForm.status" placeholder="全部状态" clearable style="width: 120px" @change="loadData"><el-option label="想读" value="unread" /><el-option label="在读" value="reading" /><el-option label="读过" value="finished" /></el-select></el-form-item>
        <el-form-item label="评分"><el-select v-model="searchForm.rating" placeholder="全部评分" clearable style="width: 120px" @change="loadData"><el-option label="5 星 🌟🌟🌟🌟🌟" :value="5" /><el-option label="4 星 🌟🌟🌟🌟" :value="4" /><el-option label="3 星 🌟🌟🌟" :value="3" /><el-option label="2 星 🌟🌟" :value="2" /><el-option label="1 星 🌟" :value="1" /></el-select></el-form-item>
        <el-form-item><el-button type="primary" :icon="Search" @click="loadData">查询</el-button><el-button :icon="Refresh" @click="handleReset">重置</el-button></el-form-item>
      </el-form>
    </el-card>

    <el-row :gutter="20" style="display: flex; flex-wrap: wrap;">
      <el-col v-for="book in books" :key="book.id" :xs="12" :sm="8" :md="6" :lg="4" :xl="4" style="margin-bottom: 20px;">
        <el-card :body-style="{ padding: '0px' }" shadow="hover" class="book-card">
          
          <div class="image-box">
            <img :src="book.cover_url || 'https://via.placeholder.com/300x400?text=No+Cover'" class="image" />
            <el-tag class="status-tag" :type="statusMap[book.status]?.type" effect="dark">{{ statusMap[book.status]?.text }}</el-tag>
          </div>
          
          <div class="content-box">
            <h3 class="title" :title="book.title">{{ book.title }}</h3>
            
            <div class="author-row">
              <span class="author" :title="book.author">{{ book.author || '未知作者' }}</span>
              <el-tag size="small" type="info" effect="plain" v-if="book.category">{{ book.category }}</el-tag>
            </div>

            <div class="meta-info">
              <span v-if="book.word_count">{{ (book.word_count / 10000).toFixed(1) }}万字</span>
              <el-divider direction="vertical" v-if="book.word_count && book.page_count" />
              <span v-if="book.page_count">{{ book.page_count }}页</span>
            </div>

            <div class="progress-box" v-if="book.status !== 'unread'">
              <div class="progress-labels">
                <span>已读 <strong style="color: #409EFF; font-size: 14px;">{{ book.read_pages || 0 }}</strong> 页</span>
                <span>共 {{ book.page_count || 0 }} 页</span>
              </div>
              <el-progress 
                :percentage="calcProgress(book.read_pages, book.page_count)" 
                :stroke-width="10" 
                :color="customColors"
                :text-inside="false"
                :show-text="false"
              />
            </div>
            
            <div class="bottom">
              <el-rate v-model="book.rating" disabled show-score text-color="#ff9900" />
              <div class="actions">
                <el-button link type="success" :icon="EditPen" title="读书感悟" @click="openBookNotes(book)"></el-button>
                <el-button link :icon="Edit" title="编辑" @click="openEdit(book)"></el-button>
                <el-button link type="danger" :icon="Delete" title="删除" @click="handleDelete(book)"></el-button>
              </div>
            </div>
          </div>

        </el-card>
      </el-col>
    </el-row>

    <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑书籍' : '新书入库'" width="550px">
      <el-form label-width="80px">
        
        <el-form-item label="封面上传">
          <el-upload
            class="cover-uploader"
            action="#"
            :show-file-list="false"
            :http-request="handleCoverUpload"
            :before-upload="beforeCoverUpload"
          >
            <div v-loading="coverUploading" class="upload-area">
              <img v-if="form.cover_url" :src="form.cover_url" class="uploaded-cover" />
              <el-icon v-else class="uploader-icon"><Plus /></el-icon>
            </div>
          </el-upload>
          <div style="font-size: 12px; color: #909399; margin-top: 5px; line-height: 1.5;">
            推荐比例 3:4。图片将直传至七牛云。<br>
            如不上传则使用默认封面。
          </div>
        </el-form-item>

        <el-row :gutter="20">
          <el-col :span="12"><el-form-item label="书名"><el-input v-model="form.title" placeholder="请输入书名" /></el-form-item></el-col>
          <el-col :span="12"><el-form-item label="作者"><el-input v-model="form.author" placeholder="请输入作者" /></el-form-item></el-col>
        </el-row>

        <el-form-item label="分类">
          <el-select v-model="form.category" placeholder="请选择书籍分类" style="width: 100%">
            <el-option v-for="item in categoryOptions" :key="item.value" :label="item.label" :value="item.value" />
          </el-select>
        </el-form-item>

        <el-row :gutter="20">
          <el-col :span="12"><el-form-item label="字数"><el-input-number v-model="form.word_count" :min="0" :step="10000" placeholder="字数" style="width: 100%" /></el-form-item></el-col>
          <el-col :span="12"><el-form-item label="总页数"><el-input-number v-model="form.page_count" :min="0" placeholder="总页数" style="width: 100%" /></el-form-item></el-col>
        </el-row>

        <template v-if="isEdit">
          <el-form-item label="状态">
            <el-select v-model="form.status" style="width: 100%">
              <el-option label="想读" value="unread" />
              <el-option label="正在读" value="reading" />
              <el-option label="已读完" value="finished" />
            </el-select>
          </el-form-item>
          <el-form-item label="评分"><el-rate v-model="form.rating" /></el-form-item>
        </template>
      </el-form>
      <template #footer><el-button @click="dialogVisible = false">取消</el-button><el-button type="primary" @click="handleSubmit">保存</el-button></template>
    </el-dialog>

    <el-drawer v-model="notesDrawerVisible" :title="`《${currentBook?.title}》的读书感悟`" size="500px">
      <div class="quick-publish-box" style="margin-bottom: 20px;">
        <el-input v-model="newNoteContent" type="textarea" :rows="4" placeholder="读到了什么好句子？或者有什么灵感？记下来吧..." />
        <div style="text-align: right; margin-top: 10px;"><el-button type="primary" @click="submitNewNote" :loading="noteSubmitting">发布感悟</el-button></div>
      </div>
      <el-divider border-style="dashed">往期回顾</el-divider>
      <div v-loading="notesLoading" style="flex: 1; overflow-y: auto; padding: 5px;">
        <div v-if="bookNotes.length > 0">
          <div v-for="note in bookNotes" :key="note.id" style="background: #f9fbff; border-radius: 8px; padding: 15px; margin-bottom: 15px; border: 1px solid #eef2ff; border-left: 4px solid #67C23A; transition: all 0.3s;">
            <div style="font-size: 14px; color: #303133; line-height: 1.6; white-space: pre-wrap;">{{ note.content }}</div>
            <div style="font-size: 11px; color: #909399; margin-top: 12px; text-align: right; font-family: Consolas;">{{ new Date(note.created_at).toLocaleString() }}</div>
          </div>
        </div>
        <el-empty v-else description="这本书还没有留下你的足迹" :image-size="80" />
      </div>
    </el-drawer>

  </div>
</template>

<style scoped>
.book-container { padding: 10px; }
.header { display: flex; justify-content: space-between; margin-bottom: 20px; }
.search-box { margin-bottom: 20px; background-color: #f8f9fa; border: none; }

.book-card { 
  height: 100%;
  display: flex; 
  flex-direction: column;
  transition: all 0.3s; 
}
.book-card:hover { 
  transform: translateY(-5px); 
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1) !important; 
}

:deep(.el-card__body) {
  flex: 1;
  display: flex;
  flex-direction: column;
  padding: 0 !important; 
}

/* 🌟 核心调整：利用 aspect-ratio 比例代替固定高度，完美实现“变高变窄”的视觉 */
.image-box { 
  position: relative; 
  width: 100%;
  aspect-ratio: 3 / 4; /* 标准书籍的高窄比 */
  overflow: hidden; 
  background-color: #f5f7fa; 
  flex-shrink: 0; 
}
.image { width: 100%; height: 100%; object-fit: cover; }
.status-tag { position: absolute; top: 10px; right: 10px; }

.content-box {
  padding: 14px;
  display: flex;
  flex-direction: column;
  flex: 1; 
}

.title { margin: 0; font-size: 16px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.author-row { display: flex; justify-content: space-between; align-items: center; margin: 8px 0; }
.author { font-size: 13px; color: #666; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.meta-info { font-size: 12px; color: #999; margin-bottom: 12px; min-height: 18px; }

.progress-box { margin-bottom: 15px; padding: 0 2px; }
.progress-labels { display: flex; justify-content: space-between; font-size: 12px; color: #666; margin-bottom: 5px; }

.bottom { 
  margin-top: auto; 
  display: flex; 
  justify-content: space-between; 
  align-items: center; 
}
.actions { display: flex; gap: 2px; }

/* 🌟 上传组件样式 */
.cover-uploader {
  border: 1px dashed #d9d9d9;
  border-radius: 6px;
  cursor: pointer;
  position: relative;
  overflow: hidden;
  width: 120px;
  height: 160px; /* 3:4 比例 */
  display: flex;
  justify-content: center;
  align-items: center;
  background-color: #f8f9fa;
  transition: border-color 0.3s;
}
.cover-uploader:hover {
  border-color: #409EFF;
}
.upload-area {
  width: 100%;
  height: 100%;
  display: flex;
  justify-content: center;
  align-items: center;
}
.uploader-icon {
  font-size: 28px;
  color: #8c939d;
}
.uploaded-cover {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 4px;
}
</style>