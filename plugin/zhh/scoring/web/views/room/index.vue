<i18n lang="yaml">
zh_CN:
  mainTitle: 房间管理
  subTitle: 统一查询入口：房间 + 成员 + 逐笔流水 + 对局战绩快照
  roomNo: 房号
  ownerId: 房主ID
  status: 状态
  waiting: 等待中
  playing: 进行中
  settled: 已结算
  closed: 已关闭
  memberLimit: 人数上限
  currentRound: 当前轮次
  battleCount: 对局数
  recordCount: 流水数
  createdAt: 创建时间
  selection: 选择
  operation: 操作
  viewDetail: 查看详情
  forceClose: 强制关闭
  closeSelected: 批量关闭
  closeConfirm: 确定强制关闭选中的房间吗？
  closeSuccess: 关闭成功
  search: 搜索
  reset: 重置
  searchFold: 展开
  searchUnFold: 收起
  detailTitle: 房间详情
  members: 成员
  records: 流水
  battles: 对局与快照
  transfers: 清算转账
  nickname: 昵称
  score: 分数
  role: 角色
  owner: 房主
  member: 成员
  memberStatus: 成员状态
  inRoom: 在房
  left: 已退出
  memberSettled: 已单独结算
  roundNo: 轮次
  recordType: 类型
  typeScore: 计分
  typeExpense: 支出
  typeSettle: 清算
  amount: 金额
  targetUser: 收款方
  remark: 备注
  noData: 暂无数据
  close: 关闭
zh_TW:
  mainTitle: 房間管理
  subTitle: 統一查詢入口：房間 + 成員 + 逐筆流水 + 對局戰績快照
  roomNo: 房號
  ownerId: 房主ID
  status: 狀態
  waiting: 等待中
  playing: 進行中
  settled: 已結算
  closed: 已關閉
  memberLimit: 人數上限
  currentRound: 當前輪次
  battleCount: 對局數
  recordCount: 流水數
  createdAt: 創建時間
  selection: 選擇
  operation: 操作
  viewDetail: 查看詳情
  forceClose: 強制關閉
  closeSelected: 批量關閉
  closeConfirm: 確定強制關閉選中的房間嗎？
  closeSuccess: 關閉成功
  search: 搜索
  reset: 重置
  searchFold: 展開
  searchUnFold: 收起
  detailTitle: 房間詳情
  members: 成員
  records: 流水
  battles: 對局與快照
  transfers: 清算轉賬
  nickname: 昵稱
  score: 分數
  role: 角色
  owner: 房主
  member: 成員
  memberStatus: 成員狀態
  inRoom: 在房
  left: 已退出
  memberSettled: 已單獨結算
  roundNo: 輪次
  recordType: 類型
  typeScore: 計分
  typeExpense: 支出
  typeSettle: 清算
  amount: 金額
  targetUser: 收款方
  remark: 備註
  noData: 暫無數據
  close: 關閉
en:
  mainTitle: Room Management
  subTitle: 'Unified query entry: rooms + members + records + battle snapshots'
  roomNo: Room No
  ownerId: Owner ID
  status: Status
  waiting: Waiting
  playing: Playing
  settled: Settled
  closed: Closed
  memberLimit: Member Limit
  currentRound: Current Round
  battleCount: Battles
  recordCount: Records
  createdAt: Created At
  selection: Selection
  operation: Operation
  viewDetail: Detail
  forceClose: Force Close
  closeSelected: Batch Close
  closeConfirm: Force close the selected rooms?
  closeSuccess: Closed successfully
  search: Search
  reset: Reset
  searchFold: Expand
  searchUnFold: Collapse
  detailTitle: Room Detail
  members: Members
  records: Records
  battles: Battles & Snapshots
  transfers: Settle Transfers
  nickname: Nickname
  score: Score
  role: Role
  owner: Owner
  member: Member
  memberStatus: Member Status
  inRoom: In Room
  left: Left
  memberSettled: Settled
  roundNo: Round
  recordType: Type
  typeScore: Score
  typeExpense: Expense
  typeSettle: Settle
  amount: Amount
  targetUser: Target
  remark: Remark
  noData: No data
  close: Close
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
import { deleteByIds, detail, page } from '../../api/room.ts'

