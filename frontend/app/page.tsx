'use client'

import { useEffect, useState } from 'react'
import { useRouter } from 'next/navigation'
import Link from 'next/link'
import {
  Sun, Cloud, CloudRain, Package, TrendingUp, AlertTriangle,
  FileText, Sparkles, ChevronRight, BarChart3, Zap,
} from 'lucide-react'
import { useAuth } from '@/lib/auth-context'
import { operationsApi, type WeatherData } from '@/lib/api/operations'
import { suggestionsApi } from '@/lib/api/suggestions'
import { inventoryApi, type InventoryItem } from '@/lib/api/inventory'

function todayStr() {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

function todayLabel() {
  const d = new Date()
  const weekdays = ['周日', '周一', '周二', '周三', '周四', '周五', '周六']
  return `${d.getFullYear()}年${d.getMonth() + 1}月${d.getDate()}日 ${weekdays[d.getDay()]}`
}

function WeatherIcon({ condition }: { condition: string }) {
  if (/晴/.test(condition)) return <Sun className="w-6 h-6" />
  if (/雨/.test(condition)) return <CloudRain className="w-6 h-6" />
  return <Cloud className="w-6 h-6" />
}

const QUICK_ACTIONS = [
  { title: 'AI助手', icon: Sparkles, href: '/manage', bg: 'bg-[#941100]' },
  { title: '库存查询', icon: Package, href: '/inventory', bg: 'bg-[#FF9300]' },
  { title: '销售报表', icon: BarChart3, href: '/sales-report', bg: 'bg-[#941100]' },
  { title: '损耗管理', icon: AlertTriangle, href: '/damage', bg: 'bg-[#FF9300]' },
  { title: '录入进货', icon: FileText, href: '/manage', bg: 'bg-[#941100]' },
  { title: '经营建议', icon: TrendingUp, href: '/manage', bg: 'bg-[#FF9300]' },
]

export default function HomePage() {
  const { token, isAuthenticated, loading } = useAuth()
  const router = useRouter()

  const [weather, setWeather] = useState<WeatherData | null>(null)
  const [lowStock, setLowStock] = useState<InventoryItem[]>([])
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const [suggestions, setSuggestions] = useState<any[]>([])

  useEffect(() => {
    if (!loading && !isAuthenticated) {
      router.push('/manage')
    }
  }, [loading, isAuthenticated, router])

  useEffect(() => {
    if (!token) return
    const load = async () => {
      try {
        const [wRes, invRes, sugRes] = await Promise.allSettled([
          operationsApi.weather(token),
          inventoryApi.getInventory(token),
          suggestionsApi.get(token),
        ])

        if (wRes.status === 'fulfilled') {
          const d = wRes.value
          // eslint-disable-next-line @typescript-eslint/no-explicit-any
          setWeather((d as any)?.data ?? (d as any) ?? null)
        }

        if (invRes.status === 'fulfilled') {
          const items: InventoryItem[] = invRes.value?.data ?? []
          setLowStock(items.filter(i => Number(i.current_qty) <= 5).slice(0, 5))
        }

        if (sugRes.status === 'fulfilled') {
          // eslint-disable-next-line @typescript-eslint/no-explicit-any
          const inner = (sugRes.value as any)?.data?.data ?? (sugRes.value as any)?.data ?? {}
          // eslint-disable-next-line @typescript-eslint/no-explicit-any
          const list: any[] = [...(inner.purchase_suggestions ?? []), ...(inner.promo_suggestions ?? [])]
          setSuggestions(list.slice(0, 3))
        }
      } catch { /* ignore */ }
    }
    load()
  }, [token])

  if (loading) {
    return (
      <div className="flex items-center justify-center h-screen bg-[#f5f4f0]">
        <div className="flex flex-col items-center gap-3">
          <div className="w-12 h-12 rounded-2xl bg-[#941100] flex items-center justify-center text-2xl shadow-lg">🍜</div>
          <p className="text-gray-400 text-sm">加载中...</p>
        </div>
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-gradient-to-b from-[#941100]/5 to-white pb-24">

      {/* ── 顶部英雄区 ── */}
      <div className="relative overflow-visible pb-2.5">
        <div className="relative h-52 rounded-b-3xl overflow-hidden">
          {/* 渐变背景 */}
          <div className="absolute inset-0 bg-gradient-to-br from-[#941100] via-[#c0392b] to-[#FF9300]" />
          {/* 装饰纹理 */}
          <div className="absolute inset-0 opacity-10 bg-[radial-gradient(circle_at_20%_50%,white_1px,transparent_1px),radial-gradient(circle_at_80%_20%,white_1px,transparent_1px)] bg-[length:40px_40px]" />

          {/* 内容 */}
          <div className="relative text-white p-6 h-full flex flex-col justify-between">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-xs opacity-80 font-medium tracking-wide uppercase">舌尖香港</p>
                <h2 className="text-lg font-semibold mt-0.5">{todayLabel()}</h2>
              </div>
              {weather && (
                <div className="flex items-center gap-2 bg-white/20 rounded-2xl px-3 py-2 backdrop-blur-sm">
                  <WeatherIcon condition={weather.condition ?? ''} />
                  <div className="text-right">
                    <p className="text-base font-bold leading-tight">
                      {weather.temperature_low != null && weather.temperature_high != null
                        ? `${weather.temperature_low}°~${weather.temperature_high}°`
                        : weather.temperature_high != null
                        ? `${weather.temperature_high}°C`
                        : '--'}
                    </p>
                    <p className="text-[10px] opacity-80">{weather.condition ?? ''}</p>
                  </div>
                </div>
              )}
            </div>

            <div>
              <p className="text-sm opacity-80">今日门店数据已就绪</p>
              <p className="text-xs opacity-60 mt-0.5">点击 AI助手 开始汇报</p>
            </div>
          </div>
        </div>

        {/* 天气/库存简报浮层 */}
        {weather?.suggestion && (
          <div className="px-4 -mt-4 relative z-10">
            <div className="bg-white/95 backdrop-blur-sm rounded-2xl px-4 py-3 shadow-lg border border-[#941100]/10 flex items-start gap-3">
              <Zap className="w-4 h-4 text-[#FF9300] shrink-0 mt-0.5" />
              <p className="text-xs text-gray-600 leading-relaxed">{weather.suggestion}</p>
            </div>
          </div>
        )}
      </div>

      {/* ── 快捷操作 ── */}
      <div className="px-4 mt-4">
        <div className="flex items-center gap-2 mb-3">
          <div className="w-1 h-4 rounded-full bg-[#941100]" />
          <h3 className="text-sm font-semibold text-gray-700">快捷操作</h3>
        </div>
        <div className="grid grid-cols-3 gap-3">
          {QUICK_ACTIONS.map((action) => {
            const Icon = action.icon
            return (
              <Link
                key={action.title}
                href={action.href}
                className={`${action.bg} text-white p-4 rounded-2xl shadow-md flex flex-col items-center gap-2 active:scale-95 transition-transform hover:shadow-lg`}
              >
                <Icon className="w-6 h-6" strokeWidth={2} />
                <span className="text-xs text-center leading-tight font-medium">{action.title}</span>
              </Link>
            )
          })}
        </div>
      </div>

      {/* ── 低库存预警 ── */}
      {lowStock.length > 0 && (
        <div className="px-4 mt-5">
          <div className="flex items-center justify-between mb-3">
            <div className="flex items-center gap-2">
              <div className="w-1 h-4 rounded-full bg-[#FF9300]" />
              <h3 className="text-sm font-semibold text-gray-700">库存预警</h3>
            </div>
            <Link href="/inventory" className="text-xs text-[#941100] flex items-center gap-0.5">
              查看全部 <ChevronRight className="w-3 h-3" />
            </Link>
          </div>
          <div className="space-y-2">
            {lowStock.map((item) => {
              const qty = Number(item.current_qty)
              const isEmpty = qty === 0
              return (
                <div key={item.id} className="bg-white rounded-2xl px-4 py-3 shadow-sm border border-gray-100 flex items-center justify-between">
                  <div className="flex items-center gap-2.5">
                    <div className={`w-8 h-8 rounded-xl flex items-center justify-center ${isEmpty ? 'bg-red-100' : 'bg-amber-50'}`}>
                      <Package className={`w-4 h-4 ${isEmpty ? 'text-red-500' : 'text-amber-500'}`} />
                    </div>
                    <div>
                      <p className="text-sm font-medium text-gray-800">{item.product_name ?? '-'}</p>
                      <p className="text-xs text-gray-400">{item.unit ?? ''}</p>
                    </div>
                  </div>
                  <span className={`text-sm font-bold tabular-nums px-2.5 py-1 rounded-full ${isEmpty ? 'bg-red-50 text-red-500' : 'bg-amber-50 text-amber-500'}`}>
                    {isEmpty ? '售罄' : `剩 ${qty}`}
                  </span>
                </div>
              )
            })}
          </div>
        </div>
      )}

      {/* ── AI建议 ── */}
      <div className="px-4 mt-5">
        <div className="flex items-center justify-between mb-3">
          <div className="flex items-center gap-2">
            <div className="w-1 h-4 rounded-full bg-[#941100]" />
            <h3 className="text-sm font-semibold text-gray-700">AI智能建议</h3>
          </div>
          <Link href="/manage" className="text-xs text-[#941100] flex items-center gap-0.5">
            去AI助手 <ChevronRight className="w-3 h-3" />
          </Link>
        </div>

        {suggestions.length === 0 ? (
          <div className="bg-white rounded-2xl p-6 text-center shadow-sm border border-gray-100">
            <Sparkles className="w-10 h-10 text-gray-200 mx-auto mb-2" />
            <p className="text-sm text-gray-400">暂无新建议，库存销售状态良好</p>
          </div>
        ) : (
          <div className="space-y-3">
            {suggestions.map((s, i) => (
              <div key={i} className="bg-white rounded-2xl px-4 py-3 shadow-sm border border-gray-100">
                <div className="flex items-start gap-3">
                  <div className="w-8 h-8 rounded-xl bg-[#941100]/10 flex items-center justify-center shrink-0 mt-0.5">
                    <Sparkles className="w-4 h-4 text-[#941100]" />
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 mb-1">
                      <p className="text-sm font-semibold text-gray-800">{s.product_name}</p>
                      {s.urgency === 'urgent' && (
                        <span className="text-[10px] px-1.5 py-0.5 rounded-full bg-red-100 text-red-600 font-bold">紧急</span>
                      )}
                      {s.urgency === 'high' && (
                        <span className="text-[10px] px-1.5 py-0.5 rounded-full bg-orange-100 text-orange-500 font-bold">重要</span>
                      )}
                    </div>
                    <p className="text-xs text-gray-500 leading-relaxed">{s.reason}</p>
                    <p className="text-xs text-[#941100] font-medium mt-1">{s.action}</p>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

    </div>
  )
}
