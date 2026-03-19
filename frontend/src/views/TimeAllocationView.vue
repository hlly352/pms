<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { 
  Setting, Clock, CirclePlus, Delete, Timer, VideoPlay, Edit, Link
} from '@element-plus/icons-vue'
import request from '@/utils/request'
import { getTimeAccounts } from '@/api/time_account' 
import { getTimeRules, saveTimeRule, getTimeLogs, deleteTimeLog, toggleTimeRuleStatus } from '@/api/time_allocation'

const activeTab = ref('rules')
const loading = ref(false)
const accounts = ref([])
const rules = ref([])
const logs = ref([])
const taskMappings = ref([])
const mappingLoading = ref(false)
// 任务来源中英文翻译字典
const sourceMap = {
  'manual': '手动任务',
  'reading': '阅读任务',
  'recitation': '背诵任务'
}

// 加载任务来源绑定列表
const loadMappings = async () => {
  mappingLoading.value = true
  try {
    const res = await request.get('/task-mappings') // 替换为你实际的路由
    taskMappings.value = res.data || res
  } catch (e) { console.error(e) } finally {
    mappingLoading.value = false
  }
}

// 监听 Tab 切换，点过去的时候自动加载
import { watch } from 'vue'
watch(activeTab, (val) => {
  if (val === 'mappings' && taskMappings.value.length === 0) {
    loadMappings()
  }
})

// 下拉框改变时直接保存
const handleMappingChange = async (row) => {
  try {
    await request.post('/task-mappings', {
      source: row.source,
      time_account_id: row.time_account_id || null
    })
    ElMessage.success(`【${row.source}】关联保存成功`)
  } catch (e) {
    ElMessage.error('保存失败')
  }
}
const weekOptions = [
  { label: '一', value: 1 }, { label: '二', value: 2 }, { label: '三', value: 3 },
  { label: '四', value: 4 }, { label: '五', value: 5 }, { label: '六', value: 6 }, { label: '日', value: 0 }
]

const loadDashboard = async () => {
  loading.value = true
  try {
    const [accountsRes, rulesRes, logsRes] = await Promise.all([
      getTimeAccounts(), getTimeRules(), getTimeLogs()
    ])
    accounts.value = (accountsRes.data || accountsRes).filter(a => a.status === 1)
    rules.value = rulesRes.data || rulesRes
    logs.value = logsRes.data || logsRes
  } finally {
    loading.value = false
  }
}
// 格式化时间辅助函数
const formatDateTime = (dateStr) => {
  if (!dateStr) return '-'
  const d = new Date(dateStr)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
}
// ==========================================
// 🌟 核心：手动切换规则的运行状态
// ==========================================
const handleToggleStatus = async (rule) => {
  try {
    await toggleTimeRuleStatus(rule.id, rule.is_active)
    ElMessage.success(rule.is_active ? `已启用【${rule.name}】规则` : `已停用【${rule.name}】`)
    // 如果你在后端设置了“排他性”（只能开一个），这里需要重新 loadDashboard() 刷新列表
    loadDashboard()
  } catch (e) {
    rule.is_active = rule.is_active === 1 ? 0 : 1 // 失败则回滚开关
    console.error(e)
  }
}

// ==========================================
// 规则设置
// ==========================================
const ruleDialogVisible = ref(false)
const ruleLoading = ref(false)
const ruleForm = ref({ id: null, name: '', remark: '', items: [] })

const openRuleDialog = (row = null) => {
  if (row) {
    const items = row.items.map(i => ({
      account_id: i.time_account_id,
      days_of_week: typeof i.days_of_week === 'string' ? JSON.parse(i.days_of_week) : (i.days_of_week || []),
      minutes: Math.round(i.allocate_hours * 60)
    }))
    ruleForm.value = { id: row.id, name: row.name, remark: row.remark, items }
  } else {
    ruleForm.value = { id: null, name: '', remark: '', items: [] }
  }
  ruleDialogVisible.value = true
}

const addRuleItem = () => {
  ruleForm.value.items.push({ account_id: null, days_of_week: [1,2,3,4,5], minutes: 45 })
}
const removeRuleItem = (index) => {
  ruleForm.value.items.splice(index, 1)
}

const handleSaveRule = async () => {
  if (!ruleForm.value.name) return ElMessage.warning('规则名称不能为空！')
  if (ruleForm.value.items.length === 0) return ElMessage.warning('请至少添加一条具体规则！')

  const payloadItems = ruleForm.value.items.map(item => ({
    account_id: item.account_id,
    days_of_week: item.days_of_week,
    ratio: 0, 
    allocate_hours: Number((item.minutes / 60).toFixed(2)) 
  }))

  ruleLoading.value = true
  try {
    await saveTimeRule({ ...ruleForm.value, items: payloadItems })
    ElMessage.success('自动化规则保存成功！')
    ruleDialogVisible.value = false
    loadDashboard()
  } catch (e) { console.error(e) } finally { ruleLoading.value = false }
}

