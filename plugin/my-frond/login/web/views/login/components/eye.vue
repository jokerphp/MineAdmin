<template>
  <div
    ref="el"
    class="eye-wrap"
    :style="{
      width: `${size}px`,
      height: blinking ? '2px' : `${size}px`,
      backgroundColor: eyeColor,
      overflow: 'hidden',
    }"
  >
    <div
      v-if="!blinking"
      class="eye-pupil"
      :style="{
        width: `${pupilSize}px`,
        height: `${pupilSize}px`,
        backgroundColor: pupilColor,
        transform: `translate(${x}px, ${y}px)`,
      }"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'

interface Props {
  size?: number
  pupilSize?: number
  maxDistance?: number
  eyeColor?: string
  pupilColor?: string
  blinking?: boolean
  forceLookX?: number
  forceLookY?: number
  /** 父组件统一监听的鼠标位置 */
  mouse?: { x: number, y: number }
}

const props = withDefaults(defineProps<Props>(), {
  size: 48,
  pupilSize: 16,
  maxDistance: 10,
  eyeColor: 'white',
  pupilColor: '#2D2D2D',
  blinking: false,
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
.eye-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 9999px;
  transition: all 0.15s ease-out;
}
.eye-pupil {
  border-radius: 9999px;
  transition: transform 0.1s ease-out;
}
</style>
