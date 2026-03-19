<script setup>
import { ref, reactive, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { getSettings, saveSettings } from '@/api/setting'
import { updateEmail, updatePassword } from '@/api/user' // 👈 引入修改个人信息的接口
import { ElMessage } from 'element-plus'
import { Operation, Lock, Message } from '@element-plus/icons-vue'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const authStore = useAuthStore()

// 默认激活的标签页
const activeTab = ref('basic')

// 🌟 监听路由参数：如果从右上角点击"个人设置"跳转过来(带有 ?tab=security)，直接切换到账号安全 Tab
watch(() => route.query.tab, (newTab) => {
  if (newTab) {
    activeTab.value = newTab
  }
}, { immediate: true })

// ==========================
// 1. 系统设置 (全局)
// ==========================
const form = ref({
  site_title: '',      // 系统名称
  user_motto: '',      // 个性签名
  avatar_url: '',      // 头像链接
  theme_color: '#409EFF' // 主题色
})

// 🌟 动态应用 Element Plus 主题色 (实装预留功能)
const applyThemeColor = (color) => {
  if (!color) return
  document.documentElement.style.setProperty('--el-color-primary', color)
}

const loadData = async () => {
  const res = await getSettings()
  if (res) {
    for (const key in form.value) {
      if (res[key]) {
        form.value[key] = res[key]
      }
    }
    // 页面加载时应用主题色
    applyThemeColor(form.value.theme_color)
  }
}

const handleSaveBasic = async () => {
  try {
    await saveSettings(form.value)
    applyThemeColor(form.value.theme_color) // 保存时即刻生效
    ElMessage.success('系统配置已保存，全局生效')
  } catch (error) {
    console.error(error)
  }
}

// ==========================
// 2. 个人设置 (仅限当前登录用户)
// ==========================

// --- 邮箱修改 ---
const emailFormRef = ref(null)
const emailForm = reactive({ email: authStore.user?.email || '' })
const emailRules = {
  email: [
    { required: true, message: '请输入邮箱地址', trigger: 'blur' },
    { type: 'email', message: '请输入正确的邮箱格式', trigger: ['blur', 'change'] }
  ]
}

const handleSaveEmail = async () => {
  if (!emailFormRef.value) return
  await emailFormRef.value.validate(async (valid) => {
    if (valid) {
      try {
        await updateEmail({ email: emailForm.email })
        ElMessage.success('邮箱修改成功')
        if (authStore.user) authStore.user.email = emailForm.email // 实时更新本地状态
      } catch (error) {
        console.error(error)
      }
    }
  })
}

// --- 密码修改 ---
const pwdFormRef = ref(null)
const pwdForm = reactive({
  old_password: '',
  new_password: '',
  new_password_confirmation: ''
})

// 二次密码校验逻辑
const validatePass2 = (rule, value, callback) => {
  if (value === '') {
    callback(new Error('请再次输入密码确认'))
  } else if (value !== pwdForm.new_password) {
    callback(new Error('两次输入的新密码不一致!'))
  } else {
    callback()
  }
}

const pwdRules = {
  old_password: [{ required: true, message: '请输入当前密码', trigger: 'blur' }],
  new_password: [
    { required: true, message: '请输入新密码', trigger: 'blur' },
    { min: 6, message: '密码长度不能少于 6 个字符', trigger: 'blur' }
  ],
  new_password_confirmation: [
    { required: true, validator: validatePass2, trigger: 'blur' }
  ]
}

const handleSavePassword = async () => {
  if (!pwdFormRef.value) return
  await pwdFormRef.value.validate(async (valid) => {
    if (valid) {
      try {
        await updatePassword(pwdForm)
        ElMessage.success('密码修改成功，下次请使用新密码登录')
        pwdFormRef.value.resetFields() // 清空密码输入框
      } catch (error) {
        console.error(error)
      }
    }
  })
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="setting-container">
    <h2>设置中心 ⚙️</h2>
    
    <el-card shadow="never" class="box-card">
      <el-tabs v-model="activeTab">
        
        <el-tab-pane label="系统设置" name="basic">
          <div class="tab-content">
            <el-form :model="form" label-width="120px" style="max-width: 600px;">
              
              <el-form-item label="系统名称">
                <el-input v-model="form.site_title" placeholder="默认：MyLife 个人管理" />
                <div class="tip">显示在浏览器标题栏和侧边栏顶部</div>
              </el-form-item>

              <el-form-item label="个性签名">
                <el-input v-model="form.user_motto" type="textarea" placeholder="一句话介绍自己..." />
              </el-form-item>

              <el-form-item label="头像地址">
                <el-input v-model="form.avatar_url" placeholder="输入图片 URL" />
                <div class="avatar-preview" v-if="form.avatar_url">
                  <el-avatar :size="50" :src="form.avatar_url" />
                  <span style="margin-left:10px; color:#999; font-size: 12px;">预览效果</span>
                </div>
              </el-form-item>

              <el-form-item label="主题色">
                <el-color-picker v-model="form.theme_color" @change="applyThemeColor" />
                <div class="tip" style="margin-left: 15px;">实时改变系统主色调，保存后生效</div>
              </el-form-item>

              <el-form-item>
                <el-button type="primary" :icon="Operation" @click="handleSaveBasic">保存全局配置</el-button>
              </el-form-item>

            </el-form>
          </div>
        </el-tab-pane>

        <el-tab-pane label="个人设置 (账号安全)" name="security">
          <div class="tab-content">
            
            <div class="security-section">
              <h4 class="section-title"><el-icon><Message /></el-icon> 更改绑定邮箱</h4>
              <el-form ref="emailFormRef" :model="emailForm" :rules="emailRules" label-width="120px" style="max-width: 500px;">
                <el-form-item label="新邮箱地址" prop="email">
                  <el-input v-model="emailForm.email" placeholder="输入新邮箱" />
                </el-form-item>
                <el-form-item>
                  <el-button type="primary" @click="handleSaveEmail">更新邮箱</el-button>
                </el-form-item>
              </el-form>
            </div>

            <el-divider />

            <div class="security-section">
              <h4 class="section-title"><el-icon><Lock /></el-icon> 更改登录密码</h4>
              <el-form ref="pwdFormRef" :model="pwdForm" :rules="pwdRules" label-width="120px" style="max-width: 500px;">
                <el-form-item label="当前密码" prop="old_password">
                  <el-input v-model="pwdForm.old_password" type="password" show-password placeholder="请输入当前密码验证身份" />
                </el-form-item>
                <el-form-item label="新密码" prop="new_password">
                  <el-input v-model="pwdForm.new_password" type="password" show-password placeholder="请输入新密码 (至少6位)" />
                </el-form-item>
                <el-form-item label="确认新密码" prop="new_password_confirmation">
                  <el-input v-model="pwdForm.new_password_confirmation" type="password" show-password placeholder="请再次输入新密码确认" />
                </el-form-item>
                <el-form-item>
                  <el-button type="danger" @click="handleSavePassword">确认修改密码</el-button>
                </el-form-item>
              </el-form>
            </div>

          </div>
        </el-tab-pane>

      </el-tabs>
    </el-card>
  </div>
</template>

<style scoped>
.setting-container {
  max-width: 900px;
  padding-bottom: 40px;
}
.tab-content {
  padding: 20px 0;
}
.tip {
  font-size: 12px;
  color: #999;
  line-height: 1.5;
  margin-top: 5px;
}
.avatar-preview {
  margin-top: 10px;
  display: flex;
  align-items: center;
}
.security-section {
  padding: 10px 0;
}
.section-title {
  margin-bottom: 20px;
  color: #303133;
  display: flex;
  align-items: center;
  gap: 8px;
}
</style>