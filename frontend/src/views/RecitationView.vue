<script setup>
import { ref, reactive, onMounted, computed, watch } from 'vue'
import request from '@/utils/request'
import { ElMessage, ElMessageBox } from 'element-plus'
import { 
  Plus, Delete, Edit, Folder, Document, 
  MoreFilled, Search, FolderAdd, DocumentAdd,
  Notebook, ArrowRight, Printer 
} from '@element-plus/icons-vue'
import { MdEditor, MdPreview } from 'md-editor-v3'
import 'md-editor-v3/lib/style.css'

// ================= 状态定义 =================
const loading = ref(false)
const treeData = ref([]) 
const rules = ref([])

// 笔记本相关状态
const notebooks = ref([])
const currentNotebookId = ref(null)

// 左侧搜索
const filterText = ref('')
const treeRef = ref(null)

// 当前选中的节点
const currentNode = ref(null) 
const isEdit = ref(false) 
const contentLoading = ref(false)
const submitLock = ref(false)

// 自定义指令：自动聚焦
const vFocus = {
  mounted: (el) => {
    const input = el.querySelector('input')
    if (input) input.focus()
  }
}
//默认提醒时间为当前
const reminderTime = ref(new Date().toTimeString().slice(0, 5)) // 格式 "HH:mm"
// ================= 核心逻辑 =================

// 1. 加载笔记本列表
const loadNotebooks = async () => {
  const res = await request.get('/notebooks')
  notebooks.value = res
  
  // 如果当前没选中笔记本，且列表不为空，默认选中第一个
  if (!currentNotebookId.value && res.length > 0) {
    currentNotebookId.value = res[0].id
    loadData()
  }
}

// 2. 加载目录树 (依赖 currentNotebookId)
const loadData = async () => {
  if (!currentNotebookId.value) return
  
  loading.value = true
  try {
    const [listRes, ruleRes] = await Promise.all([
      request.get('/recitations', { params: { notebook_id: currentNotebookId.value } }),
      request.get('/rules', { params: { module: 'recitation' } })
    ])
    treeData.value = listRes.map(item => ({ ...item, isEdit: false }))
    rules.value = ruleRes
    
    // 切换笔记本后，清空右侧内容
    currentNode.value = null
  } finally {
    loading.value = false
  }
}
// 🌟 2. 增加 PDF 预览的函数
const isPdfLoading = ref(false)

const handlePreviewPDF = async () => {
  if (!currentNode.value || currentNode.value.type !== 'doc') {
    return ElMessage.warning('请选择一篇具体的文档进行预览')
  }
  
  isPdfLoading.value = true
  try {
    // 核心提示：必须配置 responseType 为 'blob'，告诉 axios 我们接收的是文件流而不是 JSON
    const res = await request.get(`/recitations/${currentNode.value.id}/pdf`, {
      responseType: 'blob' 
    })
    
    // 将后端返回的二进制流转化为 Blob 对象
    // 注意：如果你的 request 拦截器直接返回了 res.data，这里直接用 res 即可；如果返回的是完整 response，这里用 res.data
    const blob = new Blob([res], { type: 'application/pdf' }) 
    
    // 生成一个本地的临时 URL
    const fileURL = window.URL.createObjectURL(blob)
    
    // 在浏览器新标签页中打开这个 URL，浏览器会自动调用内置的 PDF 阅读器
    window.open(fileURL)
    
  } catch (error) {
    console.error('PDF 生成失败', error)
    ElMessage.error('PDF 生成失败，请稍后重试')
  } finally {
    isPdfLoading.value = false
  }
}
// 3. 监听笔记本切换
const handleNotebookChange = () => {
  loadData()
}

// 4. 新建笔记本
const handleAddNotebook = () => {
  ElMessageBox.prompt('请输入新笔记本名称', '新建笔记本', {
    confirmButtonText: '创建',
    cancelButtonText: '取消',
  }).then(async ({ value }) => {
    if(!value) return
    const res = await request.post('/notebooks', { name: value })
    notebooks.value.push(res)
    currentNotebookId.value = res.id // 自动切换到新笔记本
    handleNotebookChange()
    ElMessage.success('创建成功')
  })
}

