import { apiClient } from './client'

export type DamageStatus = 1 | 2 | 3 | 4
export type RefundClaimStatus = 1 | 2 | 3 | 4 | 5

export interface DamageRecord {
  id: number
  store_id: number
  product_id: number
  purchase_order_item_id: number | null
  supplier_id: number | null
  qty: number
  unit_cost: number | null
  total_claimed: number | null
  reason: string
  image_paths: string[] | null
  status: DamageStatus
  occurred_at: string
  operator_id: number | null
  notes: string | null
  created_at: string
  updated_at: string
  product?: { id: number; name: string; unit: string }
  supplier?: { id: number; name: string } | null
  operator?: { id: number; name: string } | null
}

export interface DamageByProduct {
  product_id: number
  product_name: string
  unit: string
  total_qty: number
  total_claimed: number
  records_count: number
}

export interface DamageBySupplier {
  supplier_id: number
  supplier_name: string
  contact: string | null
  contact_phone: string | null
  total_qty: number
  total_claimed: number
  records_count: number
}

export interface DamageStats {
  from: string
  to: string
  total_qty: number
  total_claimed: number
  pending_claims_count: number
  by_product: DamageByProduct[]
  by_supplier: DamageBySupplier[]
}

export interface CreateDamageRequest {
  product_name: string
  qty: number
  reason: string
  notes?: string
  occurred_at?: string
  image_base64?: string[]
  purchase_order_item_id?: number
}

export interface SupplierRefundClaimItem {
  id: number
  claim_id: number
  damage_record_id: number
  product_id: number
  product_name: string
  qty: number
  unit_cost: number | null
  claimed_amount: number
  purchase_order_id: number | null
  damage_record?: Pick<DamageRecord, 'id' | 'reason' | 'occurred_at' | 'image_paths' | 'notes'>
}

export interface SupplierRefundClaim {
  id: number
  store_id: number
  supplier_id: number
  claim_no: string
  status: RefundClaimStatus
  total_items: number
  total_qty: number
  total_amount: number
  submitted_at: string | null
  resolved_at: string | null
  notes: string | null
  created_at: string
  supplier?: { id: number; name: string; contact_name: string | null; contact_phone: string | null }
  items?: SupplierRefundClaimItem[]
}

export interface CreateRefundClaimRequest {
  supplier_id: number
  damage_record_ids: number[]
  notes?: string
}

const API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api'

export const damageApi = {
  list: (token: string, params?: { date?: string; product_id?: number; status?: number; supplier_id?: number }) => {
    const qs = params
      ? '?' + new URLSearchParams(Object.entries(params).filter(([, v]) => v !== undefined).map(([k, v]) => [k, String(v)])).toString()
      : ''
    return apiClient.get<{ data: DamageRecord[]; total: number }>(`/damage${qs}`, token)
  },

  stats: (token: string, params?: { from?: string; to?: string }) => {
    const qs = params
      ? '?' + new URLSearchParams(Object.entries(params).filter(([, v]) => v !== undefined).map(([k, v]) => [k, String(v)])).toString()
      : ''
    return apiClient.get<{ data: DamageStats }>(`/damage/stats${qs}`, token)
  },

  create: (data: CreateDamageRequest, token: string) =>
    apiClient.post<{ message: string; data: DamageRecord }>('/damage', data, token),

  /** 追加图片 — 使用 native fetch 发送 multipart */
  uploadImages: async (id: number, files: File[], token: string): Promise<{ message: string; image_paths: string[] }> => {
    const form = new FormData()
    files.forEach(f => form.append('images[]', f))
    const res = await fetch(`${API_URL}/damage/${id}/images`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
      body: form,
    })
    if (!res.ok) {
      const err = await res.json().catch(() => ({ message: res.statusText }))
      throw new Error(err.message || 'Upload failed')
    }
    return res.json()
  },
}

export const refundClaimApi = {
  list: (token: string, params?: { status?: number; supplier_id?: number }) => {
    const qs = params
      ? '?' + new URLSearchParams(Object.entries(params).filter(([, v]) => v !== undefined).map(([k, v]) => [k, String(v)])).toString()
      : ''
    return apiClient.get<{ data: SupplierRefundClaim[]; total: number }>(`/refund-claims${qs}`, token)
  },

  show: (id: number, token: string) =>
    apiClient.get<{ data: SupplierRefundClaim }>(`/refund-claims/${id}`, token),

  create: (data: CreateRefundClaimRequest, token: string) =>
    apiClient.post<{ message: string; data: SupplierRefundClaim }>('/refund-claims', data, token),

  updateStatus: (id: number, status: RefundClaimStatus, token: string, notes?: string) =>
    apiClient.put<{ message: string; data: SupplierRefundClaim }>(`/refund-claims/${id}/status`, { status, notes }, token),
}
