import { apiClient } from './client'

export interface PurchaseOrderItem {
  id?: number
  product_id?: number
  product_name: string
  ordered_qty: number
  received_qty?: number
  unit?: string
  unit_price?: number
  product?: { id: number; name: string; unit: string }
}

export interface PurchaseOrder {
  id: number
  order_no: string
  store_id: number
  supplier_id: number | null
  status: 1 | 2 | 3 | 4
  date: string
  notes: string | null
  items?: PurchaseOrderItem[]
}

export interface CreatePurchaseOrderRequest {
  date: string
  supplier_id?: number
  notes?: string
  items: {
    product_name: string
    ordered_qty: number
    unit_price?: number
  }[]
}

export const purchaseOrdersApi = {
  list: (token: string, params?: { date?: string; status?: number }) => {
    const qs = params ? '?' + new URLSearchParams(Object.entries(params).filter(([, v]) => v !== undefined).map(([k, v]) => [k, String(v)])).toString() : ''
    return apiClient.get<{ data: PurchaseOrder[] }>(`/purchase-orders${qs}`, token)
  },

  show: (id: number, token: string) =>
    apiClient.get<{ data: PurchaseOrder }>(`/purchase-orders/${id}`, token),

  create: (data: CreatePurchaseOrderRequest, token: string) =>
    apiClient.post<{ message: string; data: PurchaseOrder }>('/purchase-orders', data, token),
}
