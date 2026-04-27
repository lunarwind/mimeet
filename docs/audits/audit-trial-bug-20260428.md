# 體驗方案「永遠顯示已使用過」Bug — 診斷報告

**報告類型：** Bug 診斷（階段一）
**診斷日期：** 2026-04-28
**診斷者：** Claude Code（本機）
**狀態：** 🔴 待使用者確認修法方案後執行階段二

---

## 章節 0 — 取證紀錄

| 項目 | 結果 |
|---|---|
| 診斷日期時間 | 2026-04-28（Asia/Taipei） |
| 當前 branch | `develop` |
| 當前 commit hash | `1b979e3`（Merge pull request #49 from lunarwind/codex/execute-audit-on-authentication-module）|
| 未 commit 的修改 | 只有 `docs/audits/audit-payment-mode-switch-20260427.md`（untracked，不影響診斷）|
| 本機 vs origin/develop | ✅ 同步（up to date with origin/develop）|
| 本機 develop vs origin/main | develop 領先 main 6 commits（全部為 docs/audit 類），但關鍵檔案（SubscriptionController、usePayment.ts、payment.ts）`git diff HEAD..origin/main` 結果為空，代表**本機讀到的程式碼與 main 完全一致** |
| Docker 環境可用性 | 無需 Docker 執行診斷；API 取得嘗試失敗（staging uid=1 帳號不可用），但程式碼已足夠確認 root cause |

---

## 章節 1 — 本機程式碼證據

### 1.1 後端：`SubscriptionController::trial()` 的回傳結構

**檔案：** `/home/chuck/projects/mimeet/backend/app/Http/Controllers/Api/V1/SubscriptionController.php`
**行號：** 188–214

```php
// 行 188-214（完整 trial() 方法）
public function trial(Request $request): JsonResponse
{
    $plan = SubscriptionPlan::where('is_trial', true)->where('is_active', true)->first();

    $eligible = $plan && !\App\Models\Order::where('user_id', $request->user()->id)
        ->whereHas('plan', fn ($q) => $q->where('is_trial', true))
        ->where('status', 'paid')
        ->exists();

    return response()->json([
        'success' => true,
        'code' => 200,
        'message' => 'OK',
        'data' => [
            'plan' => $plan ? [
                'id' => $plan->slug,
                'name' => $plan->name,
                'duration_days' => $plan->duration_days,
                'price' => $plan->price,
                'currency' => $plan->currency,
                'features' => $plan->features,
            ] : null,
            'eligible' => $eligible,    // ← 行 211：key 名稱是 'eligible'（小寫）
        ],
    ]);
}
```

**後端回傳的 JSON 結構：**
```json
{
  "success": true,
  "code": 200,
  "message": "OK",
  "data": {
    "plan": {
      "id": "trial",
      "name": "新手體驗方案",
      "duration_days": 30,
      "price": 199,
      "currency": "TWD",
      "features": [...]
    },
    "eligible": true
  }
}
```

**`data` 頂層只有 `plan` 和 `eligible` 兩個 key。不存在 `isEligible`、`available`、`price`（頂層）、`durationDays` 等欄位。**

---

### 1.2 前端：`TrialInfo` interface 定義

**檔案：** `/home/chuck/projects/mimeet/frontend/src/types/payment.ts`
**行號：** 55–60

```typescript
export interface TrialInfo {
  available: boolean      // ← 行 56：後端 data 中不存在此 key
  price: number           // ← 行 57：後端 data 中不存在此 key（在 data.plan.price 裡）
  durationDays: number    // ← 行 58：後端 data 中不存在此 key（在 data.plan.duration_days 裡）
  isEligible: boolean     // ← 行 59：後端 data 中的 key 是 'eligible'，不是 'isEligible'
}
```

**`TrialInfo` interface 與後端實際回傳的 `data` 結構完全不對齊。**

---

### 1.3 前端：`fetchTrialInfo()` — 沒有欄位映射

**檔案：** `/home/chuck/projects/mimeet/frontend/src/composables/usePayment.ts`
**行號：** 121–132

```typescript
async function fetchTrialInfo(): Promise<TrialInfo | null> {
    isLoading.value = true
    try {
      const res = await client.get<{ data: TrialInfo }>('/subscription/trial')
      trialInfo.value = res.data.data    // ← 行 125：直接賦值，沒有任何欄位映射
      return trialInfo.value
    } catch (e) {
      return null
    } finally {
      isLoading.value = false
    }
}
```

