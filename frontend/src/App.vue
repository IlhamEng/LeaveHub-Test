<script setup lang="ts">
import { onMounted } from "vue";
import { RouterView } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import ToastContainer from "@/components/ui/ToastContainer.vue";

const auth = useAuthStore();

// Rehydrate auth state from localStorage on first load
onMounted(() => {
    auth.rehydrate();
});
</script>

<template>
    <RouterView />
    <ToastContainer />
</template>
