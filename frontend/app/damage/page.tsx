'use client'

import { useEffect, useRef, useState } from 'react'
import { useRouter } from 'next/navigation'
import { useAuth } from '@/lib/auth-context'
import {
  damageApi,
  refundClaimApi,
  type DamageRecord,
  type DamageStats,
  type SupplierRefundClaim,
  type RefundClaimStatus,
} from '@/lib/api/damage'

// ─── Constants ───────────────────────────────────────────────────────────────

const REASONS = ['变质', '包装破损', '运输损坏', '过期', '其他']

const DAMAGE_STATUS_MAP: Record<number, { label: string; cls: string }> = {
  1: { label: '待提交', cls: 'bg-amber-50 text-amber-600 border-amber-200' },
  2: { label: '已提交供应商', cls: 'bg-blue-50 text-blue-600 border-blue-200' },
  3: { label: '已退款', cls: 'bg-emerald-50 text-emerald-600 border-emerald-200' },
  4: { label: '已关闭', cls: 'bg-gray-100 text-gray-400 border-gray-200' },
}

const CLAIM_STATUS_MAP: Record<number, { label: string; cls: string }> = {
  1: { label: '草稿', cls: 'bg-gray-100 text-gray-500 border-gray-200' },
  2: { label: '已提交', cls: 'bg-blue-50 text-blue-600 border-blue-200' },
  3: { label: '供应商确认', cls: 'bg-violet-50 text-violet-600 border-violet-200' },
  4: { label: '已退款', cls: 'bg-emerald-50 text-emerald-600 border-emerald-200' },
  5: { label: '已拒绝', cls: 'bg-red-50 text-red-500 border-red-200' },
}

const API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api'

// ─── Helper ──────────────────────────────────────────────────────────────────

