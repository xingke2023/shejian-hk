import { apiClient } from './client'

export interface InventoryItem {
  id: number
  product_id: number
  product_name: string
  unit: string
  is_fresh: boolean
  current_qty: number
  available_qty: number
  last_in_at: string | null
  last_out_at: string | null
  last_sold_at: string | null
  updated_at: string | null
}

export interface InventoryTransaction {
  id: number
  product_name: string
  unit: string
  transaction_type: number
  type_label: string
  qty_change: number
  qty_before: number
  qty_after: number
  notes: string | null
  created_at: string
}

export interface AdjustRequest {
  product_id: number
  mode: 'sold_out' | 'adjust' | 'damage'
  qty?: number
  notes?: string
}

export const inventoryApi = {
  list: (token: string) =>
    apiClient.get<{ data: InventoryItem[] }>('/inventory', token),

  transactions: (token: string) =>
    apiClient.get<{ data: InventoryTransaction[] }>('/inventory/transactions', token),

  adjust: (data: AdjustRequest, token: string) =>
    apiClient.post<{ message: string }>('/inventory/adjust', data, token),

  /** @deprecated use inventoryApi.list */
  getInventory: (token: string) =>
    apiClient.get<{ data: InventoryItem[] }>('/inventory', token),

  /** @deprecated use inventoryApi.transactions */
  getTransactions: (token: string) =>
    apiClient.get<{ data: InventoryTransaction[] }>('/inventory/transactions', token),
}
