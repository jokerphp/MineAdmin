<template>
  <div class="cc-login">
    <div class="cc-login__grid">
      <!-- 左侧品牌区 -->
      <div class="cc-login__aside">
        <div class="cc-login__aside-inner">
          <div class="cc-login__aside-top" />
          <div class="cc-login__illustration">
            <AuthIllustration
              :is-typing="isTyping"
              :show-password="showPassword"
              :password-length="form.password.length"
              :password-active="passwordFocused"
            />
          </div>
          <div class="cc-login__aside-links">
            <a href="javascript:void(0)" @click="ElMessage.info('请联系管理员了解隐私说明')">隐私说明</a>
            <a href="javascript:void(0)" @click="ElMessage.info('请联系管理员了解使用条款')">使用条款</a>
          </div>
        </div>
        <div class="cc-login__aside-grid" />
        <div class="cc-login__aside-blur cc-login__aside-blur--1" />
        <div class="cc-login__aside-blur cc-login__aside-blur--2" />
      </div>

      <!-- 右侧表单区 -->
      <div class="cc-login__panel">
        <div class="cc-login__panel-inner">
          <div class="cc-login__panel-enter">
            <div class="cc-login__heading">
              <h1>{{ appTitle }}</h1>
            </div>

            <form class="cc-login__form" @submit.prevent="handleLogin">
              <div class="cc-login__field">
                <label for="cc-username" class="cc-login__label">用户名</label>
                <input
                  id="cc-username"
                  v-model="form.username"
                  type="text"
                  placeholder="请输入用户名"
                  autocomplete="off"
                  class="cc-login__input"
                  @focus="emailFocused = true"
                  @blur="emailFocused = false"
                >
              </div>

              <div class="cc-login__field">
                <label for="cc-password" class="cc-login__label">登录密码</label>
                <div class="cc-login__input-wrap">
                  <input
                    id="cc-password"
                    v-model="form.password"
                    :type="showPassword ? 'text' : 'password'"
                    placeholder="••••••••"
                    autocomplete="current-password"
                    class="cc-login__input cc-login__input--password"
                    @focus="passwordFocused = true"
                    @blur="passwordFocused = false"
                  >
                  <button
                    type="button"
                    class="cc-login__eye"
                    :aria-label="showPassword ? '隐藏密码' : '显示密码'"
                    @click="showPassword = !showPassword"
                  >
                    <svg v-if="!showPassword" class="cc-login__eye-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
                      <circle cx="12" cy="12" r="3" />
                    </svg>
                    <svg v-else class="cc-login__eye-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" />
                      <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68" />
                      <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61" />
                      <line x1="2" x2="22" y1="2" y2="22" />
                    </svg>
                  </button>
                </div>
              </div>

              <div class="cc-login__options">
                <button
                  type="button"
                  class="cc-login__remember"
                  role="checkbox"
                  :aria-checked="remember"
                  @click="remember = !remember"
                >
                  <span class="cc-login__checkbox" :class="{ checked: remember }">
                    <svg v-if="remember" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                      <polyline points="20 6 9 17 4 12" />
                    </svg>
                  </span>
                  <span class="cc-login__remember-text">在这台设备保持登录</span>
                </button>
                <a href="javascript:void(0)" class="cc-login__forgot" @click="ElMessage.info('请联系管理员重置密码')">找回密码</a>
              </div>

              <InteractiveHoverButton
                type="submit"
                text="进入账户"
                class="cc-login__submit"
              />
            </form>

            <div class="cc-login__social">
              <InteractiveHoverButton
                text="使用 Google 继续"
                class="cc-login__social-btn"
                @click="ElMessage.info('第三方登录暂未开放')"
              >
                <template #icon>
                  <svg class="cc-login__google" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 488 512">
                    <path fill="currentColor" d="M488 261.8C488 403.3 391.1 504 248 504 110.8 504 0 393.2 0 256S110.8 8 248 8c66.8 0 126 23.4 172.9 61.9l-76.2 76.2C322.3 113.2 289.4 96 248 96c-88.8 0-160.1 71.9-160.1 160.1s71.3 160.1 160.1 160.1c98.2 0 135-70.4 140.8-106.9H248v-85.3h236.1c2.3 12.7 3.9 26.9 3.9 41.4z" />
                  </svg>
                </template>
              </InteractiveHoverButton>
            </div>

            <div class="cc-login__signup">
              还没有账户？
              <a href="javascript:void(0)" @click="ElMessage.info('请联系管理员开通账号')">立即创建</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import useUserStore from '@/store/modules/useUserStore.ts'
