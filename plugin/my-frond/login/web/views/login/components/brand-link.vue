<template>
  <div class="brand-link" :class="{ centered }">
    <span class="brand-icon">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 2v4" />
        <path d="M12 22v-4" />
        <path d="M2 12h4" />
        <path d="M22 12h-4" />
        <circle cx="12" cy="12" r="4" />
      </svg>
    </span>
    <span class="brand-name">求职导航系统</span>
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{ centered?: boolean }>(), {
  centered: false,
})
</script>

<style scoped>
.brand-link {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 18px;
  font-weight: 600;
  color: inherit;
}
.brand-link.centered {
  justify-content: center;
}
.brand-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.1);
  padding: 2px;
  backdrop-filter: blur(4px);
}
.brand-icon svg {
  width: 20px;
  height: 20px;
}
</style>
