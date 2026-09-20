<i18n lang="yaml">
zh_CN:
  mainTitle: 会员管理
  subTitle: 微信小程序会员（member_user），支持昵称/openid/状态筛选
  appId: 应用标识
  openid: openid
  nickname: 昵称
  avatar: 头像
  gender: 性别
  bio: 简介
  phone: 手机号
  status: 状态
  normal: 正常
  disabled: 停用
  unknown: 未知
  male: 男
  female: 女
  loginCount: 登录次数
  lastLoginAt: 最后登录
  createdAt: 注册时间
  remark: 备注
  selection: 选择
  operation: 操作
  edit: 编辑
  delete: 删除
  editTitle: 编辑会员
  deleteSelected: 批量删除
  deleteConfirm: 确定删除选中的会员吗？
  deleteSuccess: 删除成功
  updateSuccess: 更新成功
  search: 搜索
  reset: 重置
  searchFold: 展开
  searchUnFold: 收起
  cancel: 取消
  ok: 确定
zh_TW:
  mainTitle: 會員管理
  subTitle: 微信小程式會員（member_user），支持昵稱/openid/狀態篩選
  appId: 應用標識
  openid: openid
  nickname: 昵稱
  avatar: 頭像
  gender: 性別
  bio: 簡介
  phone: 手機號
  status: 狀態
  normal: 正常
  disabled: 停用
  unknown: 未知
  male: 男
  female: 女
  loginCount: 登錄次數
  lastLoginAt: 最後登錄
  createdAt: 註冊時間
  remark: 備註
  selection: 選擇
  operation: 操作
  edit: 編輯
  delete: 刪除
  editTitle: 編輯會員
  deleteSelected: 批量刪除
  deleteConfirm: 確定刪除選中的會員嗎？
  deleteSuccess: 刪除成功
  updateSuccess: 更新成功
  search: 搜索
  reset: 重置
  searchFold: 展開
  searchUnFold: 收起
  cancel: 取消
  ok: 確定
en:
  mainTitle: Member Management
  subTitle: WeChat mini program members (member_user)
  appId: App ID
  openid: openid
  nickname: Nickname
  avatar: Avatar
  gender: Gender
  bio: Bio
  phone: Phone
  status: Status
  normal: Normal
  disabled: Disabled
  unknown: Unknown
  male: Male
  female: Female
  loginCount: Login Count
  lastLoginAt: Last Login
  createdAt: Created At
  remark: Remark
  selection: Selection
  operation: Operation
  edit: Edit
  delete: Delete
  editTitle: Edit Member
  deleteSelected: Batch Delete
  deleteConfirm: Delete the selected members?
  deleteSuccess: Deleted successfully
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
import { ElAvatar, ElTag } from 'element-plus'
import { h } from 'vue'
import MaDictSelect from '@/components/ma-dict-picker/ma-dict-select.vue'
import { useMessage } from '@/hooks/useMessage.ts'
import { ResultCode } from '@/utils/ResultCode.ts'
import hasAuth from '@/utils/permission/hasAuth.ts'
import { deleteByIds, page, save } from '../../api/member.ts'

defineOptions({ name: 'userCenter:member' })

const { localTrans: t } = useTrans()
const msg = useMessage()

const proTableRef = ref<MaProTableExpose>() as Ref<MaProTableExpose>
const selections = ref<any[]>([])

const dialogVisible = ref(false)
const dialogLoading = ref(false)
const editForm = ref<Record<string, any>>({})

function statusText(status: number): string {
  return status === 1 ? t('normal') : t('disabled')
}

function genderText(gender: number): string {
  if (gender === 1) {
    return t('male')
  }
  return gender === 2 ? t('female') : t('unknown')
}

const searchItems: MaSearchItem[] = [
  { label: () => t('appId'), prop: 'app_id', render: 'input' },
  { label: () => t('openid'), prop: 'openid', render: 'input' },
  { label: () => t('nickname'), prop: 'nickname', render: 'input' },
  {
    label: () => t('status'),
    prop: 'status',
    render: () => MaDictSelect,
    renderProps: {
      clearable: true,
      placeholder: '',
      dictName: 'system-status',
    },
  },
]

const tableColumns: MaProTableColumns[] = [
  { type: 'selection', showOverflowTooltip: false, label: () => t('selection') },
  { type: 'index' },
  {
    label: () => t('avatar'),
    prop: 'avatar',
    width: '90px',
    cellRender: ({ row }) => h(ElAvatar, { src: row.avatar === '' ? undefined : row.avatar, size: 36 }),
  },
  { label: () => t('nickname'), prop: 'nickname' },
  { label: () => t('openid'), prop: 'openid', showOverflowTooltip: true },
  { label: () => t('appId'), prop: 'app_id' },
  {
    label: () => t('gender'),
    prop: 'gender',
    width: '90px',
    cellRender: ({ row }) => genderText(row.gender),
  },
  {
    label: () => t('status'),
    prop: 'status',
    width: '90px',
    cellRender: ({ row }) => h(ElTag, { type: row.status === 1 ? 'success' : 'danger' }, () => statusText(row.status)),
  },
  { label: () => t('loginCount'), prop: 'login_count', width: '100px' },
  { label: () => t('lastLoginAt'), prop: 'last_login_at', width: '170px' },
  { label: () => t('createdAt'), prop: 'created_at', width: '170px' },
  {
    type: 'operation',
    label: () => t('operation'),
    operationConfigure: {
      actions: [
        {
          name: 'edit',
          icon: 'material-symbols:person-edit',
          show: () => hasAuth('userCenter:member:update'),
          text: () => t('edit'),
          onClick: ({ row }) => openEdit(row),
        },
        {
          name: 'del',
          icon: 'mdi:delete',
          show: () => hasAuth('userCenter:member:delete'),
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

function openEdit(row: any) {
  editForm.value = {
    id: row.id,
    nickname: row.nickname ?? '',
    gender: row.gender ?? 0,
    bio: row.bio ?? '',
    phone: row.phone ?? '',
    status: row.status ?? 1,
    remark: row.remark ?? '',
  }
  dialogVisible.value = true
}

async function handleSubmit() {
  dialogLoading.value = true
  try {
    const { id, ...data } = editForm.value
    const response = await save(id, data)
    if (response.code === ResultCode.SUCCESS) {
      msg.success(t('updateSuccess'))
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
        <el-button
          v-auth="['userCenter:member:delete']"
          type="danger"
          plain
          :disabled="selections.length < 1"
          @click="handleDelete"
        >
          {{ t('deleteSelected') }}
        </el-button>
      </template>
    </MaProTable>

    <el-dialog v-model="dialogVisible" :title="t('editTitle')" width="560px" :close-on-click-modal="false">
      <el-form :model="editForm" label-width="90px">
        <el-form-item :label="t('nickname')">
          <el-input v-model="editForm.nickname" maxlength="30" show-word-limit />
        </el-form-item>
        <el-form-item :label="t('gender')">
          <el-radio-group v-model="editForm.gender">
            <el-radio :value="0">
              {{ t('unknown') }}
            </el-radio>
            <el-radio :value="1">
              {{ t('male') }}
            </el-radio>
            <el-radio :value="2">
              {{ t('female') }}
            </el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item :label="t('bio')">
          <el-input v-model="editForm.bio" maxlength="255" type="textarea" :rows="2" />
        </el-form-item>
        <el-form-item :label="t('phone')">
          <el-input v-model="editForm.phone" maxlength="20" />
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
