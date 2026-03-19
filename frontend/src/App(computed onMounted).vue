<script setup>
  //1.从vue 仓库里拿出ref这个工具
    import { ref, computed,onMounted } from 'vue'
  //2.定义变量
  const note = ref('')
  const list = ref([])
  const inputRef = ref(null)
  //挂载完成后执行
  onMounted(() => {
    //inputRef.value就是inpupt的原生DOM
      inputRef.value.focus()
  })
  const total = computed(() => {
    return list.value.length
  } )
  function add_note() {
    if(note.value != '') {
        list.value.push(note.value)
        note.value = ''

    }
  }
  //删除函数
  function del(index) {
    list.value.splice(index, 1)
  }
</script>

<template>
  <div>
    <input type="text" ref="inputRef" v-model="note" placeholder="写点...">
    <button @click="add_note">添加文字</button>
  </div>
  <div v-if="total > 0">
    <ul>
        <li v-for="(item, index) in list" :key="index">
            {{ index }} -- {{ item }}
            <button @click="del(index)">删除</button>
        </li>
    </ul>
    <p>当前共有 {{ total }} 项任务</p>
  </div>
  <p v-else>🎉 太棒了，任务都做完了！</p>
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
