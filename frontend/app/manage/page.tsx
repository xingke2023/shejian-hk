'use client'

import { useEffect, useRef, useState } from 'react'
import { useRouter } from 'next/navigation'
import { useAuth } from '@/lib/auth-context'
import { assistantApi, type AiOperation } from '@/lib/api/assistant'
import { inventoryApi } from '@/lib/api/inventory'
import { salesApi } from '@/lib/api/sales'
import { suggestionsApi } from '@/lib/api/suggestions'
import { purchaseOrdersApi } from '@/lib/api/purchase-orders'
import { operationsApi } from '@/lib/api/operations'
import { damageApi } from '@/lib/api/damage'

// ─── Types ──────────────────────────────────────────────────────────────────

type QuickAction = 'inventory' | 'sales_today' | 'daily_overview' | 'purchase_orders' | 'weather' | 'daily_logs' | 'sales_report' | 'suggestions' | 'damage_stats'
type WriteAction = 'purchase_entry' | 'sales_supplement' | 'damage_entry'

interface ChatMessage {
  id: number
  role: 'user' | 'system'
  text: string
  cardType?: QuickAction
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  cardData?: any
  operations?: AiOperation[]
  imagePreview?: string
  timestamp: Date
}

// ─── Helpers ────────────────────────────────────────────────────────────────

function todayStr() {
  return new Date().toISOString().slice(0, 10)
}



// eslint-disable-next-line @typescript-eslint/no-explicit-any
// ─── Card Renderers ─────────────────────────────────────────────────────────

