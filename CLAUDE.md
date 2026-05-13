# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**舌尖香港 · AI店长助手** — 生鲜门店 AI 管理系统，前后端分离的 monorepo。

- **Backend**: Laravel 12 API (PHP 8.4) — `backend/`
- **Frontend**: Next.js 16 (React 19.2, TypeScript) — `frontend/`
- **Admin Panel**: Filament v3 — `http://0.0.0.0:8080/admin`

---

## 核心业务逻辑（单店视角）

每天的数据库记录由 6 个维度组成，全部按天存档：

| # | 数据 | 存储表 |
|---|------|--------|
| 1 | 往日库存（每品种开盘数量） | `inventory_daily_snapshots.opening_qty`（当天第一笔事务时冻结） |
| 2 | 今日进货明细 | `purchase_orders` + `purchase_order_items` |
| 3 | 今日库存 = 往日 + 进货 | `inventory_daily_snapshots.closing_qty`（实时滚动更新） |
| 4 | 今日销售情况（每笔+补录） | `sales_orders` + `sales_order_items` |
| 5 | 今日所有远程指令留档 | `daily_operation_logs`（AI/手动/后台，含非库存类指令） |
| 6 | 今日销售汇总 | `sales_daily_summaries`（per-product 汇总量/金额/售罄时间） |

**完整写链（以进货为例）：**
```
Filament 进货单"确认收货"
  → inventory.current_qty += ordered_qty
  → inventory_transactions  type=1
  → inventory_daily_snapshots.received_qty +=  /  closing_qty = qtyAfter
  → daily_operation_logs  source=3, intent=stock_in
```

**完整写链（以销售为例）：**
```
POST /api/sales
  → sales_orders + sales_order_items
  → inventory.current_qty -= qty,  last_sold_at = now()
  → inventory_transactions  type=2
  → inventory_daily_snapshots.sold_qty +=  /  closing_qty = qtyAfter
      └─ 若 closing_qty 首次归零 → sold_out_at = now()（当天唯一）
  → sales_daily_summaries  sales_qty / sales_amount / transaction_count +=
```

**核心表职责：**

| 表 | 职责 |
|---|------|
| `inventory` | 实时库存（current_qty / last_sold_at），滚动更新 |
| `inventory_transactions` | 所有变动审计流水，永不删除 |
| `inventory_daily_snapshots` | per-product 每日快照：opening / received / sold / damage / closing / sold_out_at |
| `purchase_orders` + `purchase_order_items` | 每日进货计划与实收明细 |
| `sales_orders` + `sales_order_items` | 每笔零售流水 |
| `sales_daily_summaries` | per-product 每日销售汇总（量/金额/均价/笔数） |
| `daily_operation_logs` | 所有远程指令留档（AI + 手动 API + Filament 后台） |

---

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

### Authentication — Three separate systems

**External API (JWT token-based, recommended for remote/machine callers):**
1. POST `/api/login` → returns `jwt_token` (JWT) + `token` (Sanctum opaque)
2. `login` field accepts **username** OR email — system auto-detects by presence of `@`
3. JWT encodes `store_id` as a claim; middleware extracts it via `User::resolveStoreId()`
4. Signed with `JWT_SECRET` (HS256). Configured in `backend/config/jwt.php`

**Frontend app (Sanctum opaque token):**
1. Same POST `/api/login` → use the `token` field
2. Token stored in `localStorage`, sent as `Authorization: Bearer {token}`
3. `AuthProvider` (`frontend/lib/auth-context.tsx`) manages global state

**Admin panel (Filament session-based):**
- URL: `/admin`, login at `/admin/login`
- Only users with `is_admin = true` can access (enforced via `User::canAccessPanel()`)
- Admin account: `admin@sjtxg.com` / `Admin@2026`
- Demo account: `demo@example.com` / `username: demo` / `password` (no admin access)

**API route middleware:** All protected routes use `auth.hybrid` (registered in `bootstrap/app.php`), which tries JWT first, then falls back to Sanctum. `store_id` is always resolved from the token — never trusted from the request body.

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

API files: `lib/api/assistant.ts`, `lib/api/inventory.ts`, `lib/api/sales.ts`, `lib/api/purchase-orders.ts`, `lib/api/damage.ts`, `lib/api/suggestions.ts`, `lib/api/operations.ts`, `lib/api/resumes.ts`, `lib/api/auth.ts`

### AI Assistant Flow

