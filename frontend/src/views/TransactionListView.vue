<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, h, nextTick } from 'vue'
import { getTransactions, createTransaction, updateTransaction, deleteTransaction } from '@/api/transaction'
import { getSubjects } from '@/api/subject' 
import { getProjects } from '@/api/project' 
import { getAccounts } from '@/api/account'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search, Refresh, Plus, Edit, Delete, Wallet, PieChart, DataLine, Switch } from '@element-plus/icons-vue' // 🌟 引入 Switch 图标
import * as echarts from 'echarts'
import { useRoute } from 'vue-router'

// ==========================================
// 1. 日期初始化逻辑
// ==========================================
const getDefaultDateRange = () => {
  const now = new Date()
  const year = now.getFullYear()
  const month = now.getMonth()
  const date = now.getDate()

  let startYear = year, startMonth = month, endYear = year, endMonth = month
  if (date >= 25) {
    endMonth = month + 1
  } else {
    startMonth = month - 1
  }

  const startDate = new Date(startYear, startMonth, 25)
  const endDate = new Date(endYear, endMonth, 24)
  
  const formatDate = (d) => {
    const y = d.getFullYear()
    const m = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    return `${y}-${m}-${day}`
  }
  return [formatDate(startDate), formatDate(endDate)]
}

// ==========================================
// 2. 基础状态与变量定义
// ==========================================
const list = ref([])
const loading = ref(false)
const pagination = reactive({ current: 1, size: 10, total: 0 })
const globalTotalAmount = ref(0) 

const queryParams = reactive({ 
  type: '', 
  main_subject_id: null,
  sub_subject_id: null,
  remark: '',
  dateRange: getDefaultDateRange()
})

const dialogVisible = ref(false)
const isEdit = ref(false)
const formLoading = ref(false)

const form = ref({ 
  type: 'expense', 
  items: [] 
})

const projectList = ref([])
const financialAccounts = ref([])

// ==========================================
// 3. ECharts 图表专用的状态与实例
// ==========================================
const mainChartRef = ref(null)
const subChartRef = ref(null)
let mainChartInstance = null
let subChartInstance = null

// ==========================================
// 4. 数据加载 (科目、项目与账户)
// ==========================================
const expenseSubjects = ref([])
const incomeSubjects = ref([])
const transferSubjects = ref([]) // 🌟 新增：转账科目集合

const stripDummyRoot = (tree) => {
  let result = []
  tree.forEach(node => {
    if ((node.subject_name === '支出' || node.subject_name === '收入' || node.subject_name === '转账') && node.children) {
      result.push(...node.children)
    } else {
      result.push(node)
    }
  })
  return result
}

const loadSubjects = async () => {
  try {
    const resExp = await getSubjects({ subject_type: 'expense' })
    expenseSubjects.value = stripDummyRoot(resExp.data || resExp || [])
    
    const resInc = await getSubjects({ subject_type: 'income' })
    incomeSubjects.value = stripDummyRoot(resInc.data || resInc || [])

    // 🌟 加载转账科目
    const resTrans = await getSubjects({ subject_type: 'transfer' })
    transferSubjects.value = stripDummyRoot(resTrans.data || resTrans || [])
  } catch (error) {
    console.error('获取科目失败:', error)
  }
}

const loadProjects = async () => {
  try {
    const res = await getProjects({ per_page: 500 }) 
    projectList.value = res.data?.data || res.data || res || []
  } catch (error) {
    console.error('获取项目列表失败:', error)
  }
}

const loadAccounts = async () => {
  try {
    const res = await getAccounts()
    financialAccounts.value = (res.data?.data || res.data || res || []).filter(a => a.status === 1)
  } catch (error) {
    console.error('获取财务账户失败:', error)
  }
}

// 🌟 根据类型选择对应的树
const getSubjectTreeByType = (type) => {
  if (type === 'income') return incomeSubjects.value;
  if (type === 'transfer') return transferSubjects.value;
  return expenseSubjects.value;
}

