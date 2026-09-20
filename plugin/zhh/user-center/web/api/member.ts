/**
 * 用戶中心 - 會員接口（方案 §6.4、§8.2）
 */
import type { PageList, ResponseStruct } from '#/global'

const http = useHttp()

export interface MemberVo {
  id?: number
  app_id?: string
  openid?: string
  nickname?: string
  avatar?: string
  background?: string
  gender?: number
  bio?: string
  phone?: string
  status?: number
  login_count?: number
  last_login_at?: string
  remark?: string
  created_at?: string
}

export interface MemberSearchVo {
  app_id?: string
  openid?: string
  nickname?: string
  status?: number
  created_at?: string[]
}

export function page(params: MemberSearchVo): Promise<ResponseStruct<PageList<MemberVo>>> {
  return http.get('/admin/user-center/member', { params })
}

export function detail(id: number): Promise<ResponseStruct<MemberVo>> {
  return http.get(`/admin/user-center/member/${id}`)
}

export function save(id: number, data: MemberVo): Promise<ResponseStruct<null>> {
  return http.put(`/admin/user-center/member/${id}`, data)
}

export function deleteByIds(ids: number[]): Promise<ResponseStruct<null>> {
  return http.delete('/admin/user-center/member', { data: ids })
}
