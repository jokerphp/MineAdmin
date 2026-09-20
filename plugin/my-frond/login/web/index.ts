/**
 * 求职导航系统 - 登录页插件入口
 *
 * 功能：
 * 1. 在应用启动时（install 钩子）用同名 name: 'login' 路由覆盖系统默认登录页，
 *    这样未登录用户访问 /login 时直接渲染插件提供的登录页面。
 *    （registerRoute 钩子仅在登录后 initRoutes 时才触发，无法覆盖未登录场景，
 *    因此必须在 install 阶段直接操作 router）
 * 2. hooks.login 在登录请求成功后触发，可观察登录信息。
 */
import type { Router } from 'vue-router'
import type { Plugin } from '#/global'

function registerLoginRoute(router: Router) {
  // vue-router4 同名 addRoute 会先 removeRoute 再添加，直接覆盖静态 login 路由
  router.addRoute({
    name: 'login',
    path: '/login',
    component: () => import('./views/login/index.vue'),
    meta: {
      title: '登录',
      i18n: 'menu.login',
    },
  })
  // 初始导航可能在插件 install 时已用旧 matcher 解析完成，
  // 等待 router 就绪后若仍停留在 /login，重新解析一次以命中新路由
  router.isReady().then(() => {
    if (router.currentRoute.value.name === 'login') {
      router.replace(router.currentRoute.value.fullPath)
    }
  })
}

const pluginConfig: Plugin.PluginConfig = {
  install(app) {
    const router = app.config.globalProperties.$router as Router
    if (router) {
      registerLoginRoute(router)
    }
    console.log('[my-frond/login] 登录插件已启动')
  },
  config: {
    enable: true,
    info: {
      name: 'my-frond/login',
      version: '1.0.0',
      author: 'zhh',
      description: '求职导航系统自定义登录页',
    },
  },
  hooks: {
    start: (config) => {
      console.log('[my-frond/login] 插件启动', config.info.version)
    },
    // 登录后 initRoutes 触发，同名 addRoute 会再次确认覆盖（幂等）
    registerRoute: (router: Router) => {
      registerLoginRoute(router)
    },
    login: (formInfo) => {
      console.log('[my-frond/login] 登录成功，用户信息：', formInfo)
    },
  },
}

export default pluginConfig
