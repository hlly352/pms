import { ref, computed } from 'vue'
import {defineStore} from 'pinia'

// defineStore('todo') 是给仓库起的唯一名字

export const useTodoStore = defineStore('todo', () => {
    //1. State(数据) ->对应原来的list
    const list = ref([])
    //3. Actions(方法) -> 对应原来的 getData
    async function fetchDatas() {
        //优化：如果仓库里已有数据了，就别去请求了，防止覆盖
        if (list.value.length > 0) return
        
        const response = await fetch('https://jsonplaceholder.typicode.com/todos?_limit=5')
        const data = await response.json()
        //映射数据结构
        list.value = data.map(item => ({
            id: item.id,
            title: item.title,
            done: item.completed
        }))
    }
    //2. Getters(计算属性) ->对应原来的 total
    const total = computed(() => list.value.length)
    //对应原来的 add_text
    function add(title) {
        if (title != '') {
            list.value.push({id: Date.now(), title: title, done: false})
        }
    }
    //对应原来的 del
    function del(index) {
        list.value.splice(index, 1)
    }
    //把组件需要用的东西 return 出去
    return { list, total, fetchDatas, add, del }
})