function todayStr() {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

// ─── Tab: 损耗录入 ────────────────────────────────────────────────────────────

function EntryTab({ token }: { token: string }) {
  const [product, setProduct] = useState('')
  const [qty, setQty] = useState('')
  const [reason, setReason] = useState(REASONS[0])
  const [customReason, setCustomReason] = useState('')
  const [notes, setNotes] = useState('')
  const [occurredAt, setOccurredAt] = useState(todayStr())
  const [images, setImages] = useState<{ file: File; preview: string }[]>([])
  const [submitting, setSubmitting] = useState(false)
  const [result, setResult] = useState<{ ok: boolean; msg: string } | null>(null)
  const fileInputRef = useRef<HTMLInputElement>(null)

  const finalReason = reason === '其他' ? customReason : reason

  const handleImageAdd = (e: React.ChangeEvent<HTMLInputElement>) => {
    const files = Array.from(e.target.files ?? [])
    if (images.length + files.length > 5) {
      alert('最多上传5张图片')
      return
    }
    const newImgs = files.map(f => ({ file: f, preview: URL.createObjectURL(f) }))
    setImages(prev => [...prev, ...newImgs])
    e.target.value = ''
  }

  const removeImage = (i: number) => {
    setImages(prev => {
      URL.revokeObjectURL(prev[i].preview)
      return prev.filter((_, j) => j !== i)
    })
  }

  const handleSubmit = async () => {
    if (!product.trim() || !qty || !finalReason.trim()) return
    setSubmitting(true)
    setResult(null)
    try {
      let damageRecordId: number | null = null

      // If images exist and are large, use multipart; otherwise use base64
      if (images.length > 0) {
        // Create record first (no images), then upload images separately
        const res = await damageApi.create({
          product_name: product.trim(),
          qty: Number(qty),
          reason: finalReason.trim(),
          notes: notes.trim() || undefined,
          occurred_at: occurredAt,
        }, token)
        damageRecordId = res.data.id

        // Upload images via multipart
        await damageApi.uploadImages(damageRecordId, images.map(i => i.file), token)
      } else {
        await damageApi.create({
          product_name: product.trim(),
          qty: Number(qty),
          reason: finalReason.trim(),
          notes: notes.trim() || undefined,
          occurred_at: occurredAt,
        }, token)
      }

      setResult({ ok: true, msg: '损耗记录已保存，库存已扣减。' })
      setProduct('')
      setQty('')
      setReason(REASONS[0])
      setCustomReason('')
      setNotes('')
      setOccurredAt(todayStr())
      setImages([])
    } catch (e) {
      setResult({ ok: false, msg: e instanceof Error ? e.message : '提交失败，请重试。' })
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="space-y-4 p-4">
      <div className="bg-white rounded-2xl border border-gray-100 p-4 space-y-3.5 shadow-sm">
        <h2 className="text-sm font-semibold text-gray-700">损耗登记</h2>

        {/* Product name */}
        <div>
          <label className="text-xs text-gray-400 font-medium block mb-1">商品名称 *</label>
          <input
            value={product}
            onChange={e => setProduct(e.target.value)}
            placeholder="如：番茄、白菜、猪肉"
            className="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-300 focus:border-transparent placeholder:text-gray-300"
          />
        </div>

        {/* Qty + date */}
        <div className="grid grid-cols-2 gap-3">
          <div>
            <label className="text-xs text-gray-400 font-medium block mb-1">损耗数量 *</label>
            <input
              type="number"
              value={qty}
              min="0.001"
              step="0.1"
              onChange={e => setQty(e.target.value)}
              placeholder="0.0"
              className="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-300 focus:border-transparent placeholder:text-gray-300"
            />
          </div>
          <div>
            <label className="text-xs text-gray-400 font-medium block mb-1">发生日期</label>
            <input
              type="date"
              value={occurredAt}
              max={todayStr()}
              onChange={e => setOccurredAt(e.target.value)}
              className="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-300 focus:border-transparent"
            />
          </div>
        </div>

        {/* Reason selector */}
        <div>
          <label className="text-xs text-gray-400 font-medium block mb-1.5">损耗原因 *</label>
          <div className="flex flex-wrap gap-2">
            {REASONS.map(r => (
              <button
                key={r}
                onClick={() => setReason(r)}
                className={`px-3 py-1.5 rounded-full text-xs font-semibold transition-colors border ${
                  reason === r
                    ? 'bg-red-500 text-white border-red-500 shadow-sm'
                    : 'bg-white text-gray-500 border-gray-200 hover:bg-gray-50'
                }`}
              >
                {r}
              </button>
            ))}
          </div>
          {reason === '其他' && (
            <input
              value={customReason}
              onChange={e => setCustomReason(e.target.value)}
              placeholder="请说明原因..."
              className="mt-2 w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300 focus:border-transparent placeholder:text-gray-300"
            />
          )}
        </div>

        {/* Notes */}
        <div>
          <label className="text-xs text-gray-400 font-medium block mb-1">备注（可选）</label>
          <input
            value={notes}
            onChange={e => setNotes(e.target.value)}
            placeholder="补充说明..."
            className="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-300 focus:border-transparent placeholder:text-gray-300"
          />
        </div>

        {/* Image upload */}
        <div>
          <label className="text-xs text-gray-400 font-medium block mb-1.5">损耗照片（最多5张）</label>
          <div className="flex flex-wrap gap-2">
            {images.map((img, i) => (
              <div key={i} className="relative w-16 h-16 shrink-0">
                <img src={img.preview} alt="" className="w-full h-full object-cover rounded-xl border border-gray-200" />
                <button
                  onClick={() => removeImage(i)}
                  className="absolute -top-1.5 -right-1.5 w-5 h-5 bg-red-500 text-white rounded-full text-xs flex items-center justify-center leading-none font-bold shadow"
                >
                  ×
                </button>
              </div>
            ))}
            {images.length < 5 && (
              <button
                onClick={() => fileInputRef.current?.click()}
                className="w-16 h-16 border-2 border-dashed border-gray-200 rounded-xl flex flex-col items-center justify-center text-gray-300 hover:border-red-300 hover:text-red-300 transition-colors"
              >
                <span className="text-2xl leading-none">+</span>
                <span className="text-[9px] mt-0.5">拍照</span>
              </button>
            )}
            <input ref={fileInputRef} type="file" accept="image/*" multiple capture="environment" className="hidden" onChange={handleImageAdd} />
          </div>
        </div>

        {/* Submit */}
        {result && (
          <div className={`rounded-xl px-3 py-2.5 text-sm font-medium ${result.ok ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600'}`}>
            {result.ok ? '✅ ' : '❌ '}{result.msg}
          </div>
        )}

        <button
          onClick={handleSubmit}
          disabled={submitting || !product.trim() || !qty || !finalReason.trim()}
          className="w-full bg-red-500 hover:bg-red-600 disabled:bg-gray-200 text-white rounded-xl py-2.5 text-sm font-semibold transition-colors shadow-sm"
        >
          {submitting ? '提交中...' : '确认录入损耗'}
        </button>
      </div>
    </div>
  )
}

// ─── Tab: 损耗记录 ────────────────────────────────────────────────────────────

function RecordsTab({ token }: { token: string }) {
  const [records, setRecords] = useState<DamageRecord[]>([])
  const [loading, setLoading] = useState(true)
  const [filterDate, setFilterDate] = useState('')
  const [expandedId, setExpandedId] = useState<number | null>(null)
  const [imgModal, setImgModal] = useState<string | null>(null)

  const BASE_URL = API_URL.replace('/api', '')

  const load = async () => {
    setLoading(true)
    try {
      const res = await damageApi.list(token, filterDate ? { date: filterDate } : undefined)
      setRecords(res.data ?? [])
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => { load() }, [filterDate]) // eslint-disable-line react-hooks/exhaustive-deps

  return (
    <div className="space-y-3 p-4">
      <div className="flex gap-2 items-center">
        <input
          type="date"
          value={filterDate}
          max={todayStr()}
          onChange={e => setFilterDate(e.target.value)}
          className="flex-1 bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300"
        />
        {filterDate && (
          <button onClick={() => setFilterDate('')} className="text-xs text-gray-400 hover:text-gray-600 px-2 py-2 rounded-lg hover:bg-gray-100 transition-colors">
            清除
          </button>
        )}
      </div>

      {loading ? (
        <div className="text-center py-12 text-gray-300 text-sm">加载中...</div>
      ) : records.length === 0 ? (
        <div className="text-center py-12 text-gray-300 text-sm">暂无损耗记录</div>
      ) : (
        <div className="space-y-2.5">
          {records.map(rec => {
            const st = DAMAGE_STATUS_MAP[rec.status] ?? { label: String(rec.status), cls: 'bg-gray-100 text-gray-400 border-gray-200' }
            const isExpanded = expandedId === rec.id
            return (
              <div key={rec.id} className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <button
                  onClick={() => setExpandedId(isExpanded ? null : rec.id)}
                  className="w-full text-left px-4 py-3 flex items-center gap-3"
                >
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 flex-wrap">
                      <span className="font-semibold text-gray-800 text-sm">{rec.product?.name ?? '-'}</span>
                      <span className={`text-[10px] px-2 py-0.5 rounded-full font-medium border ${st.cls}`}>{st.label}</span>
                    </div>
                    <div className="text-xs text-gray-400 mt-0.5 truncate">
                      {rec.qty}{rec.product?.unit ?? ''} · {rec.reason} · {rec.occurred_at?.slice(0, 10)}
                    </div>
                  </div>
                  {rec.total_claimed != null && (
                    <div className="text-right shrink-0">
                      <div className="text-sm font-bold text-red-500 tabular-nums">¥{Number(rec.total_claimed).toFixed(2)}</div>
                      <div className="text-[10px] text-gray-300">索赔金额</div>
                    </div>
                  )}
                  <span className={`text-gray-300 text-sm transition-transform ml-1 ${isExpanded ? 'rotate-90' : ''}`}>›</span>
                </button>

                {isExpanded && (
                  <div className="px-4 pb-4 space-y-3 border-t border-gray-50 pt-3">
                    <div className="grid grid-cols-2 gap-2 text-xs">
                      {rec.supplier && (
                        <div className="bg-gray-50 rounded-xl px-3 py-2">
                          <div className="text-gray-400 mb-0.5">关联供应商</div>
                          <div className="font-medium text-gray-700">{rec.supplier.name}</div>
                        </div>
                      )}
                      {rec.unit_cost != null && (
                        <div className="bg-gray-50 rounded-xl px-3 py-2">
                          <div className="text-gray-400 mb-0.5">进价</div>
                          <div className="font-medium text-gray-700 tabular-nums">¥{Number(rec.unit_cost).toFixed(2)}/单位</div>
                        </div>
                      )}
                      {rec.operator && (
                        <div className="bg-gray-50 rounded-xl px-3 py-2">
                          <div className="text-gray-400 mb-0.5">操作人</div>
                          <div className="font-medium text-gray-700">{rec.operator.name}</div>
                        </div>
                      )}
                    </div>

                    {rec.notes && (
                      <p className="text-xs text-gray-500 bg-amber-50 rounded-xl px-3 py-2">{rec.notes}</p>
                    )}

                    {rec.image_paths && rec.image_paths.length > 0 && (
                      <div>
                        <div className="text-xs text-gray-400 mb-1.5">损耗照片</div>
                        <div className="flex flex-wrap gap-2">
                          {rec.image_paths.map((path, i) => (
                            <button key={i} onClick={() => setImgModal(`${BASE_URL}${path}`)}>
                              <img
                                src={`${BASE_URL}${path}`}
                                alt=""
                                className="w-16 h-16 object-cover rounded-xl border border-gray-200 hover:opacity-80 transition-opacity"
                              />
                            </button>
                          ))}
                        </div>
                      </div>
                    )}
                  </div>
                )}
              </div>
            )
          })}
        </div>
      )}

      {/* Image modal */}
      {imgModal && (
        <div
          className="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4"
          onClick={() => setImgModal(null)}
        >
          <img src={imgModal} alt="" className="max-w-full max-h-full rounded-2xl object-contain" />
        </div>
      )}
    </div>
  )
}

// ─── Tab: 退款申请 ────────────────────────────────────────────────────────────

function RefundTab({ token }: { token: string }) {
  const [stats, setStats] = useState<DamageStats | null>(null)
  const [pendingRecords, setPendingRecords] = useState<DamageRecord[]>([])
  const [claims, setClaims] = useState<SupplierRefundClaim[]>([])
  const [loading, setLoading] = useState(true)
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set())
  const [submitting, setSubmitting] = useState(false)
  const [statusUpdating, setStatusUpdating] = useState<number | null>(null)
  const [activeSupplier, setActiveSupplier] = useState<number | null>(null)
  const [subTab, setSubTab] = useState<'create' | 'list'>('create')

  const load = async () => {
    setLoading(true)
    try {
      const [statsRes, pendingRes, claimsRes] = await Promise.all([
        damageApi.stats(token, { from: new Date(Date.now() - 30 * 86400000).toISOString().slice(0, 10), to: todayStr() }),
        damageApi.list(token, { status: 1 }),
        refundClaimApi.list(token),
      ])
      setStats(statsRes.data)
      setPendingRecords(pendingRes.data ?? [])
      setClaims(claimsRes.data ?? [])
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => { load() }, []) // eslint-disable-line react-hooks/exhaustive-deps

  const toggleRecord = (id: number) => {
    setSelectedIds(prev => {
      const next = new Set(prev)
      if (next.has(id)) {
        next.delete(id)
      } else {
        next.add(id)
      }
      return next
    })
  }

  // Group pending records by supplier
  const bySupplier = pendingRecords.reduce<Record<number, { name: string; records: DamageRecord[] }>>((acc, rec) => {
    if (!rec.supplier_id || !rec.supplier) return acc
    if (!acc[rec.supplier_id]) {
      acc[rec.supplier_id] = { name: rec.supplier.name, records: [] }
    }
    acc[rec.supplier_id].records.push(rec)
    return acc
  }, {})

  const noSupplierRecords = pendingRecords.filter(r => !r.supplier_id)

  const selectedForSupplier = activeSupplier
    ? pendingRecords.filter(r => r.supplier_id === activeSupplier && selectedIds.has(r.id))
    : []

  const handleCreateClaim = async () => {
    if (!activeSupplier || selectedForSupplier.length === 0) return
    setSubmitting(true)
    try {
      await refundClaimApi.create({
        supplier_id: activeSupplier,
        damage_record_ids: selectedForSupplier.map(r => r.id),
      }, token)
      setSelectedIds(new Set())
      setActiveSupplier(null)
      await load()
      setSubTab('list')
    } catch (e) {
      alert(e instanceof Error ? e.message : '创建失败，请重试')
    } finally {
      setSubmitting(false)
    }
  }

  const handleUpdateStatus = async (claimId: number, status: RefundClaimStatus) => {
    setStatusUpdating(claimId)
    try {
      await refundClaimApi.updateStatus(claimId, status, token)
      await load()
    } catch (e) {
      alert(e instanceof Error ? e.message : '更新失败')
    } finally {
      setStatusUpdating(null)
    }
  }

  if (loading) {
    return <div className="text-center py-12 text-gray-300 text-sm p-4">加载中...</div>
  }

  return (
    <div className="space-y-3 p-4">
      {/* Stats summary */}
      {stats && (
        <div className="grid grid-cols-3 gap-2">
          <div className="bg-white rounded-2xl border border-gray-100 p-3 text-center shadow-sm">
            <div className="text-lg font-bold text-red-500 tabular-nums">{stats.total_qty.toFixed(0)}</div>
            <div className="text-[10px] text-gray-400 mt-0.5">近30天损耗量</div>
          </div>
          <div className="bg-white rounded-2xl border border-gray-100 p-3 text-center shadow-sm">
            <div className="text-lg font-bold text-red-500 tabular-nums">¥{stats.total_claimed.toFixed(0)}</div>
            <div className="text-[10px] text-gray-400 mt-0.5">可索赔金额</div>
          </div>
          <div className="bg-white rounded-2xl border border-gray-100 p-3 text-center shadow-sm">
            <div className="text-lg font-bold text-amber-500 tabular-nums">{stats.pending_claims_count}</div>
            <div className="text-[10px] text-gray-400 mt-0.5">待提交条目</div>
          </div>
        </div>
      )}

      {/* Sub-tabs */}
      <div className="flex rounded-xl overflow-hidden border border-gray-100">
        <button
          onClick={() => setSubTab('create')}
          className={`flex-1 py-2 text-xs font-semibold transition-colors ${subTab === 'create' ? 'bg-red-500 text-white' : 'bg-white text-gray-400 hover:bg-gray-50'}`}
        >
          生成申请单
        </button>
        <button
          onClick={() => setSubTab('list')}
          className={`flex-1 py-2 text-xs font-semibold transition-colors ${subTab === 'list' ? 'bg-red-500 text-white' : 'bg-white text-gray-400 hover:bg-gray-50'}`}
        >
          申请单列表 {claims.length > 0 && <span className={`ml-1 px-1.5 py-0.5 rounded-full text-[10px] ${subTab === 'list' ? 'bg-red-400' : 'bg-gray-100 text-gray-500'}`}>{claims.length}</span>}
        </button>
      </div>

      {subTab === 'create' ? (
        <div className="space-y-3">
          {Object.keys(bySupplier).length === 0 && noSupplierRecords.length === 0 ? (
            <div className="text-center py-12 text-gray-300 text-sm bg-white rounded-2xl border border-gray-100">
              <div className="text-3xl mb-2">✅</div>
              <p>暂无待提交的损耗记录</p>
            </div>
          ) : (
            <>
              {/* Supplier grouped records */}
              {Object.entries(bySupplier).map(([suppId, { name, records }]) => {
                const sId = Number(suppId)
                const isActive = activeSupplier === sId
                const selectedCount = records.filter(r => selectedIds.has(r.id)).length
                const totalClaimed = records.reduce((s, r) => s + Number(r.total_claimed ?? 0), 0)

                return (
                  <div key={sId} className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                    <button
                      onClick={() => setActiveSupplier(isActive ? null : sId)}
                      className="w-full text-left px-4 py-3 flex items-center justify-between"
                    >
                      <div>
                        <div className="font-semibold text-sm text-gray-800">{name}</div>
                        <div className="text-xs text-gray-400 mt-0.5">{records.length} 条损耗 · 可索赔 ¥{totalClaimed.toFixed(2)}</div>
                      </div>
                      <div className="flex items-center gap-2">
                        {selectedCount > 0 && isActive && (
                          <span className="text-[10px] bg-red-100 text-red-600 px-2 py-0.5 rounded-full font-bold">已选 {selectedCount}</span>
                        )}
                        <span className={`text-gray-300 text-sm transition-transform ${isActive ? 'rotate-90' : ''}`}>›</span>
                      </div>
                    </button>

                    {isActive && (
                      <div className="border-t border-gray-50 px-4 pb-3 space-y-2 pt-3">
                        {records.map(rec => (
                          <label key={rec.id} className="flex items-start gap-3 cursor-pointer group">
                            <input
                              type="checkbox"
                              checked={selectedIds.has(rec.id)}
                              onChange={() => toggleRecord(rec.id)}
                              className="mt-0.5 accent-red-500"
                            />
                            <div className="flex-1 min-w-0">
                              <div className="text-sm font-medium text-gray-700 group-hover:text-red-600 transition-colors">
                                {rec.product?.name} — {rec.qty}{rec.product?.unit} · {rec.reason}
                              </div>
                              <div className="text-xs text-gray-400">{rec.occurred_at?.slice(0, 10)}{rec.total_claimed != null ? ` · 索赔 ¥${Number(rec.total_claimed).toFixed(2)}` : ''}</div>
                            </div>
                          </label>
                        ))}

                        {selectedCount > 0 && (
                          <div className="pt-2 flex justify-end">
                            <button
                              onClick={handleCreateClaim}
                              disabled={submitting}
                              className="bg-red-500 hover:bg-red-600 disabled:bg-gray-200 text-white text-xs font-semibold px-4 py-2 rounded-xl transition-colors shadow-sm"
                            >
                              {submitting ? '生成中...' : `生成退款申请单（${selectedCount} 条）`}
                            </button>
                          </div>
                        )}
                      </div>
                    )}
                  </div>
                )
              })}

              {/* No-supplier records info */}
              {noSupplierRecords.length > 0 && (
                <div className="bg-amber-50 rounded-2xl border border-amber-100 px-4 py-3">
                  <p className="text-xs text-amber-600 font-semibold mb-1">{noSupplierRecords.length} 条记录未关联供应商</p>
                  <p className="text-xs text-amber-500">这些损耗无法生成退款申请，可能是库存调整或无对应进货单。</p>
                  <div className="mt-2 space-y-1">
                    {noSupplierRecords.map(r => (
                      <div key={r.id} className="text-xs text-amber-600">
                        {r.product?.name} — {r.qty}{r.product?.unit} · {r.reason} · {r.occurred_at?.slice(0, 10)}
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </>
          )}
        </div>
      ) : (
        <div className="space-y-2.5">
          {claims.length === 0 ? (
            <div className="text-center py-12 text-gray-300 text-sm bg-white rounded-2xl border border-gray-100">暂无申请单记录</div>
          ) : (
            claims.map(claim => {
              const st = CLAIM_STATUS_MAP[claim.status] ?? { label: String(claim.status), cls: 'bg-gray-100 text-gray-400' }
              return (
                <div key={claim.id} className="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 space-y-3">
                  <div className="flex items-start justify-between gap-2">
                    <div>
                      <div className="font-semibold text-sm text-gray-800">{claim.claim_no}</div>
                      <div className="text-xs text-gray-400 mt-0.5">{claim.supplier?.name ?? '未知供应商'}</div>
                    </div>
                    <span className={`text-[10px] px-2 py-0.5 rounded-full font-medium border shrink-0 ${st.cls}`}>{st.label}</span>
                  </div>

                  <div className="grid grid-cols-3 gap-2 text-center">
                    <div className="bg-gray-50 rounded-xl py-2">
                      <div className="text-sm font-bold text-gray-700 tabular-nums">{claim.total_items}</div>
                      <div className="text-[10px] text-gray-400">品类</div>
                    </div>
                    <div className="bg-gray-50 rounded-xl py-2">
                      <div className="text-sm font-bold text-gray-700 tabular-nums">{Number(claim.total_qty).toFixed(1)}</div>
                      <div className="text-[10px] text-gray-400">总数量</div>
                    </div>
                    <div className="bg-red-50 rounded-xl py-2">
                      <div className="text-sm font-bold text-red-600 tabular-nums">¥{Number(claim.total_amount).toFixed(2)}</div>
                      <div className="text-[10px] text-red-400">申请金额</div>
                    </div>
                  </div>

                  <div className="text-xs text-gray-300 tabular-nums">
                    创建于 {claim.created_at?.slice(0, 10)}
                    {claim.submitted_at && ` · 提交于 ${claim.submitted_at.slice(0, 10)}`}
                    {claim.resolved_at && ` · 结案于 ${claim.resolved_at.slice(0, 10)}`}
                  </div>

                  {/* Status action buttons */}
                  {claim.status === 1 && (
                    <button
                      onClick={() => handleUpdateStatus(claim.id, 2)}
                      disabled={statusUpdating === claim.id}
                      className="w-full bg-blue-500 hover:bg-blue-600 disabled:bg-gray-200 text-white text-xs font-semibold py-2 rounded-xl transition-colors"
                    >
                      {statusUpdating === claim.id ? '处理中...' : '提交给供应商'}
                    </button>
                  )}
                  {claim.status === 2 && (
                    <div className="grid grid-cols-2 gap-2">
                      <button
                        onClick={() => handleUpdateStatus(claim.id, 4)}
                        disabled={statusUpdating === claim.id}
                        className="bg-emerald-500 hover:bg-emerald-600 disabled:bg-gray-200 text-white text-xs font-semibold py-2 rounded-xl transition-colors"
                      >
                        {statusUpdating === claim.id ? '...' : '标记已退款'}
                      </button>
                      <button
                        onClick={() => handleUpdateStatus(claim.id, 5)}
                        disabled={statusUpdating === claim.id}
                        className="bg-gray-200 hover:bg-gray-300 disabled:bg-gray-100 text-gray-600 text-xs font-semibold py-2 rounded-xl transition-colors"
                      >
                        {statusUpdating === claim.id ? '...' : '供应商拒绝'}
                      </button>
                    </div>
                  )}
                </div>
              )
            })
          )}
        </div>
      )}
    </div>
  )
}

// ─── Main Page ────────────────────────────────────────────────────────────────

type Tab = 'entry' | 'records' | 'refund'

export default function DamagePage() {
  const { token, isAuthenticated, loading } = useAuth()
  const router = useRouter()
  const [tab, setTab] = useState<Tab>('entry')

  useEffect(() => {
    if (!loading && !isAuthenticated) {
      router.push('/login')
    }
  }, [loading, isAuthenticated, router])

  if (loading || !isAuthenticated || !token) {
    return (
      <div className="flex items-center justify-center h-screen bg-[#f5f4f0]">
        <div className="flex flex-col items-center gap-3">
          <div className="w-12 h-12 rounded-2xl bg-red-500 flex items-center justify-center text-2xl shadow-lg">⚠️</div>
          <p className="text-gray-400 text-sm">加载中...</p>
        </div>
      </div>
    )
  }

  const TABS: { id: Tab; label: string; emoji: string }[] = [
    { id: 'entry',   label: '录入',    emoji: '✍️' },
    { id: 'records', label: '记录',    emoji: '📋' },
    { id: 'refund',  label: '退款申请', emoji: '💰' },
  ]

  return (
    <div className="min-h-screen bg-[#f5f4f0] max-w-2xl mx-auto pb-24">
      {/* Header */}
      <div className="bg-white px-4 py-3 flex items-center justify-between shadow-[0_1px_0_0_#e5e3dc] sticky top-0 z-10">
        <div className="flex items-center gap-2.5">
          <button onClick={() => router.back()} className="text-gray-400 hover:text-gray-600 w-8 h-8 flex items-center justify-center rounded-xl hover:bg-gray-100 transition-colors">
            ‹
          </button>
          <div>
            <div className="text-sm font-bold text-gray-800 leading-tight">损耗管理</div>
            <div className="text-[10px] text-gray-400 leading-tight">记录 · 统计 · 索赔</div>
          </div>
        </div>
        <a href="/manage" className="text-orange-500 hover:text-orange-600 text-xs font-medium px-2 py-1 rounded-lg hover:bg-orange-50 transition-colors">
          返回助手
        </a>
      </div>

      {/* Tabs */}
      <div className="bg-white border-b border-gray-100 px-4 flex gap-0">
        {TABS.map(t => (
          <button
            key={t.id}
            onClick={() => setTab(t.id)}
            className={`flex-1 py-3 text-xs font-semibold transition-colors relative ${
              tab === t.id
                ? 'text-red-600'
                : 'text-gray-400 hover:text-gray-600'
            }`}
          >
            <span className="flex items-center justify-center gap-1">
              <span>{t.emoji}</span>
              <span>{t.label}</span>
            </span>
            {tab === t.id && (
              <span className="absolute bottom-0 left-0 right-0 h-0.5 bg-red-500 rounded-t-full" />
            )}
          </button>
        ))}
      </div>

      {/* Tab Content */}
      <div className="pb-8">
        {tab === 'entry'   && <EntryTab token={token} />}
        {tab === 'records' && <RecordsTab token={token} />}
        {tab === 'refund'  && <RefundTab token={token} />}
      </div>
    </div>
  )
}
