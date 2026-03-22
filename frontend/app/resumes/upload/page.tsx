'use client'

import { useState, useRef } from 'react'
import { useRouter } from 'next/navigation'
import { useAuth } from '@/lib/auth-context'
import { resumeApi, type ParsedResume } from '@/lib/api/resumes'

type TabId = 'single' | 'batch' | 'image'

const FIELD_LABELS: Record<string, string> = {
  name: '姓名',
  phone: '电话',
  age: '年龄',
  districts: '意向区域',
  work_types: '工作类型',
  positions: '意向岗位',
  experience_years: '工作经验（年）',
  salary_min: '薪资下限',
  salary_max: '薪资上限',
  salary_unit: '薪资单位',
  languages: '语言',
  skills: '技能',
  notes: '备注',
}

const SALARY_UNIT_LABEL: Record<number, string> = { 1: '元/月', 2: '元/日', 3: '元/小时' }

function PreviewCard({
  parsed,
  onConfirm,
  onReset,
  saving,
}: {
  parsed: ParsedResume
  onConfirm: () => void
  onReset: () => void
  saving: boolean
}) {
  return (
    <div className="bg-white rounded-xl border p-4 space-y-3">
      <div className="text-sm font-medium text-gray-700 border-b pb-2">解析结果预览</div>

      <div className="space-y-2 text-sm">
        {Object.entries(FIELD_LABELS).map(([key, label]) => {
          const val = (parsed as Record<string, unknown>)[key]
          if (!val && val !== 0) return null

          let display: string
          if (Array.isArray(val)) {
            display = val.join(' · ')
          } else if (key === 'salary_unit') {
            display = SALARY_UNIT_LABEL[val as number] ?? String(val)
          } else {
            display = String(val)
          }

          return (
            <div key={key} className="flex">
              <span className="text-gray-400 w-28 shrink-0">{label}</span>
              <span className="text-gray-800">{display}</span>
            </div>
          )
        })}
      </div>

      <div className="flex gap-2 pt-2">
        <button
          onClick={onConfirm}
          disabled={saving}
          className="flex-1 bg-blue-500 text-white py-2 rounded-xl text-sm font-medium disabled:opacity-50"
        >
          {saving ? '保存中...' : '确认保存'}
        </button>
        <button
          onClick={onReset}
          className="px-4 py-2 border rounded-xl text-sm text-gray-600"
        >
          重新输入
        </button>
      </div>
    </div>
  )
}

function SingleTab({ token }: { token: string }) {
  const [text, setText] = useState('')
  const [parsing, setParsing] = useState(false)
  const [saving, setSaving] = useState(false)
  const [parsed, setParsed] = useState<ParsedResume | null>(null)
  const [saved, setSaved] = useState(false)

  async function handleParse() {
    if (!text.trim()) return
    setParsing(true)
    try {
      const res = await resumeApi.parse({ text: text.trim() }, token)
      setParsed(res.data)
    } catch (err) {
      console.error(err)
      alert('AI解析失败，请检查网络或API配置')
    } finally {
      setParsing(false)
    }
  }

  async function handleConfirm() {
    if (!parsed) return
    setSaving(true)
    try {
      await resumeApi.create({ ...parsed, raw_text: text, source: 2 }, token)
      setSaved(true)
      setParsed(null)
      setText('')
    } catch (err) {
      console.error(err)
      alert('保存失败')
    } finally {
      setSaving(false)
    }
  }

  if (saved) {
    return (
      <div className="text-center py-10 space-y-3">
        <div className="text-4xl">✅</div>
        <p className="text-gray-700 font-medium">简历已保存</p>
        <button onClick={() => setSaved(false)} className="bg-blue-500 text-white px-4 py-2 rounded-xl text-sm">
          继续录入
        </button>
      </div>
    )
  }

  if (parsed) {
    return (
      <PreviewCard
        parsed={parsed}
        onConfirm={handleConfirm}
        onReset={() => setParsed(null)}
        saving={saving}
      />
    )
  }

  return (
    <div className="space-y-3">
      <p className="text-sm text-gray-500">粘贴或输入简历文字，AI自动解析结构化信息</p>
      <textarea
        value={text}
        onChange={(e) => setText(e.target.value)}
        placeholder={'例如：陈大文，男，28岁，筲箕湾居住\n意向：小时工/兼职，收银员/理货员\n经验：收银3年，超市工作\n薪资：时薪60-80元\n语言：粤语、普通话\n随时可上班'}
        rows={8}
        className="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-blue-400 bg-gray-50"
      />
      <button
        onClick={handleParse}
        disabled={parsing || !text.trim()}
        className="w-full bg-blue-500 text-white py-3 rounded-xl text-sm font-medium disabled:opacity-50"
      >
        {parsing ? 'AI解析中...' : 'AI智能解析'}
      </button>
    </div>
  )
}

