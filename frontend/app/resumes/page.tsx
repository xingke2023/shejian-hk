'use client'

import { useEffect, useState, useRef } from 'react'
import { useRouter } from 'next/navigation'
import { useAuth } from '@/lib/auth-context'
import { resumeApi, type Resume, type SearchResult } from '@/lib/api/resumes'

const WORK_TYPE_COLOR: Record<string, string> = {
  全职: 'bg-blue-100 text-blue-700',
  兼职: 'bg-purple-100 text-purple-700',
  小时工: 'bg-amber-100 text-amber-700',
}

const STATUS_LABEL: Record<number, { label: string; color: string }> = {
  0: { label: '无效', color: 'bg-gray-100 text-gray-500' },
  1: { label: '求职中', color: 'bg-green-100 text-green-700' },
  2: { label: '已入职', color: 'bg-blue-100 text-blue-700' },
  3: { label: '暂不求职', color: 'bg-orange-100 text-orange-700' },
}

function salaryText(r: Resume): string {
  if (!r.salary_min && !r.salary_max) return '薪资面议'
  const unit = r.salary_unit === 1 ? '/月' : r.salary_unit === 2 ? '/日' : '/时'
  if (r.salary_min && r.salary_max) return `¥${r.salary_min}~${r.salary_max}${unit}`
  return `¥${r.salary_min ?? r.salary_max}${unit}`
}