// 管理笔记本 (删除当前笔记本)
const handleEditNotebook = () => {
    ElMessageBox.confirm('确认删除当前笔记本及其所有文档吗？此操作不可恢复！', '高危操作', {
        type: 'error'
    }).then(async () => {
        await request.delete(`/notebooks/${currentNotebookId.value}`)
        ElMessage.success('已删除')
        currentNotebookId.value = null
        loadNotebooks() // 重新加载列表
    })
}

// 5. 转换平铺数据为树形结构
const treeDataSource = computed(() => {
  const data = JSON.parse(JSON.stringify(treeData.value))
  const result = []
  const map = {}
  data.forEach(item => { item.children = []; map[item.id] = item })
  data.forEach(item => {
    const parent = map[item.parent_id]
    if (parent) { parent.children.push(item) } else { result.push(item) }
  })
  return result
})

// 6. 面包屑计算属性
const breadcrumbs = computed(() => {
  if (!currentNode.value) return []
  
  const path = []
  const findPath = (nodes, targetId) => {
    for (const node of nodes) {
      if (node.id === targetId) {
        path.push({ id: node.id, title: node.title })
        return true
      }
      if (node.children && node.children.length > 0) {
        if (findPath(node.children, targetId)) {
          path.unshift({ id: node.id, title: node.title })
          return true
        }
      }
    }
    return false
  }
  
  findPath(treeDataSource.value, currentNode.value.id)
  
  const currentNb = notebooks.value.find(n => n.id === currentNotebookId.value)
  if (currentNb) {
      path.unshift({ id: 'root', title: currentNb.name, isRoot: true })
  }
  
  return path
})

// 7. 计算当前选中的规则对象 (用于展示详情卡片)
const currentRule = computed(() => {
  if (!currentNode.value || !currentNode.value.rule_id) return null
  return rules.value.find(r => r.id === currentNode.rule_id)
})

// 8. 格式化规则间隔 (将逗号转为分号，美化显示)
// 8. 格式化规则间隔 (升级版：支持更多数据格式)
const formatRuleInterval = (rule) => {
    if (!rule) return ''
    
    let val = null

    // 情况1：直接在 intervals 字段 (例如 "1,2,4")
    if (rule.intervals) {
        val = rule.intervals
    } 
    // 情况2：在 details.intervals (例如 details: { intervals: "1,2,4" })
    else if (rule.details?.intervals) {
        val = rule.details.intervals
    }
    // 🔴 情况3：在 details.items (这是你自定义规则最可能的存储位置)
    // 格式通常是: details: { items: [ {value: 3}, {value: 2} ] }
    else if (rule.details?.items && Array.isArray(rule.details.items)) {
        // 提取 value 值并组成数组
        val = rule.details.items.map(item => item.value)
    }

    // --- 开始格式化 ---
    
    // 如果是数组 [3, 2]，转为 "3; 2"
    if (Array.isArray(val)) {
        return val.join('; ')
    }
    
    // 如果是字符串 "3,2"，转为 "3; 2"
    if (typeof val === 'string' && val.trim() !== '') {
        return val.replace(/,/g, '; ').replace(/，/g, '; ')
    }

    // 兜底模拟显示 (只有读不到数据时才会显示这个)
    if (rule.name && rule.name.includes('艾宾浩斯')) return '1; 2; 4; 7; 15'
    
    // 如果实在读不到，返回空或者原来的默认值
    return '未配置间隔' 
}

// --- 树操作逻辑 ---
const filterNode = (value, data) => data.title.includes(value)
const onFilterChanged = (val) => treeRef.value.filter(val)

const handleNodeClick = async (data) => {
  if (data.isEdit || data.type === 'folder') return 
  if (currentNode.value && currentNode.value.id === data.id) return
  contentLoading.value = true
  try {
    const res = await request.get(`/recitations/${data.id}`)
    currentNode.value = res
    isEdit.value = false 
  } finally { contentLoading.value = false }
}