function BatchTab({ token }: { token: string }) {
  const [text, setText] = useState('')
  const [processing, setProcessing] = useState(false)
  const [result, setResult] = useState<{ total: number; success: number; failed: number } | null>(null)

  async function handleBatch() {
    const blocks = text.split(/\n---+\n/).map((b) => b.trim()).filter(Boolean)
    if (blocks.length === 0) return
    setProcessing(true)
    try {
      const res = await resumeApi.batch(
        blocks.map((b) => ({ text: b })),
        token,
      )
      setResult(res)
    } catch (err) {
      console.error(err)
      alert('批量处理失败')
    } finally {
      setProcessing(false)
    }
  }

  if (result) {
    return (
      <div className="text-center py-10 space-y-3">
        <div className="text-4xl">📋</div>
        <p className="text-lg font-semibold text-gray-800">批量导入完成</p>
        <div className="flex justify-center gap-6 text-sm">
          <div className="text-center">
            <div className="text-2xl font-bold text-gray-800">{result.total}</div>
            <div className="text-gray-400">共提交</div>
          </div>
          <div className="text-center">
            <div className="text-2xl font-bold text-green-600">{result.success}</div>
            <div className="text-gray-400">成功</div>
          </div>
          <div className="text-center">
            <div className="text-2xl font-bold text-red-500">{result.failed}</div>
            <div className="text-gray-400">失败</div>
          </div>
        </div>
        <button onClick={() => { setResult(null); setText('') }} className="bg-blue-500 text-white px-4 py-2 rounded-xl text-sm">
          继续导入
        </button>
      </div>
    )
  }

  const blockCount = text.split(/\n---+\n/).filter((b) => b.trim()).length

  return (
    <div className="space-y-3">
      <p className="text-sm text-gray-500">
        每份简历之间用 <code className="bg-gray-100 px-1 rounded">---</code> 分隔，一次最多50份
      </p>
      <textarea
        value={text}
        onChange={(e) => setText(e.target.value)}
        placeholder={'张三，男，25岁，柴湾，小时工，收银员，经验2年\n---\n李小红，女，30岁，西湾河，全职，理货员，普通话粤语\n---\n王大明，男，筲箕湾，兼职，生鲜切配...'}
        rows={10}
        className="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-blue-400 bg-gray-50 font-mono"
      />
      {blockCount > 0 && (
        <p className="text-xs text-gray-400">检测到 {blockCount} 份简历</p>
      )}
      <button
        onClick={handleBatch}
        disabled={processing || blockCount === 0}
        className="w-full bg-blue-500 text-white py-3 rounded-xl text-sm font-medium disabled:opacity-50"
      >
        {processing ? `AI批量解析中 (${blockCount}份)...` : `批量解析并导入 (${blockCount}份)`}
      </button>
    </div>
  )
}

