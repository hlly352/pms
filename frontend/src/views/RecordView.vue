<script setup>
import { ref, onMounted } from 'vue'
import { getRecordList, addRecord, deleteRecord } from '@/api/record'
import { ElMessage } from 'element-plus'
import { Delete, ChatDotSquare } from '@element-plus/icons-vue'

const records = ref([])
const content = ref('')

// 加载数据
const loadData = async () => {
  const res = await getRecordList()
  records.value = res
}

// 发布
const handlePublish = async () => {
  if (!content.value.trim()) return ElMessage.warning('写点什么吧...')
  
  try {
    await addRecord(content.value)
    ElMessage.success('发布成功')
    content.value = '' // 清空输入框
    loadData() // 刷新列表
  } catch (error) {
    console.error(error)
  }
}

// 删除
const handleDelete = async (id) => {
  if(!confirm('确定删除这条记录吗？')) return
  await deleteRecord(id)
  ElMessage.success('已删除')
  loadData()
}

// 格式化时间 (把 2026-02-04T12:00:00.000000Z 变成好看的格式)
const formatDate = (dateStr) => {
  const date = new Date(dateStr)
  return date.toLocaleString() // 或者用更简单的 date.toISOString().split('T')[0]
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="record-container">
    <h2>日常碎碎念 💭</h2>
    
    <div class="publish-box">
      <el-input
        v-model="content"
        type="textarea"
        :rows="3"
        placeholder="此刻你在想什么？记录下来吧..."
        maxlength="200"
        show-word-limit
      />
      <div class="btn-box">
        <el-button type="primary" @click="handlePublish">发布动态</el-button>
      </div>
    </div>

    <div class="timeline-box">
      <el-timeline>
        <el-timeline-item
          v-for="item in records"
          :key="item.id"
          :timestamp="formatDate(item.created_at)"
          placement="top"
          type="primary"
          :icon="ChatDotSquare"
        >
          <el-card class="record-card">
            <div class="card-content">
              <p>{{ item.content }}</p>
              <el-button 
                type="danger" 
                link 
                :icon="Delete" 
                class="del-btn"
                @click="handleDelete(item.id)"
              />
            </div>
          </el-card>
        </el-timeline-item>
      </el-timeline>
      
      <el-empty v-if="records.length === 0" description="还没有记录，发一条试试？" />
    </div>
  </div>
</template>

<style scoped>
.record-container {
  max-width: 800px;
  margin: 0 auto;
}
.publish-box {
  background: white;
  padding: 20px;
  border-radius: 8px;
  margin-bottom: 30px;
  box-shadow: 0 2px 12px 0 rgba(0,0,0,0.05);
}
.btn-box {
  margin-top: 10px;
  text-align: right;
}
.timeline-box {
  padding: 0 10px;
}
.record-card {
  border-radius: 8px;
}
.card-content {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}
.card-content p {
  margin: 0;
  font-size: 15px;
  line-height: 1.6;
  white-space: pre-wrap; /* 保留换行符 */
}
.del-btn {
  margin-left: 10px;
  opacity: 0; /* 默认隐藏删除按钮 */
  transition: opacity 0.3s;
}
.record-card:hover .del-btn {
  opacity: 1; /* 鼠标悬停时才显示删除按钮 */
}
</style>