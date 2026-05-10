import { apiClient } from './client'

export interface SalesOrderItem {
  id: number
  product_id: number
  qty: number
  unit_price: number
  discount_amount: number
  subtotal: number
  product?: { id: number; name: string; unit: string }
}

export interface SalesOrder {
  id: number
  order_no: string
  store_id: number
  cashier_id: number | null
  total_amount: number
  discount_amount: number
  paid_amount: number
  payment_method: 1 | 2 | 3 | 4 | 5
  status: 1 | 2 | 3
  sold_at: string
  notes: string | null
  cashier?: { id: number; name: string }
  items?: SalesOrderItem[]
}

export interface SourceBreakdown {
  qty: number
  amount: number
}

export interface SalesTodaySummary {
  date: string
  total_orders: number
  total_amount: number
  total_qty: number
  payment_breakdown: Record<number, number>
  sales_breakdown: {
    pos_qty: number
    pos_amount: number
    supplement_qty: number
    supplement_amount: number
    ai_qty: number
    ai_amount: number
  }
}

export interface SalesReportProduct {
  product_id: number
  product_name: string
  unit: string
  is_fresh: boolean
  sales_qty: number
  sales_amount: number
  avg_price: number | null
  transaction_count: number
  sales_breakdown: {
    pos: SourceBreakdown
    supplement: SourceBreakdown
    ai: SourceBreakdown
  }
}

export interface PaymentBreakdownItem {
  method: number
  label: string
  count: number
  amount: number
}

export interface SalesReport {
  date: string
  total_orders: number
  total_skus: number
  total_qty: number
  total_amount: number
  payment_breakdown: PaymentBreakdownItem[]
  source_breakdown: {
    pos: SourceBreakdown
    supplement: SourceBreakdown
    ai: SourceBreakdown
  }
  products: SalesReportProduct[]
}

export interface SalesSummaryProduct {
  product_id: number
  product_name: string
  unit: string
  is_fresh: boolean
  sales_qty: number
  sales_amount: number
  avg_selling_price: number | null
  transaction_count: number
  sales_breakdown: {
    pos: SourceBreakdown
    supplement: SourceBreakdown
    ai: SourceBreakdown
  }
}

export interface SalesSummary {
  date: string
  total_skus: number
  total_qty: number
  total_amount: number
  products: SalesSummaryProduct[]
}

export interface SupplementRequest {
  product_name: string
  type: 'sold_out' | 'remaining' | 'qty'
  remaining_qty?: number
  sold_qty?: number
  unit_price?: number
  occurred_at?: string
  notes?: string
}

export interface SupplementResult {
  product_id: number
  product_name: string
  unit: string
  type: string
  qty_before: number
  qty_after: number
  sold_qty: number
  sold_amount: number
  sales_order_no?: string
  sold_out_at: string | null
  skipped: boolean
  skip_reason: string | null
}

export const salesApi = {
  list: (token: string, params?: { date?: string; cashier_id?: number; status?: number }) => {
    const qs = params ? '?' + new URLSearchParams(Object.entries(params).filter(([, v]) => v !== undefined).map(([k, v]) => [k, String(v)])).toString() : ''
    return apiClient.get<{ data: SalesOrder[]; total: number }>(`/sales${qs}`, token)
  },

  show: (id: number, token: string) =>
    apiClient.get<{ data: SalesOrder }>(`/sales/${id}`, token),

  todaySummary: (token: string) =>
    apiClient.get<{ data: SalesTodaySummary }>('/sales/today', token),

  summary: (date: string, token: string) =>
    apiClient.get<{ data: SalesSummary }>(`/sales/summary?date=${date}`, token),

  report: (date: string, token: string) =>
    apiClient.get<{ data: SalesReport }>(`/sales/report?date=${date}`, token),

  supplement: (data: SupplementRequest, token: string) =>
    apiClient.post<{ message: string; data: SupplementResult }>('/sales/supplement', data, token),
}
