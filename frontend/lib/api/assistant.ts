import { apiClient } from './client'

export interface SendMessageParams {
  text?: string
  image_base64?: string
  session_id?: number
}

export interface AiOperation {
  product_id: number
  product_name: string
  action: string
  qty: number
  unit: string
  qty_before: number
  qty_after: number
}

export interface AiMessageResponse {
  reply: string
  intent: string
  operations: AiOperation[]
  session_id: number
  transcribed_text?: string
  card_type?: string
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  card_data?: any
}

const API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api'

export const assistantApi = {
  sendMessage: (params: SendMessageParams, token: string) =>
    apiClient.post<AiMessageResponse>('/ai/message', params, token),

  sendVoice: async (audioBlob: Blob, token: string): Promise<AiMessageResponse> => {
    const formData = new FormData()
    formData.append('audio', audioBlob, 'recording.webm')
    const response = await fetch(`${API_URL}/ai/voice`, {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: 'application/json',
      },
      body: formData,
    })
    if (!response.ok) {
      const err = await response.json().catch(() => ({ message: response.statusText }))
      throw new Error(err.message || '语音上传失败')
    }
    return response.json()
  },

  getSessions: (token: string) =>
    apiClient.get<{ data: unknown[] }>('/ai/sessions', token),

  getSessionMessages: (sessionId: number, token: string) =>
    apiClient.get<unknown[]>(`/ai/sessions/${sessionId}/messages`, token),
}
