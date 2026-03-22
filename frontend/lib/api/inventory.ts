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

export const inventoryApi = {
  getInventory: (token: string) =>
    apiClient.get<{ data: InventoryItem[] }>('/inventory', token),

  getTransactions: (token: string) =>
    apiClient.get<{ data: InventoryTransaction[] }>('/inventory/transactions', token),
}