const triggerAutoRun = async () => {
  try {
    const res = await request.post('/time-allocations/auto-run')
    if (res.generated_hours > 0) {
      ElMessage.success(`🎉 ${res.message} 共为您充入 ${res.generated_hours} 小时。`)
      loadDashboard()
    } else {
      ElMessage.info(res.message)
    }
  } catch (e) { console.error(e) }
}

const handleDeleteLog = async (row) => {
  await deleteTimeLog(row.id)
  ElMessage.success('撤回成功')
  loadDashboard()
}

onMounted(() => { loadDashboard() })
</script>

<template>
  <div class="page-container" v-loading="loading">
    <el-card shadow="never" class="main-card">
      <template #header>
        <div style="display: flex; justify-content: space-between; align-items: center;">
          <span style="font-size: 16px; font-weight: bold; color: #303133;">
            <el-icon style="margin-right: 5px; color: #7b61ff;"><Timer /></el-icon>时间自动化生成引擎
          </span>
          <el-button type="success" :icon="VideoPlay" @click="triggerAutoRun" plain>
            手动触发一次今日分配
          </el-button>
        </div>
      </template>

      <el-tabs v-model="activeTab" class="custom-tabs">
        <el-tab-pane name="rules">
          <template #label><el-icon><Setting /></el-icon> 自动化规则库</template>
          
          <div style="margin: 15px 0;">
            <el-button type="primary" :icon="CirclePlus" @click="openRuleDialog()">新建作息配置方案</el-button>
          </div>

          <el-row :gutter="20">
            <el-col :span="12" v-for="rule in rules" :key="rule.id" style="margin-bottom: 20px;">
              <el-card shadow="hover" :class="['rule-card', { 'is-disabled': rule.is_active === 0 }]">
                <div class="rule-header">
                  <div style="display: flex; align-items: center; gap: 10px;">
                    <el-switch 
                      v-model="rule.is_active" 
                      :active-value="1" 
                      :inactive-value="0" 
                      style="--el-switch-on-color: #13ce66;"
                      @change="handleToggleStatus(rule)" 
                    />
                    <span class="rule-name">{{ rule.name }}</span>
                    <el-tag v-if="rule.is_active === 1" size="small" type="success" effect="dark">生效中</el-tag>
                  </div>
                  <el-button link type="primary" :icon="Edit" @click="openRuleDialog(rule)">配置</el-button>
                </div>
                
                <div class="rule-desc">{{ rule.remark || '暂无说明' }}</div>
                
                <div class="rule-detail-list">
                  <div v-for="item in rule.items" :key="item.id" class="rule-detail-item">
                    <span style="font-weight: bold; width: 100px;">{{ item.account?.name }}</span>
                    <span class="days-text">
                      周 {{ (typeof item.days_of_week === 'string' ? JSON.parse(item.days_of_week) : item.days_of_week)
                            .map(d => d === 0 ? '日' : ['一','二','三','四','五','六'][d-1]).join('、') }}
                    </span>
                    <el-tag :type="rule.is_active === 1 ? 'primary' : 'info'" effect="plain" size="small" style="font-weight:bold;">
                      + {{ Math.round(item.allocate_hours * 60) }} 分钟
                    </el-tag>
                  </div>
                </div>
              </el-card>
            </el-col>
          </el-row>
        </el-tab-pane>

        <el-tab-pane name="logs">
          <template #label><el-icon><Clock /></el-icon> 自动发放记录</template>
          <el-table :data="logs" border stripe style="width: 100%; margin-top: 15px;">
            <el-table-column type="expand">
              <template #default="props">
                <div style="padding: 15px 30px; background: #fafafa;">
                  <el-table :data="props.row.items" size="small" border>
                    <el-table-column prop="account_name" label="充值账户" width="180" />
                    <el-table-column label="入账时长" align="right">
                      <template #default="{ row }">
                        <span style="color: #67C23A; font-weight: bold;">
                          + {{ Number(row.allocated_hours).toFixed(2) }} h (约{{Math.round(row.allocated_hours*60)}}分钟)
                        </span>
                      </template>
                    </el-table-column>
                  </el-table>
                </div>
              </template>
            </el-table-column>
            <el-table-column label="执行时间" width="160" align="center">
  <template #default="{ row }">
    <span style="font-family: Consolas;">{{ formatDateTime(row.created_at) }}</span>
  </template>