**對比 `fetchCurrentSubscription()`（同檔案，行 60–89）**，可見其他方法都有明確映射：

```typescript
async function fetchCurrentSubscription(): Promise<CurrentSubscription | null> {
    // ...
    // Map backend snake_case → frontend camelCase  ← 行 70 有明確的映射注釋
    const sub: CurrentSubscription = {
        planType: raw.plan_id ?? raw.planType ?? '',
        planName: raw.plan_name ?? raw.planName ?? '',
        expiresAt: raw.expires_at ?? raw.expiresAt ?? '',
        autoRenew: raw.auto_renew ?? raw.autoRenew ?? false,
        daysRemaining: raw.days_remaining ?? raw.daysRemaining ?? ...,
    }
    // ...
}
```

**`fetchTrialInfo()` 是整個 `usePayment.ts` 中唯一**沒有做 snake_case → camelCase 映射的 async function。

---

### 1.4 前端：`TrialView.vue` 的條件判斷

**檔案：** `/home/chuck/projects/mimeet/frontend/src/views/app/TrialView.vue`
**行號：** 40–45

```html
<!-- 行 40：已使用過 block 的顯示條件 -->
<div v-if="trialInfo && !trialInfo.isEligible" class="used-notice">
  <div class="used-notice__icon">ℹ️</div>
  <div class="used-notice__title">您已使用過體驗方案</div>
  <p class="used-notice__desc">每位會員限購一次體驗方案，您可以選擇正式訂閱方案繼續享受全功能。</p>
  <button class="btn-primary btn-full" @click="$router.push('/app/shop')">查看正式方案</button>
</div>

<!-- 行 47：可購買 block 的顯示條件（v-else）-->
<template v-else>
```

---

### 1.5 axios client — 無全域 snake_case 轉換

**檔案：** `/home/chuck/projects/mimeet/frontend/src/api/client.ts`
**行號：** 24–61

```typescript
// Response Interceptor：統一錯誤處理（行 24-61）
client.interceptors.response.use(
  (response) => response,   // ← 成功的 response 直接 passthrough，無任何 key 轉換
  (error: AxiosError) => {  // ← 失敗的 response 只做 HTTP 狀態碼的 toast 通知
    // 401, 403, 422, 429, 5xx 處理...
  },
)
```

**axios client 的 response interceptor 不做任何 snake_case → camelCase 轉換。** 每個 API module 須自行映射。

---

### 1.6 後端：`PaymentService.createOrderRecord()` 的 TRIAL_ALREADY_USED 邏輯

**檔案：** `/home/chuck/projects/mimeet/backend/app/Services/PaymentService.php`
**行號：** 87–94

```php
if ($plan->is_trial) {
    $alreadyUsed = Order::where('user_id', $user->id)
        ->whereHas('plan', fn ($q) => $q->where('is_trial', true))
        ->where('status', 'paid')    // ← 行 91：只有 status='paid' 才算用過
        ->exists();
    if ($alreadyUsed) {
        throw new \Exception('TRIAL_ALREADY_USED');
    }
}
```

**後端的 TRIAL_ALREADY_USED 邏輯正確**：只有 `status='paid'` 的訂單才算用過，`pending`（mock 或放棄付款產生的）不影響資格。

---

## 章節 2 — API 實際回傳的 JSON

staging 測試帳號（`admin@mimeet.club` / `Test1234`）登入失敗（`INVALID_CREDENTIALS`）——此帳號僅在 `php artisan mimeet:reset --force` 後才存在，staging 目前無此用戶。

**但 Root Cause 已可從程式碼直接確認（見章節 4），不依賴 API 測試。**

後端 `trial()` 方法（SubscriptionController.php:211）的回傳結構已由程式碼直接確認：`data.eligible`（非 `data.isEligible`）。

---

## 章節 3 — 資料庫實際狀態

無法自動執行（SSH credentials 需手動輸入）。

以下 SQL 供使用者代跑驗證（僅 SELECT）：

```sql
-- 查全表 trial orders 狀態統計（確認有無殭屍 paid 訂單）
SELECT o.status, COUNT(*) AS cnt
FROM orders o
JOIN subscription_plans sp ON sp.id = o.plan_id
WHERE sp.is_trial = 1
GROUP BY o.status;

-- 查 trial plan 設定是否正常
SELECT id, slug, name, price, duration_days, is_trial, is_active
FROM subscription_plans
WHERE is_trial = 1 OR slug LIKE '%trial%';
```

