import { createRouter, createWebHistory } from 'vue-router'

//1. 定义路由规则，这里的component 需要指向我们稍后要创建的 .vue 文件‘
const router = createRouter({
    //使用 HTML5 的历史模式（URL里没有 # 号）
    history: createWebHistory(import.meta.env.BASE_URL),
    routes: [
        {
            path: '/', //浏览器地址是 / 时
            name: 'home', 
            //懒加载写法（推荐）：只有访问的时候才加载这个文件，省流量
            component: () => import('../views/TodoView.vue')
        },
        {
            path: '/about', //浏览器地址是 /about 时
            name: 'about',
            component: ()=> import('../views/AboutView.vue')
        }
    ]
})
export default router