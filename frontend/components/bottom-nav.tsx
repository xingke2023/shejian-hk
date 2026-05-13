'use client'

import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { Home, MessageCircle, BarChart3 } from 'lucide-react'

const NAV_ITEMS = [
  { label: '首页', path: '/', icon: Home },
  { label: 'AI助手', path: '/manage', icon: MessageCircle, primary: true },
  { label: '报表', path: '/sales-report', icon: BarChart3 },
]

// Pages with their own bottom input bar — hide the global nav
const HIDDEN_PATHS = ['/manage', '/assistant', '/login', '/register']

export function BottomNav() {
  const pathname = usePathname()

  if (HIDDEN_PATHS.includes(pathname)) return null

  return (
    <nav className="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-100 z-50">
      <div className="flex items-center justify-around h-16 max-w-2xl mx-auto">
        {NAV_ITEMS.map((item) => {
          const isActive = pathname === item.path
          const Icon = item.icon

          if (item.primary) {
            return (
              <Link
                key={item.path}
                href={item.path}
                className="flex flex-col items-center justify-center -mt-8"
              >
                <div className="w-14 h-14 rounded-full shadow-lg flex items-center justify-center bg-gradient-to-br from-[#941100] to-[#FF9300]">
                  <Icon className="w-7 h-7 text-white" />
                </div>
              </Link>
            )
          }

          return (
            <Link
              key={item.path}
              href={item.path}
              className={`flex flex-col items-center justify-center gap-1 px-4 py-2 transition-colors ${
                isActive ? 'text-[#941100]' : 'text-gray-400'
              }`}
            >
              <Icon className="w-6 h-6" />
              <span className="text-xs">{item.label}</span>
            </Link>
          )
        })}
      </div>
    </nav>
  )
}