```
User input (text / image base64 / voice file)
  → POST /api/ai/message  or  POST /api/ai/voice
  → AiService::parseInventoryIntent()   (OpenAI-compatible API)
  → AiAssistantController: intent is query? → fetchQueryData() → card_type + card_data
                            intent is write? → dispatchToInventory()
                                ├─ Product::findOrCreateByName()
                                ├─ Inventory::firstOrCreate([store_id, product_id])
                                ├─ InventoryTransaction::create()
                                ├─ InventoryDailySnapshot::record()
                                ├─ action=in  → PurchaseOrder + PurchaseOrderItem (一批合一张单)
                                ├─ action=sell/sold_out → SalesOrder + SalesDailySummary
                                └─ action=out → DamageRecord (自动关联最近进货单/供应商)
  → Returns { reply, intent, operations[], card_type?, card_data?, session_id }
```

**AI 模型分工（三路独立配置）：**

| 用途 | 环境变量 | 当前值 |
|------|---------|-------|
| 文字意图解析 | `AI_BASE_URL` / `AI_MODEL` | DeepSeek `deepseek-v4-pro` |
| 图片识别 | `AI_VISION_BASE_URL` / `AI_VISION_MODEL` | fidelityai `gemini-3-flash-preview` |
| 语音转文字 | `AI_WHISPER_BASE_URL` / `AI_WHISPER_MODEL` | fidelityai `whisper-1` |

**AI intent 分两大类：**
- **写入类** → `dispatchToInventory()`，返回 `operations[]`
- **查询类** → `fetchQueryData()`，返回 `card_type` + `card_data`（直接查 DB，不再调 LLM）

### Services Layer

| Service | 职责 |
|---------|------|
| `AiService` | LLM 调用封装（文字/图片/语音三路） |
| `SuggestionService` | 进货/促销建议算法（近7天快照→补货优先级），同时被 `SuggestionController` 和 `AiAssistantController` 调用 |
| `ResumeParserService` | 简历 AI 解析 + 自然语言搜索条件提取 |
| `JwtService` | JWT 签发与验证（HS256，secret 来自 `config/jwt.php`） |
| `SalesUploadService` | 销售数据批量上传处理 |

### Database

MySQL. Connection: `laravel` / `laravel_password` / `laravel_app`.

MVP seed data: organization_id=1, region_id=1, store_id=1 (硬编码在 controllers，待权限系统接入后动态化).

Key table groups:
- **AI**: `ai_sessions`, `ai_messages`
- **Inventory**: `inventory`, `inventory_transactions`, `inventory_daily_snapshots`
- **Daily ops**: `daily_operation_logs`, `sales_daily_summaries`
- **Products**: `products`, `product_categories`
- **Sales**: `sales_orders`, `sales_order_items`
- **Suppliers**: `suppliers`, `supplier_products`, `purchase_orders`, `purchase_order_items`
- **Org**: `organizations`, `regions`, `stores`, `users`, `roles`, `permissions`, `user_store_roles`
- **Finance**: `expenses`, `expense_categories`
- **HR**: `employees`, `schedules`, `attendance_records`, `salary_records`
- **Talent**: `resumes`

### Filament Admin

- Panel provider: `backend/app/Providers/Filament/AdminPanelProvider.php`
- Resources auto-discovered from `backend/app/Filament/Resources/`
- Current resources by nav group:
  - **销售管理**: `SalesOrderResource`
  - **商品管理**: `ProductResource`
  - **库存管理**: `DailyOperations` (每日营运概览, sort=0), `InventoryResource`, `PurchaseOrderResource`
  - **供应商**: `SupplierResource` (含 SupplierProductsRelationManager)
  - **财务管理**: `ExpenseResource`, `ExpenseCategoryResource`
  - **人才库**: `ResumeResource`
  - **系统**: `UserResource`, `RoleResource`, `PostResource`

Add new resource:
```bash
php artisan make:filament-resource Product --generate
```

## 前端注意事项

### 生产模式运行
前端以 `npm run start`（生产模式）运行在 `:3113`，**修改前端代码后必须重新 build 才能生效**：
```bash
cd frontend
npm run build
# 重启进程（当前用 nohup/后台方式启动）
nohup npm run start > /tmp/nextjs.log 2>&1 &
```