const findMainAndSubIds = (subjectId, type) => {
  const subjects = getSubjectTreeByType(type)
  for (const main of subjects) {
    if (main.id === subjectId) return { main: main.id, sub: null }
    if (main.children) {
      const sub = main.children.find(c => c.id === subjectId)
      if (sub) return { main: main.id, sub: sub.id }
    }
  }
  return { main: null, sub: null }
}

const getMainSubjectName = (row) => {
  if (!row || !row.subject_id) return '-'
  const tree = getSubjectTreeByType(row.type)
  for (const main of tree) {
    if (main.id === row.subject_id) return main.subject_name
    if (main.children && main.children.some(c => c.id === row.subject_id)) {
      return main.subject_name
    }
  }
  return '未知'
}

// ==========================================
// 5. 搜索栏下拉联动计算
// ==========================================
const queryMainOptions = computed(() => {
  if (queryParams.type === 'expense') return expenseSubjects.value
  if (queryParams.type === 'income') return incomeSubjects.value
  if (queryParams.type === 'transfer') return transferSubjects.value // 🌟 支持转账
  return [...expenseSubjects.value, ...incomeSubjects.value, ...transferSubjects.value]
})

const querySubOptions = computed(() => {
  if (!queryParams.main_subject_id) return []
  const mainSubject = queryMainOptions.value.find(item => item.id === queryParams.main_subject_id)
  return mainSubject && mainSubject.children ? mainSubject.children : []
})

// ==========================================
// 6. 动态表单下拉联动计算与操作
// ==========================================
const currentMainOptions = computed(() => {
  // 🌟 表单主科目下拉框：只保留 status 为 1 的主科目
  const rawList = getSubjectTreeByType(form.value.type)
  return rawList.filter(item => String(item.status) === '1')
})

const getSubOptions = (mainId) => {
  if (!mainId) return []
  const rawList = getSubjectTreeByType(form.value.type)
  const mainSubject = rawList.find(item => item.id === mainId)
  
  if (mainSubject && mainSubject.children) {
    return mainSubject.children.filter(child => String(child.status) === '1')
  }
  return []
}

const handleAddItem = () => {
  const lastItem = form.value.items[form.value.items.length - 1]
  const defaultDate = lastItem ? lastItem.transaction_date : new Date().toISOString().split('T')[0]
  form.value.items.push({
    id: null, 
    main_subject_id: null, 
    sub_subject_id: null,
    account_id: null, 
    target_account_id: null, // 🌟 新增：转入目标账户 (仅转账用)
    amount: undefined, 
    transaction_date: defaultDate, 
    remark: '',
    project_id: null 
  })
}

const handleRemoveItem = (index) => form.value.items.splice(index, 1)

const handleTypeChangeInForm = () => { 
  form.value.items.forEach(item => { 
    item.main_subject_id = null; 
    item.sub_subject_id = null;
    item.project_id = null; 
    item.account_id = null;
    item.target_account_id = null; // 🌟 清理转账目标
  })
}

const handleMainCategoryChange = (index) => {
  const item = form.value.items[index]
  item.sub_subject_id = null
  
  if (!item.project_id) {
    const mainSub = currentMainOptions.value.find(s => s.id === item.main_subject_id)
    if (mainSub && mainSub.account_id) {
      item.account_id = mainSub.account_id
    }
  }
}

const handleProjectChange = (index) => {
  const item = form.value.items[index]
  if (item.project_id) {
    const proj = projectList.value.find(p => p.id === item.project_id)
    if (proj && proj.account_id) {
      item.account_id = proj.account_id
    }
  } else {
    const mainSub = currentMainOptions.value.find(s => s.id === item.main_subject_id)
    if (mainSub && mainSub.account_id) {
      item.account_id = mainSub.account_id
    }
  }
}

// ==========================================
// 7. 数据加载 (表格 + 图表)
// ==========================================
const buildQueryParams = () => {
  const params = { 
    type: queryParams.type,
    remark: queryParams.remark,
    subject_id: queryParams.sub_subject_id || queryParams.main_subject_id || ''
  }
  if (queryParams.dateRange && queryParams.dateRange.length === 2) {
    params.start_date = queryParams.dateRange[0]
    params.end_date = queryParams.dateRange[1]
  }
  return params
}