defineOptions({ name: 'scoring:room' })

const { localTrans: t } = useTrans()
const msg = useMessage()

const proTableRef = ref<MaProTableExpose>() as Ref<MaProTableExpose>
const selections = ref<any[]>([])

const detailVisible = ref(false)
const detailLoading = ref(false)
const detailData = ref<Record<string, any>>({ room: {}, members: [], records: [], battles: [], battle_members: [], transfers: [] })

function statusText(status: number): string {
  switch (status) {
    case 0:
      return t('waiting')
    case 1:
      return t('playing')
    case 2:
      return t('settled')
    default:
      return t('closed')
  }
}

function statusType(status: number): string {
  switch (status) {
    case 1:
      return 'success'
    case 2:
      return 'info'
    case 3:
      return 'danger'
    default:
      return 'warning'
  }
}

function memberStatusText(status: number): string {
  switch (status) {
    case 0:
      return t('inRoom')
    case 1:
      return t('left')
    default:
      return t('memberSettled')
  }
}

function recordTypeText(type: number): string {
  switch (type) {
    case 1:
      return t('typeScore')
    case 2:
      return t('typeExpense')
    default:
      return t('typeSettle')
  }
}

const searchItems: MaSearchItem[] = [
  { label: () => t('roomNo'), prop: 'room_no', render: 'input' },
  { label: () => t('ownerId'), prop: 'owner_id', render: 'input' },
  { label: () => t('status'), prop: 'status', render: 'input' },
]