const handleAdd = async (type, parentId = 0) => {
  if (parentId !== 0 && treeRef.value) {
    const node = treeRef.value.getNode(parentId)
    if (node) node.expand()
  }
  const tempNode = {
    id: -1, 
    title: '', 
    type: type, 
    parent_id: parentId, 
    content: '',
    
    // ✅ 关键：设为 null，让下拉框显示 placeholder "请选择规则"
    rule_id: null, 
    
    isEdit: true, 
    notebook_id: currentNotebookId.value 
  }
  treeData.value.push(tempNode)
}
// 图片上传处理函数
// 📷 图片上传处理函数
const onUploadImg = async (files, callback) => {
  try {
    const res = await Promise.all(
      files.map((file) => {
        return new Promise((resolve, reject) => {
          const form = new FormData();
          form.append('file', file);

          // 🔴 关键点 1：这里千万不要手动加 headers: { 'Content-Type': ... }
          // 直接传 form，Axios 会自动处理 Boundary
          request.post('/upload/image', form)
            .then((response) => resolve(response))
            .catch((error) => reject(error));
        });
      })
    );

    // 🔴 关键点 2：必须调用 callback 将 URL 回填给编辑器
    // 这里的 item 对应后端返回的 JSON，例如 { "url": "...", "alt": "..." }
    // 请确保你的 request.js 响应拦截器返回的是 res.data
    callback(res.map((item) => item.url));
    
  } catch (error) {
    console.error('图片上传失败', error);
    ElMessage.error('图片上传失败，请检查文件大小或网络');
  }
}
const handleInputConfirm = async (data) => {
  if (submitLock.value) return
  submitLock.value = true
  try {
    if (!data.title || !data.title.trim()) {
      if (data.id === -1) {
        const index = treeData.value.findIndex(item => item.id === -1)
        if (index > -1) treeData.value.splice(index, 1)
      } else {
        data.isEdit = false
      }
      return
    }
    if (data.id === -1) {
      const payload = {
        title: data.title, type: data.type, parent_id: data.parent_id,
        rule_id: data.rule_id, content: '', 
        notebook_id: currentNotebookId.value 
      }
      const res = await request.post('/recitations', payload)
      ElMessage.success('创建成功')
      const index = treeData.value.findIndex(item => item.id === -1)
      if (index > -1) treeData.value[index] = { ...res, isEdit: false }
      if (res.type === 'doc') { currentNode.value = res; isEdit.value = true }
    } else {
      await request.put(`/recitations/${data.id}`, { title: data.title }) 
      ElMessage.success('重命名成功')
      data.isEdit = false
      if(currentNode.value && currentNode.value.id === data.id) {
          currentNode.value.title = data.title
      }
    }
  } catch (e) { 
      console.error(e)
      if (data.id === -1) {
       const index = treeData.value.findIndex(item => item.id === -1)
       if (index > -1) treeData.value.splice(index, 1)
      }
  } finally { submitLock.value = false }
}

const handleRename = (data) => {
  const item = treeData.value.find(i => i.id === data.id)
  if (item) item.isEdit = true
}

// 保存编辑器内容 (Content + Rule)
const handleSaveContent = async () => {
  if (!currentNode.value) return
  
  // 内容非空校验
  if (!currentNode.value.content || !currentNode.value.content.trim()) {
      ElMessage.warning('文档内容不能为空')
      return
  }
  
  try {
    const dataToSend = {
        ...currentNode.value,
        
        // 🔴🔴🔴 核心修复：强制确保 rule_id 存在
        // 如果是 undefined (未定义) 或 "" (空字符串)，都转为 null
        // 这样发给后端时就是 rule_id: null，后端就能识别到了
        rule_id: currentNode.value.rule_id ? currentNode.value.rule_id : null,
        // 🔴🔴🔴 新增：发送提醒时间给后端
        // 如果用户没选，就默认传个 09:00，或者直接传当前 reminderTime 的值
        reminder_time: reminderTime.value || '09:00',
        // 剔除不需要的字段
        created_at: undefined,
        updated_at: undefined,
        next_review_time: undefined
    }
    
    // 调试用：你可以打开控制台看这就一定有 rule_id 了
    console.log('修复后发送的数据:', dataToSend) 

    await request.put(`/recitations/${currentNode.value.id}`, dataToSend)
    
    ElMessage.success('保存成功')
    isEdit.value = false
    
    const node = treeData.value.find(i => i.id === currentNode.value.id)
    if(node) node.title = currentNode.value.title
    
  } catch (e) { console.error(e) }
}

