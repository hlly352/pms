<script setup>
  //1.从vue 仓库里拿出ref这个工具
  import { ref } from 'vue'
  //2.定义一个响应式数据，这里的count不是简单的数字0，而是一个被监控的对象
  const count = ref(0)
  //3. 定义一个动作（函数）
  function add() {
    count.value++ //注意！在script 标签里修改 ref 数据，必须加 .value
    console.log(count.value) 
  }
  //4.定义减少函数
  function decrease(){
    count.value--
    console.log(count.value)
  }
</script>

<template>
  <div>
    <h1>{{ count }}</h1>
    <p v-if="count > 5">哇！数字很大了！</p>
    <h1 :style="{ color:count > 6 ? 'red' : 'blue' }">{{ count }}</h1>
    <button @click="add">点我 +1</button>
    <button @click="decrease">点击 -1</button>
  </div>
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
