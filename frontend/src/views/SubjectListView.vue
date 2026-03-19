<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { getSubjects, createSubject, updateSubject, deleteSubject } from '@/api/subject'
import { getAccounts } from '@/api/account'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Edit, Delete, PriceTag, InfoFilled, Wallet, ArrowUp, ArrowDown } from '@element-plus/icons-vue'

// ==========================================
// 1. 状态定义
// ==========================================
const list = ref([])
const accounts = ref([]) 
const loading = ref(false)
const dialogVisible = ref(false)
const isEdit = ref(false)
const formLoading = ref(false)

const tableRef = ref(null)
const isExpandAll = ref(true) 

const form = ref({
  id: null,
  pid: 0,
  subject_name: '',
  subject_code: '',
  subject_order: 0,
  subject_type: 'expense',
  account_id: null,
  status: '1'
})

// ==========================================
// 2. 数据加载与处理
// ==========================================
const loadData = async () => {
  loading.value = true
  try {
    const [subjectRes, accountRes] = await Promise.all([
      getSubjects(),
      getAccounts()
    ])
    list.value = subjectRes.data || subjectRes || []
    const allAccounts = accountRes.data || accountRes || []
    accounts.value = allAccounts.filter(a => a.status === 1)
  } catch (error) {
    console.error('获取数据失败:', error)
  } finally {
    loading.value = false
  }
}

const parentSubjectOptions = computed(() => {
  return [{ id: 0, subject_name: '📂 根目录 (顶级科目)' }, ...list.value]
})

const getAccountName = (accountId) => {
  if (!accountId) return ''
  const acc = accounts.value.find(a => a.id === accountId)
  return acc ? acc.name : '未知账户'
}

// 🌟 核心判断：当前选中的是否为“主科目”（第二级节点）
// 原理：list.value 存的是顶级节点(支出/收入/转账)。如果表单的 pid 等于某个顶级节点的 id，说明当前正处于第二级！
const isMainSubject = computed(() => {
  return list.value.some(rootNode => rootNode.id === form.value.pid)
})

// 🌟 体验优化：当改变上级科目时，自动继承上级科目的类型 (支出/收入/转账)
watch(() => form.value.pid, (newPid) => {
  if (newPid !== 0 && list.value.length > 0) {
    const findParent = (tree, id) => {
      for (const node of tree) {
        if (node.id === id) return node
        if (node.children && node.children.length > 0) {
          const res = findParent(node.children, id)
          if (res) return res
        }
      }
      return null
    }
    const parentNode = findParent(list.value, newPid)
    if (parentNode) {
      form.value.subject_type = parentNode.subject_type
    }
  }
})

// ==========================================
// 3. 一键折叠/展开算法
// ==========================================
const toggleExpandAll = () => {
  isExpandAll.value = !isExpandAll.value
  const traverse = (data) => {
    data.forEach(row => {
      if (tableRef.value) {
        tableRef.value.toggleRowExpansion(row, isExpandAll.value)
      }
      if (row.children && row.children.length > 0) {
        traverse(row.children)
      }
    })
  }
  traverse(list.value)
}

// ==========================================
// 4. 表单操作方法
// ==========================================
const handleAdd = (row = null) => {
  isEdit.value = false
  form.value = {
    id: null,
    pid: row ? row.id : 0, 
    subject_type: row ? row.subject_type : 'expense',
    subject_name: '',
    subject_code: '',
    subject_order: 0,
    account_id: null,
    status: '1'
  }
  dialogVisible.value = true
}

// 🌟 修复之前编辑上级不生效的 Bug (清洗数据，强制转换类型，去除了 row.children)
const handleEdit = (row) => {
  isEdit.value = true
  form.value = {
    id: row.id,
    pid: Number(row.pid) || 0, 
    subject_type: row.subject_type,
    subject_name: row.subject_name,
    subject_code: row.subject_code,
    subject_order: Number(row.subject_order) || 0,
    account_id: row.account_id || null,
    status: String(row.status)
  }
  dialogVisible.value = true
}

const handleSubmit = async () => {
  if (!form.value.subject_name) return ElMessage.warning('请填写科目名称！')
  if (isEdit.value && form.value.id === form.value.pid) return ElMessage.error('上级科目不能是自己！')

  // 🌟 安全清理：只有“主科目”（第二级）且为“支出”类型时，才保留账户 ID，其他层级强制清空
  if (!isMainSubject.value || form.value.subject_type !== 'expense') {
    form.value.account_id = null
  }

  formLoading.value = true
  try {
    if (isEdit.value) await updateSubject(form.value.id, form.value)
    else await createSubject(form.value)
    
    ElMessage.success(isEdit.value ? '更新成功' : '添加成功')
    dialogVisible.value = false
    loadData()
  } catch (error) {
    console.error(error)
  } finally {
    formLoading.value = false
  }
}

