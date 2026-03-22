# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**舌尖香港 · AI店长助手** — 生鲜门店 AI 管理系统，前后端分离的 monorepo。

- **Backend**: Laravel 12 API (PHP 8.4) — `backend/`
- **Frontend**: Next.js 16 (React 19.2, TypeScript) — `frontend/`
- **Admin Panel**: Filament v3 — `http://0.0.0.0:8080/admin`

## Current Ports

| Service | Port | URL |
|---------|------|-----|
| Laravel API + Filament Admin | 8080 | `http://0.0.0.0:8080` |
| Next.js Frontend | 3113 | `http://0.0.0.0:3113` |

## Development Commands

### Backend (run from `backend/`)
```bash
php artisan serve --host=0.0.0.0 --port=8080

php artisan migrate
php artisan migrate:fresh --seed
php artisan config:clear          # Required after .env changes
php artisan route:list --path=api # Inspect API routes

php artisan make:model Foo -mf            # Model + migration + factory
php artisan make:controller Api/FooController
php artisan make:filament-resource Foo    # Filament admin resource

php artisan test
php artisan test --filter=testName
php artisan test tests/Feature/FooTest.php
```

### Frontend (run from `frontend/`)
```bash
npm run dev       # Dev server on :3113
npm run build     # Production build (use to verify TypeScript errors)
npm run lint
npx shadcn@latest add [component-name]
```

## Architecture

### Authentication — Two separate systems

**Frontend app (Sanctum token-based):**
1. POST `/api/login` → returns token + user
2. Token stored in `localStorage`, sent as `Authorization: Bearer {token}`
3. `AuthProvider` (`frontend/lib/auth-context.tsx`) manages global state
4. `useAuth()` hook used in all protected pages

**Admin panel (Filament session-based):**
- URL: `/admin`, login at `/admin/login`
- Only users with `is_admin = true` can access (enforced via `User::canAccessPanel()`)
- Admin account: `admin@sjtxg.com` / `Admin@2026`
- Demo account: `demo@example.com` / `password` (no admin access)

### API Layer Pattern (Frontend)

All API calls go through `frontend/lib/api/client.ts` (custom fetch wrapper). Token is passed **explicitly** as the last parameter — it is NOT auto-injected:

```ts
// Pattern used across all API files
export const fooApi = {
  list:   (token: string) => apiClient.get<FooResponse>('/foo', token),
  create: (data: FooData, token: string) => apiClient.post<Foo>('/foo', data, token),
}

// Voice/file uploads use native fetch directly (apiClient doesn't support FormData)
```

API files: `lib/api/assistant.ts`, `lib/api/inventory.ts`, `lib/api/resumes.ts`, `lib/api/auth.ts`, `lib/api/posts.ts`

### AI Assistant Flow

```
User input (text / image base64 / voice file)
  → POST /api/ai/message  or  POST /api/ai/voice
  → AiService::parseInventoryIntent()   (OpenAI-compatible API)
  → dispatchToInventory()
      ├─ Product::findOrCreateByName()   (fuzzy match or auto-create)
      ├─ Inventory::firstOrCreate([store_id, product_id])
      └─ InventoryTransaction::create()
  → Returns { reply, intent, operations[], session_id }
```

`AiService` (`backend/app/Services/AiService.php`) wraps the LLM API. Configure in `backend/.env`:
```
AI_BASE_URL=https://...
AI_API_KEY=...
AI_MODEL=gpt-4o
AI_VISION_MODEL=gpt-4o
AI_WHISPER_MODEL=whisper-1
```

### Database

MySQL. Connection: `laravel` / `laravel_password` / `laravel_app`.

MVP seed data: organization_id=1, region_id=1, store_id=1 (硬编码在 controllers，待权限系统接入后动态化).

Key table groups:
- **AI**: `ai_sessions`, `ai_messages`, `ai_command_templates`
- **Inventory**: `inventory`, `inventory_transactions`, `inventory_count_sheets`
- **Products**: `products`, `product_categories`, `store_products`
- **Suppliers**: `suppliers`, `supplier_products`, `purchase_orders`
- **Org**: `organizations`, `regions`, `stores`, `users`, `roles`, `permissions`
- **Finance**: `expenses`, `expense_categories`, `financial_monthly_summary`
- **HR**: `employees`, `schedules`, `attendance_records`, `salary_records`
- **Talent**: `resumes`

### Filament Admin

- Panel provider: `backend/app/Providers/Filament/AdminPanelProvider.php`
- Resources auto-discovered from `backend/app/Filament/Resources/`
- Current resources by nav group:
  - **商品管理**: `ProductResource`
  - **库存管理**: `InventoryResource`
  - **供应商**: `SupplierResource` (含 SupplierProductsRelationManager)
  - **财务管理**: `ExpenseResource`, `ExpenseCategoryResource`
  - **人才库**: `ResumeResource`
  - *(系统)*: `UserResource`, `PostResource`

Add new resource:
```bash
php artisan make:filament-resource Product --generate
```

