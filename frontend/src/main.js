// import './assets/main.css'  //此样式会把页面限制未1280px,为了显示默认页用的
import permission from './directives/permission'
import { createApp } from 'vue'
import { createPinia } from 'pinia' //新增pinia
// import App from './App(ref).vue'
import App from './App.vue'
import router from './router/index' //引入路由配置文件
//引入 Element Plus 及其样式
import ElementPlus from 'element-plus'
import 'element-plus/dist/index.css'

const app = createApp(App)
app.use(createPinia()) //挂载 Pinia
app.use(router) //挂载路由
app.directive('permission', permission)
//挂载 Element Plus
app.use(ElementPlus)

app.mount('#app')
