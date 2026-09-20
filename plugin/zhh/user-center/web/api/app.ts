/**
 * 用戶中心 - 應用（小程序）接口（方案 §6.4、§8.2）
 */
import type { PageList, ResponseStruct } from '#/global'

const http = useHttp()

export interface MemberAppVo {
  id?: number
  app_id?: string
  app_name?: string
  app_type?: string
  wechat_appid?: string
  wechat_secret?: string
  status?: number
  remark?: string
  created_at?: string
}

export interface MemberAppSearchVo {
  app_id?: string
  app_name?: string
  status?: number
}

export function page(params: MemberAppSearchVo): Promise<ResponseStruct<PageList<MemberAppVo>>> {
  return http.get('/admin/user-center/app', { params })
}

export function create(data: MemberAppVo): Promise<ResponseStruct<null>> {
  return http.post('/admin/user-center/app', data)
}

export function save(data: MemberAppVo): Promise<ResponseStruct<null>> {
  return http.put('/admin/user-center/app', data)
}

export function deleteByIds(ids: number[]): Promise<ResponseStruct<null>> {
  return http.delete('/admin/user-center/app', { data: ids })
}
