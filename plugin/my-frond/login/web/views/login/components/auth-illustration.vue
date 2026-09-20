<template>
  <div class="ai-wrap" :style="{ width: '550px', height: '400px' }">
    <!-- 紫色人物 -->
    <div
      ref="purpleRef"
      class="ai-body ai-purple"
      :style="purpleStyle"
    >
      <div class="ai-eyes" :style="purpleEyeStyle">
        <Eye
          :size="18"
          :pupil-size="7"
          :max-distance="5"
          :blinking="purpleBlink"
          :force-look-x="purpleForceX"
          :force-look-y="purpleForceY"
          :mouse="mouse"
        />
        <Eye
          :size="18"
          :pupil-size="7"
          :max-distance="5"
          :blinking="purpleBlink"
          :force-look-x="purpleForceX"
          :force-look-y="purpleForceY"
          :mouse="mouse"
        />
      </div>
    </div>

    <!-- 黑色人物 -->
    <div
      ref="blackRef"
      class="ai-body ai-black"
      :style="blackStyle"
    >
      <div class="ai-eyes ai-eyes-black" :style="blackEyeStyle">
        <Eye
          :size="16"
          :pupil-size="6"
          :max-distance="4"
          :blinking="blackBlink"
          :force-look-x="blackForceX"
          :force-look-y="blackForceY"
          :mouse="mouse"
        />
        <Eye
          :size="16"
          :pupil-size="6"
          :max-distance="4"
          :blinking="blackBlink"
          :force-look-x="blackForceX"
          :force-look-y="blackForceY"
          :mouse="mouse"
        />
      </div>
    </div>

    <!-- 橙色人物（圆顶） -->
    <div
      ref="orangeRef"
      class="ai-body ai-orange"
      :style="orangeStyle"
    >
      <div class="ai-eyes" :style="orangeEyeStyle">
        <EyeDot :force-look-x="orangeForceX" :force-look-y="orangeForceY" :mouse="mouse" />
        <EyeDot :force-look-x="orangeForceX" :force-look-y="orangeForceY" :mouse="mouse" />
      </div>
    </div>

    <!-- 黄色人物（圆顶） -->
    <div
      ref="yellowRef"
      class="ai-body ai-yellow"
      :style="yellowStyle"
    >
      <div class="ai-eyes ai-eyes-yellow" :style="yellowEyeStyle">
        <EyeDot :force-look-x="yellowForceX" :force-look-y="yellowForceY" :mouse="mouse" />
        <EyeDot :force-look-x="yellowForceX" :force-look-y="yellowForceY" :mouse="mouse" />
      </div>
      <div class="ai-mouth" :style="yellowMouthStyle" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import Eye from './eye.vue'
import EyeDot from './eye-dot.vue'

interface Props {
  isTyping?: boolean
  showPassword?: boolean
  passwordLength?: number
  passwordActive?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  isTyping: false,
  showPassword: false,
  passwordLength: 0,
  passwordActive: false,
})

const clamp = (value: number, min: number, max: number) => Math.max(min, Math.min(max, value))

/* 鼠标位置（父组件统一监听，rAF 节流后分发给眼睛子组件） */
const mouse = ref({ x: 0, y: 0 })

/* 四个卡通人物的 DOM 引用 */
const purpleRef = ref<HTMLDivElement | null>(null)
const blackRef = ref<HTMLDivElement | null>(null)
const orangeRef = ref<HTMLDivElement | null>(null)
const yellowRef = ref<HTMLDivElement | null>(null)

/* 人物身体/面部随鼠标计算（1:1 还原原版 metrics） */
interface Metrics { faceX: number, faceY: number, bodySkew: number }

/* 当前显示值：由 mousemove 直接更新，平滑过渡交给 CSS transition（与原版一致） */
const metricsState = reactive<Record<string, Metrics>>({
  purple: { faceX: 0, faceY: 0, bodySkew: 0 },
  black: { faceX: 0, faceY: 0, bodySkew: 0 },
  orange: { faceX: 0, faceY: 0, bodySkew: 0 },
  yellow: { faceX: 0, faceY: 0, bodySkew: 0 },
})

/* 缓存人物矩形：人物布局固定，仅在挂载与 resize 时测量，避免 mousemove 中反复触发回流 */
const purpleRect = ref<DOMRect | null>(null)
const blackRect = ref<DOMRect | null>(null)
const orangeRect = ref<DOMRect | null>(null)
const yellowRect = ref<DOMRect | null>(null)