const tableColumns: MaProTableColumns[] = [
  { type: 'selection', showOverflowTooltip: false, label: () => t('selection') },
  { type: 'index' },
  { label: () => t('roomNo'), prop: 'room_no' },
  { label: () => t('ownerId'), prop: 'owner_id', width: '100px' },
  {
    label: () => t('status'),
    prop: 'status',
    width: '100px',
    cellRender: ({ row }) => h(ElTag, { type: statusType(row.status) }, () => statusText(row.status)),
  },
  { label: () => t('currentRound'), prop: 'current_round', width: '100px' },
  { label: () => t('battleCount'), prop: 'battle_count', width: '100px' },
  { label: () => t('recordCount'), prop: 'record_count', width: '100px' },
  { label: () => t('createdAt'), prop: 'created_at', width: '170px' },
  {
    type: 'operation',
    label: () => t('operation'),
    operationConfigure: {
      actions: [
        {
          name: 'detail',
          icon: 'material-symbols:visibility-outline',
          show: () => hasAuth('scoring:room:index'),
          text: () => t('viewDetail'),
          onClick: ({ row }) => openDetail(row.id),
        },
        {
          name: 'close',
          icon: 'mdi:close-circle-outline',
          show: ({ row }) => hasAuth('scoring:room:delete') && row.status !== 3,
          text: () => t('forceClose'),
          onClick: ({ row }, proxy: MaProTableExpose) => {
            msg.delConfirm(t('closeConfirm')).then(async () => {
              const response = await deleteByIds([row.id])
              if (response.code === ResultCode.SUCCESS) {
                msg.success(t('closeSuccess'))
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

async function openDetail(id: number) {
  detailLoading.value = true
  detailVisible.value = true
  try {
    const response = await detail(id)
    if (response.code === ResultCode.SUCCESS) {
      detailData.value = response.data as Record<string, any>
    }
    else {
      msg.error(response.message)
    }
  }
  finally {
    detailLoading.value = false
  }
}

function handleClose() {
  const ids = selections.value.map((item: any) => item.id)
  msg.delConfirm(t('closeConfirm')).then(async () => {
    const response = await deleteByIds(ids)
    if (response.code === ResultCode.SUCCESS) {
      msg.success(t('closeSuccess'))
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
          v-auth="['scoring:room:delete']"
          type="danger"
          plain
          :disabled="selections.length < 1"
          @click="handleClose"
        >
          {{ t('closeSelected') }}
        </el-button>
      </template>
    </MaProTable>

    <el-drawer v-model="detailVisible" :title="t('detailTitle')" size="70%" :loading="detailLoading">
      <el-descriptions :column="3" border>
        <el-descriptions-item :label="t('roomNo')">
          {{ detailData.room?.room_no }}
        </el-descriptions-item>
        <el-descriptions-item :label="t('ownerId')">
          {{ detailData.room?.owner_id }}
        </el-descriptions-item>
        <el-descriptions-item :label="t('status')">
          {{ statusText(detailData.room?.status) }}
        </el-descriptions-item>
        <el-descriptions-item :label="t('currentRound')">
          {{ detailData.room?.current_round }}
        </el-descriptions-item>
        <el-descriptions-item :label="t('createdAt')">
          {{ detailData.room?.created_at }}
        </el-descriptions-item>
        <el-descriptions-item :label="t('settled')">
          {{ detailData.room?.finished_at || '-' }}
        </el-descriptions-item>
      </el-descriptions>

      <el-divider>{{ t('members') }}</el-divider>
      <el-table :data="detailData.members" border size="small">
        <el-table-column prop="user_id" label="ID" width="90" />
        <el-table-column :label="t('nickname')" prop="nickname" />
        <el-table-column :label="t('score')" prop="score" width="100" />
        <el-table-column :label="t('role')" width="90">
          <template #default="{ row }">
            {{ row.role === 1 ? t('owner') : t('member') }}
          </template>
        </el-table-column>
        <el-table-column :label="t('memberStatus')" width="120">
          <template #default="{ row }">
            {{ memberStatusText(row.status) }}
          </template>
        </el-table-column>
      </el-table>

      <el-divider>{{ t('records') }}</el-divider>
      <el-table :data="detailData.records" border size="small">
        <el-table-column :label="t('createdAt')" prop="created_at" width="170" />
        <el-table-column :label="t('roundNo')" prop="round_no" width="80" />
        <el-table-column :label="t('recordType')" width="100">
          <template #default="{ row }">
            {{ recordTypeText(row.record_type) }}
          </template>
        </el-table-column>
        <el-table-column prop="user_id" label="ID" width="90" />
        <el-table-column :label="t('targetUser')" prop="target_user_id" width="110" />
        <el-table-column :label="t('amount')" prop="amount" width="100" />
        <el-table-column :label="t('remark')" prop="remark" />
      </el-table>

      <el-divider>{{ t('battles') }}</el-divider>
      <el-table :data="detailData.battles" border size="small">
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column :label="t('currentRound')" prop="total_rounds" width="100" />
        <el-table-column label="Tea Fee" prop="tea_fee_total" width="110" />
        <el-table-column :label="t('createdAt')" prop="started_at" width="170" />
        <el-table-column :label="t('settled')" prop="finished_at" width="170" />
      </el-table>

      <el-table :data="detailData.battle_members" border size="small" class="mt-2">
        <el-table-column prop="battle_id" label="Battle" width="90" />
        <el-table-column prop="user_id" label="ID" width="90" />
        <el-table-column :label="t('nickname')" prop="nickname" />
        <el-table-column :label="t('score')" prop="total_score" width="100" />
        <el-table-column label="Rank" prop="rank" width="80" />
        <el-table-column :label="t('amount')" prop="tea_fee" width="100" />
      </el-table>

      <el-divider>{{ t('transfers') }}</el-divider>
      <el-table :data="detailData.transfers" border size="small">
        <el-table-column prop="battle_id" label="Battle" width="90" />
        <el-table-column prop="from_user_id" label="From" width="90" />
        <el-table-column prop="to_user_id" label="To" width="90" />
        <el-table-column :label="t('amount')" prop="amount" width="100" />
        <el-table-column :label="t('createdAt')" prop="created_at" width="170" />
      </el-table>
    </el-drawer>
  </div>
</template>

<style scoped lang="scss">
</style>
