<i18n lang="yaml">
zh_CN:
  mainTitle: 应用管理
  subTitle: 小程序/程序应用（member_app），新增一条记录即可对接不同程序
  appId: 应用标识
  appName: 应用名称
  appType: 应用类型
  wechatMiniProgram: 微信小程序
  other: 其他
  wechatAppid: 微信AppID
  wechatSecret: 微信Secret
  wechatSecretKeep: 留空表示不修改
  status: 状态
  normal: 正常
  disabled: 停用
  remark: 备注
  selection: 选择
  operation: 操作
  deleteSelected: 批量删除
  createdAt: 创建时间
  add: 新增应用
  edit: 编辑
  delete: 删除
  addTitle: 新增应用
  editTitle: 编辑应用
  deleteConfirm: 确定删除选中的应用吗？
  deleteSuccess: 删除成功
  createSuccess: 新增成功
  updateSuccess: 更新成功
  search: 搜索
  reset: 重置
  searchFold: 展开
  searchUnFold: 收起
  cancel: 取消
  ok: 确定
zh_TW:
  mainTitle: 應用管理
  subTitle: 小程式/程序應用（member_app），新增一條記錄即可對接不同程序
  appId: 應用標識
  appName: 應用名稱
  appType: 應用類型
  wechatMiniProgram: 微信小程式
  other: 其他
  wechatAppid: 微信AppID
  wechatSecret: 微信Secret
  wechatSecretKeep: 留空表示不修改
  status: 狀態
  normal: 正常
  disabled: 停用
  remark: 備註
  selection: 選擇
  operation: 操作
  deleteSelected: 批量刪除
  createdAt: 創建時間
  add: 新增應用
  edit: 編輯
  delete: 刪除
  addTitle: 新增應用
  editTitle: 編輯應用
  deleteConfirm: 確定刪除選中的應用嗎？
  deleteSuccess: 刪除成功
  createSuccess: 新增成功
  updateSuccess: 更新成功
  search: 搜索
  reset: 重置
  searchFold: 展開
  searchUnFold: 收起
  cancel: 取消
  ok: 確定
en:
  mainTitle: App Management
  subTitle: Mini program applications (member_app)
  appId: App ID
  appName: App Name
  appType: App Type
  wechatMiniProgram: WeChat Mini Program
  other: Other
  wechatAppid: WeChat AppID
  wechatSecret: WeChat Secret
  wechatSecretKeep: Leave empty to keep unchanged
  status: Status
  normal: Normal
  disabled: Disabled
  remark: Remark
  selection: Selection
  operation: Operation
  deleteSelected: Batch Delete
  createdAt: Created At
  add: Add App
  edit: Edit
  delete: Delete
  addTitle: Add App
  editTitle: Edit App
  deleteConfirm: Delete the selected apps?
  deleteSuccess: Deleted successfully
  createSuccess: Created successfully
  updateSuccess: Updated successfully
  search: Search
  reset: Reset
  searchFold: Expand
  searchUnFold: Collapse
  cancel: Cancel
  ok: OK
</i18n>

<script setup lang="ts">
import type { MaProTableColumns, MaProTableExpose, MaProTableOptions, MaProTableSchema } from '@mineadmin/pro-table'
import type { MaSearchItem } from '@mineadmin/search'
import type { Ref } from 'vue'
import { ElTag } from 'element-plus'
import { h } from 'vue'
import { useMessage } from '@/hooks/useMessage.ts'
import { ResultCode } from '@/utils/ResultCode.ts'
import hasAuth from '@/utils/permission/hasAuth.ts'
import { create, deleteByIds, page, save } from '../../api/app.ts'

defineOptions({ name: 'userCenter:app' })

const { localTrans: t } = useTrans()
const msg = useMessage()

const proTableRef = ref<MaProTableExpose>() as Ref<MaProTableExpose>
const selections = ref<any[]>([])

const dialogVisible = ref(false)
const dialogLoading = ref(false)
const dialogType = ref<'add' | 'edit'>('add')
const editForm = ref<Record<string, any>>({})

function statusText(status: number): string {
  return status === 1 ? t('normal') : t('disabled')
}

const searchItems: MaSearchItem[] = [
  { label: () => t('appId'), prop: 'app_id', render: 'input' },
  { label: () => t('appName'), prop: 'app_name', render: 'input' },
]

const tableColumns: MaProTableColumns[] = [
  { type: 'selection', showOverflowTooltip: false, label: () => t('selection') },
  { type: 'index' },
  { label: () => t('appId'), prop: 'app_id' },
  { label: () => t('appName'), prop: 'app_name' },
  { label: () => t('appType'), prop: 'app_type' },
  { label: () => t('wechatAppid'), prop: 'wechat_appid' },
  {
    label: () => t('status'),
    prop: 'status',
    width: '90px',
    cellRender: ({ row }) => h(ElTag, { type: row.status === 1 ? 'success' : 'danger' }, () => statusText(row.status)),
  },
  { label: () => t('remark'), prop: 'remark' },
  { label: () => t('createdAt'), prop: 'created_at', width: '170px' },
  {
    type: 'operation',
    label: () => t('operation'),
    operationConfigure: {
      actions: [
        {
          name: 'edit',
          icon: 'material-symbols:edit-outline',
          show: () => hasAuth('userCenter:app:update'),
          text: () => t('edit'),
          onClick: ({ row }) => openEdit(row),
        },
        {
          name: 'del',
          icon: 'mdi:delete',
          show: () => hasAuth('userCenter:app:delete'),
          text: () => t('delete'),
          onClick: ({ row }, proxy: MaProTableExpose) => {
            msg.delConfirm(t('deleteConfirm')).then(async () => {
              const response = await deleteByIds([row.id])
              if (response.code === ResultCode.SUCCESS) {
                msg.success(t('deleteSuccess'))
                await proxy.refresh()
              }
            })
          },
        },
      ],
    },
  },
]