### 日期处理陷阱
前端所有 `todayStr()` 函数**必须使用本地时间**，不能用 `new Date().toISOString().slice(0, 10)`（返回 UTC，香港时间凌晨 0-8 点会早一天）：
```ts
// ✅ 正确
function todayStr() {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}
// ❌ 错误（UTC 日期）
return new Date().toISOString().slice(0, 10)
```
后端时区为 `Asia/Shanghai`，MySQL 也使用 SYSTEM（CST），日期存储和查询均基于本地时间。

---

## 部署架构（生产）

nginx 反向代理（`/etc/nginx/conf.d/default.conf`）：

| 域名 | 代理到 | 内容 |
|------|--------|------|
| `s.xingke888.com` | `:8080` | Laravel API + Filament 后台 |
| `s.tianhegongyuan.com` | `:3113` | Next.js 前端 |

两个域名均通过 Cloudflare 代理（橙云），SSL 证书由 Let's Encrypt 签发（`/etc/letsencrypt/live/`）。

**⚠️ nginx 配置修改后需以 root 执行 `nginx -t && nginx -s reload`**，非 root 执行 `nginx -t` 会因证书权限报错（误报，实际 nginx 进程可正常读取）。

---

## Laravel 12 Conventions

- No `app/Http/Kernel.php` — middleware registered in `bootstrap/app.php`
- Use `casts()` method (not `$casts` property) on models
- Use Form Request classes for validation (not inline in controllers)
- Never call `env()` outside config files — always use `config('key')`
- `store_id = 1` is hardcoded in MVP controllers until the role/permission system is integrated

## Key Config Files

- `backend/.env` — DB, AI keys, APP_URL, SANCTUM_STATEFUL_DOMAINS, `JWT_SECRET`
- `backend/config/ai.php` — AI service config (reads from .env)
- `backend/config/jwt.php` — JWT secret + algo (HS256)
- `frontend/.env.local` — `NEXT_PUBLIC_API_URL`
- `frontend/package.json` — port configured in `dev`/`start` scripts

---

## Modules & API Reference

### 认证 Auth

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/register` | 注册（需 `username` 字段，唯一） |
| POST | `/api/login` | 登录 `{login, password}`，返回 `{token, jwt_token, store_id, user}` |
| POST | `/api/logout` | 登出（需 Bearer token） |
| GET | `/api/me` | 当前用户信息（含 `store_id`, `roles`） |

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
      ├─ InventoryTransaction::create()
      ├─ InventoryDailySnapshot::record()
      ├─ SalesOrder + SalesOrderItem (for sell/sold_out/remaining actions)
      └─ SalesDailySummary::accumulate(source='ai')
```

**AI 识别的 intent / action 枚举：**

| intent | action | 语义 | 后端写入 |
|--------|--------|------|---------|
| `purchase_receipt` | `in` | 进货到货 | InventoryTransaction + PurchaseOrder/Item |
| `sale_report` | `sell` | 有具体售出量 | InventoryTransaction + SalesOrder + SalesDailySummary |
| `sold_out` | `sold_out` | 商品完全售罄 | 同上，qty=0 |
| `remaining` | `remaining` | 报告剩余量（倒推售出量） | 同上 |
| `stocktake` | `adjust` | 盘点上报绝对值 | InventoryTransaction(type=4) |
| `waste_report` | `out` | 损耗/报废（items 含 `reason` 字段） | InventoryTransaction(type=3) + DamageRecord |
| `inventory_query` | — | 查当前库存 | 返回 card_type=inventory |
| `sales_today_query` | — | 查今日/历史销售 | 返回 card_type=sales_today |
| `daily_overview_query` | — | 查每日运营概览 | 返回 card_type=daily_overview |
| `purchase_orders_query` | — | 查进货单 | 返回 card_type=purchase_orders |
| `daily_logs_query` | — | 查操作日志 | 返回 card_type=daily_logs |
| `weather_query` | — | 查天气（LLM生成+DB缓存） | 返回 card_type=weather |
| `refund_claims_query` | — | 查供应商退款申请 | 返回 card_type=refund_claims |
| `suggestions_query` | — | 查进货/促销建议 | 返回 card_type=suggestions，调 SuggestionService |
| `other` | — | 非库存类 | 仅写 DailyOperationLog |

---

### 库存管理 Inventory

