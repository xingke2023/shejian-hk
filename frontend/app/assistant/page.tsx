'use client'

import { useEffect, useRef, useState } from 'react'
import { useRouter } from 'next/navigation'
import { useAuth } from '@/lib/auth-context'
import { assistantApi, type AiMessageResponse, type AiOperation } from '@/lib/api/assistant'

interface Message {
  id: number
  role: 'user' | 'ai'
  content: string
  inputType: 'text' | 'image' | 'voice' | 'mixed'
  operations?: AiOperation[]
  transcribedText?: string
  imagePreview?: string
}

export default function AssistantPage() {
  const { token, isAuthenticated, loading } = useAuth()
  const router = useRouter()

  const [messages, setMessages] = useState<Message[]>([
    {
      id: 0,
      role: 'ai',
      content: '您好！我是AI店长助手。您可以通过文字、图片或语音告诉我进货、库存或损耗情况，我会自动录入系统。',
      inputType: 'text',
    },
  ])
  const [inputText, setInputText] = useState('')
  const [selectedImage, setSelectedImage] = useState<{ base64: string; preview: string } | null>(null)
  const [isRecording, setIsRecording] = useState(false)
  const [isSending, setIsSending] = useState(false)
  const [sessionId, setSessionId] = useState<number | undefined>()
  const [currentSessionId, setCurrentSessionId] = useState<number | undefined>()

  const messagesEndRef = useRef<HTMLDivElement>(null)
  const fileInputRef = useRef<HTMLInputElement>(null)
  const mediaRecorderRef = useRef<MediaRecorder | null>(null)
  const audioChunksRef = useRef<Blob[]>([])

  useEffect(() => {
    if (!loading && !isAuthenticated) {
      router.push('/login')
    }
  }, [loading, isAuthenticated, router])

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [messages])

  const addMessage = (msg: Omit<Message, 'id'>) => {
    setMessages(prev => [...prev, { ...msg, id: Date.now() }])
  }

  const handleSend = async () => {
    if (!token) return
    if (!inputText.trim() && !selectedImage) return
    if (isSending) return

    const text = inputText.trim()
    const image = selectedImage

    setInputText('')
    setSelectedImage(null)
    setIsSending(true)

    addMessage({
      role: 'user',
      content: text || '（图片）',
      inputType: image && text ? 'mixed' : image ? 'image' : 'text',
      imagePreview: image?.preview,
    })

    try {
      const result = await assistantApi.sendMessage(
        { text, image_base64: image?.base64, session_id: currentSessionId },
        token
      )
      setCurrentSessionId(result.session_id)

      addMessage({
        role: 'ai',
        content: result.reply,
        inputType: 'text',
        operations: result.operations,
      })
    } catch {
      addMessage({ role: 'ai', content: '发送失败，请检查网络或重试。', inputType: 'text' })
    } finally {
      setIsSending(false)
    }
  }

  const handleImageSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (!file) return

    const reader = new FileReader()
    reader.onload = () => {
      const dataUrl = reader.result as string
      const base64 = dataUrl.split(',')[1]
      setSelectedImage({ base64, preview: dataUrl })
    }
    reader.readAsDataURL(file)
    e.target.value = ''
  }

  const handleVoiceToggle = async () => {
    if (!token) return

    if (isRecording) {
      mediaRecorderRef.current?.stop()
      setIsRecording(false)
      return
    }

    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true })
      const mediaRecorder = new MediaRecorder(stream)
      mediaRecorderRef.current = mediaRecorder
      audioChunksRef.current = []

      mediaRecorder.ondataavailable = e => {
        if (e.data.size > 0) audioChunksRef.current.push(e.data)
      }

      mediaRecorder.onstop = async () => {
        stream.getTracks().forEach(t => t.stop())
        const audioBlob = new Blob(audioChunksRef.current, { type: 'audio/webm' })

        addMessage({ role: 'user', content: '🎤 语音消息（识别中...）', inputType: 'voice' })
        setIsSending(true)

        try {
          const result = await assistantApi.sendVoice(audioBlob, token)
          setCurrentSessionId(result.session_id)

          setMessages(prev => {
            const updated = [...prev]
            const lastUserIdx = [...updated].reverse().findIndex(m => m.role === 'user')
            if (lastUserIdx !== -1) {
              const realIdx = updated.length - 1 - lastUserIdx
              updated[realIdx] = {
                ...updated[realIdx],
                content: `🎤 ${result.transcribed_text || '语音消息'}`,
              }
            }
            return updated
          })

          addMessage({
            role: 'ai',
            content: result.reply,
            inputType: 'text',
            operations: result.operations,
          })
        } catch {
          addMessage({ role: 'ai', content: '语音识别失败，请重试或改用文字输入。', inputType: 'text' })
        } finally {
          setIsSending(false)
        }
      }

      mediaRecorder.start()
      setIsRecording(true)
    } catch {
      alert('无法访问麦克风，请检查权限设置。')
    }
  }

  if (loading) {
    return <div className="flex items-center justify-center h-screen text-gray-500">加载中...</div>
  }

  return (
    <div className="flex flex-col h-screen bg-gray-50">
      {/* 顶部导航 */}
      <div className="bg-white border-b px-4 py-3 flex items-center justify-between">
        <h1 className="text-lg font-semibold text-gray-800">AI 店长助手</h1>
        <a href="/inventory" className="text-sm text-blue-600 hover:underline">查看库存 →</a>
      </div>

      {/* 消息列表 */}
      <div className="flex-1 overflow-y-auto px-4 py-4 space-y-4">
        {messages.map(msg => (
          <div key={msg.id} className={`flex ${msg.role === 'user' ? 'justify-end' : 'justify-start'}`}>
            {msg.role === 'ai' && (
              <div className="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center text-white text-xs mr-2 shrink-0 mt-1">
                AI
              </div>
            )}
            <div className={`max-w-[80%] space-y-2`}>
              {/* 图片预览 */}
              {msg.imagePreview && (
                <div className={`flex ${msg.role === 'user' ? 'justify-end' : 'justify-start'}`}>
                  <img src={msg.imagePreview} alt="上传图片" className="max-w-48 max-h-48 rounded-lg object-cover" />
                </div>
              )}

              {/* 消息气泡 */}
              <div
                className={`px-4 py-3 rounded-2xl text-sm leading-relaxed ${
                  msg.role === 'user'
                    ? 'bg-blue-500 text-white rounded-br-sm'
                    : 'bg-white text-gray-800 shadow-sm border rounded-bl-sm'
                }`}
              >
                {msg.content}
              </div>

              {/* 操作摘要卡片 */}
              {msg.operations && msg.operations.length > 0 && (
                <div className="bg-green-50 border border-green-200 rounded-xl px-4 py-3 text-sm">
                  <div className="text-green-700 font-medium mb-2">✓ 已录入库存</div>
                  {msg.operations.map((op, i) => (
                    <div key={i} className="flex items-center gap-2 text-green-600 text-xs">
                      <span className={`px-1.5 py-0.5 rounded text-white text-xs ${
                        op.action === 'in' ? 'bg-green-500' :
                        op.action === 'out' ? 'bg-red-400' : 'bg-yellow-500'
                      }`}>
                        {op.action === 'in' ? '入' : op.action === 'out' ? '出' : '调'}
                      </span>
                      <span className="font-medium">{op.product_name}</span>
                      <span>{op.qty}{op.unit}</span>
                      <span className="text-gray-400">{op.qty_before} → {op.qty_after}{op.unit}</span>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        ))}

        {isSending && (
          <div className="flex justify-start">
            <div className="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center text-white text-xs mr-2 shrink-0">
              AI
            </div>
            <div className="bg-white shadow-sm border px-4 py-3 rounded-2xl rounded-bl-sm">
              <div className="flex gap-1 items-center">
                <span className="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style={{ animationDelay: '0ms' }} />
                <span className="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style={{ animationDelay: '150ms' }} />
                <span className="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style={{ animationDelay: '300ms' }} />
              </div>
            </div>
          </div>
        )}
        <div ref={messagesEndRef} />
      </div>

      {/* 图片预览条 */}
      {selectedImage && (
        <div className="bg-white border-t px-4 py-2 flex items-center gap-3">
          <img src={selectedImage.preview} alt="预览" className="w-14 h-14 object-cover rounded-lg border" />
          <span className="text-sm text-gray-500 flex-1">图片已选择</span>
          <button onClick={() => setSelectedImage(null)} className="text-gray-400 hover:text-gray-600 text-lg">×</button>
        </div>
      )}

      {/* 输入区 */}
      <div className="bg-white border-t px-4 py-3">
        <div className="flex items-end gap-2">
          {/* 图片按钮 */}
          <button
            onClick={() => fileInputRef.current?.click()}
            className="w-10 h-10 flex items-center justify-center rounded-full bg-gray-100 hover:bg-gray-200 text-gray-600 shrink-0"
            title="上传图片"
          >
            📷
          </button>
          <input ref={fileInputRef} type="file" accept="image/*" className="hidden" onChange={handleImageSelect} />

          {/* 语音按钮 */}
          <button
            onClick={handleVoiceToggle}
            className={`w-10 h-10 flex items-center justify-center rounded-full shrink-0 transition-colors ${
              isRecording ? 'bg-red-500 text-white animate-pulse' : 'bg-gray-100 hover:bg-gray-200 text-gray-600'
            }`}
            title={isRecording ? '点击停止录音' : '按住录音'}
          >
            🎤
          </button>

          {/* 文字输入 */}
          <textarea
            value={inputText}
            onChange={e => setInputText(e.target.value)}
            onKeyDown={e => {
              if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault()
                handleSend()
              }
            }}
            placeholder={isRecording ? '录音中...' : '输入进货、库存或损耗信息...'}
            disabled={isRecording}
            rows={1}
            className="flex-1 border rounded-2xl px-4 py-2.5 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-blue-300 max-h-32 disabled:bg-gray-50"
          />

          {/* 发送按钮 */}
          <button
            onClick={handleSend}
            disabled={isSending || (!inputText.trim() && !selectedImage)}
            className="w-10 h-10 flex items-center justify-center rounded-full bg-blue-500 hover:bg-blue-600 disabled:bg-gray-200 text-white shrink-0 transition-colors"
          >
            {isSending ? '…' : '↑'}
          </button>
        </div>

        {isRecording && (
          <p className="text-center text-red-500 text-xs mt-2 animate-pulse">● 录音中，点击麦克风停止</p>
        )}
      </div>
    </div>
  )
}