**預期結果**（若 bug 只是前端問題）：
- `status='paid'` 的 trial orders 應為 0 或極少（真實付款過）
- trial plan 應存在且 `is_active=1`，price=199，duration_days=30

---

## 章節 4 — Root Cause 確認

### Root Cause（一句話）

**`fetchTrialInfo()` 沒有做欄位映射，後端回傳 `data.eligible`，前端直接賦值給 `trialInfo`，但 `TrialView.vue` 讀取的是 `trialInfo.isEligible`，因為 key 不存在所以永遠是 `undefined`，`!undefined === true`，「已使用過」區塊永遠顯示。**

### 邏輯推導

```
後端回傳：        res.data.data = { plan: {...}, eligible: true }
fetchTrialInfo()： trialInfo.value = res.data.data   ← 直接賦值，無映射
trialInfo.value：  { plan: {...}, eligible: true }
                   (isEligible key 不存在)
TrialView 判斷：   v-if="trialInfo && !trialInfo.isEligible"
                 = truthy_object && !undefined
                 = true && true
                 = true   ← 永遠顯示「已使用過」
```

### 佐證

| 證據 | 位置 |
|---|---|
| 後端回傳 `eligible`（非 `isEligible`）| SubscriptionController.php:211 |
| `fetchTrialInfo()` 無映射，直接賦值 | usePayment.ts:125 |
| `TrialInfo` interface 定義 `isEligible` | payment.ts:59 |
| template 使用 `trialInfo.isEligible` | TrialView.vue:40 |
| axios client 無全域 key 轉換 | client.ts:25-61 |

### 為什麼其他可能原因被排除

| 原因假設 | 排除依據 |
|---|---|
| 後端業務邏輯錯誤（誤判為 used）| 後端 `trial()` 查 `status='paid'`（PaymentService:91），邏輯正確；mock 產生的 `pending` 訂單不影響 |
| 資料庫殘留 paid trial 訂單 | 即使有，也只影響「真的用過」的帳號；對「從未購買過體驗方案」的新用戶，後端應回 `eligible: true`，但前端仍顯示錯誤 |
| 前端讀取路由錯誤 | routes/api.php:117 確認端點為 `GET /subscription/trial`，usePayment.ts:124 呼叫 `/subscription/trial`，一致 |
| axios client 全域轉換造成異常 | client.ts response interceptor 直接 passthrough 成功 response，無 key 轉換 |

---

## 章節 5 — 修法選項

### Option A：前端 `fetchTrialInfo()` 加映射（推薦）

**改動範圍：**
- 僅修改 `frontend/src/composables/usePayment.ts`
- 行 121–132（fetchTrialInfo 函式），約改 **5 行**

**改動前：**
```typescript
// usePayment.ts:121-132（現況）
async function fetchTrialInfo(): Promise<TrialInfo | null> {
  isLoading.value = true
  try {
    const res = await client.get<{ data: TrialInfo }>('/subscription/trial')
    trialInfo.value = res.data.data          // ← 直接賦值，無映射
    return trialInfo.value
  } catch (e) {
    return null
  } finally {
    isLoading.value = false
  }
}
```

**改動後：**
```typescript
async function fetchTrialInfo(): Promise<TrialInfo | null> {
  isLoading.value = true
  try {
    const res = await client.get('/subscription/trial')
    const raw = res.data.data
    if (!raw) {
      trialInfo.value = null
      return null
    }
    trialInfo.value = {
      available: !!raw.plan,
      price: raw.plan?.price ?? 199,
      durationDays: raw.plan?.duration_days ?? 30,
      isEligible: raw.eligible ?? raw.isEligible ?? false,   // ← 關鍵映射
    }
    return trialInfo.value
  } catch (e) {
    return null
  } finally {
    isLoading.value = false
  }
}
```

**是否需要清理資料庫：** 否
**是否需要 rebuild：** 僅需 rebuild 前端（`npm run build` in `frontend/`）
**是否需要更新規格文件：** 建議更新 API-001 §10.5 的 TrialInfo 結構描述（低優先）
**回滾難度：** 極低，`git revert` 一個 commit 即可

**優點：**
1. 改動最小（~5 行），風險極低
2. 與 `fetchCurrentSubscription()` 的映射模式一致
3. 後端無需修改，不影響任何其他呼叫端