function measureRects() {
  purpleRect.value = purpleRef.value?.getBoundingClientRect() ?? null
  blackRect.value = blackRef.value?.getBoundingClientRect() ?? null
  orangeRect.value = orangeRef.value?.getBoundingClientRect() ?? null
  yellowRect.value = yellowRef.value?.getBoundingClientRect() ?? null
}

function calcMetrics(rect: DOMRect | null): Metrics {
  if (!rect)
    return { faceX: 0, faceY: 0, bodySkew: 0 }
  const centerX = rect.left + rect.width / 2
  const centerY = rect.top + rect.height / 3
  const dx = mouse.value.x - centerX
  const dy = mouse.value.y - centerY
  return {
    faceX: clamp(dx / 20, -15, 15),
    faceY: clamp(dy / 30, -10, 10),
    bodySkew: clamp(-dx / 120, -6, 6),
  }
}

/* rAF 节流：同一帧内多次 mousemove 只更新一次，CSS transition 负责平滑过渡 */
let rafId = 0
const handleMove = (event: MouseEvent) => {
  mouse.value = { x: event.clientX, y: event.clientY }
  if (rafId)
    return
  rafId = requestAnimationFrame(() => {
    rafId = 0
    metricsState.purple = calcMetrics(purpleRect.value)
    metricsState.black = calcMetrics(blackRect.value)
    metricsState.orange = calcMetrics(orangeRect.value)
    metricsState.yellow = calcMetrics(yellowRect.value)
  })
}

onMounted(() => {
  measureRects()
  window.addEventListener('mousemove', handleMove, { passive: true })
  window.addEventListener('resize', measureRects)
})
onUnmounted(() => {
  if (rafId)
    cancelAnimationFrame(rafId)
  window.removeEventListener('mousemove', handleMove)
  window.removeEventListener('resize', measureRects)
})

/* 随机眨眼 */
const purpleBlink = ref(false)
const blackBlink = ref(false)
const blinkTimers: ReturnType<typeof setTimeout>[] = []
function queueBlink(target: { value: boolean }, timers: ReturnType<typeof setTimeout>[]) {
  timers.push(setTimeout(() => {
    target.value = true
    timers.push(setTimeout(() => {
      target.value = false
      queueBlink(target, timers)
    }, 150))
  }, 3000 + Math.random() * 4000))
}
onMounted(() => {
  queueBlink(purpleBlink, blinkTimers)
  queueBlink(blackBlink, blinkTimers)
})
onUnmounted(() => {
  blinkTimers.forEach(t => clearTimeout(t))
})

/* 打字看向键盘 / 密码偷看 */
const typingLook = ref(false)
const peek = ref(false)
const typingTimer: ReturnType<typeof setTimeout>[] = []
const peekTimer: ReturnType<typeof setTimeout>[] = []

watch(() => props.isTyping, (val) => {
  if (!val) {
    typingLook.value = false
    return
  }
  typingLook.value = true
  typingTimer.forEach(t => clearTimeout(t))
  typingTimer.length = 0
  typingTimer.push(setTimeout(() => { typingLook.value = false }, 800))
})

watch(() => [props.passwordLength, props.showPassword], () => {
  if (props.passwordLength <= 0 || !props.showPassword) {
    peek.value = false
    return
  }
  peekTimer.forEach(t => clearTimeout(t))
  peekTimer.length = 0
  peekTimer.push(setTimeout(() => {
    peek.value = true
    peekTimer.push(setTimeout(() => { peek.value = false }, 800))
  }, 2000 + Math.random() * 3000))
})

const passwordVisible = computed(() => props.showPassword && (props.passwordLength > 0 || props.passwordActive))
const hidingPassword = computed(() => (props.passwordLength > 0 || props.passwordActive) && !props.showPassword)

/* 紫色人物样式 */
const purpleStyle = computed(() => {
  const height = props.isTyping || hidingPassword.value ? '440px' : '400px'
  const skew = passwordVisible.value
    ? 'skewX(0deg)'
    : (props.isTyping || hidingPassword.value)
      ? `skewX(${metricsState.purple.bodySkew - 12}deg) translateX(40px)`
      : `skewX(${metricsState.purple.bodySkew}deg)`
  return {
    left: '70px',
    width: '180px',
    height,
    backgroundColor: '#6C3FF5',
    borderRadius: '10px 10px 0 0',
    zIndex: 1,
    transform: skew,
    transformOrigin: 'bottom center',
  }
})
const purpleEyeStyle = computed(() => {
  const left = passwordVisible.value ? '20px' : typingLook.value ? '55px' : `${45 + metricsState.purple.faceX}px`
  const top = passwordVisible.value ? '35px' : typingLook.value ? '65px' : `${40 + metricsState.purple.faceY}px`
  return { left, top, gap: '32px' }
})
const purpleForceX = computed(() => passwordVisible.value ? (peek.value ? 4 : -4) : typingLook.value ? 3 : undefined)
const purpleForceY = computed(() => passwordVisible.value ? (peek.value ? 5 : -4) : typingLook.value ? 4 : undefined)