</el-table-column>
            <el-table-column prop="rule_name" label="动作触发器" width="220">
              <template #default="{ row }"><el-tag :type="row.rule_name.includes('系统') ? 'success' : ''">{{ row.rule_name }}</el-tag></template>
            </el-table-column>
            <el-table-column label="当日总发放 (h)" width="150" align="right">
              <template #default="{ row }">
                <span style="font-weight: bold;">{{ Number(row.total_hours).toFixed(2) }} h</span>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="100" align="center">
              <template #default="{ row }"><el-button type="danger" link :icon="Delete" @click="handleDeleteLog(row)">撤回</el-button></template>
            </el-table-column>
          </el-table>
        </el-tab-pane>
        <el-tab-pane name="mappings">
          <template #label><el-icon><Link /></el-icon> 任务结算配置</template>
          
          <div style="margin: 15px 0; color: #909399; font-size: 13px;">
            <el-icon><InfoFilled /></el-icon> 系统已自动抓取独立的任务来源类型 (项目类型除外)。在此绑定后，完成该类型任务将自动扣除对应账户的时间。
          </div>

          <el-table :data="taskMappings" border stripe v-loading="mappingLoading" style="width: 100%; max-width: 600px;">
            <el-table-column label="任务来源类型" width="220">
              <template #default="{ row }">
                <div style="display: flex; align-items: center; gap: 8px;">
                  <span style="font-weight: bold; color: #303133;">
                    {{ sourceMap[row.source] || row.source }}
                  </span>
                  <el-tag effect="plain" type="info" size="small">
                    {{ row.source }}
                  </el-tag>
                </div>
              </template>
            </el-table-column>
            
            <el-table-column label="默认结算时间账户">
              <template #default="{ row }">
                <el-select 
                  v-model="row.time_account_id" 
                  placeholder="未绑定 (打卡时不扣时间)" 
                  clearable 
                  style="width: 100%;"
                  @change="handleMappingChange(row)"
                >
                  <el-option 
                    v-for="acc in accounts" 
                    :key="acc.id" 
                    :label="acc.name" 
                    :value="acc.id" 
                  />
                </el-select>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <el-dialog v-model="ruleDialogVisible" :title="ruleForm.id ? '配置配置方案' : '新建配置方案'" width="700px" destroy-on-close top="8vh">
      <el-form :model="ruleForm" label-width="80px">
        <el-form-item label="方案名称" required>
          <el-input v-model="ruleForm.name" placeholder="如：冲刺期作息方案" />
        </el-form-item>
        <el-form-item label="方案说明">
          <el-input v-model="ruleForm.remark" placeholder="简单说明一下此方案..." />
        </el-form-item>
        
        <el-divider border-style="dashed">账户定时充值规则</el-divider>
        
        <div v-for="(item, index) in ruleForm.items" :key="index" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px; position: relative;">
          <el-button type="danger" link :icon="Delete" style="position: absolute; right: 10px; top: 10px;" @click="removeRuleItem(index)"></el-button>
          
          <el-form-item label="充入账户" style="margin-bottom: 15px;">
            <el-select v-model="item.account_id" placeholder="选择时间存入的账户" style="width: 200px">
              <el-option v-for="acc in accounts" :key="acc.id" :label="acc.name" :value="acc.id" />
            </el-select>
          </el-form-item>
          <el-form-item label="执行周期" style="margin-bottom: 15px;">
            <el-checkbox-group v-model="item.days_of_week" size="small">
              <el-checkbox-button v-for="day in weekOptions" :key="day.value" :label="day.value">{{ day.label }}</el-checkbox-button>
            </el-checkbox-group>
          </el-form-item>
          <el-form-item label="每次充入" style="margin-bottom: 0;">
            <el-input-number v-model="item.minutes" :min="1" :step="5" style="width: 120px;" controls-position="right" />
            <span style="margin-left: 10px; color: #606266;">分钟</span>
          </el-form-item>
        </div>

        <el-button type="primary" plain dashed style="width: 100%; border-style: dashed;" :icon="CirclePlus" @click="addRuleItem">新增一条规则</el-button>
      </el-form>
      <template #footer>
        <el-button @click="ruleDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSaveRule" :loading="ruleLoading">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
.page-container { padding: 20px; background: #f5f7fa; min-height: calc(100vh - 84px); }
.main-card { border-radius: 8px; border: none; box-shadow: 0 2px 12px 0 rgba(0,0,0,0.05); min-height: 500px; }
.custom-tabs :deep(.el-tabs__item) { font-size: 15px; height: 50px; line-height: 50px; }

/* 🌟 规则卡片全新样式 */
.rule-card { 
  border-radius: 8px; border: 1px solid #ebeef5; transition: all 0.3s; 
  position: relative; overflow: hidden;
}
.rule-card.is-disabled {
  opacity: 0.6; filter: grayscale(80%); background-color: #fcfcfc;
}
.rule-card.is-disabled:hover { filter: grayscale(0%); opacity: 1; } /* 鼠标移上去恢复彩色方便配置 */

.rule-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dashed #ebeef5; padding-bottom: 10px; margin-bottom: 10px; }
.rule-name { font-weight: bold; font-size: 16px; color: #303133; }
.rule-desc { font-size: 12px; color: #909399; margin-bottom: 15px; }

.rule-detail-list { display: flex; flex-direction: column; gap: 8px; }
.rule-detail-item { display: flex; align-items: center; background: #f8f9fa; padding: 8px 12px; border-radius: 4px; font-size: 14px; }
.days-text { color: #7b61ff; font-size: 13px; flex: 1; font-weight: 500; }
.is-disabled .days-text { color: #909399; } /* 停用状态下文字变灰 */
</style>