const handleDelete = (data) => {
  ElMessageBox.confirm(`确认删除 "${data.title}" 吗？`, '警告', { type: 'warning' })
  .then(async () => {
    await request.delete(`/recitations/${data.id}`)
    ElMessage.success('已删除')
    if (currentNode.value && currentNode.value.id === data.id) currentNode.value = null
    loadData()
  })
}

const handleDrop = async (draggingNode, dropNode, dropType) => {
  let newParentId = 0
  if (dropType === 'inner') { newParentId = dropNode.data.id } 
  else { newParentId = dropNode.data.parent_id }
  await request.put(`/recitations/${draggingNode.data.id}`, { parent_id: newParentId })
}
const allowDrop = (draggingNode, dropNode, type) => {
  if (type === 'inner' && dropNode.data.type === 'doc') return false
  return true
}

onMounted(() => {
  loadNotebooks()
})
</script>

<template>
  <div class="page-wrapper">
    
    <div class="notebook-bar">
       <div class="nb-left">
           <el-icon class="nb-icon"><Notebook /></el-icon>
           <span class="nb-label">当前笔记本：</span>
           <el-select 
             v-model="currentNotebookId" 
             placeholder="选择笔记本" 
             @change="handleNotebookChange"
             style="width: 200px"
             size="default"
           >
             <el-option v-for="nb in notebooks" :key="nb.id" :label="nb.name" :value="nb.id" />
           </el-select>
       </div>
       <div class="nb-right">
           <el-button link type="primary" :icon="Plus" @click="handleAddNotebook">新建笔记本</el-button>
           <el-dropdown trigger="click" @command="handleEditNotebook">
               <el-button link :icon="MoreFilled" />
               <template #dropdown>
                   <el-dropdown-menu>
                       <el-dropdown-item command="delete" style="color:red;">删除当前笔记本</el-dropdown-item>
                   </el-dropdown-menu>
               </template>
           </el-dropdown>
       </div>
    </div>

    <div class="wiki-container">
      
      <div class="sidebar">
        <div class="sidebar-search">
          <el-input 
            v-model="filterText" 
            placeholder="搜索目录..." 
            :prefix-icon="Search" 
            size="small"
            clearable
            @input="onFilterChanged"
          />
          <el-dropdown trigger="click" style="margin-left: 5px;">
            <el-button size="small" :icon="Plus" circle />
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item :icon="DocumentAdd" @click="handleAdd('doc')">新建文档</el-dropdown-item>
                <el-dropdown-item :icon="FolderAdd" @click="handleAdd('folder')">新建文件夹</el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </div>

        <el-scrollbar>
          <el-tree
            ref="treeRef"
            :data="treeDataSource"
            :props="{ label: 'title', children: 'children' }"
            node-key="id"
            :filter-node-method="filterNode"
            highlight-current
            draggable
            :allow-drop="allowDrop"
            @node-click="handleNodeClick"
            @node-drop="handleDrop"
            empty-text="空空如也"
            :expand-on-click-node="false"
            default-expand-all
          >
            <template #default="{ node, data }">
              <div class="custom-tree-node">
                <span class="node-content">
                  <el-icon v-if="data.type === 'folder'" style="margin-right: 5px; color: #E6A23C;"><Folder /></el-icon>
                  <el-icon v-else style="margin-right: 5px; color: #409EFF;"><Document /></el-icon>
                  
                  <el-input 
                    v-if="data.isEdit" 
                    v-model="data.title"
                    size="small"
                    v-focus
                    @click.stop
                    @blur="handleInputConfirm(data)"
                    @keyup.enter="handleInputConfirm(data)"
                    placeholder="输入名称"
                    class="rename-input"
                  />
                  <span v-else class="node-title" :title="node.label">{{ node.label }}</span>
                </span>
                
                <span class="node-actions" v-if="!data.isEdit" @click.stop>
                  <el-dropdown trigger="click" size="small">
                    <el-icon class="action-icon"><MoreFilled /></el-icon>
                    <template #dropdown>
                      <el-dropdown-menu>
                        <el-dropdown-item v-if="data.type === 'folder'" :icon="DocumentAdd" @click="handleAdd('doc', data.id)">子文档</el-dropdown-item>
                        <el-dropdown-item v-if="data.type === 'folder'" :icon="FolderAdd" @click="handleAdd('folder', data.id)">子目录</el-dropdown-item>
                        <el-dropdown-item :icon="Edit" @click="handleRename(data)">重命名</el-dropdown-item>
                        <el-dropdown-item :icon="Delete" class="text-danger" @click="handleDelete(data)">删除</el-dropdown-item>
                      </el-dropdown-menu>
                    </template>
                  </el-dropdown>
                </span>
              </div>
            </template>
          </el-tree>
        </el-scrollbar>
      </div>

      <div class="content-area" v-loading="contentLoading">
        <div v-if="!currentNode" class="empty-state">
          <el-empty description="请选择或创建一个文档" />
        </div>

        <div v-else class="editor-wrapper">
          
          <div class="content-breadcrumb">
             <el-breadcrumb :separator-icon="ArrowRight">
                 <el-breadcrumb-item v-for="item in breadcrumbs" :key="item.id">
                     <span :class="{'active-crumb': item.id === currentNode.id}">{{ item.title }}</span>
                 </el-breadcrumb-item>
             </el-breadcrumb>
             
             <div class="breadcrumb-actions">
              <template v-if="isEdit">
                <el-button size="small" @click="isEdit = false">取消</el-button>
                <el-button size="small" type="primary" @click="handleSaveContent">保存</el-button>
              </template>
              <template v-else>
                <span class="rule-label-text">复习规则：</span>
                <el-tag size="small" type="info" effect="plain" style="margin-right: 10px;">
                  {{ rules.find(r => r.id === currentNode.rule_id)?.name || '未设置' }}
                </el-tag>
                
                <el-button 
                  size="small" 
                  type="success" 
                  plain 
                  :icon="Printer" 
                  :loading="isPdfLoading"
                  @click="handlePreviewPDF"
                >
                  预览 PDF
                </el-button>
                
                <el-button size="small" type="primary" link :icon="Edit" @click="isEdit = true">编辑内容</el-button>
              </template>
            </div>
          </div>
          
          <div class="rule-info-panel" v-if="isEdit && currentRule">
             <div class="rule-card">
                 <div class="rule-card-header">
                    <el-tag effect="dark" type="primary" size="small" style="margin-right: 8px; border-radius: 2px;">固定</el-tag>
                    <span class="rule-card-name">{{ currentRule.name }}</span>
                 </div>
                 <div class="rule-card-intervals">{{ formatRuleInterval(currentRule) }}</div>
             </div>
          </div>

          <div class="editor-title-row" v-if="isEdit">
             <el-input v-model="currentNode.title" placeholder="在此修改文档标题..." class="title-input-large" />
          </div>

          <div class="content-body">
            <MdEditor 
              v-if="isEdit"
              v-model="currentNode.content" 
              :height="'100%'"
              :toolbars="['bold', 'underline', 'italic', '-', 'title', 'quote', 'unorderedList', 'orderedList', '-', 'codeRow', 'code', 'link', 'image', 'table', '-', 'preview', 'catalog']"
              @onUploadImg="onUploadImg"
            />
            <MdPreview 
              v-else
              :modelValue="currentNode.content" 
              class="preview-mode"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* 整体页面布局 */
