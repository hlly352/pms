<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { login } from '@/api/auth' 
import { ElMessage } from 'element-plus'
import { User, Lock } from '@element-plus/icons-vue'

const router = useRouter()
const authStore = useAuthStore()
const loading = ref(false)
const formRef = ref(null)

// 🌟 修改 1：表单数据改为 username
const form = reactive({
  username: '', 
  password: ''
})

// 🌟 修改 2：去掉邮箱校验规则，改为普通文本必填校验
const rules = {
  username: [
    { required: true, message: '请输入用户名', trigger: 'blur' }
  ],
  password: [
    { required: true, message: '请输入密码', trigger: 'blur' },
    { min: 6, message: '密码长度不能少于6位', trigger: 'blur' }
  ]
}

// 登录处理函数
const handleLogin = async () => {
  if (!formRef.value) return
  
  await formRef.value.validate(async (valid) => {
    if (valid) {
      loading.value = true
      try {
        const res = await login(form)
        
        // 🛑 第一道关卡：优先检查业务状态码
        if (res.code === 401) {
          ElMessage.error(res.msg || '账号或密码错误')
          return 
        }

        // 🛑 第二道关卡：通用错误检查 (非200都算错)
        if (res.code && res.code !== 200) {
          ElMessage.error(res.msg || '操作失败')
          return
        }

        // ✅ 第三道关卡：提取 Token
        const token = res.data?.token || res.token || res.access_token

        if (token) {
          authStore.setToken(token)
          await authStore.fetchUserInfo()
          ElMessage.success('登录成功')
          router.push('/dashboard')
        } else {
          ElMessage.error('登录异常：服务器未返回有效令牌')
        }

      } catch (error) {
        console.error('登录报错:', error)
        ElMessage.error(error.message || '登录请求失败')
      } finally {
        loading.value = false
      }
    }
  })
}
</script>

<template>
  <div class="login-container">
    <div class="login-box">
      <div class="login-header">
        <h2>MyLife 个人管理系统</h2>
        <p>请登录以继续</p>
      </div>
      
      <el-form 
        ref="formRef" 
        :model="form" 
        :rules="rules" 
        class="login-form"
        @keyup.enter="handleLogin" 
      >
        <el-form-item prop="username">
          <el-input 
            v-model="form.username" 
            placeholder="用户名" 
            :prefix-icon="User"
            size="large"
          />
        </el-form-item>
        
        <el-form-item prop="password">
          <el-input 
            v-model="form.password" 
            type="password" 
            placeholder="密码" 
            :prefix-icon="Lock"
            show-password
            size="large"
          />
        </el-form-item>
        
        <el-form-item>
          <el-button 
            type="primary" 
            :loading="loading" 
            class="login-btn" 
            size="large"
            @click="handleLogin"
          >
            登 录
          </el-button>
        </el-form-item>
        
        </el-form>
    </div>
  </div>
</template>

<style scoped>
.login-container {
  height: 100vh;
  display: flex;
  justify-content: center;
  align-items: center;
  background-color: #2d3a4b; /* 深色背景 */
}

.login-box {
  width: 400px;
  padding: 40px;
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 15px 30px rgba(0,0,0,0.1);
}

.login-header {
  text-align: center;
  margin-bottom: 30px;
}

.login-header h2 {
  color: #333;
  font-weight: 600;
  margin-bottom: 10px;
}

.login-header p {
  color: #999;
  font-size: 14px;
}

.login-btn {
  width: 100%;
  font-size: 16px;
  padding: 12px 0;
}
</style>