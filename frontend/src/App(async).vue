<script setup>
  //引入组件
  import  TodoItem  from './components/TodoItem.vue'
  //1.从vue 仓库里拿出ref这个工具
  import { ref,computed,onMounted } from 'vue'
  //2.定义一个响应式数据，这里的count不是简单的数字0，而是一个被监控的对象
  const note = ref('')
  const list = ref([])
  const inputRef = ref(null)
  //计算数组元素个数
  const total = computed(()=> {
        return list.value.length
  })
  //定义一个获取数据的函数
  async function getData() {
      //发起请求，await 的意思是：在这里暂停一下，等 fetch 把数据拿回来，再往下走
      const response = await fetch('https://jsonplaceholder.typicode.com/todos?_limit=5')
      //把拿到的数据转成json格式
      const data = await response.json()
      //处理数据（map 是数组的高级用法，意思是把每个对象里的 title 拿出来组成新数组）
      const titles = data.map(item => ({id:item.id, title:item.title, done: item.completed}))
      //赋值给响应式变量
      list.value = titles
      
    } 
    //加载页面自动聚焦
    onMounted(()=> {
      inputRef.value.focus()
      
      getData()
    })
    //函数
  function add_text() {
    if(note.value != '') {
        list.value.push({id: Date.now(), title: note.value, done: false})
        note.value = ''
    }
  }
  function del(index) {
    list.value.splice(index, 1)
  }
</script>

<template>
    <input type="text" ref="inputRef" v-model="note"  placeholder="写点...">
    <button @click="add_text">添加文字</button>
    <div v-if="total > 0">
        <li v-for="(item, index) in list" :key="item.id">
            <TodoItem :task="item" @del-items="del(index)" />
            
        </li>
    </div>
    <p v-else>恭喜你！任务全部完成了！</p>
</template>

<style scoped>
header {
  line-height: 1.5;
}

.logo {
  display: block;
  margin: 0 auto 2rem;
}

@media (min-width: 1024px) {
  header {
    display: flex;
    place-items: center;
    padding-right: calc(var(--section-gap) / 2);
  }

  .logo {
    margin: 0 2rem 0 0;
  }

  header .wrapper {
    display: flex;
    place-items: flex-start;
    flex-wrap: wrap;
  }
}
</style>
