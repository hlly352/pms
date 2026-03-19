<script setup>
import { ref, computed, onMounted } from 'vue'
import { getStats, getRules, saveRule, executeAllocation, getLogs, deleteLog } from '@/api/allocation'
import { getAccounts } from '@/api/account' 
import { ElMessage, ElMessageBox } from 'element-plus'
import { 
  Money, Setting, Clock, CirclePlus, Check, Search, Refresh, Delete 
} from '@element-plus/icons-vue'

// ==========================================
// 1. 全局状态与基础数据
// ==========================================
const activeTab = ref('execute')
const loading = ref(false)
const accounts = ref([])
const stats = ref({ total_income: 0, total_allocated: 0, unallocated_pool: 0 })
const rules = ref([])
const logs = ref([])

// 🌟 新增：日志搜索状态
const logQueryParams = ref({ rule_id: null, dateRange: null })

// ==========================================
// 2. 辅助与工具函数
// ==========================================
// 🌟 新增：格式化时间戳，提取 YYYY-MM-DD
const formatDateOnly = (dateStr) => {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

// 提取查询日志的参数
const getLogParams = () => {
  const params = {}
  if (logQueryParams.value.rule_id) params.rule_id = logQueryParams.value.rule_id
  if (logQueryParams.value.dateRange && logQueryParams.value.dateRange.length === 2) {
    params.start_date = logQueryParams.value.dateRange[0]
    params.end_date = logQueryParams.value.dateRange[1]
  }
  return params
}

// 加载大盘和基础数据 (支持带条件查询日志)
const loadDashboard = async () => {
  loading.value = true
  try {
    const [statsRes, accountsRes, rulesRes, logsRes] = await Promise.all([
      getStats(), 
      getAccounts(), 
      getRules(), 
      getLogs(getLogParams()) // 🌟 传入搜索参数
    ])
    stats.value = statsRes.data || statsRes
    accounts.value = (accountsRes.data || accountsRes).filter(a => a.status === 1) // 只要正常账户
    rules.value = rulesRes.data || rulesRes
    logs.value = logsRes.data || logsRes
  } catch (e) {
    console.error('加载分配模块失败', e)
  } finally {
    loading.value = false
  }
}

// ==========================================
// 3. 分配执行台 (Execute)
// ==========================================
const execForm = ref({ rule_id: null, amount: undefined, remark: '' })
const execLoading = ref(false)

// 一键填入所有未分配金额
const handleFillUnallocated = () => {
  execForm.value.amount = stats.value.unallocated_pool
}

// 计算预览分配结果
const previewItems = computed(() => {
  if (!execForm.value.rule_id || !execForm.value.amount) return []
  const rule = rules.value.find(r => r.id === execForm.value.rule_id)
  if (!rule) return []
  
  return rule.items.map(item => {
    return {
      account_name: item.account?.name || '未知账户',
      color: item.account?.color || '#409EFF',
      ratio: item.ratio,
      allocated: ((execForm.value.amount * item.ratio) / 100).toFixed(2)
    }
  }).filter(item => item.ratio > 0)
})

// 执行分配 🚀
const handleExecute = async () => {
  if (!execForm.value.rule_id) return ElMessage.warning('请选择分配规则！')
  if (!execForm.value.amount || execForm.value.amount <= 0) return ElMessage.warning('请输入有效的分配金额！')

  if (execForm.value.amount > stats.value.unallocated_pool) {
    await ElMessageBox.confirm('分配金额超过了当前待分配资金池，确定要透支分配吗？', '提示', { type: 'warning' })
  }

  execLoading.value = true
  try {
    await executeAllocation(execForm.value)
    ElMessage.success('资金分配执行成功！各大账户已入账。')
    execForm.value = { rule_id: null, amount: undefined, remark: '' }
    loadDashboard() // 刷新全局数据
    activeTab.value = 'logs' // 自动跳转到日志页看爽感
  } catch (e) {
    console.error(e)
  } finally {
    execLoading.value = false
  }
}

// ==========================================
// 4. 规则管理库 (Rule Engine)
// ==========================================
const ruleDialogVisible = ref(false)
const ruleLoading = ref(false)
const ruleForm = ref({ id: null, name: '', remark: '', items: [] })

// 算总比例
const ruleTotalRatio = computed(() => {
  return ruleForm.value.items.reduce((sum, item) => sum + Number(item.ratio), 0)
})

const openRuleDialog = (row = null) => {
  if (row) {
    // 编辑模式：匹配现有规则，没有的账户补0
    const items = accounts.value.map(acc => {
      const existing = row.items.find(i => i.account_id === acc.id)
      return { account_id: acc.id, account_name: acc.name, color: acc.color, ratio: existing ? existing.ratio : 0 }
    })
    ruleForm.value = { id: row.id, name: row.name, remark: row.remark, items }
  } else {
    // 新增模式：拉出所有账户，比例全为0
    const items = accounts.value.map(acc => ({ account_id: acc.id, account_name: acc.name, color: acc.color, ratio: 0 }))
    ruleForm.value = { id: null, name: '', remark: '', items }
  }
  ruleDialogVisible.value = true
}

const handleSaveRule = async () => {
  if (!ruleForm.value.name) return ElMessage.warning('规则名称不能为空！')
  if (ruleTotalRatio.value !== 100) return ElMessage.warning('规则比例总和必须刚好等于 100%！')

  ruleLoading.value = true
  try {
    // 过滤掉比例为 0 的项目，减轻数据库压力
    const payload = {
      ...ruleForm.value,
      items: ruleForm.value.items.filter(i => i.ratio > 0)
    }
    await saveRule(payload)
    ElMessage.success('规则保存成功！')
    ruleDialogVisible.value = false
    loadDashboard()
  } catch (e) {
    console.error(e)
  } finally {
    ruleLoading.value = false
  }
}

// ==========================================
// 5. 🌟 日志搜索与撤回 (Log Actions)
// ==========================================
const handleSearchLogs = () => loadDashboard()

const handleResetLogs = () => {
  logQueryParams.value = { rule_id: null, dateRange: null }
  loadDashboard()
}

// 🌟 核心：删除日志并安全撤回资金
const handleDeleteLog = (row) => {
  ElMessageBox.confirm('⚠️ 危险操作：删除此日志将会把当时分配的资金从各大账户中强制扣除并退回！确定撤销吗？', '资金撤回确认', {
    type: 'error', 
    confirmButtonText: '确定撤回', 
    cancelButtonText: '取消'
  }).then(async () => {
    try {
      await deleteLog(row.id)
      ElMessage.success('操作成功！资金已退回待分配池。')
      loadDashboard() // 重新加载大盘和日志，数字会瞬间恢复
    } catch (e) {
      console.error('撤回资金失败:', e)
    }
  }).catch(() => {})
}

// ==========================================
// 6. 生命周期
// ==========================================
onMounted(() => {
  loadDashboard()
})
</script>

<template>
  <div class="page-container" v-loading="loading">
    
    <el-row :gutter="20" class="dashboard-row">
      <el-col :span="8">
        <el-card shadow="never" class="stat-card bg-primary">
          <div class="stat-title">待分配资金池 (元)</div>
          <div class="stat-value pool-value">{{ Number(stats.unallocated_pool).toFixed(2) }}</div>
          <div class="stat-desc">你可以随时将这些钱分配至对应账户</div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="never" class="stat-card">
          <div class="stat-title text-muted">历史总收入 (元)</div>
          <div class="stat-value text-success">{{ Number(stats.total_income).toFixed(2) }}</div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="never" class="stat-card">
          <div class="stat-title text-muted">历史已分配 (元)</div>
          <div class="stat-value text-warning">{{ Number(stats.total_allocated).toFixed(2) }}</div>
        </el-card>
      </el-col>
    </el-row>

    <el-card shadow="never" class="main-card">
      <el-tabs v-model="activeTab" class="custom-tabs">
        
        <el-tab-pane name="execute">
          <template #label><el-icon><Money /></el-icon> 资金分配执行</template>
          
          <el-row :gutter="40" style="margin-top: 20px;">
            <el-col :span="12">
              <div class="exec-form-wrapper">
                <h3 class="section-title">设置本次分配</h3>
                <el-form :model="execForm" label-position="top">
                  <el-form-item label="选择分配规则" required>
                    <el-select v-model="execForm.rule_id" placeholder="请选择一条你预设的规则" style="width: 100%" size="large">
                      <el-option v-for="rule in rules" :key="rule.id" :label="rule.name" :value="rule.id">
                        <span style="float: left">{{ rule.name }}</span>
                        <span style="float: right; color: #8492a6; font-size: 13px">{{ rule.remark }}</span>
                      </el-option>
                    </el-select>
                  </el-form-item>
                  
                  <el-form-item label="本次分配金额 (元)" required>
                    <el-input v-model="execForm.amount" size="large" placeholder="0.00">
                      <template #append>
                        <el-button @click="handleFillUnallocated" type="primary" plain>全部分配</el-button>
                      </template>
                    </el-input>
                  </el-form-item>

                  <el-form-item label="分配备注说明">
                    <el-input v-model="execForm.remark" type="textarea" rows="2" placeholder="例如：2026年3月工资分配..." />
                  </el-form-item>

                  <el-button type="primary" size="large" style="width: 100%; margin-top: 10px;" :icon="Check" :loading="execLoading" @click="handleExecute">
                    🚀 立即执行分配
                  </el-button>
                </el-form>
              </div>
            </el-col>

            <el-col :span="12">
              <div class="preview-wrapper">
                <h3 class="section-title">切分预览图</h3>
                <div v-if="previewItems.length > 0" class="preview-list">
                  <div v-for="(item, idx) in previewItems" :key="idx" class="preview-item" :style="{ borderLeft: `4px solid ${item.color}` }">
                    <div class="preview-info">
                      <span class="preview-name">{{ item.account_name }}</span>
                      <el-tag size="small" :color="item.color + '20'" :style="{ color: item.color, border: 'none' }">{{ item.ratio }}%</el-tag>
                    </div>
                    <div class="preview-amount" :style="{ color: item.color }">
                      + ￥{{ item.allocated }}
                    </div>
                  </div>
                </div>
                <el-empty v-else description="请在左侧选择规则并输入金额查看预览" :image-size="100" />
              </div>
            </el-col>
          </el-row>
        </el-tab-pane>

        <el-tab-pane name="rules">
            <template #label><el-icon><Setting /></el-icon> 规则引擎库</template>
          
          <div style="margin: 15px 0;">
            <el-button type="primary" :icon="CirclePlus" @click="openRuleDialog()">新建分配规则</el-button>
          </div>

          <el-row :gutter="20">
            <el-col :span="8" v-for="rule in rules" :key="rule.id" style="margin-bottom: 20px;">
              <el-card shadow="hover" class="rule-card">
                <div class="rule-header">
                  <span class="rule-name">{{ rule.name }}</span>
                  <el-button link type="primary" :icon="Edit" @click="openRuleDialog(rule)">配置</el-button>
                </div>
                <div class="rule-desc">{{ rule.remark || '暂无说明' }}</div>
                <div class="rule-tags">
                  <el-tag v-for="item in rule.items" :key="item.id" size="small" style="margin: 0 5px 5px 0;">
                    {{ item.account?.name }} ({{ item.ratio }}%)
                  </el-tag>
                </div>
              </el-card>
            </el-col>
          </el-row>
        </el-tab-pane>

        <el-tab-pane name="logs">
          <template #label><el-icon><Clock /></el-icon> 历史日志</template>
          
          <el-form :inline="true" :model="logQueryParams" class="search-form" style="margin-top: 15px;">
            <el-form-item label="分配规则">
              <el-select v-model="logQueryParams.rule_id" placeholder="全部规则" clearable style="width: 160px" @change="handleSearchLogs">
                <el-option v-for="rule in rules" :key="rule.id" :label="rule.name" :value="rule.id" />
              </el-select>
            </el-form-item>
            <el-form-item label="执行日期段">
              <el-date-picker
                v-model="logQueryParams.dateRange"
                type="daterange"
                range-separator="至"
                start-placeholder="开始日期"
                end-placeholder="结束日期"
                value-format="YYYY-MM-DD"
                style="width: 260px"
                clearable
                @change="handleSearchLogs"
              />
            </el-form-item>
            <el-form-item>
              <el-button type="primary" :icon="Search" @click="handleSearchLogs">搜索</el-button>
              <el-button :icon="Refresh" @click="handleResetLogs">重置</el-button>
            </el-form-item>
          </el-form>

          <el-table :data="logs" border stripe style="width: 100%; margin-top: 15px;">
            <el-table-column type="expand">
              <template #default="props">
                <div class="log-detail-box">
                  <h4 style="margin: 0 0 10px 0; color: #606266;">资金去向明细快照：</h4>
                  <el-table :data="props.row.items" size="small" border>
                    <el-table-column prop="account_name" label="当时入账账户" width="180" />
                    <el-table-column label="执行比例" width="100" align="center">
                      <template #default="{ row }">{{ row.ratio }}%</template>
                    </el-table-column>
                    <el-table-column label="实际入账金额" align="right">
                      <template #default="{ row }">
                        <span style="color: #67C23A; font-weight: bold;">+ ￥{{ Number(row.allocated_amount).toFixed(2) }}</span>
                      </template>
                    </el-table-column>
                  </el-table>
                </div>
              </template>
            </el-table-column>
            
            <el-table-column label="执行日期" width="130" align="center">
              <template #default="{ row }">
                {{ formatDateOnly(row.created_at) }}
              </template>
            </el-table-column>
            
            <el-table-column prop="rule_name" label="使用的规则" width="180">
              <template #default="{ row }"><el-tag effect="plain">{{ row.rule_name }}</el-tag></template>
            </el-table-column>
            <el-table-column label="分配总金额 (元)" width="150" align="right">
              <template #default="{ row }">
                <span style="font-weight: bold; font-family: Consolas;">￥{{ Number(row.total_amount).toFixed(2) }}</span>
              </template>
            </el-table-column>
            <el-table-column prop="remark" label="备注" min-width="150" show-overflow-tooltip />
            
            <el-table-column label="操作" width="100" align="center" fixed="right">
              <template #default="{ row }">
                <el-button type="danger" link :icon="Delete" @click="handleDeleteLog(row)">删除</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

      </el-tabs>
    </el-card>

    <el-dialog v-model="ruleDialogVisible" :title="ruleForm.id ? '配置分配规则' : '新建分配规则'" width="550px" destroy-on-close top="8vh">
      <el-form :model="ruleForm" label-width="80px">
        <el-form-item label="规则名称" required>
          <el-input v-model="ruleForm.name" placeholder="如：每月发薪日分配法" />
        </el-form-item>
        <el-form-item label="规则说明">
          <el-input v-model="ruleForm.remark" type="textarea" rows="2" placeholder="写个备注..." />
        </el-form-item>

        <el-divider border-style="dashed">比例切分盘</el-divider>

        <div class="ratio-summary" style="text-align: center; margin-bottom: 20px;">
          当前已分配比例: 
          <span :style="{ fontSize: '20px', fontWeight: 'bold', color: ruleTotalRatio === 100 ? '#67C23A' : '#F56C6C' }">
            {{ ruleTotalRatio }}%
          </span>
          <div v-if="ruleTotalRatio !== 100" style="font-size: 12px; color: #F56C6C; margin-top: 5px;">
            (注意：总比例必须精确等于 100% 才能保存)
          </div>
        </div>

        <div class="ratio-scroll-box">
          <div v-for="(item, index) in ruleForm.items" :key="index" class="ratio-item">
            <div class="ratio-label">
              <span class="dot" :style="{ backgroundColor: item.color || '#ccc' }"></span>
              {{ item.account_name }}
            </div>
            <div class="ratio-slider">
              <el-slider v-model="item.ratio" :step="1" show-input input-size="small" />
            </div>
          </div>
        </div>
      </el-form>

      <template #footer>
        <el-button @click="ruleDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSaveRule" :loading="ruleLoading" :disabled="ruleTotalRatio !== 100">保存规则</el-button>
      </template>
    </el-dialog>

  </div>
</template>

<style scoped>
.page-container { padding: 20px; background: #f5f7fa; min-height: calc(100vh - 84px); }

/* 大盘卡片 */
.dashboard-row { margin-bottom: 20px; }
.stat-card { border-radius: 8px; border: none; text-align: center; padding: 10px 0; }
.bg-primary { background: linear-gradient(135deg, #409EFF 0%, #3a8ee6 100%); color: white; }
.stat-title { font-size: 14px; margin-bottom: 10px; }
.text-muted { color: #909399; }
.stat-value { font-size: 28px; font-weight: bold; font-family: Consolas, monospace; }
.pool-value { font-size: 36px; text-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.text-success { color: #67C23A; }
.text-warning { color: #E6A23C; }
.stat-desc { font-size: 12px; margin-top: 8px; opacity: 0.8; }

/* 主体内容区 */
.main-card { border-radius: 8px; border: none; box-shadow: 0 2px 12px 0 rgba(0,0,0,0.05); min-height: 500px; }
.custom-tabs :deep(.el-tabs__item) { font-size: 15px; height: 50px; line-height: 50px; }
.section-title { margin: 0 0 20px 0; color: #303133; font-size: 16px; border-left: 4px solid #409EFF; padding-left: 10px; }

/* 预览列表 */
.preview-wrapper { background: #f8f9fa; border-radius: 8px; padding: 20px; height: 100%; border: 1px dashed #dcdfe6; }
.preview-list { display: flex; flex-direction: column; gap: 12px; }
.preview-item { background: white; padding: 12px 15px; border-radius: 6px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
.preview-name { font-weight: bold; margin-right: 10px; color: #303133; }
.preview-amount { font-size: 18px; font-weight: bold; font-family: Consolas; }

/* 规则卡片 */
.rule-card { border-radius: 8px; }
.rule-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #ebeef5; padding-bottom: 10px; margin-bottom: 10px; }
.rule-name { font-weight: bold; font-size: 15px; color: #303133; }
.rule-desc { font-size: 12px; color: #909399; margin-bottom: 15px; height: 18px; overflow: hidden; }

/* 日志展开项 */
.log-detail-box { padding: 15px 30px; background: #fafafa; border-radius: 4px; }

/* 比例控制台滑块 */
.ratio-scroll-box { max-height: 350px; overflow-y: auto; padding-right: 10px; }
.ratio-item { display: flex; align-items: center; margin-bottom: 15px; }
.ratio-label { width: 100px; font-weight: bold; font-size: 13px; display: flex; align-items: center; }
.ratio-label .dot { width: 8px; height: 8px; border-radius: 50%; margin-right: 8px; }
.ratio-slider { flex: 1; margin-left: 15px; }
</style>