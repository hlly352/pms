<script setup>
  //引入组件
  import  TodoItem  from '../components/TodoItem.vue'
  //1.从vue 仓库里拿出ref这个工具
  import { ref, onMounted } from 'vue'
  //1. 引入建好的仓库
  import { useTodoStore } from '../stores/todo'
  //2. 初始化仓库
  const store = useTodoStore()
  //保留与UI相关的变量
  const note = ref('')
  const inputRef = ref(null)

    //加载页面自动聚焦
    onMounted(()=> {
      inputRef.value.focus()
      //3. 调用仓库的方法
      store.fetchDatas()
    })
    function add_text() {
        //调用仓库的add 方法
        store.add(note.value)
        note.value = ''
    }

  function del(index) {
    store.del(index)
  }
</script>

<template>
    <el-input 
     type="text" 
     ref="inputRef" 
     v-model="note"  
     placeholder="写点..."
     @keyup.enter="add_text"
     />
     <br />
    <el-button type="primary" @click="add_text">添加文字</el-button>
    <div v-if="store.total > 0">
        <li v-for="(item, index) in store.list" :key="item.id">
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