const handleDelete = (row) => {
  if (row.children && row.children.length > 0) {
    return ElMessage.warning('该科目下存在子科目，请先删除子科目！')
  }
  ElMessageBox.confirm(`确定要删除科目 "${row.subject_name}" 吗？此操作不可恢复。`, '危险操作', { 
    type: 'error', confirmButtonText: '确定删除', cancelButtonText: '取消'
  }).then(async () => {
    await deleteSubject(row.id)
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
            <el-icon class="header-icon"><PriceTag /></el-icon>
            <span>收支科目管理</span>
          </div>
          <div class="header-actions">
            <el-button :icon="isExpandAll ? ArrowUp : ArrowDown" @click="toggleExpandAll" style="margin-right: 10px;">
              {{ isExpandAll ? '一键折叠' : '一键展开' }}
            </el-button>
            <el-button type="primary" :icon="Plus" @click="handleAdd()">新增顶级科目</el-button>
          </div>
        </div>
      </template>

      <el-table 
        ref="tableRef"
        v-loading="loading" 
        :data="list" 
        row-key="id" 
        border 
        stripe
        default-expand-all
        :tree-props="{ children: 'children', hasChildren: 'hasChildren' }"
      >
        <el-table-column prop="subject_name" label="科目名称" min-width="200">
          <template #default="{ row }">
            <span style="font-weight: bold; color: #303133;">{{ row.subject_name }}</span>
          </template>
        </el-table-column>
        
        <el-table-column prop="subject_code" label="科目编码" width="120" align="center">
          <template #default="{ row }">
            <el-tag type="info" effect="plain" v-if="row.subject_code">{{ row.subject_code }}</el-tag>
            <span v-else style="color: #c0c4cc;">-</span>
          </template>
        </el-table-column>

        <el-table-column label="自动扣款账户" min-width="150" align="center">
          <template #default="{ row }">
            <el-tag 
              v-if="row.account_id" 
              type="warning" 
              effect="light"
              style="display: inline-flex; align-items: center; white-space: nowrap; height: auto; padding: 4px 8px;"
            >
              <el-icon style="margin-right: 4px;"><Wallet /></el-icon>
              <span>{{ getAccountName(row.account_id) }}</span>
            </el-tag>
            <span v-else style="color: #dcdfe6; font-size: 12px;">-</span>
          </template>
        </el-table-column>
        
        <el-table-column prop="subject_order" label="排序权重" width="90" align="center" />
        
        <el-table-column prop="status" label="状态" width="90" align="center">
          <template #default="{ row }">
            <el-tag :type="row.status === '1' ? 'success' : 'danger'" size="small" effect="dark">
              {{ row.status === '1' ? '正常' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        
        <el-table-column label="操作" width="260" align="center" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link :icon="Plus" @click="handleAdd(row)">加子科目</el-button>
            <el-button type="warning" link :icon="Edit" @click="handleEdit(row)">编辑</el-button>
            <el-button type="danger" link :icon="Delete" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑科目' : '新增科目'" width="550px" destroy-on-close top="8vh">
      <el-form :model="form" label-width="100px" class="custom-form">
        
        <el-form-item label="上级科目">
          <el-tree-select
            v-model="form.pid"
            :data="parentSubjectOptions"
            :props="{ label: 'subject_name', value: 'id', children: 'children' }"
            check-strictly
            filterable
            default-expand-all
            style="width: 100%"
            placeholder="请选择上级科目（默认为根目录）"
          />
        </el-form-item>

        <el-form-item label="科目类型" required>
          <div style="display: flex; align-items: center;">
            <el-radio-group v-model="form.subject_type" :disabled="form.pid !== 0">
              <el-radio-button label="expense">支出科目</el-radio-button>
              <el-radio-button label="income">收入科目</el-radio-button>
              <el-radio-button label="transfer">转账流转</el-radio-button>
            </el-radio-group>
            
            <span v-if="form.pid !== 0" style="font-size: 12px; color: #909399; margin-left: 10px;">
               <el-icon style="vertical-align: middle;"><InfoFilled /></el-icon> 随上级自动继承
            </span>
          </div>
        </el-form-item>

        <el-form-item label="绑定账户" v-if="isMainSubject && form.subject_type === 'expense'">
          <el-select v-model="form.account_id" placeholder="请选择自动扣款账户" clearable style="width: 100%">
            <el-option v-for="acc in accounts" :key="acc.id" :label="acc.name" :value="acc.id" />
          </el-select>
          <div style="font-size: 12px; color: #E6A23C; line-height: 1.4; margin-top: 6px; background: #fdf6ec; padding: 5px 8px; border-radius: 4px;">
            <el-icon style="vertical-align: middle;"><InfoFilled /></el-icon>
            设置后，记账时选择该主科目或其下属子科目，将默认从该账户扣除。
          </div>
        </el-form-item>
        
        <el-form-item label="科目名称" required>
          <el-input v-model="form.subject_name" placeholder="例如：餐饮美食、工资收入" clearable />
        </el-form-item>
        
        <el-form-item label="科目编码">
          <el-input v-model="form.subject_code" placeholder="选填，例如：food, salary" clearable />
        </el-form-item>
        
        <el-form-item label="排序权重">
          <el-input-number v-model="form.subject_order" :min="0" style="width: 100%" placeholder="数字越小，排名越靠前" />
        </el-form-item>
        
        <el-form-item label="状态">
          <el-switch v-model="form.status" active-value="1" inactive-value="0" active-text="正常" inactive-text="禁用" />
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
.page-container { 
  padding: 20px; 
  background: #f5f7fa; 
  min-height: calc(100vh - 84px); 
}
.main-card {
  border-radius: 8px;
  border: none;
  box-shadow: 0 2px 12px 0 rgba(0,0,0,0.05);
}
.card-header { 
  display: flex; 
  justify-content: space-between; 
  align-items: center; 
}
.header-title {
  display: flex;
  align-items: center;
  font-size: 16px;
  font-weight: bold;
  color: #303133;
}
.header-icon {
  margin-right: 8px;
  color: #409EFF;
  font-size: 18px;
}
.header-actions {
  display: flex;
  align-items: center;
}
.custom-form {
  padding-right: 20px;
}
</style>