<script setup>
    //定义在组件里，定义需要接收什么参数
   defineProps(['task'])
   //1. 定义发射器
   const emits = defineEmits(['del-items'])
   function handDelete() {
    //2.发射信号
    emits('del-items')
   }

</script>

<template>
    <el-checkbox v-model="task.done" size="large">
    <span class="item-text" :class="{'finished': task.done}"> {{ task.title }}</span>
    </el-checkbox>
    <el-button type="danger" size="small" @click="handDelete">
        删除
    </el-button>
</template>

<style scoped>
    .item-text {
        color:  blue;
        font-weight: bold;
        margin-right: 15px;
    }
    .finished {
        text-decoration: line-through;
    }
</style>