import { apiClient } from './client'

const API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api'

export interface Resume {
  id: number
  name: string | null
  phone: string | null
  gender: 0 | 1 | 2
  age: number | null
  districts: string[] | null
  work_types: string[] | null
  positions: string[] | null
  experience_years: number | null
  salary_min: number | null
  salary_max: number | null
  salary_unit: 1 | 2 | 3
  education: 1 | 2 | 3 | 4 | null
  availability_date: string | null
  languages: string[] | null
  skills: string[] | null
  raw_text: string | null
  source: 1 | 2 | 3
  status: 0 | 1 | 2 | 3
  notes: string | null
  created_at: string
}

export interface ParsedResume extends Omit<Resume, 'id' | 'source' | 'status' | 'created_at'> {}

export interface SearchResult {
  data: Resume[]
  criteria: {
    districts: string[]
    work_types: string[]
    positions: string[]
    keywords: string[]
  }
  total: number
}

export interface BatchResult {
  total: number
  success: number
  failed: number
  results: Array<{ status: 'ok' | 'failed'; id?: number; name?: string; reason?: string }>
}

export const resumeApi = {
  list: (token: string, params?: Record<string, string>) => {
    const qs = params ? '?' + new URLSearchParams(params).toString() : ''
    return apiClient.get<{ data: Resume[]; total: number }>(`/resumes${qs}`, token)
  },

  parse: (params: { text: string; image_base64?: string }, token: string) =>
    apiClient.post<{ data: ParsedResume }>('/resumes/parse', params, token),

  create: (data: Partial<Resume> & { raw_text?: string | null; source?: number }, token: string) =>
    apiClient.post<{ data: Resume }>('/resumes', data, token),

  update: (id: number, data: Partial<Resume>, token: string) =>
    apiClient.put<{ data: Resume }>(`/resumes/${id}`, data, token),

  search: (q: string, token: string) =>
    apiClient.get<SearchResult>(`/resumes/search?q=${encodeURIComponent(q)}`, token),

  batch: async (
    items: Array<{ text: string; image_base64?: string }>,
    token: string,
  ): Promise<BatchResult> => {
    const response = await fetch(`${API_URL}/resumes/batch`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        Authorization: `Bearer ${token}`,
      },
      body: JSON.stringify({ items }),
    })
    return response.json()
  },

  destroy: (id: number, token: string) =>
    apiClient.delete<{ message: string }>(`/resumes/${id}`, token),
}
