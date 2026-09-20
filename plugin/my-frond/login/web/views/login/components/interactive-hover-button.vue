<template>
  <button
    :type="type"
    class="ihb-btn"
    :class="className"
    @click="$emit('click', $event)"
  >
    <span class="ihb-text">{{ text }}</span>
    <div class="ihb-overlay">
      <span class="ihb-overlay-text">{{ text }}</span>
      <span class="ihb-overlay-icon">
        <slot name="icon">
          <svg class="ihb-arrow" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 12h14" />
            <path d="m12 5 7 7-7 7" />
          </svg>
        </slot>
      </span>
    </div>
  </button>
</template>

<script setup lang="ts">
interface Props {
  text?: string
  type?: 'button' | 'submit'
  className?: string
}
defineProps<Props>()
defineEmits<{ (e: 'click', event: MouseEvent): void }>()
</script>

<style scoped>
.ihb-btn {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  cursor: pointer;
  overflow: hidden;
  border-radius: 9999px;
  border: 1px solid hsl(var(--border, 214.3 31.8% 91.4%));
  background: hsl(var(--background, 0 0% 100%));
  padding: 0 24px;
  font-size: 14px;
  text-align: center;
  font-weight: 600;
  transition: transform 0.2s;
  user-select: none;
  -webkit-tap-highlight-color: transparent;
}
.ihb-btn:active {
  transform: scale(0.98);
}
.ihb-text {
  display: inline-block;
  white-space: nowrap;
  transition: all 0.3s;
}
.ihb-btn:hover .ihb-text {
  transform: translateX(100%);
  opacity: 0;
}
.ihb-overlay {
  position: absolute;
  inset: 0;
  z-index: 10;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  border-radius: 9999px;
  background: hsl(var(--primary, 231 48% 48%));
  color: hsl(var(--primary-foreground, 0 0% 98%));
  opacity: 0;
  transform: translateX(-12%);
  transition: all 0.3s;
}
.ihb-btn:hover .ihb-overlay {
  opacity: 1;
  transform: translateX(0);
}
.ihb-overlay-text {
  white-space: nowrap;
}
.ihb-overlay-icon {
  display: flex;
  align-items: center;
  transition: transform 0.3s;
}
.ihb-btn:hover .ihb-overlay-icon {
  transform: translateX(1px);
}
.ihb-arrow {
  width: 16px;
  height: 16px;
}
</style>