import useSettingStore from '@/store/modules/useSettingStore.ts'
import AuthIllustration from './components/auth-illustration.vue'
import InteractiveHoverButton from './components/interactive-hover-button.vue'

const router = useRouter()
const userStore = useUserStore()
const settingStore = useSettingStore()

const appTitle = import.meta.env.VITE_APP_TITLE

const emailFocused = ref(false)
const passwordFocused = ref(false)
const showPassword = ref(false)
const remember = ref(false)
const loading = ref(false)

const form = reactive({
  username: '',
  password: '',
})

const isTyping = computed(() => emailFocused.value || passwordFocused.value)

async function handleLogin() {
  if (!form.username.trim() || !form.password) {
    ElMessage.warning('请输入用户名和密码')
    return
  }
  if (loading.value)
    return
  loading.value = true
  try {
    await userStore.login({
      username: form.username.trim(),
      password: form.password,
      code: '',
    })
    const welcomePath = settingStore.getSettings('welcomePage').path ?? null
    const redirect = router.currentRoute.value.query?.redirect as string | undefined
    await router.push({ path: redirect ?? welcomePath ?? '/' })
  }
  finally {
    loading.value = false
  }
}
</script>

<style scoped>
.cc-login {
  --background: 0 0% 100%;
  --foreground: 222.2 84% 4.9%;
  --primary: 231 48% 48%;
  --primary-foreground: 0 0% 98%;
  --muted-foreground: 215.4 16.3% 46.9%;
  --border: 214.3 31.8% 91.4%;
  --ring: 231 48% 48%;
  height: 100vh;
  height: 100dvh;
  background: hsl(var(--background));
  color: hsl(var(--foreground));
  font-family: Inter, ui-sans-serif, system-ui, sans-serif;
  -webkit-font-smoothing: antialiased;
}
html.dark .cc-login {
  --background: 20 14.3% 4.1%;
  --foreground: 60 9.1% 97.8%;
  --muted-foreground: 24 5.4% 63.9%;
  --border: 12 6.5% 15.1%;
}
.cc-login__grid {
  display: grid;
  height: 100%;
}
@media (min-width: 1024px) {
  .cc-login__grid {
    grid-template-columns: 1fr 1fr;
    overflow: hidden;
  }
}

/* 左侧品牌区 */
.cc-login__aside {
  position: relative;
  display: none;
  flex-direction: column;
  justify-content: space-between;
  padding: 48px;
  color: #fff;
  background: linear-gradient(to bottom right, #9ca3af 0%, #6b7280 50%, #4b5563 100%);
}
@media (min-width: 1024px) {
  .cc-login__aside {
    display: flex;
  }
}
html.dark .cc-login__aside {
  color: #111827;
  background: linear-gradient(to bottom right, rgba(255, 255, 255, 0.9) 0%, rgba(255, 255, 255, 0.8) 50%, rgba(255, 255, 255, 0.7) 100%);
}
html.dark .cc-login__aside-links {
  color: #374151;
}
html.dark .cc-login__aside-links a:hover {
  color: #111827;
}
.cc-login__aside-inner {
  position: relative;
  z-index: 2;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  height: 100%;
}
.cc-login__aside-top {
  flex-shrink: 0;
  height: 40px;
}
.cc-login__illustration {
  display: flex;
  align-items: flex-end;
  justify-content: center;
  height: 500px;
}
.cc-login__aside-links {
  display: flex;
  align-items: center;
  gap: 32px;
  font-size: 14px;
  color: #4b5563;
}
.cc-login__aside-links a {
  color: inherit;
  text-decoration: none;
  transition: color 0.2s;
}
.cc-login__aside-links a:hover {
  color: #111827;
}
.cc-login__aside-grid {
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(255, 255, 255, 0.05) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255, 255, 255, 0.05) 1px, transparent 1px);
  background-size: 20px 20px;
}
.cc-login__aside-blur {
  position: absolute;
  border-radius: 9999px;
  filter: blur(64px);
}
.cc-login__aside-blur--1 {
  right: 25%;
  top: 25%;
  width: 256px;
  height: 256px;
  background: rgba(156, 163, 175, 0.2);
}
.cc-login__aside-blur--2 {
  bottom: 25%;
  left: 25%;
  width: 384px;
  height: 384px;
  background: rgba(209, 213, 219, 0.2);
}