const loadData = async () => {
  loading.value = true
  try {
    const params = { page: pagination.current, per_page: pagination.size, ...buildQueryParams() }
    const res = await getTransactions(params)
    list.value = res.data?.data || res.data || []
    pagination.total = res.data?.total || res.total || 0
  } catch (error) {
    console.error('获取流水失败:', error)
  } finally {
    loading.value = false
  }
}

const loadChartData = async () => {
  try {
    const params = { page: 1, per_page: 10000, ...buildQueryParams() }
    const res = await getTransactions(params)
    const allData = res.data?.data || res.data || []
    
    // 🌟 计算汇总时，彻底排除 'transfer' 类型，防止报表失真！
    globalTotalAmount.value = allData.reduce((sum, item) => {
      if (item.type === 'transfer') return sum; 
      const val = Number(item.amount) || 0;
      return sum + (item.type === 'expense' ? -val : val);
    }, 0)

    renderCharts(allData)
  } catch (error) {
    console.error('获取图表数据失败:', error)
  }
}

const handleSearch = () => { pagination.current = 1; loadData(); loadChartData() }
const handleAllDates = () => { queryParams.dateRange = null; handleSearch() }
const resetSearch = () => { 
  queryParams.type = ''
  queryParams.main_subject_id = null
  queryParams.sub_subject_id = null
  queryParams.remark = ''
  queryParams.dateRange = getDefaultDateRange()
  handleSearch() 
}
const handleSizeChange = (val) => { pagination.size = val; loadData() }
const handleCurrentChange = (val) => { pagination.current = val; loadData() }

const getSummaries = (param) => {
  const { columns } = param
  const sums = []
  columns.forEach((column, index) => {
    if (index === 0) { sums[index] = '总合计(不含转账)'; return } // 🌟 提示修改
    if (column.label === '金额 (元)') {
      const sum = globalTotalAmount.value
      sums[index] = h('span', { style: { color: sum >= 0 ? '#67C23A' : '#F56C6C', fontSize: '16px', fontWeight: 'bold', fontFamily: 'Consolas' } }, (sum > 0 ? '+' : '') + sum.toFixed(2) + ' 元')
    } else {
      sums[index] = ''
    }
  })
  return sums
}

// ==========================================
// 8. ECharts 图表渲染逻辑
// ==========================================
const renderCharts = (allData) => {
  if (!mainChartInstance || !subChartInstance) return

  // 🌟 如果选了“全部”或“转账”，默认画支出的图，转账画饼图没有意义
  const targetType = (queryParams.type === 'income') ? 'income' : 'expense'
  const chartTitlePrefix = targetType === 'expense' ? '支出' : '收入'

  const mainDataMap = {}
  const subDataMap = {}

  allData.forEach(item => {
    if (item.type !== targetType) return

    const { main, sub } = findMainAndSubIds(item.subject_id, item.type)
    const tree = getSubjectTreeByType(item.type)

    let mainName = '未知科目'
    let subName = ''

    const mainNode = tree.find(t => t.id === main)
    if (mainNode) {
      mainName = mainNode.subject_name
      if (sub) {
        const subNode = mainNode.children?.find(c => c.id === sub)
        if (subNode) subName = subNode.subject_name
      }
    }

    mainDataMap[mainName] = (mainDataMap[mainName] || 0) + Number(item.amount)
    const finalSubName = subName ? subName : mainName
    subDataMap[finalSubName] = (subDataMap[finalSubName] || 0) + Number(item.amount)
  })

  const mainData = Object.keys(mainDataMap).map(k => ({ name: k, value: mainDataMap[k].toFixed(2) }))
  const subData = Object.keys(subDataMap).map(k => ({ name: k, value: subDataMap[k].toFixed(2) }))

  const commonOptions = {
    tooltip: { trigger: 'item', formatter: '{b}: {c}元 ({d}%)' },
    series: [{
      type: 'pie', radius: '60%', center: ['50%', '50%'], data: [],
      emphasis: { itemStyle: { shadowBlur: 10, shadowOffsetX: 0, shadowColor: 'rgba(0, 0, 0, 0.5)' } },
      label: { show: true, formatter: '{b}: {c}元\n({d}%)', lineHeight: 20 }
    }]
  }

  mainChartInstance.setOption({
    ...commonOptions,
    title: { text: `按 主科目 统计${chartTitlePrefix}`, left: 'center', top: 10, textStyle: { color: '#606266' } },
    series: [{ ...commonOptions.series[0], data: mainData }]
  }, true) // true 表示彻底重绘，防止残留

  subChartInstance.setOption({
    ...commonOptions,
    title: { text: `按 子科目 统计${chartTitlePrefix}`, left: 'center', top: 10, textStyle: { color: '#606266' } },
    series: [{ ...commonOptions.series[0], data: subData }]
  }, true)
}