const options = ref<MaProTableOptions>({
  adaptionOffsetBottom: 161,
  header: {
    mainTitle: () => t('mainTitle'),
    subTitle: () => t('subTitle'),
  },
  tableOptions: {
    on: {
      onSelectionChange: (selection: any[]) => selections.value = selection,
    },
  },
  searchOptions: {
    fold: true,
    text: {
      searchBtn: () => t('search'),
      resetBtn: () => t('reset'),
      isFoldBtn: () => t('searchFold'),
      notFoldBtn: () => t('searchUnFold'),
    },
  },
  searchFormOptions: { labelWidth: '90px' },
  requestOptions: {
    api: page,
  },
})

const schema = ref<MaProTableSchema>({
  searchItems,
  tableColumns,
})

function openAdd() {
  dialogType.value = 'add'
  editForm.value = {
    app_id: '',
    app_name: '',
    app_type: 'wechat_mini_program',
    wechat_appid: '',
    wechat_secret: '',
    status: 1,
    remark: '',
  }
  dialogVisible.value = true
}

function openEdit(row: any) {
  dialogType.value = 'edit'
  editForm.value = {
    id: row.id,
    app_id: row.app_id ?? '',
    app_name: row.app_name ?? '',
    app_type: row.app_type ?? 'wechat_mini_program',
    wechat_appid: row.wechat_appid ?? '',
    wechat_secret: '',
    status: row.status ?? 1,
    remark: row.remark ?? '',
  }
  dialogVisible.value = true
}

async function handleSubmit() {
  dialogLoading.value = true
  try {
    const { id, ...data } = editForm.value
    // wechat_secret 未填寫表示不修改（後端模型 hidden，不回顯舊值）
    if (data.wechat_secret === '') {
      delete data.wechat_secret
    }

    const response = dialogType.value === 'add' ? await create(data) : await save({ id, ...data })
    if (response.code === ResultCode.SUCCESS) {
      msg.success(dialogType.value === 'add' ? t('createSuccess') : t('updateSuccess'))
      dialogVisible.value = false
      await proTableRef.value?.refresh()
    }
    else {
      msg.error(response.message)
    }
  }
  finally {
    dialogLoading.value = false
  }
}

function handleDelete() {
  const ids = selections.value.map((item: any) => item.id)
  msg.delConfirm(t('deleteConfirm')).then(async () => {
    const response = await deleteByIds(ids)
    if (response.code === ResultCode.SUCCESS) {
      msg.success(t('deleteSuccess'))
      await proTableRef.value?.refresh()
    }
  })
}
</script>

<template>
  <div class="mine-layout">
    <MaProTable ref="proTableRef" :options="options" :schema="schema">
      <template #toolbarLeft>
        <el-button v-auth="['userCenter:app:create']" type="primary" @click="openAdd">
          {{ t('add') }}
        </el-button>
        <el-button
          v-auth="['userCenter:app:delete']"
          type="danger"
          plain
          :disabled="selections.length < 1"
          @click="handleDelete"
        >
          {{ t('deleteSelected') }}
        </el-button>
      </template>
    </MaProTable>

    <el-dialog
      v-model="dialogVisible"
      :title="dialogType === 'add' ? t('addTitle') : t('editTitle')"
      width="560px"
      :close-on-click-modal="false"
    >
      <el-form :model="editForm" label-width="110px">
        <el-form-item :label="t('appId')">
          <el-input v-model="editForm.app_id" maxlength="50" placeholder="如 scoring_wx" />
        </el-form-item>
        <el-form-item :label="t('appName')">
          <el-input v-model="editForm.app_name" maxlength="100" />
        </el-form-item>
        <el-form-item :label="t('appType')">
          <el-select v-model="editForm.app_type">
            <el-option :label="t('wechatMiniProgram')" value="wechat_mini_program" />
            <el-option :label="t('other')" value="other" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('wechatAppid')">
          <el-input v-model="editForm.wechat_appid" maxlength="64" />
        </el-form-item>
        <el-form-item :label="t('wechatSecret')">
          <el-input v-model="editForm.wechat_secret" maxlength="128" :placeholder="t('wechatSecretKeep')" />
        </el-form-item>
        <el-form-item :label="t('status')">
          <el-radio-group v-model="editForm.status">
            <el-radio :value="1">
              {{ t('normal') }}
            </el-radio>
            <el-radio :value="2">
              {{ t('disabled') }}
            </el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item :label="t('remark')">
          <el-input v-model="editForm.remark" maxlength="255" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">
          {{ t('cancel') }}
        </el-button>
        <el-button type="primary" :loading="dialogLoading" @click="handleSubmit">
          {{ t('ok') }}
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped lang="scss">
</style>
