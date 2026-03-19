<script setup>
    import { RouterLink, RouterView } from 'vue-router'
</script>

<template>
    <header>
        <nav>
            <RouterLink to="/">待办事项</RouterLink>
            <RouterLink to="/about">关于我们</RouterLink>
            <RouterLink to="/users">用户管理</RouterLink>
            <RouterLink to="/login">登录</RouterLink>
            <RouterLink to="/tasks">任务</RouterLink>
        </nav>
    </header>

    <main>
        <RouterView />
    </main>
</template>

<style scoped>
    nav {
        padding: 20px; 
        text-align: center
    }
    a {
        margin: 0 10px;
        text-decoration: none;
        color: red
    }
</style>