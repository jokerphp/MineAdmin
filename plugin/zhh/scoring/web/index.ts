/**
 * 計分業務插件前端入口（方案 §8.1）
 *
 * 【重要約定】後台菜單與路由的唯一來源 = menu 表（Database/Seeders/MenuSeeder.php）。
 * 本文件不得再聲明與 menu 表重複的 views，原因：
 * web/src/store/modules/useRouteStore.ts 的 initRoutes() 會把 plugin.views 逐條經
 * toRecordRawRoute() 後 push 進 MineRootLayoutRoute.children（根層），而側邊欄
 * （useMenuStore.ts 取 routesRaw 中根路由的 children）正是遍歷這個數組，
 * 結果同一頁面會出現兩次：一次在「打牌記賬」分組下（來自 menu 表），
 * 一次與「打牌記賬」同級（來自 views）。
 *
 * 頁面組件的解析不依賴此處的 views：menuToRoutes() 以 import.meta.glob 掃描 plugins
 * 目錄下的 views 文件，並拼出「plugins 前綴 + menu.component + 後綴」，
 * 故 web/views/room/index.vue 保持不動即可正常加載。
 *
 * 頁面 defineOptions({ name })（keepAlive 緩存用）仍與 menu 表的菜單 name 保持一致。
 */
import type { Plugin } from '#/global'

const pluginConfig: Plugin.PluginConfig = {
  install() {
    console.log('[zhh/scoring] 插件已啟動')
  },
  config: {
    enable: true,
    info: {
      name: 'zhh/scoring',
      version: '1.0.0',
      author: 'zhh',
      description: '打牌記賬計分業務插件',
    },
  },
}

export default pluginConfig