// eslint-disable-next-line @typescript-eslint/no-explicit-any
function InventoryCard({ data }: { data: any }) {
  const items: any[] = data?.data ?? (Array.isArray(data) ? data : []) // eslint-disable-line @typescript-eslint/no-explicit-any
  if (!items.length) return <p className="text-gray-400 text-xs py-2">暂无库存数据</p>
  return (
    <div className="overflow-x-auto -mx-1">
      <table className="text-xs w-full border-collapse min-w-[260px]">
        <thead>
          <tr>
            <th className="text-left px-2 py-2 font-semibold text-gray-400 uppercase tracking-wide">商品</th>
            <th className="text-right px-2 py-2 font-semibold text-gray-400 uppercase tracking-wide">库存</th>
            <th className="text-right px-2 py-2 font-semibold text-gray-400 uppercase tracking-wide">单位</th>
            <th className="text-right px-2 py-2 font-semibold text-gray-400 uppercase tracking-wide hidden sm:table-cell">最后销售</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-100">
          {items.map((item: any, i: number) => { // eslint-disable-line @typescript-eslint/no-explicit-any
            const qty = Number(item.current_qty ?? 0)
            const qtyColor = qty === 0 ? 'text-red-500' : qty < 5 ? 'text-amber-500' : 'text-emerald-600'
            const qtyBg = qty === 0 ? 'bg-red-50' : qty < 5 ? 'bg-amber-50' : 'bg-emerald-50'
            return (
              <tr key={i} className="hover:bg-gray-50/60 transition-colors">
                <td className="px-2 py-2.5 font-medium text-gray-700">{item.product?.name ?? item.product_name ?? '-'}</td>
                <td className={`px-2 py-2.5 text-right`}>
                  <span className={`inline-block px-2 py-0.5 rounded-full font-bold tabular-nums text-xs ${qtyColor} ${qtyBg}`}>{qty}</span>
                </td>
                <td className="px-2 py-2.5 text-right text-gray-400">{item.product?.unit ?? item.unit ?? ''}</td>
                <td className="px-2 py-2.5 text-right text-gray-300 hidden sm:table-cell text-xs">
                  {item.last_sold_at ? new Date(item.last_sold_at).toLocaleDateString('zh-CN', { month: 'numeric', day: 'numeric' }) : '—'}
                </td>
              </tr>
            )
          })}
        </tbody>
      </table>
      <div className="flex items-center gap-3 mt-2 px-1">
        <span className="flex items-center gap-1 text-xs text-emerald-600"><span className="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block" />充足</span>
        <span className="flex items-center gap-1 text-xs text-amber-500"><span className="w-1.5 h-1.5 rounded-full bg-amber-400 inline-block" />偏少</span>
        <span className="flex items-center gap-1 text-xs text-red-500"><span className="w-1.5 h-1.5 rounded-full bg-red-400 inline-block" />售罄</span>
      </div>
    </div>
  )
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
function SalesTodayCard({ data }: { data: any }) {
  const stats = data?.data ?? data ?? {}
  const paymentLabels: Record<number, string> = { 1: '现金', 2: '微信', 3: '支付宝', 4: '银行卡', 5: '混合' }
  const breakdown = stats.sales_breakdown ?? {}
  const totalAmount = Number(stats.total_amount ?? 0)
  const totalQty = Number(stats.total_qty ?? 0)
  return (
    <div className="space-y-3">
      <div className="grid grid-cols-3 gap-2">
        <div className="bg-orange-50 rounded-2xl p-3 text-center border border-orange-100">
          <div className="text-xl font-bold text-orange-600 tabular-nums">
            {totalAmount > 0 ? `¥${totalAmount.toFixed(2)}` : '—'}
          </div>
          <div className="text-xs text-orange-400 mt-0.5 font-medium">今日营业额</div>
        </div>
        <div className="bg-stone-50 rounded-2xl p-3 text-center border border-stone-100">
          <div className="text-xl font-bold text-stone-700 tabular-nums">{totalQty > 0 ? totalQty : (stats.total_orders ?? 0)}</div>
          <div className="text-xs text-stone-400 mt-0.5 font-medium">{totalQty > 0 ? '出库总量(斤)' : '今日订单数'}</div>
        </div>
        <div className="bg-stone-50 rounded-2xl p-3 text-center border border-stone-100">
          <div className="text-xl font-bold text-stone-700 tabular-nums">{stats.total_orders ?? 0}</div>
          <div className="text-xs text-stone-400 mt-0.5 font-medium">今日单数</div>
        </div>
      </div>
      {totalAmount === 0 && totalQty > 0 && (
        <p className="text-xs text-stone-400 text-center">AI/补录单无单价，营业额仅统计收银台录入</p>
      )}

      {stats.payment_breakdown && Object.keys(stats.payment_breakdown).length > 0 && (
        <div className="bg-gray-50 rounded-xl p-3 space-y-1.5">
          <div className="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">支付方式</div>
          {Object.entries(stats.payment_breakdown).map(([k, v]) => (
            <div key={k} className="flex justify-between text-sm text-gray-600">
              <span>{paymentLabels[Number(k)] ?? k}</span>
              <span className="tabular-nums font-medium">¥{Number(v).toFixed(2)}</span>
            </div>
          ))}
        </div>
      )}

      {Object.keys(breakdown).length > 0 && (
        <div className="bg-gray-50 rounded-xl p-3 space-y-1.5">
          <div className="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">录入来源</div>
          {breakdown.pos_amount !== undefined && (
            <div className="flex justify-between text-sm text-gray-600">
              <span className="flex items-center gap-1.5"><span className="text-base">🖥️</span>收银台</span>
              <span className="tabular-nums font-medium">¥{Number(breakdown.pos_amount ?? 0).toFixed(2)}</span>
            </div>
          )}
          {breakdown.ai_amount !== undefined && (
            <div className="flex justify-between text-sm text-gray-600">
              <span className="flex items-center gap-1.5"><span className="text-base">🤖</span>AI录入</span>
              <span className="tabular-nums font-medium">¥{Number(breakdown.ai_amount ?? 0).toFixed(2)}</span>
            </div>
          )}
          {breakdown.supplement_amount !== undefined && (
            <div className="flex justify-between text-sm text-gray-600">
              <span className="flex items-center gap-1.5"><span className="text-base">✏️</span>手动补录</span>
              <span className="tabular-nums font-medium">¥{Number(breakdown.supplement_amount ?? 0).toFixed(2)}</span>
            </div>
          )}
        </div>
      )}
    </div>
  )
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
function DailyOverviewCard({ data }: { data: any }) {
  const items: any[] = data?.data?.products ?? (Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []) // eslint-disable-line @typescript-eslint/no-explicit-any
  if (!items.length) return <p className="text-gray-400 text-xs py-2">今日暂无数据</p>
  return (
    <div className="overflow-x-auto -mx-1">
      <table className="text-xs w-full border-collapse min-w-[300px]">
        <thead>
          <tr>
            <th className="text-left px-2 py-2 font-semibold text-gray-400 uppercase tracking-wide">商品</th>
            <th className="text-right px-2 py-2 font-semibold text-gray-400">开盘</th>
            <th className="text-right px-2 py-2 font-semibold text-blue-400">进货</th>
            <th className="text-right px-2 py-2 font-semibold text-orange-400">已售</th>
            <th className="text-right px-2 py-2 font-semibold text-gray-600">结余</th>
            <th className="text-right px-2 py-2 font-semibold text-gray-400">状态</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-100">
          {items.map((item: any, i: number) => ( // eslint-disable-line @typescript-eslint/no-explicit-any
            <tr key={i} className="hover:bg-gray-50/60 transition-colors">
              <td className="px-2 py-2.5 font-medium text-gray-700">{item.product_name ?? item.product?.name ?? '-'}</td>
              <td className="px-2 py-2.5 text-right text-gray-400 tabular-nums">{item.opening_qty ?? 0}</td>
              <td className="px-2 py-2.5 text-right text-blue-500 tabular-nums font-medium">+{item.received_qty ?? 0}</td>
              <td className="px-2 py-2.5 text-right text-orange-500 tabular-nums font-medium">-{item.sold_qty ?? 0}</td>
              <td className="px-2 py-2.5 text-right font-bold tabular-nums">{item.closing_qty ?? 0}</td>
              <td className="px-2 py-2.5 text-right">
                {item.sold_out_at
                  ? <span className="text-xs px-1.5 py-0.5 bg-red-50 text-red-500 rounded-full font-medium">售罄</span>
                  : <span className="text-xs px-1.5 py-0.5 bg-emerald-50 text-emerald-500 rounded-full font-medium">在售</span>}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
function PurchaseOrdersCard({ data }: { data: any }) {
  const orders: any[] = data?.data ?? (Array.isArray(data) ? data : []) // eslint-disable-line @typescript-eslint/no-explicit-any
  const statusMap: Record<number, { label: string; cls: string }> = {
    1: { label: '待处理', cls: 'bg-amber-50 text-amber-600 border-amber-200' },
    2: { label: '已确认', cls: 'bg-blue-50 text-blue-600 border-blue-200' },
    3: { label: '已收货', cls: 'bg-emerald-50 text-emerald-600 border-emerald-200' },
    4: { label: '已取消', cls: 'bg-gray-100 text-gray-400 border-gray-200' },
  }
  if (!orders.length) return <p className="text-gray-400 text-xs py-2">今日暂无进货单</p>
  return (
    <div className="space-y-2">
      {orders.map((order: any, i: number) => { // eslint-disable-line @typescript-eslint/no-explicit-any
        const st = statusMap[order.status] ?? { label: String(order.status), cls: 'bg-gray-100 text-gray-400 border-gray-200' }
        return (
          <div key={i} className="border border-gray-100 rounded-2xl p-3 space-y-2 bg-gray-50/50">
            <div className="flex justify-between items-center">
              <span className="font-semibold text-sm text-gray-700">进货单 #{order.id}</span>
              <span className={`text-xs px-2 py-0.5 rounded-full font-medium border ${st.cls}`}>{st.label}</span>
            </div>
            {order.items?.length > 0 && (
              <div className="space-y-1">
                {order.items.map((item: any, j: number) => ( // eslint-disable-line @typescript-eslint/no-explicit-any
                  <div key={j} className="flex justify-between text-xs text-gray-600">
                    <span className="font-medium">{item.product?.name ?? item.product_name ?? '-'}</span>
                    <span className="tabular-nums text-gray-400">
                      {item.ordered_qty}{item.unit ?? ''}{item.unit_price ? ` × ¥${item.unit_price}` : ''}
                    </span>
                  </div>
                ))}
              </div>
            )}
            <div className="text-xs text-gray-300 pt-0.5">共 {order.items?.length ?? 0} 种商品 · {order.date}</div>
          </div>
        )
      })}
    </div>
  )
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
function WeatherCard({ data }: { data: any }) {
  const meta = data ?? {}
  const w = meta.data ?? meta ?? {}
  const city = meta.city ?? w.city ?? '香港'
  const date = meta.date ?? w.date ?? todayStr()
  const tempHigh = w.temperature_high ?? w.temperature ?? null
  const tempLow = w.temperature_low ?? null
  const tempDisplay = tempHigh != null
    ? (tempLow != null ? `${tempLow}°~${tempHigh}°` : `${tempHigh}°`)
    : '--'
  function emoji(c = '') {
    if (/晴/.test(c)) return '☀️'
    if (/多云/.test(c)) return '⛅'
    if (/阴/.test(c)) return '🌥️'
    if (/雨/.test(c)) return '🌧️'
    if (/雪/.test(c)) return '❄️'
    if (/雾/.test(c)) return '🌫️'
    return '🌤️'
  }
  const condition = w.condition ?? w.weather ?? w.description ?? '未知'
  return (
    <div className="bg-gradient-to-br from-sky-400 to-blue-500 rounded-2xl p-4 text-white">
      <div className="flex items-center justify-between">
        <div>
          <div className="text-4xl font-bold tabular-nums tracking-tight">
            {tempDisplay}
          </div>
          <div className="text-sky-100 mt-0.5 font-medium">{condition}</div>
          <div className="text-sky-200 text-xs mt-1">
            {city} · {date}
          </div>
        </div>
        <div className="text-6xl drop-shadow">{emoji(condition)}</div>
      </div>
      <div className="mt-3 grid grid-cols-2 gap-2">
        {w.humidity != null && (
          <div className="bg-white/20 rounded-xl px-3 py-2 text-xs">
            <div className="text-sky-100">湿度</div>
            <div className="font-semibold mt-0.5">{w.humidity}%</div>
          </div>
        )}
        {w.rain_probability != null && (
          <div className="bg-white/20 rounded-xl px-3 py-2 text-xs">
            <div className="text-sky-100">降雨概率</div>
            <div className="font-semibold mt-0.5">{w.rain_probability}%</div>
          </div>
        )}
        {w.wind_speed != null && (
          <div className="bg-white/20 rounded-xl px-3 py-2 text-xs">
            <div className="text-sky-100">风速</div>
            <div className="font-semibold mt-0.5">{w.wind_speed}</div>
          </div>
        )}
        {w.feels_like != null && (
          <div className="bg-white/20 rounded-xl px-3 py-2 text-xs">
            <div className="text-sky-100">体感温度</div>
            <div className="font-semibold mt-0.5">{w.feels_like}°C</div>
          </div>
        )}
      </div>
      {w.suggestion && (
        <div className="mt-3 text-xs text-sky-100 bg-white/20 rounded-xl px-3 py-2.5">
          💡 {w.suggestion}
        </div>
      )}
    </div>
  )
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
function DailyLogsCard({ data }: { data: any }) {
  const logs: any[] = data?.data ?? (Array.isArray(data) ? data : []) // eslint-disable-line @typescript-eslint/no-explicit-any
  const sourceMap: Record<number, { label: string; cls: string }> = {
    1: { label: 'AI', cls: 'bg-violet-100 text-violet-600' },
    2: { label: '手动', cls: 'bg-emerald-100 text-emerald-600' },
    3: { label: '后台', cls: 'bg-gray-100 text-gray-500' },
  }
  if (!logs.length) return <p className="text-gray-400 text-xs py-2">今日暂无操作记录</p>
  return (
    <div className="space-y-2 max-h-52 overflow-y-auto pr-1">
      {logs.map((log: any, i: number) => { // eslint-disable-line @typescript-eslint/no-explicit-any
        const src = sourceMap[log.source] ?? { label: '?', cls: 'bg-gray-100 text-gray-500' }
        const timeStr = log.created_at
          ? new Date(log.created_at).toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit' })
          : ''
        return (
          <div key={i} className="flex gap-2 items-start text-xs bg-gray-50 rounded-xl px-3 py-2">
            <span className={`px-1.5 py-0.5 rounded-lg text-xs font-semibold shrink-0 mt-0.5 ${src.cls}`}>{src.label}</span>
            <span className="flex-1 text-gray-600 leading-relaxed">{log.content ?? log.message ?? log.intent ?? '-'}</span>
            {timeStr && <span className="text-gray-300 shrink-0 tabular-nums">{timeStr}</span>}
          </div>
        )
      })}
    </div>
  )
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
function SuggestionsCard({ data }: { data: any }) {
  const [tab, setTab] = useState<'purchase' | 'promo'>('purchase')

  const inner = data?.data ?? data ?? {}
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const purchaseList: any[] = inner.purchase_suggestions ?? []
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const promoList: any[] = inner.promo_suggestions ?? []

  const urgencyBadge = (u: string) => {
    switch (u) {
      case 'urgent': return <span className="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-red-100 text-red-600">紧急</span>
      case 'high':   return <span className="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-orange-100 text-orange-500">重要</span>
      case 'medium': return <span className="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-500">建议</span>
      default:       return <span className="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-400">参考</span>
    }
  }

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const renderList = (list: any[], emptyText: string) => {
    if (!list.length) return <p className="text-gray-300 text-xs text-center py-6">{emptyText}</p>
    return (
      <div className="space-y-2">
        {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
        {list.map((item: any, i: number) => (
          <div key={i} className="bg-gray-50 rounded-2xl px-3 py-2.5 space-y-1">
            <div className="flex items-center gap-2">
              {urgencyBadge(item.urgency)}
              <span className="font-semibold text-sm text-gray-800">{item.product_name}</span>
              {item.is_fresh && <span className="text-[9px] bg-green-100 text-green-600 px-1 rounded">鲜</span>}
              <span className="ml-auto text-xs text-gray-400 tabular-nums shrink-0">
                余{(item.current_qty ?? 0).toFixed(0)}{item.unit}
              </span>
            </div>
            <p className="text-xs text-gray-500 leading-relaxed">{item.reason}</p>
            <p className="text-xs text-blue-600 font-medium">{item.action}</p>
          </div>
        ))}
      </div>
    )
  }

  return (
    <div className="space-y-3">
      {/* Tab */}
      <div className="flex rounded-xl overflow-hidden border border-gray-100">
        <button
          onClick={() => setTab('purchase')}
          className={`flex-1 py-2 text-xs font-semibold transition-colors ${tab === 'purchase' ? 'bg-blue-500 text-white' : 'bg-white text-gray-400 hover:bg-gray-50'}`}
        >
          🚛 进货建议 {purchaseList.length > 0 && <span className={`ml-1 px-1.5 py-0.5 rounded-full text-[10px] ${tab === 'purchase' ? 'bg-blue-400 text-white' : 'bg-gray-100 text-gray-500'}`}>{purchaseList.length}</span>}
        </button>
        <button
          onClick={() => setTab('promo')}
          className={`flex-1 py-2 text-xs font-semibold transition-colors ${tab === 'promo' ? 'bg-orange-500 text-white' : 'bg-white text-gray-400 hover:bg-gray-50'}`}
        >
          🎯 促销建议 {promoList.length > 0 && <span className={`ml-1 px-1.5 py-0.5 rounded-full text-[10px] ${tab === 'promo' ? 'bg-orange-400 text-white' : 'bg-gray-100 text-gray-500'}`}>{promoList.length}</span>}
        </button>
      </div>

      {tab === 'purchase'
        ? renderList(purchaseList, '暂无进货建议，库存充足')
        : renderList(promoList, '暂无促销建议，商品销售正常')}

      <p className="text-[10px] text-gray-300 text-center">基于近7天销售数据自动计算</p>
    </div>
  )
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
function SalesReportCard({ data: initialData }: { data: any }) {
  const { token } = useAuth()
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const [report, setReport] = useState<any>(initialData?.data ?? initialData ?? null)
  const [date, setDate] = useState((initialData?.data?.date ?? initialData?.date) || todayStr())
  const [loading, setLoading] = useState(false)
  const [expandedId, setExpandedId] = useState<number | null>(null)

  const fetchReport = async (d: string) => {
    if (!token) return
    setLoading(true)
    try {
      const res = await salesApi.report(d, token)
      setReport(res?.data ?? res)
    } finally {
      setLoading(false)
    }
  }

  const handleDateChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const d = e.target.value
    setDate(d)
    fetchReport(d)
  }

  const fmt = (n: number) => n.toFixed(2)
  const pct = (part: number, total: number) => total === 0 ? '0%' : Math.round((part / total) * 100) + '%'

  if (!report) return <p className="text-gray-400 text-xs py-2">暂无销售数据</p>

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const products: any[] = report.products ?? []
  const total = Number(report.total_amount ?? 0)
  const src = report.source_breakdown ?? {}

  return (
    <div className="space-y-3">
      {/* Date picker */}
      <div className="flex items-center gap-2">
        <span className="text-xs text-gray-400 shrink-0">日期</span>
        <input
          type="date"
          value={date}
          max={todayStr()}
          onChange={handleDateChange}
          className="flex-1 text-xs border border-gray-200 rounded-lg px-2 py-1 focus:outline-none focus:ring-2 focus:ring-orange-300"
        />
        {loading && <span className="text-xs text-gray-300">加载中…</span>}
      </div>

      {/* Summary row */}
      <div className="grid grid-cols-3 gap-2">
        <div className="bg-orange-50 rounded-2xl p-2.5 text-center border border-orange-100">
          <div className="text-base font-bold text-orange-600 tabular-nums">¥{fmt(total)}</div>
          <div className="text-[10px] text-orange-400 mt-0.5">总销售额</div>
        </div>
        <div className="bg-stone-50 rounded-2xl p-2.5 text-center border border-stone-100">
          <div className="text-base font-bold text-stone-700 tabular-nums">{report.total_orders ?? 0}</div>
          <div className="text-[10px] text-stone-400 mt-0.5">销售笔数</div>
        </div>
        <div className="bg-stone-50 rounded-2xl p-2.5 text-center border border-stone-100">
          <div className="text-base font-bold text-stone-700 tabular-nums">{report.total_skus ?? 0}</div>
          <div className="text-[10px] text-stone-400 mt-0.5">品种数</div>
        </div>
      </div>

      {/* Source breakdown bar */}
      {total > 0 && (
        <div>
          <div className="flex rounded-lg overflow-hidden h-2 mb-2">
            {Number(src.pos?.amount) > 0 && <div className="bg-blue-400" style={{ width: pct(src.pos.amount, total) }} />}
            {Number(src.supplement?.amount) > 0 && <div className="bg-amber-400" style={{ width: pct(src.supplement.amount, total) }} />}
            {Number(src.ai?.amount) > 0 && <div className="bg-emerald-400" style={{ width: pct(src.ai.amount, total) }} />}
          </div>
          <div className="flex gap-3 text-[10px]">
            {([['pos', 'POS', 'bg-blue-400', 'text-blue-500'], ['supplement', '补录', 'bg-amber-400', 'text-amber-500'], ['ai', 'AI', 'bg-emerald-400', 'text-emerald-500']] as const).map(([key, label, bg, color]) => (
              <div key={key} className="flex items-center gap-1">
                <span className={`w-1.5 h-1.5 rounded-full inline-block ${bg}`} />
                <span className="text-gray-400">{label}</span>
                <span className={`font-semibold tabular-nums ${color}`}>¥{fmt(Number(src[key]?.amount ?? 0))}</span>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Per-product table */}
      {products.length === 0 ? (
        <p className="text-gray-300 text-xs text-center py-4">当日暂无销售记录</p>
      ) : (
        <div className="overflow-x-auto -mx-1">
          <table className="text-xs w-full border-collapse min-w-[280px]">
            <thead>
              <tr>
                <th className="text-left px-2 py-2 font-semibold text-gray-400">商品</th>
                <th className="text-right px-2 py-2 font-semibold text-gray-400">销量</th>
                <th className="text-right px-2 py-2 font-semibold text-gray-400">金额</th>
                <th className="text-right px-2 py-2 font-semibold text-gray-400">均价</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-50">
              {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
              {products.map((row: any, i: number) => {
                const isExpanded = expandedId === row.product_id
                return (
                  <>
                    <tr
                      key={row.product_id}
                      onClick={() => setExpandedId(isExpanded ? null : row.product_id)}
                      className={`cursor-pointer transition-colors ${isExpanded ? 'bg-orange-50/50' : 'hover:bg-gray-50/60'}`}
                    >
                      <td className="px-2 py-2">
                        <span className="font-medium text-gray-700">{row.product_name}</span>
                        {i === 0 && <span className="ml-1 text-[9px] bg-orange-100 text-orange-500 px-1 rounded">TOP</span>}
                      </td>
                      <td className="px-2 py-2 text-right tabular-nums text-gray-600">{row.sales_qty}<span className="text-gray-300 ml-0.5">{row.unit}</span></td>
                      <td className="px-2 py-2 text-right tabular-nums font-semibold text-gray-800">¥{fmt(row.sales_amount)}</td>
                      <td className="px-2 py-2 text-right tabular-nums text-gray-400">{row.avg_price != null ? `¥${fmt(row.avg_price)}` : '—'}</td>
                    </tr>
                    {isExpanded && (
                      <tr key={`${row.product_id}-d`} className="bg-orange-50/30">
                        <td colSpan={4} className="px-3 py-2">
                          <div className="flex flex-wrap gap-3 text-[10px]">
                            {([['pos', 'POS', 'bg-blue-400'], ['supplement', '补录', 'bg-amber-400'], ['ai', 'AI', 'bg-emerald-400']] as const).map(([key, label, bg]) => (
                              <div key={key} className="flex items-center gap-1">
                                <span className={`w-1.5 h-1.5 rounded-full inline-block ${bg}`} />
                                <span className="text-gray-400">{label}</span>
                                <span className="font-medium text-gray-600 tabular-nums">
                                  {row.sales_breakdown[key].qty}{row.unit} / ¥{fmt(row.sales_breakdown[key].amount)}
                                </span>
                              </div>
                            ))}
                          </div>
                        </td>
                      </tr>
                    )}
                  </>
                )
              })}
            </tbody>
          </table>
          <p className="text-[10px] text-gray-300 text-center mt-1.5">点击行展开来源明细</p>
        </div>
      )}
    </div>
  )
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
function DamageStatsCard({ data }: { data: any }) {
  const inner = data?.data ?? data ?? {}
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const byProduct: any[] = inner.by_product ?? []
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const bySupplier: any[] = inner.by_supplier ?? []

  return (
    <div className="space-y-3">
      {/* Summary */}
      <div className="grid grid-cols-2 gap-2">
        <div className="bg-red-50 rounded-2xl p-3 text-center border border-red-100">
          <div className="text-xl font-bold text-red-600 tabular-nums">{Number(inner.total_qty ?? 0).toFixed(1)}</div>
          <div className="text-xs text-red-400 mt-0.5 font-medium">损耗总量</div>
        </div>
        <div className="bg-stone-50 rounded-2xl p-3 text-center border border-stone-100">
          <div className="text-xl font-bold text-stone-700 tabular-nums">¥{Number(inner.total_claimed ?? 0).toFixed(2)}</div>
          <div className="text-xs text-stone-400 mt-0.5 font-medium">可索赔金额</div>
        </div>
      </div>
      {inner.pending_claims_count > 0 && (
        <div className="bg-amber-50 rounded-xl px-3 py-2 flex items-center justify-between">
          <span className="text-xs text-amber-600 font-semibold">{inner.pending_claims_count} 条损耗待提交退款申请</span>
          <a href="/damage" className="text-xs text-amber-600 underline underline-offset-2 font-medium">去处理 ›</a>
        </div>
      )}
      {byProduct.length > 0 && (
        <div>
          <div className="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">按商品</div>
          <div className="space-y-1">
            {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
            {byProduct.slice(0, 5).map((r: any, i: number) => (
              <div key={i} className="flex justify-between items-center text-xs bg-gray-50 rounded-lg px-3 py-2">
                <span className="font-medium text-gray-700">{r.product_name}</span>
                <span className="tabular-nums text-red-500 font-semibold">{Number(r.total_qty).toFixed(1)}{r.unit}</span>
              </div>
            ))}
          </div>
        </div>
      )}
      {bySupplier.length > 0 && (
        <div>
          <div className="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">按供应商（可索赔）</div>
          <div className="space-y-1">
            {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
            {bySupplier.slice(0, 4).map((r: any, i: number) => (
              <div key={i} className="flex justify-between items-center text-xs bg-gray-50 rounded-lg px-3 py-2">
                <span className="font-medium text-gray-700">{r.supplier_name}</span>
                <span className="tabular-nums text-red-500 font-semibold">¥{Number(r.total_claimed).toFixed(2)}</span>
              </div>
            ))}
          </div>
        </div>
      )}
      <a href="/damage" className="block text-center text-xs text-red-500 font-semibold py-2 rounded-xl bg-red-50 hover:bg-red-100 transition-colors">
        查看完整损耗管理 ›
      </a>
    </div>
  )
}

function CardRenderer({ type, data }: { type: QuickAction; data: unknown }) {
  switch (type) {
    case 'inventory':       return <InventoryCard data={data} />
    case 'sales_today':     return <SalesTodayCard data={data} />
    case 'daily_overview':  return <DailyOverviewCard data={data} />
    case 'purchase_orders': return <PurchaseOrdersCard data={data} />
    case 'weather':         return <WeatherCard data={data} />
    case 'daily_logs':      return <DailyLogsCard data={data} />
    case 'sales_report':    return <SalesReportCard data={data} />
    case 'suggestions':     return <SuggestionsCard data={data} />
    case 'damage_stats':    return <DamageStatsCard data={data} />
  }
}

// ─── Quick Actions ───────────────────────────────────────────────────────────

const QUICK_ACTIONS: { emoji: string; label: string; action: QuickAction }[] = [
  { emoji: '📦', label: '库存',   action: 'inventory' },
  { emoji: '💰', label: '今日销售', action: 'sales_today' },
  { emoji: '📊', label: '每日概览', action: 'daily_overview' },
  { emoji: '🚛', label: '今日进货', action: 'purchase_orders' },
  { emoji: '🌤️', label: '天气',    action: 'weather' },
  { emoji: '📋', label: '操作日志', action: 'daily_logs' },
  { emoji: '📈', label: '销售报表', action: 'sales_report' },
  { emoji: '💡', label: '经营建议', action: 'suggestions' },
  { emoji: '⚠️', label: '损耗统计', action: 'damage_stats' },
]

const WRITE_ACTIONS: { emoji: string; label: string; action: WriteAction }[] = [
  { emoji: '📥', label: '录入进货', action: 'purchase_entry' },
  { emoji: '🛒', label: '补录销售', action: 'sales_supplement' },
  { emoji: '⚠️', label: '记录损耗', action: 'damage_entry' },
]

const CARD_TITLES: Record<QuickAction, string> = {
  inventory:       '📦 当前库存',
  sales_today:     '💰 今日销售汇总',
  daily_overview:  '📊 每日运营概览',
  purchase_orders: '🚛 今日进货单',
  weather:         '🌤️ 今日天气',
  daily_logs:      '📋 操作日志',
  sales_report:    '📈 每日销售报表',
  suggestions:     '💡 进货 & 促销建议',
  damage_stats:    '⚠️ 损耗统计',
}

// ─── Main Page ───────────────────────────────────────────────────────────────

export default function ManagePage() {
  const { token, user, isAuthenticated, loading, login, logout } = useAuth()
  const router = useRouter()

  const [loginField, setLoginField] = useState('')
  const [loginPassword, setLoginPassword] = useState('')
  const [loginError, setLoginError] = useState('')
  const [loginLoading, setLoginLoading] = useState(false)
  const [messages, setMessages] = useState<ChatMessage[]>([
    {
      id: 0,
      role: 'system',
      text: '您好！我是门店AI助手。\n\n点击下方快捷按钮可直接查询库存、销售、天气等数据；也可以用文字或语音告诉我进货、销售情况，我会自动录入系统。',
      timestamp: new Date(),
    },
  ])
  const [inputText, setInputText] = useState('')
  const [isSending, setIsSending] = useState(false)
  const [sessionId, setSessionId] = useState<number | undefined>()
  const [selectedImage, setSelectedImage] = useState<{ base64: string; preview: string } | null>(null)
  const [isRecording, setIsRecording] = useState(false)
  const [interimText, setInterimText] = useState('')

  const [activeForm, setActiveForm] = useState<WriteAction | null>(null)
  const [purchaseItems, setPurchaseItems] = useState([{ name: '', qty: '', unit: '斤', price: '' }])
  const [suppProduct, setSuppProduct] = useState('')
  const [suppType, setSuppType] = useState<'sold_out' | 'remaining' | 'qty'>('sold_out')
  const [suppQty, setSuppQty] = useState('')

  const [damageProduct, setDamageProduct] = useState('')
  const [damageQty, setDamageQty] = useState('')
  const [damageReason, setDamageReason] = useState('变质')

  const messagesEndRef = useRef<HTMLDivElement>(null)
  const fileInputRef = useRef<HTMLInputElement>(null)
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const speechRecognitionRef = useRef<any>(null)

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [messages])

  const addMsg = (msg: Omit<ChatMessage, 'id' | 'timestamp'>) => {
    setMessages(prev => [...prev, { ...msg, id: Date.now() + Math.random(), timestamp: new Date() }])
  }

  const handleQuickAction = async (action: QuickAction) => {
    if (!token || isSending) return
    const qa = QUICK_ACTIONS.find(a => a.action === action)!
    addMsg({ role: 'user', text: `${qa.emoji} ${qa.label}` })
    setIsSending(true)
    try {
      let cardData: unknown
      switch (action) {
        case 'inventory':       cardData = await inventoryApi.list(token); break
        case 'sales_today':     cardData = await salesApi.todaySummary(token); break
        case 'daily_overview':  cardData = await operationsApi.dailyOverview(todayStr(), token); break
        case 'purchase_orders': cardData = await purchaseOrdersApi.list(token, { date: todayStr() }); break
        case 'weather':         cardData = await operationsApi.weather(token); break
        case 'daily_logs':      cardData = await operationsApi.dailyLogs(token); break
        case 'sales_report':    cardData = await salesApi.report(todayStr(), token); break
        case 'suggestions':     cardData = await suggestionsApi.get(token); break
        case 'damage_stats':    cardData = await damageApi.stats(token, { from: new Date(Date.now() - 30 * 86400000).toISOString().slice(0, 10), to: todayStr() }); break
      }
      addMsg({ role: 'system', text: CARD_TITLES[action], cardType: action, cardData })
    } catch {
      addMsg({ role: 'system', text: '查询失败，请稍后重试。' })
    } finally {
      setIsSending(false)
    }
  }

  const openWriteForm = (action: WriteAction) => {
    setActiveForm(prev => prev === action ? null : action)
    setPurchaseItems([{ name: '', qty: '', unit: '斤', price: '' }])
    setSuppProduct('')
    setSuppType('sold_out')
    setSuppQty('')
    setDamageProduct('')
    setDamageQty('')
    setDamageReason('变质')
  }

  const handlePurchaseSubmit = async () => {
    if (!token || isSending) return
    const validItems = purchaseItems.filter(i => i.name.trim() && Number(i.qty) > 0)
    if (!validItems.length) return
    addMsg({ role: 'user', text: `📥 录入进货：${validItems.map(i => `${i.name} ${i.qty}${i.unit}`).join('、')}` })
    setActiveForm(null)
    setIsSending(true)
    try {
      const data = {
        date: todayStr(),
        items: validItems.map(i => ({
          product_name: i.name.trim(),
          ordered_qty: Number(i.qty),
          ...(i.price ? { unit_price: Number(i.price) } : {}),
        })),
      }
      const result = await purchaseOrdersApi.create(data, token)
      const order = result?.data
      addMsg({ role: 'system', text: `✅ 进货单已录入（单号 #${order?.id ?? '—'}），库存已更新。` })
    } catch {
      addMsg({ role: 'system', text: '❌ 进货录入失败，请检查数据后重试。' })
    } finally {
      setIsSending(false)
    }
  }

  const handleSupplementSubmit = async () => {
    if (!token || isSending) return
    if (!suppProduct.trim()) return
    const typeLabel = suppType === 'sold_out' ? '全部卖完' : suppType === 'remaining' ? `还剩 ${suppQty}` : `卖了 ${suppQty}`
    addMsg({ role: 'user', text: `🛒 补录销售：${suppProduct} — ${typeLabel}` })
    setActiveForm(null)
    setIsSending(true)
    try {
      const suppData: Parameters<typeof salesApi.supplement>[0] = { product_name: suppProduct.trim(), type: suppType }
      if (suppType === 'remaining') suppData.remaining_qty = Number(suppQty)
      if (suppType === 'qty') suppData.sold_qty = Number(suppQty)
      await salesApi.supplement(suppData, token)
      addMsg({ role: 'system', text: `✅ 销售补录成功，库存已更新。` })
    } catch {
      addMsg({ role: 'system', text: '❌ 补录失败，请检查数据后重试。' })
    } finally {
      setIsSending(false)
    }
  }

  const handleDamageSubmit = async () => {
    if (!token || isSending) return
    if (!damageProduct.trim() || !damageQty) return
    addMsg({ role: 'user', text: `⚠️ 损耗记录：${damageProduct} — ${damageQty} · 原因：${damageReason}` })
    setActiveForm(null)
    setIsSending(true)
    try {
      await damageApi.create({
        product_name: damageProduct.trim(),
        qty: Number(damageQty),
        reason: damageReason,
      }, token)
      addMsg({ role: 'system', text: `✅ 损耗已记录，库存已扣减。如需生成供应商退款申请，请前往损耗管理页面。` })
    } catch {
      addMsg({ role: 'system', text: '❌ 损耗记录失败，请重试。' })
    } finally {
      setIsSending(false)
    }
  }

  const handleSend = async () => {
    if ((!inputText.trim() && !selectedImage) || isSending) return

    if (!isAuthenticated) {
      const text = inputText.trim()
      setInputText('')
      addMsg({ role: 'user', text })
      const m = text.match(/^(?:登录|login)\s+(\S+)\s+(\S+)/i)
      if (m) {
        setLoginLoading(true)
        try {
          await login({ login: m[1], password: m[2] })
          addMsg({ role: 'system', text: `✅ 登录成功！欢迎使用舌尖香港门店助手。` })
        } catch {
          addMsg({ role: 'system', text: '❌ 用户名或密码错误，请重试。\n\n格式：登录 用户名 密码' })
        } finally {
          setLoginLoading(false)
        }
      } else {
        addMsg({ role: 'system', text: '请先登录。\n\n发送：登录 用户名 密码\n例：登录 demo Demo@2026\n\n也可在下方表单填写账号密码登录。' })
      }
      return
    }

    const text = inputText.trim()
    const image = selectedImage
    setInputText('')
    setSelectedImage(null)
    setIsSending(true)
    addMsg({ role: 'user', text: text || '（图片）', imagePreview: image?.preview })
    try {
      const result = await assistantApi.sendMessage({ text, image_base64: image?.base64, session_id: sessionId }, token!)
      setSessionId(result.session_id)
      addMsg({
        role: 'system',
        text: result.reply,
        operations: result.operations,
        cardType: result.card_type as QuickAction | undefined,
        cardData: result.card_data,
      })
    } catch {
      addMsg({ role: 'system', text: '发送失败，请检查网络后重试。' })
    } finally {
      setIsSending(false)
    }
  }

  const handleVoiceToggle = () => {
    if (!isAuthenticated) return

    if (isRecording) {
      speechRecognitionRef.current?.stop()
      setIsRecording(false)
      setInterimText('')
      return
    }

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const SpeechRecognition = (window as any).SpeechRecognition || (window as any).webkitSpeechRecognition
    if (!SpeechRecognition) {
      alert('您的浏览器不支持语音识别，请使用 Chrome 或 Edge。')
      return
    }

    const recognition = new SpeechRecognition()
    recognition.lang = 'zh-CN'
    recognition.continuous = true
    recognition.interimResults = true

    recognition.onresult = (event: { results: { [key: number]: { [key: number]: { transcript: string }; isFinal: boolean }; length: number } }) => {
      let interim = ''
      let final = ''
      for (let i = event.results.length - 1; i >= 0; i--) {
        if (event.results[i].isFinal) {
          final = event.results[i][0].transcript + final
        } else {
          interim = event.results[i][0].transcript + interim
        }
      }
      if (final) {
        setInputText(prev => (prev + final).trim())
        setInterimText('')
      } else {
        setInterimText(interim)
      }
    }

    recognition.onerror = (event: { error: string }) => {
      if (event.error !== 'aborted') {
        setInterimText('')
      }
    }

    recognition.onend = () => {
      setIsRecording(false)
      setInterimText('')
    }

    speechRecognitionRef.current = recognition
    recognition.start()
    setIsRecording(true)
  }

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault()
    setLoginError('')
    setLoginLoading(true)
    try {
      await login({ login: loginField, password: loginPassword })
    } catch {
      setLoginError('用户名或密码错误，请重试')
    } finally {
      setLoginLoading(false)
    }
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center h-screen bg-[#f5f4f0]">
        <div className="flex flex-col items-center gap-3">
          <div className="w-12 h-12 rounded-2xl bg-orange-500 flex items-center justify-center text-2xl shadow-lg">🍜</div>
          <p className="text-gray-400 text-sm">加载中...</p>
        </div>
      </div>
    )
  }

  return (
    <div className="flex flex-col h-screen bg-[#f5f4f0] max-w-2xl mx-auto">

      {/* ── Header ── */}
      <div className="bg-white px-4 py-3 flex items-center justify-between shrink-0 shadow-[0_1px_0_0_#e5e3dc]">
        <div className="flex items-center gap-2.5">
          <div>
            <div className="text-sm font-bold text-gray-800 leading-tight">舌尖香港</div>
            <div className="text-[10px] text-gray-400 leading-tight">AI门店助手</div>
          </div>
        </div>
        <div className="flex items-center gap-2 text-xs">
          {user && (
            <span className="text-gray-500 hidden sm:flex items-center gap-1.5 bg-gray-50 rounded-full px-3 py-1.5">
              <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 inline-block" />
              {(user as { name?: string; username?: string }).name ?? (user as { name?: string; username?: string }).username}
            </span>
          )}
          <a href="/inventory" className="text-orange-500 hover:text-orange-600 hidden sm:inline font-medium px-2 py-1 rounded-lg hover:bg-orange-50 transition-colors">库存</a>
          <a href="/sales-report" className="text-orange-500 hover:text-orange-600 hidden sm:inline font-medium px-2 py-1 rounded-lg hover:bg-orange-50 transition-colors">销售报表</a>
          <a href="/damage" className="text-red-500 hover:text-red-600 hidden sm:inline font-medium px-2 py-1 rounded-lg hover:bg-red-50 transition-colors">损耗管理</a>
          <a href="/assistant" className="text-orange-500 hover:text-orange-600 hidden sm:inline font-medium px-2 py-1 rounded-lg hover:bg-orange-50 transition-colors">AI助手</a>
          <button
            onClick={() => logout().then(() => router.push('/login'))}
            className="text-gray-400 hover:text-red-500 transition-colors px-2 py-1 rounded-lg hover:bg-red-50"
          >
            退出
          </button>
        </div>
      </div>

      {/* ── Messages ── */}
      <div className="flex-1 overflow-y-auto px-4 py-5 space-y-5">
        {messages.map(msg => (
          <div key={msg.id} className={`flex ${msg.role === 'user' ? 'justify-end' : 'justify-start'}`}>

            {msg.role === 'system' && (
              <div className="w-8 h-8 rounded-2xl bg-orange-500 flex items-center justify-center text-white text-[10px] font-bold mr-2.5 shrink-0 mt-1 shadow-sm">
                AI
              </div>
            )}

            <div className={`flex flex-col gap-1.5 ${msg.role === 'user' ? 'items-end' : 'items-start'} max-w-[85%]`}>

              {msg.imagePreview && (
                <img src={msg.imagePreview} alt="上传图片" className="max-w-48 max-h-48 rounded-2xl object-cover shadow-md" />
              )}

              <div className={`px-4 py-3 rounded-2xl text-sm leading-relaxed whitespace-pre-line shadow-sm ${
                msg.role === 'user'
                  ? 'bg-stone-800 text-white rounded-br-md'
                  : 'bg-white text-gray-700 rounded-bl-md border border-stone-100'
              }`}>
                {msg.text}
              </div>

              {msg.cardType && msg.cardData !== undefined && (
                <div className="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm w-full mt-1">
                  <CardRenderer type={msg.cardType} data={msg.cardData} />
                </div>
              )}

              {msg.operations && msg.operations.length > 0 && (
                <div className="bg-emerald-50 border border-emerald-100 rounded-2xl px-4 py-3 w-full mt-1">
                  <div className="flex items-center gap-1.5 text-emerald-600 font-semibold text-xs mb-2">
                    <span className="w-4 h-4 rounded-full bg-emerald-500 flex items-center justify-center text-white text-[10px]">✓</span>
                    已录入系统
                  </div>
                  <div className="space-y-1.5">
                    {msg.operations.map((op, i) => (
                      <div key={i} className="flex items-center gap-2 text-xs">
                        <span className={`px-2 py-0.5 rounded-lg text-white text-[10px] font-bold ${
                          op.action === 'in' ? 'bg-emerald-500' :
                          op.action === 'out' ? 'bg-red-400' :
                          op.action === 'sell' ? 'bg-orange-400' : 'bg-amber-400'
                        }`}>
                          {op.action === 'in' ? '进货' : op.action === 'out' ? '出库' : op.action === 'sell' ? '销售' : '调整'}
                        </span>
                        <span className="font-semibold text-gray-700">{op.product_name}</span>
                        <span className="tabular-nums text-gray-500">{op.qty}{op.unit}</span>
                        <span className="text-emerald-400 tabular-nums ml-auto">{op.qty_before} → {op.qty_after}</span>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              <span className="text-[10px] text-gray-300 px-1 tabular-nums">
                {msg.timestamp.toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit' })}
              </span>
            </div>

            {msg.role === 'user' && (
              <div className="w-8 h-8 rounded-2xl bg-gray-100 flex items-center justify-center text-sm ml-2.5 shrink-0 mt-1">
                👤
              </div>
            )}
          </div>
        ))}

        {isSending && (
          <div className="flex justify-start">
            <div className="w-8 h-8 rounded-2xl bg-orange-500 flex items-center justify-center text-white text-[10px] font-bold mr-2.5 shrink-0 shadow-sm">
              AI
            </div>
            <div className="bg-white shadow-sm border border-stone-100 px-5 py-3.5 rounded-2xl rounded-bl-md">
              <div className="flex gap-1.5 items-center">
                {[0, 160, 320].map(d => (
                  <span key={d} className="w-2 h-2 bg-stone-200 rounded-full animate-bounce" style={{ animationDelay: `${d}ms` }} />
                ))}
              </div>
            </div>
          </div>
        )}

        <div ref={messagesEndRef} />
      </div>

      {/* ── Image preview bar ── */}
      {selectedImage && (
        <div className="bg-white border-t border-stone-100 px-4 py-2.5 flex items-center gap-3 shrink-0">
          <img src={selectedImage.preview} alt="预览" className="w-12 h-12 object-cover rounded-xl border border-stone-100 shrink-0" />
          <span className="text-xs text-stone-400 flex-1">AI 将自动识别图片类型，可附加文字补充说明</span>
          <button onClick={() => setSelectedImage(null)} className="w-6 h-6 flex items-center justify-center rounded-full text-stone-300 hover:text-stone-500 hover:bg-stone-100 transition-colors text-lg leading-none shrink-0">×</button>
        </div>
      )}

      {/* ── Bottom area ── */}
      <div className="bg-white border-t border-gray-100 shrink-0">
        {!isAuthenticated ? (
          <div>
            <div className="flex items-end gap-2 px-4 py-3">
              <textarea
                value={inputText}
                onChange={e => setInputText(e.target.value)}
                onKeyDown={e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); handleSend() } }}
                placeholder="发送「登录 用户名 密码」快速登录..."
                rows={1}
                className="flex-1 bg-gray-50 border border-gray-200 rounded-2xl px-4 py-2.5 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-orange-300 focus:border-transparent max-h-24 placeholder:text-gray-300 transition-shadow"
              />
              <button
                onClick={handleSend}
                disabled={isSending || !inputText.trim()}
                className="w-10 h-10 flex items-center justify-center rounded-2xl bg-orange-500 hover:bg-orange-600 disabled:bg-stone-200 text-white shrink-0 transition-colors font-bold text-base shadow-sm disabled:shadow-none"
              >
                {isSending ? '…' : '↑'}
              </button>
            </div>
            <div className="border-t border-stone-100 px-4 py-3 space-y-2.5">
              <p className="text-xs text-stone-300 text-center">或填写账号密码登录</p>
              {loginError && <p className="text-xs text-red-500 text-center">{loginError}</p>}
              <form onSubmit={handleLogin} className="flex gap-2">
                <input
                  type="text"
                  placeholder="用户名 / 邮箱"
                  value={loginField}
                  onChange={e => setLoginField(e.target.value)}
                  required
                  className="flex-1 bg-stone-50 border border-stone-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300 focus:border-transparent min-w-0 placeholder:text-stone-300"
                />
                <input
                  type="password"
                  placeholder="密码"
                  value={loginPassword}
                  onChange={e => setLoginPassword(e.target.value)}
                  required
                  className="flex-1 bg-stone-50 border border-stone-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300 focus:border-transparent min-w-0 placeholder:text-stone-300"
                />
                <button
                  type="submit"
                  disabled={loginLoading}
                  className="shrink-0 bg-orange-500 hover:bg-orange-600 disabled:bg-stone-200 text-white rounded-xl px-4 py-2 text-sm font-semibold transition-colors shadow-sm"
                >
                  {loginLoading ? '…' : '登录'}
                </button>
              </form>
            </div>
          </div>
        ) : (
          <>
            {/* All actions in one scrollable row */}
            <div className="px-4 pt-3 pb-2 flex gap-2 overflow-x-auto scrollbar-hide">
              {QUICK_ACTIONS.map(({ emoji, label, action }) => (
                <button
                  key={action}
                  onClick={() => { setActiveForm(null); handleQuickAction(action) }}
                  disabled={isSending}
                  className="shrink-0 flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 active:bg-gray-300 disabled:opacity-40 rounded-full text-xs text-gray-600 font-medium transition-colors"
                >
                  <span className="text-sm">{emoji}</span>
                  <span>{label}</span>
                </button>
              ))}
              <div className="w-px bg-gray-200 shrink-0 mx-1 self-stretch" />
              {WRITE_ACTIONS.map(({ emoji, label, action }) => (
                <button
                  key={action}
                  onClick={() => openWriteForm(action)}
                  disabled={isSending}
                  className={`shrink-0 flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold transition-colors ${
                    activeForm === action
                      ? 'bg-orange-500 text-white shadow-sm'
                      : 'bg-orange-50 hover:bg-orange-100 text-orange-600 border border-orange-200'
                  }`}
                >
                  <span className="text-sm">{emoji}</span>
                  <span>{label}</span>
                </button>
              ))}
            </div>

            {/* Purchase entry form */}
            {activeForm === 'purchase_entry' && (
              <div className="mx-4 mb-2 border border-stone-200 rounded-2xl bg-stone-50 p-3.5 space-y-2.5">
                <div className="flex items-center justify-between">
                  <span className="text-sm font-semibold text-stone-700 flex items-center gap-1.5">
                    <span className="w-5 h-5 rounded-lg bg-orange-500 flex items-center justify-center text-white text-xs">📥</span>
                    录入今日进货
                  </span>
                  <button onClick={() => setActiveForm(null)} className="w-6 h-6 flex items-center justify-center rounded-full text-stone-400 hover:text-stone-600 hover:bg-white transition-colors text-lg leading-none">×</button>
                </div>
                {purchaseItems.map((item, i) => (
                  <div key={i} className="flex gap-2 items-center">
                    <input
                      placeholder="商品名"
                      value={item.name}
                      onChange={e => setPurchaseItems(prev => prev.map((p, j) => j === i ? { ...p, name: e.target.value } : p))}
                      className="flex-1 bg-white border border-stone-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300 focus:border-transparent placeholder:text-stone-300"
                    />
                    <input
                      type="number"
                      placeholder="数量"
                      value={item.qty}
                      min="0"
                      onChange={e => setPurchaseItems(prev => prev.map((p, j) => j === i ? { ...p, qty: e.target.value } : p))}
                      className="w-16 bg-white border border-stone-200 rounded-xl px-2 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300 focus:border-transparent text-center placeholder:text-stone-300"
                    />
                    <select
                      value={item.unit}
                      onChange={e => setPurchaseItems(prev => prev.map((p, j) => j === i ? { ...p, unit: e.target.value } : p))}
                      className="bg-white border border-stone-200 rounded-xl px-2 py-2 text-sm focus:outline-none text-stone-600"
                    >
                      {['斤', '个', '箱', '袋', '瓶', '千克'].map(u => <option key={u}>{u}</option>)}
                    </select>
                    {purchaseItems.length > 1 && (
                      <button onClick={() => setPurchaseItems(prev => prev.filter((_, j) => j !== i))} className="w-6 h-6 flex items-center justify-center rounded-full text-stone-300 hover:text-red-400 hover:bg-red-50 transition-colors text-lg leading-none shrink-0">×</button>
                    )}
                  </div>
                ))}
                <div className="flex justify-between items-center pt-0.5">
                  <button
                    onClick={() => setPurchaseItems(prev => [...prev, { name: '', qty: '', unit: '斤', price: '' }])}
                    className="text-xs text-orange-500 hover:text-orange-600 font-medium"
                  >
                    + 添加商品
                  </button>
                  <button
                    onClick={handlePurchaseSubmit}
                    disabled={isSending || !purchaseItems.some(i => i.name.trim() && Number(i.qty) > 0)}
                    className="bg-orange-500 hover:bg-orange-600 disabled:bg-stone-200 text-white rounded-xl px-5 py-1.5 text-sm font-semibold transition-colors shadow-sm"
                  >
                    确认收货
                  </button>
                </div>
              </div>
            )}

            {/* Sales supplement form */}
            {activeForm === 'sales_supplement' && (
              <div className="mx-4 mb-2 border border-stone-200 rounded-2xl bg-stone-50 p-3.5 space-y-2.5">
                <div className="flex items-center justify-between">
                  <span className="text-sm font-semibold text-stone-700 flex items-center gap-1.5">
                    <span className="w-5 h-5 rounded-lg bg-orange-500 flex items-center justify-center text-white text-xs">🛒</span>
                    补录销售
                  </span>
                  <button onClick={() => setActiveForm(null)} className="w-6 h-6 flex items-center justify-center rounded-full text-stone-400 hover:text-stone-600 hover:bg-white transition-colors text-lg leading-none">×</button>
                </div>
                <input
                  placeholder="商品名（如：番茄、白菜）"
                  value={suppProduct}
                  onChange={e => setSuppProduct(e.target.value)}
                  className="w-full bg-white border border-stone-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300 focus:border-transparent placeholder:text-stone-300"
                />
                <div className="flex gap-2">
                  {(['sold_out', 'remaining', 'qty'] as const).map(t => (
                    <button
                      key={t}
                      onClick={() => setSuppType(t)}
                      className={`flex-1 py-2 rounded-xl text-xs font-semibold transition-colors border ${
                        suppType === t
                          ? 'bg-orange-500 text-white border-orange-500 shadow-sm'
                          : 'bg-white text-stone-500 border-stone-200 hover:bg-stone-50'
                      }`}
                    >
                      {t === 'sold_out' ? '全部卖完' : t === 'remaining' ? '还剩多少' : '卖了多少'}
                    </button>
                  ))}
                </div>
                {suppType !== 'sold_out' && (
                  <div className="flex gap-2 items-center">
                    <input
                      type="number"
                      placeholder={suppType === 'remaining' ? '剩余数量' : '售出数量'}
                      value={suppQty}
                      min="0"
                      onChange={e => setSuppQty(e.target.value)}
                      className="flex-1 bg-white border border-stone-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300 focus:border-transparent placeholder:text-stone-300"
                    />
                    <span className="text-sm text-stone-400 font-medium">斤</span>
                  </div>
                )}
                <div className="flex justify-end pt-0.5">
                  <button
                    onClick={handleSupplementSubmit}
                    disabled={isSending || !suppProduct.trim() || (suppType !== 'sold_out' && !suppQty)}
                    className="bg-orange-500 hover:bg-orange-600 disabled:bg-stone-200 text-white rounded-xl px-5 py-1.5 text-sm font-semibold transition-colors shadow-sm"
                  >
                    确认补录
                  </button>
                </div>
              </div>
            )}

            {/* Damage entry form */}
            {activeForm === 'damage_entry' && (
              <div className="mx-4 mb-2 border border-red-200 rounded-2xl bg-red-50/50 p-3.5 space-y-2.5">
                <div className="flex items-center justify-between">
                  <span className="text-sm font-semibold text-red-700 flex items-center gap-1.5">
                    <span className="w-5 h-5 rounded-lg bg-red-500 flex items-center justify-center text-white text-xs">⚠</span>
                    记录损耗
                  </span>
                  <button onClick={() => setActiveForm(null)} className="w-6 h-6 flex items-center justify-center rounded-full text-red-300 hover:text-red-500 hover:bg-white transition-colors text-lg leading-none">×</button>
                </div>
                <input
                  placeholder="商品名（如：番茄、白菜）"
                  value={damageProduct}
                  onChange={e => setDamageProduct(e.target.value)}
                  className="w-full bg-white border border-red-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300 focus:border-transparent placeholder:text-red-200"
                />
                <div className="flex gap-2 items-center">
                  <input
                    type="number"
                    placeholder="损耗数量"
                    value={damageQty}
                    min="0.001"
                    step="0.1"
                    onChange={e => setDamageQty(e.target.value)}
                    className="flex-1 bg-white border border-red-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300 focus:border-transparent placeholder:text-red-200"
                  />
                  <select
                    value={damageReason}
                    onChange={e => setDamageReason(e.target.value)}
                    className="flex-1 bg-white border border-red-200 rounded-xl px-2 py-2 text-sm focus:outline-none text-gray-600"
                  >
                    {['变质', '包装破损', '运输损坏', '过期', '其他'].map(r => <option key={r}>{r}</option>)}
                  </select>
                </div>
                <div className="flex justify-between items-center pt-0.5">
                  <a href="/damage" className="text-xs text-red-400 hover:text-red-600 font-medium">📋 完整损耗管理 ›</a>
                  <button
                    onClick={handleDamageSubmit}
                    disabled={isSending || !damageProduct.trim() || !damageQty}
                    className="bg-red-500 hover:bg-red-600 disabled:bg-stone-200 text-white rounded-xl px-5 py-1.5 text-sm font-semibold transition-colors shadow-sm"
                  >
                    确认损耗
                  </button>
                </div>
              </div>
            )}

            {/* Input row */}
            <div className="flex items-end gap-2 px-4 pb-4 pt-2">
              <button
                onClick={() => fileInputRef.current?.click()}
                className="w-9 h-9 flex items-center justify-center rounded-2xl bg-gray-100 hover:bg-gray-200 text-gray-500 shrink-0 transition-colors text-base"
                title="上传图片"
              >
                📷
              </button>
              <input
                ref={fileInputRef}
                type="file"
                accept="image/*"
                className="hidden"
                onChange={e => {
                  const file = e.target.files?.[0]
                  if (!file) return
                  const reader = new FileReader()
                  reader.onload = () => {
                    const dataUrl = reader.result as string
                    setSelectedImage({ base64: dataUrl.split(',')[1], preview: dataUrl })
                  }
                  reader.readAsDataURL(file)
                  e.target.value = ''
                }}
              />

              <button
                onClick={handleVoiceToggle}
                className={`w-9 h-9 flex items-center justify-center rounded-2xl shrink-0 transition-all text-base ${
                  isRecording
                    ? 'bg-red-500 text-white shadow-lg shadow-red-200 animate-pulse'
                    : 'bg-gray-100 hover:bg-gray-200 text-gray-500'
                }`}
                title={isRecording ? '点击停止' : '语音输入'}
              >
                🎤
              </button>

              <div className="relative flex-1">
                <textarea
                  value={inputText}
                  onChange={e => setInputText(e.target.value)}
                  onKeyDown={e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); handleSend() } }}
                  placeholder={isRecording ? '请说话...' : '说点什么，或告诉我今天的库存情况...'}
                  rows={1}
                  className="w-full bg-stone-50 border border-stone-200 rounded-2xl px-4 py-2.5 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-orange-300 focus:border-transparent max-h-32 transition-colors placeholder:text-stone-300"
                />
                {interimText && (
                  <div className="absolute left-4 bottom-2.5 text-sm text-stone-400 pointer-events-none truncate max-w-[calc(100%-2rem)]">
                    {interimText}
                  </div>
                )}
              </div>

              <button
                onClick={handleSend}
                disabled={isSending || (!inputText.trim() && !selectedImage)}
                className="w-10 h-10 flex items-center justify-center rounded-2xl bg-orange-500 hover:bg-orange-600 disabled:bg-stone-200 text-white shrink-0 transition-colors font-bold text-base shadow-sm disabled:shadow-none"
              >
                {isSending ? '…' : '↑'}
              </button>
            </div>

            {isRecording && (
              <p className="text-center text-red-500 text-xs pb-3 -mt-2 font-medium flex items-center justify-center gap-1.5">
                <span className="w-1.5 h-1.5 rounded-full bg-red-500 animate-ping inline-block" />
                正在识别语音，说完后点麦克风停止
              </p>
            )}
          </>
        )}
      </div>
    </div>
  )
}