**缺點：**
1. `TrialInfo` interface 仍與後端實際結構不對齊（有 `available` / `price` / `durationDays` 是前端自行計算的，非後端直接回傳）
2. 兩個 key（`eligible` / `isEligible`）並存的 fallback 可能讓後續維護者困惑
3. TypeScript generic 型別宣告 `<{ data: TrialInfo }>` 要一起拿掉，否則 TypeScript 仍會誤以為 raw 是 TrialInfo

---

### Option B：後端欄位改名為 `is_eligible`，前端同步更新

**改動範圍：**
- `backend/app/Http/Controllers/Api/V1/SubscriptionController.php`（1 行：`'eligible' → 'is_eligible'`）
- `frontend/src/composables/usePayment.ts`（改 `fetchTrialInfo` 映射）
- 約改 **1 + 5 = 6 行**

**改動後後端：**
```php
// SubscriptionController.php:211
'is_eligible' => $eligible,   // snake_case 命名更一致
```

**改動後前端映射：**
```typescript
isEligible: raw.is_eligible ?? false,
```

**優點：**
1. 後端欄位命名與其他 snake_case 欄位（`is_trial`、`is_active`）一致
2. 前端映射語意更清晰（`is_eligible` → `isEligible`）
3. 若未來有其他 client 呼叫此 API，欄位命名更直覺

**缺點：**
1. 後端也要改，需要 backend deploy（cache:clear）
2. 如果有其他 client 或 external 呼叫 `GET /subscription/trial`（目前確認只有前端），需一起更新
3. 改動兩個 layer，風險略高於 Option A

---

### Option C：前端 + 清理 `TrialInfo` interface（全面修正）

在 Option A 的基礎上，同步修正 `TrialInfo` interface 使其真實反映後端回傳結構。

**改動範圍：**
- `frontend/src/composables/usePayment.ts`（fetchTrialInfo 映射，~7 行）
- `frontend/src/types/payment.ts`（TrialInfo interface，~5 行）
- 合計約 **12 行**

**改動後 `TrialInfo`：**
```typescript
// payment.ts
export interface TrialInfo {
  plan: {
    id: string
    name: string
    durationDays: number
    price: number
    features: string[]
  } | null
  isEligible: boolean
}
```

**優點：**
1. interface 真實反映後端結構，TypeScript 型別安全
2. 消除 `available`（未使用）、頂層 `price` / `durationDays`（TrialView.vue 中是硬編碼，未讀取）等冗餘欄位
3. 為未來 TrialView 讀取動態 price / duration 打好基礎

**缺點：**
1. 改動較多，TypeScript compile 可能需要更多測試確認
2. `TrialView.vue` 硬編碼 NT$199 / 30 天，此次不修改仍保持；若 interface 改了但 view 仍硬編碼，會造成兩者不一致（短期可接受）
3. 比 Option A 多 7 行改動，工期略長

---

## 章節 6 — 推薦方案

**推薦：Option A（前端 fetchTrialInfo 加映射）**

**理由：**

1. **最小改動，最快修復**：只改 `usePayment.ts` ~5 行，所有行為立即正確，無需後端部署
2. **風險最低**：後端不變，不影響任何其他使用 `/subscription/trial` 的路徑（目前僅 `TrialView.vue` 一個呼叫端）
3. **模式一致**：與同檔案的 `fetchCurrentSubscription()` 採用相同的「raw 映射」模式，符合既有慣例
4. **與 Option B 對比**：Option B 需要後端 + 前端一起改，改 API contract 需要額外確認沒有其他呼叫端；Option A 不改 API contract，回滾成本更低
5. **與 Option C 對比**：Option C 更乾淨，但此時刻的優先級是「止血」，interface 清理可列為後續 tech debt，不必在緊急修復中一起做

**上線前驗收清單：**
1. 全新測試帳號（未購買過 trial）→ TrialView 顯示「立即購買 NT$199」（非「已使用過」）
2. 已購買過 trial 的帳號 → 顯示「已使用過」
3. `purchaseTrial()` 成功後跳轉 ECPay（或 mock）
4. 完成 trial purchase → 再次進入 TrialView → 顯示「已使用過」
5. ShopView 點擊體驗方案 entry → 跳轉 TrialView 路由正確

---

## 章節 7 — 連帶問題（不在這次修）