.page-wrapper {
  display: flex; flex-direction: column; height: calc(100vh - 84px);
  margin: 10px; background: #fff; border-radius: 8px; border: 1px solid #e6e6e6; overflow: hidden;
}

/* 顶部笔记本栏 */
.notebook-bar {
    height: 50px; background: #f8f9fa; border-bottom: 1px solid #e6e6e6;
    display: flex; align-items: center; justify-content: space-between; padding: 0 20px;
}
.nb-left { display: flex; align-items: center; gap: 10px; }
.nb-icon { font-size: 18px; color: #606266; }
.nb-label { font-size: 14px; color: #606266; font-weight: bold; }

/* 容器布局 */
.wiki-container { display: flex; flex: 1; overflow: hidden; }

/* 左侧样式 */
.sidebar { width: 260px; background-color: #fff; border-right: 1px solid #e6e6e6; display: flex; flex-direction: column; }
.sidebar-search { padding: 10px; display: flex; align-items: center; border-bottom: 1px solid #f0f0f0; }
.custom-tree-node { flex: 1; display: flex; align-items: center; justify-content: space-between; font-size: 14px; padding-right: 8px; overflow: hidden; height: 100%; }
.node-content { display: flex; align-items: center; flex: 1; overflow: hidden; }
.rename-input { width: 100%; margin-right: 5px; }
.node-title { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.node-actions { display: none; }
.custom-tree-node:hover .node-actions { display: block; }
.action-icon { color: #909399; transform: rotate(90deg); cursor: pointer; }
:deep(.el-tree-node__content) { height: 34px; }
:deep(.el-tree-node.is-current > .el-tree-node__content) { background-color: #ecf5ff; color: #409EFF; font-weight: bold; }

/* 右侧内容区 */
.content-area { flex: 1; background-color: #fff; display: flex; flex-direction: column; overflow: hidden; }
.empty-state { display: flex; align-items: center; justify-content: center; height: 100%; color: #909399; }
.editor-wrapper { display: flex; flex-direction: column; height: 100%; }

/* 面包屑导航栏 */
.content-breadcrumb {
  height: 50px; padding: 0 20px; border-bottom: 1px solid #f0f0f0;
  display: flex; justify-content: space-between; align-items: center; background: #fff;
}
:deep(.el-breadcrumb__inner) { color: #606266; font-weight: normal; }
.active-crumb { font-weight: bold; color: #303133; }

/* 右侧操作栏样式优化 */
.breadcrumb-actions { display: flex; align-items: center; }
.rule-label-text { font-size: 14px; color: #606266; margin-right: 5px; display: flex; align-items: center; }

/* 规则详情卡片样式 */
.rule-info-panel {
    padding: 10px 20px 0 20px;
}
.rule-card {
    border: 1px solid #e4e7ed;
    background-color: #fafafa;
    border-radius: 4px;
    padding: 12px 15px;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}
.rule-card-header {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
}
.rule-card-name {
    font-weight: bold;
    color: #303133;
    font-size: 14px;
}
.rule-card-intervals {
    color: #606266;
    font-size: 13px;
    padding-left: 2px;
}

/* 编辑模式下的标题输入 */
.editor-title-row { padding: 10px 20px 0; }
.title-input-large :deep(.el-input__wrapper) { box-shadow: none; font-size: 24px; font-weight: bold; padding-left: 0; }

.content-body { flex: 1; overflow: auto; padding: 0; }
:deep(.md-editor) { height: 100% !important; border: none; }
:deep(.md-editor-preview-wrapper) { padding: 20px 40px; }
.preview-mode { padding: 20px 40px; }
.text-danger { color: #F56C6C; }
/* 让 md-editor-v3 预览区的所有图片默认居中显示 */
:deep(.md-editor-preview img) {
  display: block;
  margin: 10px auto; /* 上下留点间距，左右设为 auto 就会自动居中 */
  max-width: 100%; /* 防止大图撑爆页面 */
  border-radius: 4px; /* 顺便加个好看的圆角（可选） */
  box-shadow: 0 2px 12px 0 rgba(0, 0, 0, 0.1); /* 顺便加个阴影让图片更有质感（可选） */
}
</style>