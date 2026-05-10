import { apiClient } from './client'

export type SuggestionUrgency = 'urgent' | 'high' | 'medium' | 'low'

export interface Suggestion {
  product_id: number
  product_name: string
  unit: string
  is_fresh: boolean
  urgency: SuggestionUrgency
  reason: string
  action: string
  current_qty: number
  daily_sales_rate: number
  days_of_stock: number | null
  suggested_qty?: number
  days_since_last_sale?: number | null
}

export interface SuggestionsResponse {
  generated_at: string
  purchase_suggestions: Suggestion[]
  promo_suggestions: Suggestion[]
}

export const suggestionsApi = {
  get: (token: string) =>
    apiClient.get<{ data: SuggestionsResponse }>('/suggestions', token),
}