## Laravel 12 Conventions

- No `app/Http/Kernel.php` — middleware registered in `bootstrap/app.php`
- Use `casts()` method (not `$casts` property) on models
- Use Form Request classes for validation (not inline in controllers)
- Never call `env()` outside config files — always use `config('key')`
- `store_id = 1` is hardcoded in MVP controllers until the role/permission system is integrated

## Key Config Files

- `backend/.env` — DB, AI keys, APP_URL, SANCTUM_STATEFUL_DOMAINS
- `backend/config/ai.php` — AI service config (reads from .env)
- `frontend/.env.local` — `NEXT_PUBLIC_API_URL`
- `frontend/package.json` — port configured in `dev`/`start` scripts

---

## Modules & API Reference

### 认证 Auth

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/register` | 注册 |
| POST | `/api/login` | 登录，返回 `{token, user}` |
| POST | `/api/logout` | 登出（需 Bearer token） |
| GET | `/api/me` | 当前用户信息 |

---

### AI 助手 — 碎片化输入自动入库

`backend/app/Services/AiService.php` — `backend/app/Http/Controllers/Api/AiAssistantController.php`

| Method | Endpoint | Body / Query | Description |
|--------|----------|-------------|-------------|
| POST | `/api/ai/message` | `{text, image_base64?, session_id?}` | 文字/图片 → AI解析 → 写库存，返回 `{reply, intent, operations[], session_id}` |
| POST | `/api/ai/voice` | `multipart: audio file` | 语音 → Whisper转文字 → 同 message 流程 |
| GET | `/api/ai/sessions` | — | 当前用户会话列表 |
| GET | `/api/ai/sessions/{id}/messages` | — | 会话消息记录 |

**AI解析流程：**
```
输入(文字/图片/语音) → AiService::parseInventoryIntent()
  → {intent, items:[{product_name,qty,unit,action}], reply}
  → dispatchToInventory()
      ├─ Product::findOrCreateByName()
      ├─ Inventory::firstOrCreate([store_id, product_id])
      └─ InventoryTransaction::create()
```

---

### 库存管理 Inventory

`backend/app/Http/Controllers/Api/InventoryController.php`

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/inventory` | 当前门店库存列表（含商品名、单位） |
| GET | `/api/inventory/transactions` | 库存流水（最近100条，含中文类型标签） |

**前端页面：** `/inventory` — 当前库存 / 库存流水 双Tab

---

### 人才简历库 Resumes

`backend/app/Services/ResumeParserService.php` — `backend/app/Http/Controllers/Api/ResumeController.php`

| Method | Endpoint | Body / Query | Description |
|--------|----------|-------------|-------------|
| POST | `/api/resumes/parse` | `{text, image_base64?}` | AI解析简历文本/图片 → 结构化数据（仅预览，不保存） |
| POST | `/api/resumes` | 结构化简历字段 | 保存一份简历 |
| POST | `/api/resumes/batch` | `{items:[{text, image_base64?}]}` | 批量解析并保存（最多50份），返回 `{total,success,failed}` |
| GET | `/api/resumes/search` | `?q=自然语言描述` | NL搜索：AI解析条件 → JSON_CONTAINS查询，返回 `{data,criteria,total}` |
| GET | `/api/resumes` | `?status=&work_type=&district=` | 列表（结构化过滤+分页） |
| PUT | `/api/resumes/{id}` | 字段 | 更新 |
| DELETE | `/api/resumes/{id}` | — | 软删除 |

**搜索流程：**
```
「找筲箕湾附近能做小时工的人」
  → ResumeParserService::parseSearchQuery()
  → {districts:["筲箕湾","西湾河","柴湾"], work_types:["小时工"]}
  → WHERE JSON_CONTAINS(districts, "筲箕湾") OR ...
```

**简历字段说明：**
- `districts` / `work_types` / `positions` / `languages` / `skills` — JSON数组
- `work_types` 枚举值：`全职` / `兼职` / `小时工`
- `salary_unit`: 1=月 2=日 3=小时
- `source`: 1=手动 2=AI解析 3=文件上传
- `status`: 0=无效 1=求职中 2=已入职 3=暂不求职

**前端页面：** `/resumes`（搜索+列表）、`/resumes/upload`（单份/批量/图片 三Tab录入）

---

### Filament 后台管理模块

| 导航分组 | Resource | 功能 |
|---------|----------|------|
| 商品管理 | ProductResource | 商品档案 CRUD，按分类/生鲜/状态过滤 |
| 库存管理 | InventoryResource | 多门店库存查看 + 手动修正，低库存过滤 |
| 供应商 | SupplierResource | 供应商档案 + 供货商品关联表（价格/首选标记） |
| 财务管理 | ExpenseResource | 门店支出记录，AI录入标识，日期/分类/状态过滤 |
| 财务管理 | ExpenseCategoryResource | 支出科目树（支持上下级，标记是否销售成本） |
| 人才库 | ResumeResource | 简历档案管理，TagsInput编辑多值字段 |
