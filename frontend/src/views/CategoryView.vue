<template>
    <div class="app-container">
      <div class="filter-container" style="margin-bottom: 20px;">
        <el-input v-model="queryParams.name" placeholder="输入分类名称搜索" style="width: 200px; margin-right: 10px;" clearable />
        <el-button type="primary" icon="Search" @click="fetchData">查询</el-button>
        <el-button type="success" icon="Plus" @click="handleAdd">新增分类</el-button>
      </div>
  
      <el-table v-loading="loading" :data="tableData" border style="width: 100%">
        <el-table-column prop="id" label="ID" width="80" align="center" />
        <el-table-column prop="name" label="分类名称" width="200" />
        <el-table-column prop="description" label="分类描述" min-width="300" />
        <el-table-column prop="sort" label="排序权重" width="100" align="center" />
        <el-table-column prop="created_at" label="创建时间" width="180" align="center" />
        <el-table-column label="操作" width="200" align="center">
          <template #default="scope">
            <el-button size="small" type="primary" icon="Edit" @click="handleEdit(scope.row)">编辑</el-button>
            <el-button size="small" type="danger" icon="Delete" @click="handleDelete(scope.row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
  
      <el-pagination
        v-model:current-page="queryParams.page"
        v-model:page-size="queryParams.per_page"
        :total="total"
        layout="total, sizes, prev, pager, next, jumper"
        style="margin-top: 20px; justify-content: flex-end;"
        @size-change="fetchData"
        @current-change="fetchData"
      />
  
      <el-dialog :title="dialogTitle" v-model="dialogVisible" width="500px">
        <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
          <el-form-item label="分类名称" prop="name">
            <el-input v-model="form.name" placeholder="请输入分类名称" />
          </el-form-item>
          <el-form-item label="分类描述" prop="description">
            <el-input v-model="form.description" type="textarea" placeholder="请输入描述信息" />
          </el-form-item>
          <el-form-item label="排序权重" prop="sort">
            <el-input-number v-model="form.sort" :min="0" placeholder="数字越大越靠前" />
          </el-form-item>
        </el-form>
        <template #footer>
          <el-button @click="dialogVisible = false">取消</el-button>
          <el-button type="primary" @click="submitForm" :loading="submitLoading">确定</el-button>
        </template>
      </el-dialog>
    </div>
  </template>
  
  <script setup>
  import { ref, reactive, onMounted } from 'vue'
  import { ElMessage, ElMessageBox } from 'element-plus'
  // 假设你有一个 request.js 封装了 axios
  import request from '@/utils/request' 
  
  const loading = ref(false)
  const tableData = ref([])
  const total = ref(0)
  const queryParams = reactive({ page: 1, per_page: 10, name: '' })
  
  // 弹窗相关
  const dialogVisible = ref(false)
  const dialogTitle = ref('')
  const submitLoading = ref(false)
  const formRef = ref(null)
  const form = reactive({ id: null, name: '', description: '', sort: 0 })
  const rules = {
    name: [{ required: true, message: '分类名称不能为空', trigger: 'blur' }]
  }
  
  // 获取数据
  const fetchData = async () => {
    loading.value = true
    try {
      const res = await request.get('/categories', { params: queryParams })
      tableData.value = res.data
      total.value = res.total
    } catch (error) {
      console.error(error)
    } finally {
      loading.value = false
    }
  }
  
  // 新增按钮
  const handleAdd = () => {
    form.id = null
    form.name = ''
    form.description = ''
    form.sort = 0
    dialogTitle.value = '新增分类'
    dialogVisible.value = true
  }
  
  // 编辑按钮
  const handleEdit = (row) => {
    Object.assign(form, row)
    dialogTitle.value = '编辑分类'
    dialogVisible.value = true
  }
  
  // 提交表单
  const submitForm = async () => {
    if (!formRef.value) return
    await formRef.value.validate(async (valid) => {
      if (valid) {
        submitLoading.value = true
        try {
          if (form.id) {
            await request.put(`/categories/${form.id}`, form)
            ElMessage.success('更新成功')
          } else {
            await request.post('/categories', form)
            ElMessage.success('新增成功')
          }
          dialogVisible.value = false
          fetchData()
        } catch (error) {
          ElMessage.error(error.response?.data?.message || '操作失败')
        } finally {
          submitLoading.value = false
        }
      }
    })
  }
  
  // 删除分类
  const handleDelete = (row) => {
    ElMessageBox.confirm(`确定要删除分类 "${row.name}" 吗？`, '警告', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning',
    }).then(async () => {
      try {
        await request.delete(`/categories/${row.id}`)
        ElMessage.success('删除成功')
        fetchData()
      } catch (error) {
        ElMessage.error('删除失败')
      }
    }).catch(() => {})
  }
  
  onMounted(() => {
    fetchData()
  })
  </script>