`backend/app/Http/Controllers/Api/InventoryController.php`

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/inventory` | 当前门店库存列表（含商品名、单位、last_sold_at） |
| GET | `/api/inventory/transactions` | 库存流水（最近100条，含中文类型标签） |
| POST | `/api/inventory/adjust` | 手动调整库存（sold_out / adjust / damage 三种模式） |
| POST | `/api/inventory/sales-summary` | 补录（旧接口，按 product_id + date，推荐改用 `/api/sales/supplement`） |
| GET | `/api/inventory/daily-overview` | 每日库存运营概览（含 `sales_breakdown` 来源明细） |
| GET | `/api/daily-logs` | 今日操作日志（所有远程指令，含 AI / 手动 / 后台） |
| GET | `/api/products` | 商品列表（`?q=&is_fresh=&category_id=`，用于查 product_id） |
| GET | `/api/purchase-orders` | 进货单列表（`?date=&status=`） |
| GET | `/api/purchase-orders/{id}` | 进货单详情（含明细） |
| POST | `/api/purchase-orders` | 创建进货单并立即确认收货（items 用商品名，自动匹配/创建商品） |

**`inventory` 关键字段：**
- `current_qty` — 当前库存量（实时滚动，不按天重置）
- `last_sold_at` — 最后一次销售时间（辅助判断销售速度/滞销）

**`inventory_daily_snapshots` 关键字段：**
- `opening_qty` — 开盘库存（当天第一笔事务时冻结，**进货不会修改此值**）
- `received_qty` — 今日进货合计（多张进货单累加）
- `sold_qty` — 今日销售出库合计（含补录）
- `damage_qty` — 今日损耗合计
- `closing_qty` — 结算库存（每笔事务后实时更新 = `qtyAfter`）
- `sold_out_at` — 售罄时间（`closing_qty` 首次归零时记录；补货后清除）

**今日库存公式：** `closing_qty = opening_qty + received_qty - sold_qty - damage_qty ± adjustment_qty`

**⚠️ `InventoryDailySnapshot::record()` 中 type=1（进货）只更新 `received_qty`，不修改 `opening_qty`。**

**`POST /api/inventory/adjust` 三种模式：**
- `sold_out` — 标记售罄，qty 设为 0
- `adjust` — 直接设定绝对值（盘点修正）
- `damage` — 减少指定数量（损耗报废）

**库存流水 transaction_type 枚举：**

| 值 | 类型 |
|---|---|
| 1 | 采购入库 |
| 2 | 销售出库 |
| 3 | 损耗报废 |
| 4 | 盘点调整 |
| 5 | 促销出库 |
| 6 | 调拨入库 |
| 7 | 调拨出库 |
| 8 | 退货入库 |

**`sales_daily_summaries` 来源字段（本次新增）：**
- `pos_qty` / `pos_amount` — 收银台逐笔来源
- `supplement_qty` / `supplement_amount` — 人工/API 补录来源
- `ai_qty` / `ai_amount` — AI 口头录入来源
- `sales_qty` / `sales_amount` — 三来源合计（始终等于三者之和）

统一写入方法：`SalesDailySummary::accumulate(storeId, productId, date, qty, amount, source)`

**前端页面：** `/inventory` — 当前库存 / 库存流水 双Tab

---

### 零售流水 Sales

`backend/app/Http/Controllers/Api/SalesOrderController.php`

| Method | Endpoint | Body / Query | Description |
|--------|----------|-------------|-------------|
| GET | `/api/sales` | `?date=&cashier_id=&status=` | 流水列表（分页） |
| POST | `/api/sales` | 见下方 | 新建销售单，自动扣减库存并更新 `last_sold_at` |
| GET | `/api/sales/{id}` | — | 单笔详情（含明细） |
| GET | `/api/sales/today` | — | 今日汇总（总单数、总金额、支付方式占比、`sales_breakdown`） |
| GET | `/api/sales/summary` | `?date=YYYY-MM-DD` | 按日期 per-product 销售汇总（含 `sales_breakdown`） |
| POST | `/api/sales/supplement` | 见下方 | 销售补录统一入口（三种模式） |

**POST `/api/sales` 请求体：**
```json
{
  "items": [
    { "product_id": 1, "qty": 3, "unit_price": 12, "discount_amount": 0 }
  ],
  "paid_amount": 36,
  "payment_method": 2,
  "cashier_id": 1,
  "discount_amount": 0,
  "notes": ""
}
```

**销售单状态：** 1=已完成 2=已退款 3=部分退款

**支付方式：** 1=现金 2=微信 3=支付宝 4=银行卡 5=混合

**`POST /api/sales/supplement` 三种模式：**
- `sold_out` — 商品卖完，sold_qty = 当前全部库存，库存归零，记录 `sold_out_at`
- `remaining` — 报剩余量，sold_qty = current_qty − remaining_qty，库存改为 remaining_qty
- `qty` — 报售出量，sold_qty = 指定量，库存扣减对应量

所有模式均创建 `sales_order`（order_no 前缀 `SUP-`）并调用 `SalesDailySummary::accumulate(source='supplement')`。

**销售单写库逻辑：**
```
POST /api/sales  (POS 收银台)
  → 创建 sales_orders + sales_order_items
  → 每个商品：
      ├─ InventoryTransaction::create(type=2, qty_change=-qty)
      ├─ inventory.current_qty -= qty, last_sold_at = now()
      ├─ InventoryDailySnapshot::record(type=2)
      ├─ SalesDailySummary::accumulate(source='pos')
      └─ DailyOperationLog::write(source=2, intent='sale_report')