以下問題從讀程式碼過程中發現，**不在這次 bug fix 範圍內處理**：

1. **`TrialInfo` interface 與後端實際結構不對齊**（Option C 的範圍）：interface 有 `available`、`price`（頂層）、`durationDays`（頂層），但後端不回傳這些。TrialView.vue 並未讀取這些欄位（硬編碼 199 / 30 天），目前不造成用戶可見問題，但後續維護時容易混淆。
2. **`TrialView.vue` 硬編碼 NT$199 / 30 天**：應從 `trialInfo.plan.price` 和 `trialInfo.plan.duration_days` 讀取，待 Option C 完成後可一起修
3. **`SubscriptionController::trial()` 欄位命名**：`eligible` 用 snake_case 慣例應為 `is_eligible`，與 `is_trial`、`is_active` 一致

---

## 章節 8 — 待使用者確認的問題

1. **確認採用哪個方案？**
   - Option A（前端僅加映射，~5 行，推薦）
   - Option B（後端改名 + 前端映射，~6 行，更 clean 但需後端 deploy）
   - Option C（前端全面修正，~12 行，最完整）

2. **資料庫是否有殭屍 paid trial orders 需要清理？**
   請執行章節 3 的 SELECT SQL 回報結果。若有 `status='paid'` 的 trial 訂單歸屬於不應有此訂單的用戶（例如 mock 流程殘留），是否要 DELETE？（需使用者再次確認才執行）

3. **連帶問題（章節 7）要不要排程處理？**
   建議修完本次 bug 後，在下一個 Sprint 開排 TrialInfo interface 清理（對應 Option C 的差異部分）。

4. **部署時間是否安排在工作時間進行？**
   此次修改為純前端，不影響後端，deploy 風險極低，可在任何時間部署。

---

## 附錄：完整 Grep 結果

```
grep -rn "eligible|isEligible|is_eligible|TRIAL_ALREADY" backend/ frontend/src
---
backend/app/Http/Controllers/Api/V1/SubscriptionController.php:94:  if ($e->getMessage() === 'TRIAL_ALREADY_USED') {
backend/app/Http/Controllers/Api/V1/SubscriptionController.php:193: $eligible = $plan && !\App\Models\Order::...->where('status', 'paid')->exists();
backend/app/Http/Controllers/Api/V1/SubscriptionController.php:211:     'eligible' => $eligible,
backend/app/Http/Controllers/Api/V1/SubscriptionController.php:228:  if ($e->getMessage() === 'TRIAL_ALREADY_USED') {
backend/app/Services/PaymentService.php:35:    throw new \Exception('TRIAL_ALREADY_USED');
backend/app/Services/PaymentService.php:93:    throw new \Exception('TRIAL_ALREADY_USED');
frontend/src/types/payment.ts:59:  isEligible: boolean
frontend/src/views/app/TrialView.vue:40:  <div v-if="trialInfo && !trialInfo.isEligible" class="used-notice">
```

**關鍵對比：**

| 位置 | key 名稱 |
|---|---|
| 後端回傳（SubscriptionController.php:211）| `eligible` |
| 前端 type 定義（payment.ts:59）| `isEligible` |
| 前端 template 使用（TrialView.vue:40）| `isEligible` |
| fetchTrialInfo 映射 | **無映射** |

---

*診斷完成。等待使用者確認方案後執行階段二。*

---

## 修復紀錄（階段二）

- **採用方案：** D（三邊完整對齊規格，無技術債）
- **修復日期：** 2026-04-28
- **資料庫操作：** 無（依使用者指示不清殭屍訂單）

**改動內容：**
1. `backend/app/Http/Controllers/Api/V1/SubscriptionController.php`：`trial()` 回應 `eligible` → `is_eligible`，補 `trial_available` 與 `notice`，保留 `plan` 物件
2. `frontend/src/types/payment.ts`：`TrialInfo` interface 補齊 `notice`、`plan` 物件，移除頂層 `price`/`durationDays`
3. `frontend/src/composables/usePayment.ts`：`fetchTrialInfo()` 加入 snake_case → camelCase 映射
4. `frontend/src/views/app/TrialView.vue`：price / durationDays 改用後端動態值（fallback 保留 199/30）
5. `docs/API-001_前台API規格書.md` §10.5：回應範例更新為 `plan` 物件巢狀結構

**修復 commit：** （見下方 git log）