/* 右侧表单区 */
.cc-login__panel {
  display: flex;
  min-height: 100vh;
  min-height: 100dvh;
  align-items: center;
  justify-content: center;
  background: hsl(var(--background));
  padding: 32px 24px;
}
@media (min-width: 640px) {
  .cc-login__panel {
    padding: 32px;
  }
}
@media (min-width: 1024px) {
  .cc-login__panel {
    min-height: 0;
    overflow-y: auto;
  }
}
.cc-login__panel-inner {
  width: 100%;
  max-width: 420px;
}
.cc-login__panel-enter {
  animation: cc-login-enter 420ms cubic-bezier(0.2, 0.8, 0.2, 1) both;
}
@keyframes cc-login-enter {
  from {
    opacity: 0;
    transform: translateY(14px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.cc-login__heading {
  margin-bottom: 40px;
  text-align: center;
}
.cc-login__heading h1 {
  margin: 0;
  font-size: 30px;
  font-weight: 700;
  letter-spacing: -0.025em;
}

.cc-login__form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}
.cc-login__field {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.cc-login__label {
  font-size: 14px;
  font-weight: 500;
}
.cc-login__input-wrap {
  position: relative;
}
.cc-login__input {
  box-sizing: border-box;
  width: 100%;
  height: 48px;
  border: 1px solid hsl(var(--border) / 0.6);
  border-radius: 9999px;
  background: hsl(var(--background));
  padding: 8px 16px;
  font-size: 16px;
  color: hsl(var(--foreground));
  outline: none;
  transition: border-color 0.2s, box-shadow 0.2s;
}
.cc-login__input:focus {
  border-color: hsl(var(--primary));
  box-shadow: 0 0 0 2px hsl(var(--ring));
}
.cc-login__input--password {
  padding-right: 40px;
}
.cc-login__input::placeholder {
  color: hsl(var(--muted-foreground));
}
@media (min-width: 768px) {
  .cc-login__input {
    font-size: 14px;
  }
}
.cc-login__eye {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 4px;
  border: none;
  background: none;
  cursor: pointer;
  color: hsl(var(--muted-foreground));
  transition: color 0.2s;
}
.cc-login__eye:hover {
  color: hsl(var(--foreground));
}
.cc-login__eye-icon {
  width: 20px;
  height: 20px;
}

.cc-login__options {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.cc-login__remember {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0;
  border: none;
  background: none;
  cursor: pointer;
  font-size: 14px;
  color: hsl(var(--foreground));
}
.cc-login__checkbox {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 16px;
  height: 16px;
  flex-shrink: 0;
  border: 1px solid hsl(var(--primary));
  border-radius: 2px;
  color: hsl(var(--primary-foreground));
  background: transparent;
  transition: background 0.2s;
}
.cc-login__checkbox svg {
  width: 12px;
  height: 12px;
}
.cc-login__checkbox.checked {
  background: hsl(var(--primary));
}
.cc-login__remember-text {
  font-weight: 400;
}
.cc-login__forgot {
  font-size: 14px;
  font-weight: 500;
  color: hsl(var(--primary));
  text-decoration: none;
}
.cc-login__forgot:hover {
  text-decoration: underline;
}

.cc-login__submit {
  height: 48px;
  margin-top: 20px;
  font-size: 16px;
  font-weight: 500;
  color: hsl(var(--foreground));
}

.cc-login__social {
  margin-top: 24px;
}
.cc-login__social-btn {
  height: 48px;
  font-size: 14px;
  border-color: hsl(var(--border) / 0.6);
  color: hsl(var(--foreground));
}
.cc-login__google {
  width: 20px;
  height: 20px;
}

.cc-login__signup {
  margin-top: 32px;
  text-align: center;
  font-size: 14px;
  color: hsl(var(--muted-foreground));
}
.cc-login__signup a {
  font-weight: 500;
  color: hsl(var(--foreground));
  text-decoration: none;
}
.cc-login__signup a:hover {
  text-decoration: underline;
}
</style>
