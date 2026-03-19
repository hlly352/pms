<script setup>
import { ref, onMounted } from 'vue'
// 注意：移除了批量修改比例的 API，引入了 getAccountAllocations
import { getAccounts, createAccount, updateAccount, deleteAccount, getAccountAllocations } from '@/api/account'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Edit, Delete, Wallet, Tickets } from '@element-plus/icons-vue' // 引入 Tickets 图标

// ==========================================
// 1. 基础状态
// ==========================================
const accounts = ref([])
const loading = ref(false)
const dialogVisible = ref(false)
const isEdit = ref(false)
const formLoading = ref(false)

const predefinedColors = ['#409EFF', '#67C23A', '#E6A23C', '#F56C6C', '#8E44AD', '#34495E']

// 表单去掉了 income_ratio
const form = ref({ id: null, name: '', balance: 0.00, color: '#409EFF', remark: '', status: 1 })

// 🌟 新增：入账记录弹窗的专属状态
const historyDialogVisible = ref(false)
const historyLoading = ref(false)
const historyData = ref([])
const currentAccountName = ref('')

// 格式化时间辅助函数
const formatDateTime = (dateStr) => {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
}

// ==========================================
// 2. 数据加载
// ==========================================
const loadData = async () => {
  loading.value = true
  try {
    const res = await getAccounts()
    accounts.value = res.data || res || []
  } catch (error) {
    console.error('获取账户失败:', error)
  } finally {
    loading.value = false
  }
}

// ==========================================
// 3. 🌟 新增：查看入账历史记录
// ==========================================
const handleViewHistory = async (row) => {
  currentAccountName.value = row.name
  historyDialogVisible.value = true
  historyLoading.value = true
  try {
    const res = await getAccountAllocations(row.id)
    historyData.value = res.data || res || []
  } catch (error) {
    console.error('获取分配记录失败:', error)
  } finally {
    historyLoading.value = false
  }
}

// ==========================================
// 4. 基础表单交互
// ==========================================
const handleAdd = () => {
  isEdit.value = false
  form.value = { id: null, name: '', balance: 0.00, color: '#409EFF', remark: '', status: 1 }
  dialogVisible.value = true
}

const handleEdit = (row) => {
  isEdit.value = true
  form.value = { ...row }
  dialogVisible.value = true
}

const handleSubmit = async () => {
  if (!form.value.name) return ElMessage.warning('账户名称不能为空！')

  formLoading.value = true
  try {
    if (isEdit.value) await updateAccount(form.value.id, form.value)
    else await createAccount(form.value)
    ElMessage.success('保存成功')
    dialogVisible.value = false
    loadData()
  } catch (error) {
    console.error('保存失败:', error)
  } finally {
    formLoading.value = false
  }
}

const handleDelete = (row) => {
  if (Number(row.balance) !== 0) return ElMessage.warning('该账户余额不为零，禁止直接删除！')
  ElMessageBox.confirm(`确定要删除账户【${row.name}】吗？`, '删除确认', { type: 'error' }).then(async () => {
    await deleteAccount(row.id)
    ElMessage.success('删除成功')
    loadData()
  }).catch(() => {})
}

onMounted(() => { loadData() })
</script>