const handleResize = () => {
  mainChartInstance?.resize()
  subChartInstance?.resize()
}

// ==========================================
// 9. 动态表单交互与提交逻辑
// ==========================================
const handleAdd = () => {
  isEdit.value = false
  form.value.type = 'expense'
  form.value.items = []
  handleAddItem()
  dialogVisible.value = true
}

const handleEdit = (row) => {
  isEdit.value = true
  form.value.type = row.type
  const { main, sub } = findMainAndSubIds(row.subject_id, row.type)
  
  form.value.items = [{
    id: row.id,
    main_subject_id: main,
    sub_subject_id: sub,
    account_id: row.account_id || null,
    // 🌟 编辑转账时不支持修改双账户逻辑，如果是历史的转账，它会呈现单向扣款的编辑形态
    amount: row.amount,
    transaction_date: row.transaction_date,
    remark: row.remark,
    project_id: row.project_id || null 
  }]
  dialogVisible.value = true
}

const handleAmountInput = (val, index) => { if (String(val).includes('=')) calculateAmount(index) }

const calculateAmount = (index) => {
  const item = form.value.items[index]
  let expr = String(item.amount || '').replace(/=/g, '').trim()
  if (!expr) return
  if (/^[0-9+\-*/.()\s]+$/.test(expr)) {
    try {
      const result = new Function(`return ${expr}`)()
      if (typeof result === 'number' && !isNaN(result) && isFinite(result)) {
        item.amount = Math.max(0.01, Number(result.toFixed(2)))
      }
    } catch (e) {}
  } else {
    item.amount = expr.replace(/[^0-9+\-*/.()\s]/g, '')
  }
}

