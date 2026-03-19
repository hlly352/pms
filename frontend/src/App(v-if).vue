<script setup>
    //1. 引用 ref
    import {ref} from 'vue'
    //2. 定义库存
    const count = ref(5)
    //3. 定义函数
    function decrease() {
        count.value--
        
    }
</script>

<template>
  <div>
    <p :style="{color: count < 3 ? 'red' : 'black'}">{{ count }}</p>
    <button v-if="count != 0" @click="decrease"> 点我 -1 </button>
    <p v-if="count == 0">已售罄，请补货！</p>
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