/* 黑色人物样式 */
const blackStyle = computed(() => {
  const skew = passwordVisible.value
    ? 'skewX(0deg)'
    : typingLook.value
      ? `skewX(${metricsState.black.bodySkew * 1.5 + 10}deg) translateX(20px)`
      : (props.isTyping || hidingPassword.value)
        ? `skewX(${metricsState.black.bodySkew * 1.5}deg)`
        : `skewX(${metricsState.black.bodySkew}deg)`
  return {
    left: '240px',
    width: '120px',
    height: '310px',
    backgroundColor: '#2D2D2D',
    borderRadius: '8px 8px 0 0',
    zIndex: 2,
    transform: skew,
    transformOrigin: 'bottom center',
  }
})
const blackEyeStyle = computed(() => {
  const left = passwordVisible.value ? '10px' : typingLook.value ? '32px' : `${26 + metricsState.black.faceX}px`
  const top = passwordVisible.value ? '28px' : typingLook.value ? '12px' : `${32 + metricsState.black.faceY}px`
  return { left, top, gap: '24px' }
})
const blackForceX = computed(() => passwordVisible.value ? -4 : typingLook.value ? 0 : undefined)
const blackForceY = computed(() => (passwordVisible.value || typingLook.value) ? -4 : undefined)

/* 橙色人物样式 */
const orangeStyle = computed(() => ({
  left: '0px',
  width: '240px',
  height: '200px',
  zIndex: 3,
  backgroundColor: '#FF9B6B',
  borderRadius: '120px 120px 0 0',
  transform: passwordVisible.value ? 'skewX(0deg)' : `skewX(${metricsState.orange.bodySkew}deg)`,
  transformOrigin: 'bottom center',
}))
const orangeEyeStyle = computed(() => {
  const left = passwordVisible.value ? '50px' : `${82 + metricsState.orange.faceX}px`
  const top = passwordVisible.value ? '85px' : `${90 + metricsState.orange.faceY}px`
  return { left, top, gap: '32px' }
})
const orangeForceX = computed(() => passwordVisible.value ? -5 : undefined)
const orangeForceY = computed(() => passwordVisible.value ? -4 : undefined)

/* 黄色人物样式 */
const yellowStyle = computed(() => ({
  left: '310px',
  width: '140px',
  height: '230px',
  zIndex: 4,
  backgroundColor: '#E8D754',
  borderRadius: '70px 70px 0 0',
  transform: passwordVisible.value ? 'skewX(0deg)' : `skewX(${metricsState.yellow.bodySkew}deg)`,
  transformOrigin: 'bottom center',
}))
const yellowEyeStyle = computed(() => {
  const left = passwordVisible.value ? '20px' : `${52 + metricsState.yellow.faceX}px`
  const top = passwordVisible.value ? '35px' : `${40 + metricsState.yellow.faceY}px`
  return { left, top, gap: '24px' }
})
const yellowMouthStyle = computed(() => {
  const left = passwordVisible.value ? '10px' : `${40 + metricsState.yellow.faceX}px`
  const top = passwordVisible.value ? '88px' : `${88 + metricsState.yellow.faceY}px`
  return {
    left,
    top,
    width: '80px',
    height: '4px',
    borderRadius: '9999px',
    backgroundColor: '#2D2D2D',
  }
})
const yellowForceX = computed(() => passwordVisible.value ? -5 : undefined)
const yellowForceY = computed(() => passwordVisible.value ? -4 : undefined)
</script>

<style scoped>
.ai-wrap {
  position: relative;
}
.ai-body {
  position: absolute;
  bottom: 0;
  /* 与原版一致：所有属性 0.7s 平滑过渡（身体倾斜、高度变化、密码偷看时立正） */
  transition: all 0.7s ease-in-out;
  will-change: transform;
}
.ai-eyes {
  position: absolute;
  display: flex;
  /* 与原版一致：眼睛容器位置 0.7s 平滑过渡（打字看向键盘、密码偷看时平移） */
  transition: all 0.7s ease-in-out;
  will-change: transform;
}
.ai-mouth {
  position: absolute;
  transition: all 0.2s ease-out;
}
</style>
