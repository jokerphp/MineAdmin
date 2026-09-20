<template>
  <div
    ref="el"
    class="eye-dot-wrap"
    :style="{
      width: `${size}px`,
      height: `${size}px`,
      backgroundColor: pupilColor,
      transform: `translate(${x}px, ${y}px)`,
    }"
  />
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'

interface Props {
  size?: number
  maxDistance?: number
  pupilColor?: string
  forceLookX?: number
  forceLookY?: number
  /** 父组件统一监听的鼠标位置 */
  mouse?: { x: number, y: number }
}

const props = withDefaults(defineProps<Props>(), {
  size: 12,
  maxDistance: 5,
  pupilColor: '#2D2D2D',
  forceLookX: undefined,
  forceLookY: undefined,
  mouse: () => ({ x: 0, y: 0 }),
})

const el = ref<HTMLDivElement | null>(null)
// 缓存矩形，避免每次渲染读取 getBoundingClientRect 造成强制重排
const cachedRect = ref<DOMRect | null>(null)

function updateRect() {
  cachedRect.value = el.value?.getBoundingClientRect() ?? null
}

onMounted(() => {
  updateRect()
  window.addEventListener('resize', updateRect)
})
onUnmounted(() => {
  window.removeEventListener('resize', updateRect)
})

const x = computed(() => {
  if (props.forceLookX !== undefined)
    return props.forceLookX
  const rect = cachedRect.value
  if (!rect)
    return 0
  const dx = props.mouse.x - (rect.left + rect.width / 2)
  const dy = props.mouse.y - (rect.top + rect.height / 2)
  const distance = Math.min(Math.sqrt(dx ** 2 + dy ** 2), props.maxDistance)
  const angle = Math.atan2(dy, dx)
  return Math.cos(angle) * distance
})

const y = computed(() => {
  if (props.forceLookY !== undefined)
    return props.forceLookY
  const rect = cachedRect.value
  if (!rect)
    return 0
  const dx = props.mouse.x - (rect.left + rect.width / 2)
  const dy = props.mouse.y - (rect.top + rect.height / 2)
  const distance = Math.min(Math.sqrt(dx ** 2 + dy ** 2), props.maxDistance)
  const angle = Math.atan2(dy, dx)
  return Math.sin(angle) * distance
})
</script>

<style scoped>
.eye-dot-wrap {
  border-radius: 9999px;
  transition: transform 0.1s ease-out;
}
</style>