const handleSubmit = async () => {
  if (form.value.items.length === 0) return ElMessage.warning('请至少填写一条记录！')

  const payloads = []

  for (let i = 0; i < form.value.items.length; i++) {
    const item = form.value.items[i]
    calculateAmount(i)
    
    if (!item.main_subject_id) return ElMessage.warning(`第 ${i + 1} 项：请选择主科目！`)
    const subOptions = getSubOptions(item.main_subject_id)
    if (subOptions.length > 0 && !item.sub_subject_id) return ElMessage.warning(`第 ${i + 1} 项：请选择子科目！`)
    const finalAmount = Number(item.amount)
    if (!finalAmount || finalAmount <= 0) return ElMessage.warning(`第 ${i + 1} 项：请填写有效金额！`)
    if (!item.account_id) return ElMessage.warning(`第 ${i + 1} 项：请选择资金账户！`) 
    if (!item.transaction_date) return ElMessage.warning(`第 ${i + 1} 项：请选择日期！`)

    // 🌟 特殊拦截：转账类型必须指定转出和转入，且不能相同
    if (form.value.type === 'transfer') {
       if (!item.target_account_id) return ElMessage.warning(`第 ${i + 1} 项：请选择转入的目标账户！`)
       if (item.account_id === item.target_account_id) return ElMessage.warning(`第 ${i + 1} 项：转出账户和转入账户不能是同一个！`)
       
       // 转账的底层逻辑其实是产生两条流水：一条支出，一条收入
       // 这两条记录共享同一个 remark，以此在业务上进行关联
       const commonRemark = `【资金互转】${item.remark || ''}`
       
       // 1. 转出记录 (Expense)
       payloads.push({
         id: null,
         type: 'expense',
         subject_id: item.sub_subject_id || item.main_subject_id,
         account_id: item.account_id, 
         amount: finalAmount,
         transaction_date: item.transaction_date,
         remark: commonRemark,
         project_id: null 
       });

       // 2. 转入记录 (Income)
       payloads.push({
         id: null,
         type: 'income',
         subject_id: item.sub_subject_id || item.main_subject_id,
         account_id: item.target_account_id, 
         amount: finalAmount,
         transaction_date: item.transaction_date,
         remark: commonRemark,
         project_id: null 
       });

    } else {
       // 常规的收支逻辑
       payloads.push({
         id: item.id,
         type: form.value.type,
         subject_id: item.sub_subject_id || item.main_subject_id,
         account_id: item.account_id, 
         amount: finalAmount,
         transaction_date: item.transaction_date,
         remark: item.remark,
         project_id: form.value.type === 'expense' ? (item.project_id || null) : null 
       })
    }
  }

  formLoading.value = true
  try {
    if (isEdit.value && form.value.type !== 'transfer') {
      await updateTransaction(payloads[0].id, payloads[0])
    } else {
      // 批量创建（包含了一笔转账拆分成两条记录的情况）
      await Promise.all(payloads.map(payload => createTransaction(payload)))
    }
    
    ElMessage.success('操作成功记录已保存')
    dialogVisible.value = false
    handleSearch() 
  } catch (error) {
    console.error('保存失败:', error)
  } finally {
    formLoading.value = false
  }
}

const handleDelete = (row) => {
  ElMessageBox.confirm('确定要删除这条收支记录吗？', '删除确认', { type: 'warning' }).then(async () => {
    await deleteTransaction(row.id)
    ElMessage.success('删除成功')
    handleSearch() 
  }).catch(() => {})
}

const route = useRoute()

// ==========================================
// 10. 生命周期挂载与销毁
// ==========================================
onMounted(async () => { 
  loadAccounts() 
  loadProjects() 
  await loadSubjects()
  
  if (route.query.start_date && route.query.end_date) {
     queryParams.dateRange = [route.query.start_date, route.query.end_date]
  }

  loadData()
  
  nextTick(() => {
    mainChartInstance = echarts.init(mainChartRef.value)
    subChartInstance = echarts.init(subChartRef.value)
    loadChartData() 
    window.addEventListener('resize', handleResize)
  })
})

onUnmounted(() => {
  window.removeEventListener('resize', handleResize)
  mainChartInstance?.dispose()
  subChartInstance?.dispose()
})
</script>