```

**退款逻辑（Filament 后台操作）：**
```
退款按钮 → processRefund()
  → InventoryTransaction::create(type=8, qty_change=+qty)  ← 退货入库
  → inventory.current_qty += qty
  → sales_order.status = 2
  注意：退款不重置 last_sold_at（销售记录已存在）
```

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

### 损耗管理 Damage

`backend/app/Http/Controllers/Api/DamageController.php` — `backend/app/Models/DamageRecord.php`

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/damage` | 录入损耗（扣库存 + 建 DamageRecord + 自动关联供应商） |
| GET | `/api/damage` | 列表（`?date=&product_id=&status=&supplier_id=`） |
| GET | `/api/damage/stats` | 按商品/供应商汇总（`?from=&to=`） |
| POST | `/api/damage/{id}/images` | 追加图片 |

**DamageRecord 关键字段：**
- `purchase_order_item_id` — 自动关联最近一笔已收货进货单明细（用于追溯供应商）
- `supplier_id` / `unit_cost` / `total_claimed` — 索赔金额（= qty × unit_cost）
- `status`: 1=待处理 2=已提交申请 3=已退款
- AI 录入时 `reason` 从 AI 解析的 items[].reason 中取得

**完整写链（AI 损耗为例）：**
```
AI "番茄烂了5斤"
  → intent=waste_report, action=out, reason="变质"
  → inventory.current_qty -= qty
  → InventoryTransaction(type=3)
  → InventoryDailySnapshot.damage_qty +=
  → DamageRecord（自动查最近进货单关联 supplier_id）
  → DailyOperationLog
```

---

### 供应商退款申请 Refund Claims

`backend/app/Http/Controllers/Api/SupplierRefundClaimController.php`

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/refund-claims` | 从损耗记录生成退款申请（需 `supplier_id` + `damage_record_ids[]`） |
| GET | `/api/refund-claims` | 列表（`?status=&supplier_id=`） |
| GET | `/api/refund-claims/{id}` | 详情（含明细） |
| PUT | `/api/refund-claims/{id}/status` | 更新状态（1=待处理 2=已提交 4=已退款 5=已拒绝） |

提交退款后，关联的 `DamageRecord.status` 自动更新为 2（已提交）；退款确认后更新为 3（已退款）。

---

### Filament 后台管理模块

| 导航分组 | Resource / Page | 功能 |
|---------|-----------------|------|
| 销售管理 | SalesOrderResource | 零售流水 CRUD，含退款操作（自动恢复库存），日期/收银员/支付方式/状态过滤 |
| 商品管理 | ProductResource | 商品档案 CRUD，按分类/生鲜/状态过滤 |
| 库存管理 | DailyOperations | 每日营运概览（往日库存/进货/已售/售罄状态），日期切换 |
| 库存管理 | InventoryResource | 多门店库存查看 + 手动修正，低库存过滤，显示最后销售时间 |
| 库存管理 | PurchaseOrderResource | 进货单 CRUD，确认收货自动写入 inventory + inventory_daily_snapshots |
| 供应商 | SupplierResource | 供应商档案 + 供货商品关联表（价格/首选标记） |
| 财务管理 | ExpenseResource | 门店支出记录，AI录入标识，日期/分类/状态过滤 |
| 财务管理 | ExpenseCategoryResource | 支出科目树（支持上下级，标记是否销售成本） |
| 人才库 | ResumeResource | 简历档案管理，TagsInput编辑多值字段 |
| 系统 | UserResource | 用户管理 |
| 系统 | RoleResource | 角色权限管理 |