<template>
  <div class="page-container">
    <el-card shadow="never" class="main-card">
      <template #header>
        <div class="card-header">
          <div class="header-title">
            <el-icon class="header-icon"><Wallet /></el-icon>
            <span>账户与资金池管理</span>
          </div>
          <div>
            <el-button type="primary" :icon="Plus" @click="handleAdd">新建账户</el-button>
          </div>
        </div>
      </template>
      
      <div v-loading="loading" style="min-height: 200px;">
        <el-row :gutter="20" v-if="accounts.length > 0">
          <el-col :span="6" :xs="24" :sm="12" :md="8" :lg="6" v-for="item in accounts" :key="item.id">
            <el-card shadow="hover" class="account-card" :style="{ borderTop: `4px solid ${item.color || '#409EFF'}` }">
              <div class="acc-header">
                <span class="acc-title">{{ item.name }}</span>
                <el-tag size="small" :type="item.status === 1 ? 'success' : 'info'" effect="light">
                  {{ item.status === 1 ? '正常' : '停用' }}
                </el-tag>
              </div>
              
              <div class="acc-desc">{{ item.remark || '暂无说明' }}</div>
              
              <div class="acc-balance" :style="{ color: item.color || '#409EFF' }">
                <span class="currency">￥</span>
                <span>{{ Number(item.balance).toFixed(2) }}</span>
              </div>
              
              <div class="acc-actions">
                <el-button type="primary" link :icon="Edit" @click="handleEdit(item)">编辑</el-button>
                <el-button type="success" link :icon="Tickets" @click="handleViewHistory(item)">入账记录</el-button>
                <el-button type="danger" link :icon="Delete" @click="handleDelete(item)">删除</el-button>
              </div>
            </el-card>
          </el-col>
        </el-row>
        <el-empty v-else description="暂无账户，请点击右上角新建账户" />
      </div>
    </el-card>

    <el-dialog v-model="historyDialogVisible" :title="`【${currentAccountName}】历史入账记录`" width="600px" destroy-on-close top="10vh">
      <el-table :data="historyData" v-loading="historyLoading" border stripe height="400px" style="width: 100%">
        <el-table-column label="入账时间" width="160">
          <template #default="{ row }">
            <span style="color: #909399; font-size: 13px;">{{ formatDateTime(row.created_at) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="来源 (分配规则)" min-width="120">
          <template #default="{ row }">
            <el-tag size="small" effect="plain" type="info">{{ row.log?.rule_name || '手动分配/未知' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="切分比例" width="90" align="center">
          <template #default="{ row }">
            <el-tag size="small" type="warning" disable-transitions>{{ row.ratio }}%</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="入账金额" width="130" align="right">
          <template #default="{ row }">
            <span style="color: #67C23A; font-weight: bold; font-family: Consolas;">+ ￥{{ Number(row.allocated_amount).toFixed(2) }}</span>
          </template>
        </el-table-column>
      </el-table>
    </el-dialog>

    <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑账户' : '新建账户'" width="450px" destroy-on-close top="15vh">
      <el-form :model="form" label-width="80px">
        <el-form-item label="账户名称" required>
          <el-input v-model="form.name" placeholder="例如：生活账户、成长投资" clearable />
        </el-form-item>
        <el-form-item label="期初余额" v-if="!isEdit">
          <el-input-number v-model="form.balance" :precision="2" :step="100" style="width: 100%" placeholder="0.00" />
        </el-form-item>
        <el-form-item label="主题颜色">
          <el-color-picker v-model="form.color" :predefine="predefinedColors" color-format="hex" />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="form.status" :active-value="1" :inactive-value="0" active-text="正常" inactive-text="停用" />
        </el-form-item>
        <el-form-item label="用途说明">
          <el-input v-model="form.remark" type="textarea" rows="3" placeholder="写明这个资金池的专款专用规则..." />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmit" :loading="formLoading">确认保存</el-button>
      </template>
    </el-dialog>
    
  </div>
</template>

<style scoped>
.page-container { padding: 20px; background: #f5f7fa; min-height: calc(100vh - 84px); }
.main-card { border-radius: 8px; border: none; box-shadow: 0 2px 12px 0 rgba(0,0,0,0.05); }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.header-title { display: flex; align-items: center; font-size: 16px; font-weight: bold; color: #303133; }
.header-icon { margin-right: 8px; color: #409EFF; font-size: 18px; }

/* 账户卡片样式 */
.account-card { border-radius: 8px; margin-bottom: 20px; transition: all 0.3s ease; }
.account-card:hover { transform: translateY(-5px); box-shadow: 0 8px 16px rgba(0,0,0,0.1); }
.acc-header { display: flex; justify-content: space-between; align-items: center; }
.acc-title { font-size: 16px; font-weight: bold; color: #303133; }
.acc-desc { font-size: 12px; color: #909399; margin: 10px 0 20px 0; height: 34px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }

.acc-balance { font-size: 28px; font-weight: bold; font-family: Consolas, monospace; text-align: center; margin-bottom: 20px; }
.currency { font-size: 16px; margin-right: 2px; }
.acc-actions { border-top: 1px solid #ebeef5; padding-top: 10px; display: flex; justify-content: space-around; align-items: center; }
</style>