<template>
  <div class="page-container">
    
    <el-card shadow="never" class="search-card">
      <el-form :inline="true" :model="queryParams" class="search-form">
        <el-form-item label="类型">
          <el-select v-model="queryParams.type" placeholder="全部" clearable style="width: 100px" @change="() => { queryParams.main_subject_id = null; queryParams.sub_subject_id = null; handleSearch(); }">
            <el-option label="支出" value="expense" />
            <el-option label="收入" value="income" />
            <el-option label="流转" value="transfer" />
          </el-select>
        </el-form-item>
        <el-form-item label="主科目">
          <el-select v-model="queryParams.main_subject_id" placeholder="全部" clearable style="width: 140px" @change="() => { queryParams.sub_subject_id = null; handleSearch(); }">
            <el-option v-for="item in queryMainOptions" :key="item.id" :label="item.subject_name" :value="item.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="子科目">
          <el-select v-model="queryParams.sub_subject_id" placeholder="全部" clearable :disabled="querySubOptions.length === 0" style="width: 140px" @change="handleSearch">
            <el-option v-for="item in querySubOptions" :key="item.id" :label="item.subject_name" :value="item.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="queryParams.remark" placeholder="搜索备注..." clearable style="width: 180px" @keyup.enter="handleSearch" @clear="handleSearch" />
        </el-form-item>
        <el-form-item label="日期段" style="margin-top: 10px;">
          <div style="display: flex; gap: 10px;">
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
            <el-button @click="handleAllDates">全部</el-button>
          </div>
        </el-form-item>
        <el-form-item style="margin-top: 10px;">
          <el-button type="primary" :icon="Search" @click="handleSearch">搜索</el-button>
          <el-button :icon="Refresh" @click="resetSearch">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card shadow="never" class="main-card">
      <template #header>
        <div class="card-header">
          <div class="header-title">
            <el-icon class="header-icon"><Wallet /></el-icon>
            <span>收支明细</span>
          </div>
          <el-button type="primary" :icon="Plus" @click="handleAdd">记一笔账</el-button>
        </div>
      </template>

      <el-table v-loading="loading" :data="list" border stripe style="width: 100%" show-summary :summary-method="getSummaries">
        <el-table-column prop="transaction_date" label="交易日期" width="120" align="center" sortable />
        <el-table-column label="收支类型" width="90" align="center">
          <template #default="{ row }">
            <el-tag v-if="row.type === 'income'" type="success" effect="dark">收入</el-tag>
            <el-tag v-else-if="row.type === 'expense'" type="danger" effect="dark">支出</el-tag>
            <el-tag v-else type="info" effect="dark">流转</el-tag>
          </template>
        </el-table-column>
        
        <el-table-column label="主科目" width="110" align="center">
           <template #default="{ row }">
             <el-tag type="info" effect="plain" size="small">{{ getMainSubjectName(row) }}</el-tag>
           </template>
        </el-table-column>
        
        <el-table-column label="子科目" width="130" align="center">
           <template #default="{ row }">
             <span style="font-weight: 500;">{{ row.subject ? row.subject.subject_name : '-' }}</span>
           </template>
        </el-table-column>

        <el-table-column label="资金账户" width="120" align="center">
          <template #default="{ row }">
             <span style="color: #409EFF; font-size: 13px;">
               <el-icon style="vertical-align: middle;"><Wallet /></el-icon> 
               {{ row.account ? row.account.name : '-' }}
             </span>
          </template>
        </el-table-column>

        <el-table-column label="关联项目" min-width="140" show-overflow-tooltip>
          <template #default="{ row }">
             <span v-if="row.type === 'expense' && row.project" style="color: #E6A23C; font-size: 13px;">
               <el-icon style="vertical-align: middle;"><DataLine /></el-icon> {{ row.project.name }}
             </span>
             <span v-else style="color: #c0c4cc">-</span>
          </template>
        </el-table-column>

        <el-table-column label="金额 (元)" width="140" align="right">
          <template #default="{ row }">
            <span :class="{'text-income': row.type === 'income', 'text-expense': row.type === 'expense', 'text-transfer': row.type === 'transfer'}" style="font-weight: bold; font-size: 15px; font-family: Consolas, monospace;">
              {{ row.type === 'income' ? '+' : (row.type === 'expense' ? '-' : '') }} {{ Number(row.amount).toFixed(2) }}
            </span>
          </template>
        </el-table-column>
        <el-table-column prop="remark" label="备注说明" min-width="160" show-overflow-tooltip />
        <el-table-column label="操作" width="160" align="center" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link :icon="Edit" @click="handleEdit(row)">编辑</el-button>
            <el-button type="danger" link :icon="Delete" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-container">
        <el-pagination v-model:current-page="pagination.current" v-model:page-size="pagination.size" :page-sizes="[10, 20, 50, 100]" background layout="total, sizes, prev, pager, next, jumper" :total="pagination.total" @size-change="handleSizeChange" @current-change="handleCurrentChange" />
      </div>
    </el-card>

    <el-card shadow="never" class="chart-card">
      <template #header>
        <div class="card-header">
          <div class="header-title">
            <el-icon class="header-icon"><PieChart /></el-icon>
            <span>账单统计图 (当前搜索条件范围)</span>
          </div>
        </div>
      </template>
      <el-row :gutter="20">
        <el-col :span="12">
          <div ref="mainChartRef" class="chart-container"></div>
        </el-col>
        <el-col :span="12">
          <div ref="subChartRef" class="chart-container"></div>
        </el-col>
      </el-row>
    </el-card>

    <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑账单' : '记一笔账 (支持批量)'" width="600px" destroy-on-close top="8vh">
      <el-form :model="form" label-width="70px" class="batch-form">
        
        <el-form-item label="类型" required style="margin-bottom: 25px;">
          <el-radio-group v-model="form.type" @change="handleTypeChangeInForm" :disabled="isEdit">
            <el-radio-button label="expense">支出</el-radio-button>
            <el-radio-button label="income">收入</el-radio-button>
            <el-radio-button label="transfer">转账 / 流转</el-radio-button>
          </el-radio-group>
        </el-form-item>
        
        <div v-for="(item, index) in form.items" :key="index" class="dynamic-item-box" :class="{'is-transfer-box': form.type === 'transfer'}">
          
          <div class="item-header">
            <span class="item-title">记录 {{ index + 1 }}</span>
            <el-button 
              v-if="!isEdit && form.items.length > 1" 
              type="danger" link :icon="Delete" 
              @click="handleRemoveItem(index)"
            >删除</el-button>
          </div>

          <el-form-item label="分类" required>
            <el-row :gutter="10" style="width: 100%; margin: 0;">
              <el-col :span="12" style="padding-left: 0;">
                <el-select v-model="item.main_subject_id" placeholder="主科目" @change="handleMainCategoryChange(index)" style="width: 100%">
                  <el-option v-for="opt in currentMainOptions" :key="opt.id" :label="opt.subject_name" :value="opt.id" />
                </el-select>
              </el-col>
              <el-col :span="12" style="padding-right: 0;">
                <el-select v-model="item.sub_subject_id" placeholder="子科目" :disabled="getSubOptions(item.main_subject_id).length === 0" style="width: 100%">
                  <el-option v-for="opt in getSubOptions(item.main_subject_id)" :key="opt.id" :label="opt.subject_name" :value="opt.id" />
                </el-select>
              </el-col>
            </el-row>
          </el-form-item>

          <el-form-item label="关联项目" v-if="form.type === 'expense'">
            <el-select 
              v-model="item.project_id" 
              clearable 
              filterable 
              placeholder="这笔钱是为哪个项目花的？(选填)" 
              style="width: 100%"
              @change="handleProjectChange(index)" 
            >
              <template #prefix>
                 <el-icon><DataLine /></el-icon>
              </template>
              <el-option 
                v-for="p in projectList" 
                :key="p.id" 
                :label="p.name" 
                :value="p.id" 
              />
            </el-select>
          </el-form-item>

          <div v-if="form.type !== 'transfer'">
            <el-form-item :label="form.type === 'expense' ? '付款账户' : '收款账户'" required>
              <el-select v-model="item.account_id" placeholder="请选择资金账户" style="width: 100%">
                <template #prefix><el-icon><Wallet /></el-icon></template>
                <el-option v-for="acc in financialAccounts" :key="acc.id" :label="acc.name" :value="acc.id" />
              </el-select>
            </el-form-item>
          </div>

          <div v-else class="transfer-accounts-wrapper">
             <el-form-item label="转出账户" required>
                <el-select v-model="item.account_id" placeholder="钱从哪里扣？" style="width: 100%">
                  <template #prefix><el-icon><Wallet /></el-icon></template>
                  <el-option v-for="acc in financialAccounts" :key="acc.id" :label="acc.name" :value="acc.id" />
                </el-select>
             </el-form-item>
             <div class="transfer-icon-center"><el-icon><Switch /></el-icon></div>
             <el-form-item label="转入账户" required>
                <el-select v-model="item.target_account_id" placeholder="钱进了哪里？" style="width: 100%">
                  <template #prefix><el-icon><Wallet /></el-icon></template>
                  <el-option v-for="acc in financialAccounts" :key="acc.id" :label="acc.name" :value="acc.id" />
                </el-select>
             </el-form-item>
          </div>

          <el-form-item label="金额" required>
            <el-input v-model="item.amount" placeholder="支持计算，如 5+10=" @input="val => handleAmountInput(val, index)" @blur="calculateAmount(index)" @keyup.enter="calculateAmount(index)" style="width: 100%">
              <template #prepend>￥</template>
              <template #append><el-button @click="calculateAmount(index)">=</el-button></template>
            </el-input>
          </el-form-item>

          <el-form-item label="日期" required>
            <el-date-picker v-model="item.transaction_date" type="date" placeholder="选择交易日期" value-format="YYYY-MM-DD" style="width: 100%" :clearable="false" />
          </el-form-item>

          <el-form-item label="备注" style="margin-bottom: 0;">
            <el-input v-model="item.remark" type="textarea" rows="2" placeholder="记录一下这笔钱的具体用途或来源..." />
          </el-form-item>
        </div>

        <div v-if="!isEdit && form.type !== 'transfer'" class="add-more-btn" @click="handleAddItem">
          <el-icon><Plus /></el-icon> 再记一笔 (添加下一条)
        </div>

      </el-form>
      
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmit" :loading="formLoading">保存记录</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
.page-container { padding: 20px; background: #f5f7fa; min-height: calc(100vh - 84px); }
.search-card { margin-bottom: 20px; border-radius: 8px; border: none; box-shadow: 0 2px 12px 0 rgba(0,0,0,0.05); }
.search-form .el-form-item { margin-bottom: 10px; margin-right: 15px; }
.main-card { border-radius: 8px; border: none; box-shadow: 0 2px 12px 0 rgba(0,0,0,0.05); }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.header-title { display: flex; align-items: center; font-size: 16px; font-weight: bold; color: #303133; }
.header-icon { margin-right: 8px; color: #409EFF; font-size: 18px; }
.pagination-container { margin-top: 20px; display: flex; justify-content: flex-end; }
.text-income { color: #67C23A; }
.text-expense { color: #F56C6C; }
.text-transfer { color: #909399; }
.chart-card { margin-top: 20px; border-radius: 8px; border: none; box-shadow: 0 2px 12px 0 rgba(0,0,0,0.05); }
.chart-container { width: 100%; height: 400px; }

:deep(.el-table__footer-wrapper tbody td.el-table__cell) { background-color: #fafafa; font-weight: bold; }

.dynamic-item-box {
  background: #f8f9fa;
  border: 1px solid #ebeef5;
  border-radius: 6px;
  padding: 15px 15px 15px 5px;
  margin-bottom: 15px;
  position: relative;
  transition: all 0.3s ease;
}
.dynamic-item-box:hover {
  border-color: #c0c4cc;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.is-transfer-box {
  background: #fdfdfd;
  border-color: #e4e7ed;
  border-left: 4px solid #909399;
}
.item-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
  padding-left: 10px;
}
.item-title {
  font-size: 13px;
  font-weight: bold;
  color: #909399;
  background: #e4e7ed;
  padding: 2px 8px;
  border-radius: 4px;
}
.add-more-btn {
  border: 1px dashed #c0c4cc;
  border-radius: 6px;
  text-align: center;
  padding: 12px 0;
  color: #909399;
  cursor: pointer;
  transition: all 0.3s;
  font-size: 14px;
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 5px;
}
.add-more-btn:hover {
  border-color: #409EFF;
  color: #409EFF;
  background: #ecf5ff;
}

/* 转账账户选择区样式 */
.transfer-accounts-wrapper {
  background: #f4f4f5;
  padding: 15px 15px 1px 0;
  border-radius: 6px;
  margin-bottom: 18px;
  position: relative;
}
.transfer-icon-center {
  position: absolute;
  left: 30px;
  top: 48%;
  transform: translateY(-50%);
  color: #909399;
  font-size: 18px;
  background: #f4f4f5;
  z-index: 2;
  padding: 4px;
}
</style>