function ImageTab({ token }: { token: string }) {
  const fileRef = useRef<HTMLInputElement>(null)
  const [preview, setPreview] = useState<string | null>(null)
  const [base64, setBase64] = useState<string | null>(null)
  const [text, setText] = useState('')
  const [parsing, setParsing] = useState(false)
  const [saving, setSaving] = useState(false)
  const [parsed, setParsed] = useState<ParsedResume | null>(null)
  const [saved, setSaved] = useState(false)

  function handleFile(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0]
    if (!file) return
    const reader = new FileReader()
    reader.onload = (ev) => {
      const result = ev.target?.result as string
      setPreview(result)
      setBase64(result.split(',')[1])
    }
    reader.readAsDataURL(file)
  }

  async function handleParse() {
    if (!base64) return
    setParsing(true)
    try {
      const res = await resumeApi.parse({ text: text || '请识别图片中的简历信息', image_base64: base64 }, token)
      setParsed(res.data)
    } catch (err) {
      console.error(err)
      alert('图片识别失败')
    } finally {
      setParsing(false)
    }
  }

  async function handleConfirm() {
    if (!parsed) return
    setSaving(true)
    try {
      await resumeApi.create({ ...parsed, source: 3 }, token)
      setSaved(true)
      setParsed(null)
      setPreview(null)
      setBase64(null)
      setText('')
    } catch (err) {
      console.error(err)
      alert('保存失败')
    } finally {
      setSaving(false)
    }
  }

  if (saved) {
    return (
      <div className="text-center py-10 space-y-3">
        <div className="text-4xl">✅</div>
        <p className="text-gray-700 font-medium">简历已保存</p>
        <button onClick={() => setSaved(false)} className="bg-blue-500 text-white px-4 py-2 rounded-xl text-sm">
          继续上传
        </button>
      </div>
    )
  }

  if (parsed) {
    return (
      <PreviewCard
        parsed={parsed}
        onConfirm={handleConfirm}
        onReset={() => setParsed(null)}
        saving={saving}
      />
    )
  }

  return (
    <div className="space-y-3">
      <p className="text-sm text-gray-500">上传简历图片，AI自动识别并结构化</p>

      <input
        ref={fileRef}
        type="file"
        accept="image/*"
        onChange={handleFile}
        className="hidden"
      />

      {preview ? (
        <div className="relative">
          <img src={preview} alt="简历预览" className="w-full rounded-xl border object-contain max-h-64" />
          <button
            onClick={() => { setPreview(null); setBase64(null) }}
            className="absolute top-2 right-2 bg-white border rounded-full w-6 h-6 text-gray-500 flex items-center justify-center text-xs shadow"
          >
            ✕
          </button>
        </div>
      ) : (
        <button
          onClick={() => fileRef.current?.click()}
          className="w-full border-2 border-dashed border-gray-300 rounded-xl py-10 text-gray-400 text-sm flex flex-col items-center gap-2"
        >
          <span className="text-3xl">📷</span>
          点击上传简历图片
        </button>
      )}

      {preview && (
        <>
          <textarea
            value={text}
            onChange={(e) => setText(e.target.value)}
            placeholder="可选：补充描述信息（如原始文字）"
            rows={2}
            className="w-full border border-gray-200 rounded-xl px-4 py-2 text-sm resize-none focus:outline-none bg-gray-50"
          />
          <button
            onClick={handleParse}
            disabled={parsing}
            className="w-full bg-blue-500 text-white py-3 rounded-xl text-sm font-medium disabled:opacity-50"
          >
            {parsing ? 'AI识别中...' : 'AI识别解析'}
          </button>
        </>
      )}
    </div>
  )
}

export default function ResumeUploadPage() {
  const { token, isAuthenticated, loading } = useAuth()
  const router = useRouter()
  const [activeTab, setActiveTab] = useState<TabId>('single')

  if (!loading && !isAuthenticated) {
    router.push('/login')
    return null
  }

  if (loading) {
    return <div className="flex items-center justify-center h-screen text-gray-400">加载中...</div>
  }

  const tabs: { id: TabId; label: string }[] = [
    { id: 'single', label: '单份录入' },
    { id: 'batch', label: '批量录入' },
    { id: 'image', label: '图片识别' },
  ]

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-white border-b px-4 py-3 flex items-center gap-3 sticky top-0 z-10">
        <button onClick={() => router.back()} className="text-gray-500 text-lg">←</button>
        <h1 className="text-lg font-semibold text-gray-800">录入简历</h1>
      </div>

      {/* Tabs */}
      <div className="bg-white border-b flex">
        {tabs.map((tab) => (
          <button
            key={tab.id}
            onClick={() => setActiveTab(tab.id)}
            className={`flex-1 py-3 text-sm font-medium transition-colors ${
              activeTab === tab.id
                ? 'text-blue-600 border-b-2 border-blue-600'
                : 'text-gray-500'
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      <div className="p-4">
        {activeTab === 'single' && <SingleTab token={token!} />}
        {activeTab === 'batch' && <BatchTab token={token!} />}
        {activeTab === 'image' && <ImageTab token={token!} />}
      </div>
    </div>
  )
}