function ResumeCard({ resume }: { resume: Resume }) {
  const status = STATUS_LABEL[resume.status] ?? STATUS_LABEL[1]

  return (
    <div className="bg-white rounded-xl border shadow-sm p-4 space-y-3">
      <div className="flex items-start justify-between">
        <div>
          <div className="flex items-center gap-2">
            <span className="font-semibold text-gray-800 text-base">
              {resume.name ?? '姓名未知'}
            </span>
            {resume.gender === 1 && <span className="text-xs text-blue-500">♂ 男</span>}
            {resume.gender === 2 && <span className="text-xs text-pink-500">♀ 女</span>}
            {resume.age && <span className="text-xs text-gray-400">{resume.age}岁</span>}
          </div>
          {resume.phone && (
            <div className="text-sm text-gray-500 mt-0.5">{resume.phone}</div>
          )}
        </div>
        <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${status.color}`}>
          {status.label}
        </span>
      </div>

      {/* 意向岗位 */}
      {resume.positions && resume.positions.length > 0 && (
        <div className="flex flex-wrap gap-1.5">
          {resume.positions.map((p) => (
            <span key={p} className="text-xs bg-gray-100 text-gray-700 px-2 py-0.5 rounded-full">
              {p}
            </span>
          ))}
        </div>
      )}

      {/* 区域 & 工作类型 */}
      <div className="flex flex-wrap items-center gap-1.5">
        {resume.districts?.map((d) => (
          <span key={d} className="text-xs bg-cyan-50 text-cyan-700 px-2 py-0.5 rounded-full">
            📍 {d}
          </span>
        ))}
        {resume.work_types?.map((wt) => (
          <span
            key={wt}
            className={`text-xs px-2 py-0.5 rounded-full font-medium ${WORK_TYPE_COLOR[wt] ?? 'bg-gray-100 text-gray-600'}`}
          >
            {wt}
          </span>
        ))}
      </div>

      {/* 经验 + 薪资 */}
      <div className="flex items-center gap-3 text-sm text-gray-500">
        {resume.experience_years != null && (
          <span>经验 {resume.experience_years} 年</span>
        )}
        <span>{salaryText(resume)}</span>
        {resume.languages && resume.languages.length > 0 && (
          <span>{resume.languages.join(' · ')}</span>
        )}
      </div>

      {/* 技能 */}
      {resume.skills && resume.skills.length > 0 && (
        <div className="flex flex-wrap gap-1">
          {resume.skills.map((s) => (
            <span key={s} className="text-xs border border-gray-200 text-gray-600 px-1.5 py-0.5 rounded">
              {s}
            </span>
          ))}
        </div>
      )}
    </div>
  )
}

export default function ResumesPage() {
  const { token, isAuthenticated, loading } = useAuth()
  const router = useRouter()

  const [query, setQuery] = useState('')
  const [searching, setSearching] = useState(false)
  const [resumes, setResumes] = useState<Resume[]>([])
  const [searchResult, setSearchResult] = useState<SearchResult | null>(null)
  const [mode, setMode] = useState<'all' | 'search'>('all')
  const [fetching, setFetching] = useState(true)
  const inputRef = useRef<HTMLTextAreaElement>(null)

  useEffect(() => {
    if (!loading && !isAuthenticated) router.push('/login')
  }, [loading, isAuthenticated, router])

  useEffect(() => {
    if (!token) return
    loadAll()
  }, [token])

  async function loadAll() {
    setFetching(true)
    try {
      const res = await resumeApi.list(token!)
      setResumes(res.data)
      setMode('all')
    } catch (err) {
      console.error(err)
    } finally {
      setFetching(false)
    }
  }

  async function handleSearch() {
    if (!query.trim() || !token) return
    setSearching(true)
    try {
      const result = await resumeApi.search(query.trim(), token)
      setSearchResult(result)
      setResumes(result.data)
      setMode('search')
    } catch (err) {
      console.error(err)
    } finally {
      setSearching(false)
    }
  }

  function handleKeyDown(e: React.KeyboardEvent) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault()
      handleSearch()
    }
  }

  if (loading || (fetching && resumes.length === 0)) {
    return <div className="flex items-center justify-center h-screen text-gray-400">加载中...</div>
  }

  const displayResumes = mode === 'search' ? resumes : resumes

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-white border-b px-4 py-3 flex items-center justify-between sticky top-0 z-10">
        <h1 className="text-lg font-semibold text-gray-800">人才简历库</h1>
        <button
          onClick={() => router.push('/resumes/upload')}
          className="text-sm bg-blue-500 text-white px-3 py-1.5 rounded-lg"
        >
          + 录入简历
        </button>
      </div>

      {/* 搜索框 */}
      <div className="bg-white border-b px-4 py-3 space-y-2">
        <div className="relative">
          <textarea
            ref={inputRef}
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            onKeyDown={handleKeyDown}
            placeholder={'用自然语言描述需求，例如：\n「找几个筲箕湾附近能做小时工的人」'}
            rows={2}
            className="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-blue-400 bg-gray-50"
          />
        </div>
        <div className="flex gap-2">
          <button
            onClick={handleSearch}
            disabled={searching || !query.trim()}
            className="flex-1 bg-blue-500 text-white py-2 rounded-xl text-sm font-medium disabled:opacity-50"
          >
            {searching ? '搜索中...' : '智能搜索'}
          </button>
          <button
            onClick={() => { setQuery(''); loadAll() }}
            className="px-4 py-2 border rounded-xl text-sm text-gray-600"
          >
            全部
          </button>
        </div>

        {/* 搜索条件展示 */}
        {mode === 'search' && searchResult && (
          <div className="text-xs text-gray-400 pt-1">
            找到 {searchResult.total} 条结果
            {searchResult.criteria.districts.length > 0 && (
              <> · 区域：{searchResult.criteria.districts.join('、')}</>
            )}
            {searchResult.criteria.work_types.length > 0 && (
              <> · 类型：{searchResult.criteria.work_types.join('、')}</>
            )}
          </div>
        )}
      </div>

      {/* 列表 */}
      <div className="p-4 space-y-3">
        {mode === 'all' && (
          <div className="text-xs text-gray-400 mb-1">全部简历（{resumes.length} 份）</div>
        )}

        {displayResumes.length === 0 ? (
          <div className="text-center py-16 text-gray-400">
            <div className="text-4xl mb-3">👤</div>
            <p>{mode === 'search' ? '没有匹配的简历' : '暂无简历'}</p>
            <button
              onClick={() => router.push('/resumes/upload')}
              className="mt-4 inline-block bg-blue-500 text-white px-4 py-2 rounded-lg text-sm"
            >
              去录入
            </button>
          </div>
        ) : (
          displayResumes.map((r) => <ResumeCard key={r.id} resume={r} />)
        )}
      </div>
    </div>
  )
}
