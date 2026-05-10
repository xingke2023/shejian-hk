'use client'

import { useEffect, useState, useCallback } from 'react'
import { useRouter } from 'next/navigation'
import { useAuth } from '@/lib/auth-context'
import { salesApi, type SalesReport } from '@/lib/api/sales'

// ─── Types ───────────────────────────────────────────────────────────────────

type SortKey = 'sales_amount' | 'sales_qty' | 'avg_price' | 'transaction_count'

// ─── Helpers ──────────────────────────────────────────────────────────────────

function todayStr(): string {
  return new Date().toISOString().slice(0, 10)
}

function fmt(n: number): string {
  return n.toFixed(2)
}

function pct(part: number, total: number): string {
  if (total === 0) return '0%'
  return Math.round((part / total) * 100) + '%'
}

// ─── Component ───────────────────────────────────────────────────────────────

export default function SalesReportPage() {
  const { token, isAuthenticated, loading } = useAuth()
  const router = useRouter()

  const [date, setDate] = useState(todayStr())
  const [report, setReport] = useState<SalesReport | null>(null)
  const [fetching, setFetching] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [sortKey, setSortKey] = useState<SortKey>('sales_amount')
  const [sortAsc, setSortAsc] = useState(false)
  const [expandedRow, setExpandedRow] = useState<number | null>(null)

  useEffect(() => {
    if (!loading && !isAuthenticated) router.push('/login')
  }, [loading, isAuthenticated, router])

  const fetchReport = useCallback(async () => {
    if (!token) return
    setFetching(true)
    setError(null)
    try {
      const res = await salesApi.report(date, token)
      setReport(res.data)
    } catch {
      setError('加载失败，请重试')
    } finally {
      setFetching(false)
    }
  }, [token, date])

  useEffect(() => {
    fetchReport()
  }, [fetchReport])

  const toggleSort = (key: SortKey) => {
    if (sortKey === key) setSortAsc(v => !v)
    else { setSortKey(key); setSortAsc(false) }
  }

  const sortedProducts = report
    ? [...report.products].sort((a, b) => {
        const av = a[sortKey] ?? 0
        const bv = b[sortKey] ?? 0
        return sortAsc ? (av as number) - (bv as number) : (bv as number) - (av as number)
      })
    : []

  const SortIcon = ({ k }: { k: SortKey }) => (
    <span className={`ml-0.5 text-xs ${sortKey === k ? 'text-blue-500' : 'text-gray-300'}`}>
      {sortKey === k ? (sortAsc ? '↑' : '↓') : '↕'}
    </span>
  )

  if (loading) {
    return <div className="flex items-center justify-center h-screen text-gray-400">加载中...</div>
  }

  return (
    <div className="min-h-screen bg-gray-50">

      {/* Header */}
      <div className="bg-white border-b px-4 py-3 flex items-center justify-between sticky top-0 z-10 shadow-sm">
        <div className="flex items-center gap-2">
          <button onClick={() => router.back()} className="text-gray-400 hover:text-gray-600 text-sm mr-1">←</button>
          <h1 className="text-base font-semibold text-gray-800">每日销售报表</h1>
        </div>
        <button
          onClick={fetchReport}
          disabled={fetching}
          className="text-xs text-blue-600 hover:text-blue-800 disabled:text-gray-300"
        >
          {fetching ? '刷新中…' : '刷新'}
        </button>
      </div>

      <div className="p-4 space-y-4 max-w-2xl mx-auto">

        {/* Date picker */}
        <div className="bg-white rounded-2xl border border-gray-100 p-4 flex items-center gap-3">
          <span className="text-sm text-gray-500 shrink-0">查询日期</span>
          <input
            type="date"
            value={date}
            max={todayStr()}
            onChange={e => setDate(e.target.value)}
            className="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-300"
          />
        </div>

        {error && (
          <div className="bg-red-50 border border-red-100 rounded-xl px-4 py-3 text-sm text-red-600">{error}</div>
        )}

        {fetching && !report && (
          <div className="text-center text-sm text-gray-400 py-12">加载中…</div>
        )}

        {report && (
          <>
            {/* Summary cards */}
            <div className="grid grid-cols-2 gap-3">
              <div className="bg-orange-50 border border-orange-100 rounded-2xl p-4 text-center">
                <p className="text-xs text-orange-400 mb-1">总销售额</p>
                <p className="text-2xl font-bold text-orange-600 tabular-nums">¥{fmt(report.total_amount)}</p>
              </div>
              <div className="bg-blue-50 border border-blue-100 rounded-2xl p-4 text-center">
                <p className="text-xs text-blue-400 mb-1">销售笔数</p>
                <p className="text-2xl font-bold text-blue-600 tabular-nums">{report.total_orders}</p>
              </div>
              <div className="bg-emerald-50 border border-emerald-100 rounded-2xl p-4 text-center">
                <p className="text-xs text-emerald-500 mb-1">总销售量</p>
                <p className="text-2xl font-bold text-emerald-600 tabular-nums">{report.total_qty}</p>
                <p className="text-xs text-emerald-400">（综合单位）</p>
              </div>
              <div className="bg-purple-50 border border-purple-100 rounded-2xl p-4 text-center">
                <p className="text-xs text-purple-400 mb-1">品种数</p>
                <p className="text-2xl font-bold text-purple-600 tabular-nums">{report.total_skus}</p>
              </div>
            </div>

            {/* Source breakdown */}
            {report.total_amount > 0 && (
              <div className="bg-white rounded-2xl border border-gray-100 p-4">
                <p className="text-xs font-semibold text-gray-500 mb-3 uppercase tracking-wide">录入来源分布</p>
                <div className="flex rounded-lg overflow-hidden h-4 mb-3">
                  {report.source_breakdown.pos.amount > 0 && (
                    <div
                      className="bg-blue-400"
                      style={{ width: pct(report.source_breakdown.pos.amount, report.total_amount) }}
                    />
                  )}
                  {report.source_breakdown.supplement.amount > 0 && (
                    <div
                      className="bg-amber-400"
                      style={{ width: pct(report.source_breakdown.supplement.amount, report.total_amount) }}
                    />
                  )}
                  {report.source_breakdown.ai.amount > 0 && (
                    <div
                      className="bg-emerald-400"
                      style={{ width: pct(report.source_breakdown.ai.amount, report.total_amount) }}
                    />
                  )}
                </div>
                <div className="grid grid-cols-3 gap-2 text-center">
                  {([
                    { key: 'pos', label: 'POS收银', color: 'text-blue-500', bg: 'bg-blue-400' },
                    { key: 'supplement', label: '人工补录', color: 'text-amber-500', bg: 'bg-amber-400' },
                    { key: 'ai', label: 'AI录入', color: 'text-emerald-500', bg: 'bg-emerald-400' },
                  ] as const).map(({ key, label, color, bg }) => (
                    <div key={key}>
                      <div className="flex items-center justify-center gap-1 mb-0.5">
                        <span className={`w-2 h-2 rounded-full inline-block ${bg}`} />
                        <span className="text-xs text-gray-400">{label}</span>
                      </div>
                      <p className={`text-sm font-bold tabular-nums ${color}`}>
                        ¥{fmt(report.source_breakdown[key].amount)}
                      </p>
                      <p className="text-xs text-gray-300">
                        {pct(report.source_breakdown[key].amount, report.total_amount)}
                      </p>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* Payment breakdown */}
            {report.payment_breakdown.length > 0 && (
              <div className="bg-white rounded-2xl border border-gray-100 p-4">
                <p className="text-xs font-semibold text-gray-500 mb-3 uppercase tracking-wide">支付方式</p>
                <div className="space-y-2">
                  {report.payment_breakdown.map(p => (
                    <div key={p.method} className="flex items-center gap-2">
                      <span className="text-xs text-gray-500 w-14 shrink-0">{p.label}</span>
                      <div className="flex-1 bg-gray-100 rounded-full h-2 overflow-hidden">
                        <div
                          className="bg-gray-400 h-2 rounded-full"
                          style={{ width: pct(p.amount, report.total_amount) }}
                        />
                      </div>
                      <span className="text-xs font-medium text-gray-700 tabular-nums w-20 text-right">
                        ¥{fmt(p.amount)} ({p.count}笔)
                      </span>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* Per-product table */}
            <div className="bg-white rounded-2xl border border-gray-100 overflow-hidden">
              <div className="px-4 py-3 border-b border-gray-50 flex items-center justify-between">
                <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide">品种明细</p>
                <p className="text-xs text-gray-300">{report.total_skus} 个品种</p>
              </div>
              {sortedProducts.length === 0 ? (
                <p className="text-center text-sm text-gray-300 py-10">当日暂无销售记录</p>
              ) : (
                <div className="overflow-x-auto">
                  <table className="text-xs w-full border-collapse min-w-[420px]">
                    <thead>
                      <tr className="bg-gray-50">
                        <th className="text-left px-3 py-2.5 text-gray-400 font-medium">商品</th>
                        <th
                          className="text-right px-3 py-2.5 text-gray-400 font-medium cursor-pointer select-none hover:text-blue-500"
                          onClick={() => toggleSort('sales_qty')}
                        >
                          销量<SortIcon k="sales_qty" />
                        </th>
                        <th
                          className="text-right px-3 py-2.5 text-gray-400 font-medium cursor-pointer select-none hover:text-blue-500"
                          onClick={() => toggleSort('sales_amount')}
                        >
                          金额<SortIcon k="sales_amount" />
                        </th>
                        <th
                          className="text-right px-3 py-2.5 text-gray-400 font-medium cursor-pointer select-none hover:text-blue-500"
                          onClick={() => toggleSort('avg_price')}
                        >
                          均价<SortIcon k="avg_price" />
                        </th>
                        <th
                          className="text-right px-3 py-2.5 text-gray-400 font-medium cursor-pointer select-none hover:text-blue-500 hidden sm:table-cell"
                          onClick={() => toggleSort('transaction_count')}
                        >
                          笔数<SortIcon k="transaction_count" />
                        </th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-50">
                      {sortedProducts.map((row, i) => {
                        const isExpanded = expandedRow === row.product_id
                        return (
                          <>
                            <tr
                              key={row.product_id}
                              className={`cursor-pointer transition-colors ${isExpanded ? 'bg-blue-50/50' : 'hover:bg-gray-50/60'}`}
                              onClick={() => setExpandedRow(isExpanded ? null : row.product_id)}
                            >
                              <td className="px-3 py-2.5">
                                <span className="font-medium text-gray-700">{row.product_name}</span>
                                {row.is_fresh && (
                                  <span className="ml-1 text-[10px] bg-green-100 text-green-600 px-1 rounded">鲜</span>
                                )}
                                {i === 0 && (
                                  <span className="ml-1 text-[10px] bg-orange-100 text-orange-500 px-1 rounded">TOP</span>
                                )}
                              </td>
                              <td className="px-3 py-2.5 text-right tabular-nums text-gray-700">
                                {row.sales_qty}<span className="text-gray-300 ml-0.5">{row.unit}</span>
                              </td>
                              <td className="px-3 py-2.5 text-right tabular-nums font-medium text-gray-800">
                                ¥{fmt(row.sales_amount)}
                              </td>
                              <td className="px-3 py-2.5 text-right tabular-nums text-gray-500">
                                {row.avg_price != null ? `¥${fmt(row.avg_price)}` : '—'}
                              </td>
                              <td className="px-3 py-2.5 text-right tabular-nums text-gray-400 hidden sm:table-cell">
                                {row.transaction_count}
                              </td>
                            </tr>
                            {isExpanded && (
                              <tr key={`${row.product_id}-detail`} className="bg-blue-50/30">
                                <td colSpan={5} className="px-4 py-2.5">
                                  <div className="flex gap-4 text-xs">
                                    <div className="flex items-center gap-1">
                                      <span className="w-2 h-2 rounded-full bg-blue-400 inline-block" />
                                      <span className="text-gray-400">POS</span>
                                      <span className="font-medium text-gray-600 tabular-nums ml-1">
                                        {row.sales_breakdown.pos.qty}{row.unit} / ¥{fmt(row.sales_breakdown.pos.amount)}
                                      </span>
                                    </div>
                                    <div className="flex items-center gap-1">
                                      <span className="w-2 h-2 rounded-full bg-amber-400 inline-block" />
                                      <span className="text-gray-400">补录</span>
                                      <span className="font-medium text-gray-600 tabular-nums ml-1">
                                        {row.sales_breakdown.supplement.qty}{row.unit} / ¥{fmt(row.sales_breakdown.supplement.amount)}
                                      </span>
                                    </div>
                                    <div className="flex items-center gap-1">
                                      <span className="w-2 h-2 rounded-full bg-emerald-400 inline-block" />
                                      <span className="text-gray-400">AI</span>
                                      <span className="font-medium text-gray-600 tabular-nums ml-1">
                                        {row.sales_breakdown.ai.qty}{row.unit} / ¥{fmt(row.sales_breakdown.ai.amount)}
                                      </span>
                                    </div>
                                  </div>
                                </td>
                              </tr>
                            )}
                          </>
                        )
                      })}
                    </tbody>
                  </table>
                </div>
              )}
            </div>

          </>
        )}
      </div>
    </div>
  )
}
