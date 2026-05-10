import { apiClient } from './client'

export interface DailyOverviewProduct {
  product_id: number
  product_name: string
  unit: string
  opening_qty: number
  received_qty: number
  sold_qty: number
  damage_qty: number
  closing_qty: number
  sold_out_at: string | null
  sales_breakdown?: {
    pos: { qty: number; amount: number }
    supplement: { qty: number; amount: number }
    ai: { qty: number; amount: number }
  }
}

export interface DailyOverview {
  date: string
  products: DailyOverviewProduct[]
}

export interface DailyLog {
  id: number
  source: 1 | 2 | 3
  intent: string
  content: string
  product_id: number | null
  qty_change: number | null
  operator_id: number | null
  created_at: string
}

export interface WeatherData {
  city: string
  date: string
  condition: string
  temperature_high: number | null
  temperature_low: number | null
  humidity: number | null
  rain_probability: number | null
  wind_speed: string | null
  feels_like: number | null
  suggestion: string | null
}

export const operationsApi = {
  dailyOverview: (date: string, token: string) =>
    apiClient.get<{ data: DailyOverview }>(`/inventory/daily-overview?date=${date}`, token),

  dailyLogs: (token: string) =>
    apiClient.get<{ data: DailyLog[] }>('/daily-logs', token),

  weather: (token: string) =>
    apiClient.get<{ data: WeatherData } | WeatherData>('/weather', token),
}
