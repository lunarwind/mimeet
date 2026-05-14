# 體驗方案「自動續訂 toggle」Bug — 全棧診斷

**報告類型**：Bug 診斷（階段一，盤點 + 修復計畫）
**診斷日期**：2026-05-14
**診斷者**：Claude Code（本機 + staging 唯讀）
**狀態**：🟠 待使用者拍板修法方案後執行階段二
**基準 commit**：`97fbf2d`（main）/ `1c8bf14`（develop）

---

## 結論

**體驗方案目前是否可能被續訂：是（多層風險組合）**

實機資料：admin@mimeet.club（user_id=1，membership=3.0）目前 **無任何 subscription record / order record**（staging tinker 確認），但 user 報告看到 toggle。意味著 UI 暴露點與 API 層漏洞**互相獨立、都存在**：

| 暴露面 | 暴露程度 | 是否會持久化 |
|---|---|---|
| ShopView 訂閱確認 Modal「到期後自動續訂」checkbox（line 404-407） | UI 暴露給所有方案（含 trial 進入 selectPlan 流程時） | 否（cosmetic，confirmPurchase 不讀此值） |
| ShopView 付費會員區塊 toggle（line 254-263） | UI 暴露給任何 `isPaid && currentSubscription` 用戶（含 trial sub） | 是（透過 PATCH /subscriptions/me 寫入 DB） |
| SubscriptionView toggle（line 75-80） | 同上 | 同上 |
| API PATCH /subscriptions/me（無 is_trial guard） | 任何用戶都能對任意 active sub（含 trial）切 auto_renew | 是 |
| Admin 後台 | **未暴露**（updateSubscriptionPlan 不允許改 is_trial；MemberDetail 訂閱 read-only） | — |

**根本原因**（multi-cause failure）：
1. `getActiveSubscription` 回傳 payload **不含 `is_trial`** → 前端無條件 render toggle 沒得依據
2. `SubscriptionController::update` **不檢查 plan.is_trial** → API 層無防護
3. 訂閱確認 Modal 寫死的 checkbox 對所有方案都顯示（dead UI but 誤導）

**好消息**：`PaymentService::activateSubscription` 建立邏輯有 `'auto_renew' => !$plan->is_trial`（line 200）— trial 從付款成功流程出來時**保證 auto_renew=false**。新建的 trial sub 不會帶 `auto_renew=true`，現有資料污染僅可能發生於：(a) API 漏洞被直接呼叫 / (b) 既有 staging 髒資料（DB 查到 sub_count=0、無污染）。

---

## 證據

### Step 1 — 規格層

**檔案**：`docs/PRD-001_MiMeet_約會產品需求規格書.md:702`
```
- 「不自動續費」由 `PaymentService::activateSubscription()` 在 `is_trial=true` 時強制設定 `subscriptions.auto_renew = false`
```

**檔案**：`docs/API-001_前台API規格書.md:3092-3098`（§10.5）
```json
"notice": "每位會員限購一次，購買後不可退款，不自動續費"
```

**檔案**：`docs/BRD-001_業務需求規格書.md:222`
```
購買後系統記錄該帳號已使用體驗訂閱，後續商城不再顯示體驗方案
```

**結論**：spec **明確且強制**規定 trial 不可續訂。實作須對齊。無矛盾段落。

---

### Step 2 — DB schema / seed

**檔案**：`backend/database/seeders/SubscriptionPlanSeeder.php:66-75`
```php
[
    'slug'             => 'plan_trial',
    'name'             => '體驗方案',
    ...
    'is_trial'         => true,
    ...
],
```

**檔案**：`backend/app/Models/SubscriptionPlan.php:9-16`
```php
protected $fillable = ['slug', 'name', 'price', ..., 'is_trial', 'is_active', ...];
protected $casts = ['is_trial' => 'boolean', ...];
```

**檔案**：`backend/app/Models/Subscription.php:10-16`
```php
protected $fillable = [..., 'auto_renew', 'started_at', 'expires_at', ...];
protected $casts = ['auto_renew' => 'boolean', ...];
```

**結論**：
- DB schema **無** `is_renewable` 欄位 — 透過 `subscription_plans.is_trial` 與 `subscriptions.auto_renew` 兩個 boolean 表達語意
- seeder trial record `is_trial=true` 正確
- Model 無 boot / Observer 對 `auto_renew` 做 trial-aware default（依賴 application layer 設值）

---

### Step 3 — 後端建立邏輯（trialPurchase → DB record）

**Path A：trial 專用流程**
- `SubscriptionController::trialPurchase` (`backend/app/Http/Controllers/Api/V1/SubscriptionController.php:227`)
- → `paymentService->createOrderRecord(...)`
- → 走 ECPay 付款 → callback 觸發
- → `PaymentService::activateSubscription` (`backend/app/Services/PaymentService.php:163`)

**Path B：通用方案購買流程**
- `SubscriptionController::createOrder` (line 79) — 接受任意 `plan_id`，沒擋 trial
- → `paymentService->createOrderRecord` → ECPay → callback
- → `PaymentService::activateSubscription`（同上）

**強制 force false 位置**：`backend/app/Services/PaymentService.php:200`
```php
'auto_renew' => !$plan->is_trial,
```
**註解 line 193-194**：「Trial 方案規格：「不含自動續費功能」(PRD §4.4.3)；一般訂閱保留 DB 預設 auto_renew=true」

**結論**：建立邏輯**正確**。trial 經任一路徑建立 sub 時，auto_renew 強制為 false。

---

### Step 4 — 後端切換邏輯 PATCH /subscriptions/me

**檔案**：`backend/app/Http/Controllers/Api/V1/SubscriptionController.php:137-163`

```php
public function update(Request $request): JsonResponse
{
    $request->validate([
        'auto_renew' => 'required|boolean',
    ]);

    $sub = Subscription::where('user_id', $request->user()->id)
        ->where('status', 'active')
        ->first();

    if (!$sub) {
        return response()->json([..., 'code' => 404, ...], 404);
    }

    $sub->update(['auto_renew' => $request->boolean('auto_renew')]);
    // ↑ 直接 update，無 plan.is_trial 檢查
    ...
}
```

**結論**：🔴 **無 is_trial guard**。任何持有 trial active sub 的用戶都可發 PATCH 把 `auto_renew=true`。Auth 與 active 狀態檢查存在，但對 trial 完全敞開。

---

### Step 5 — 前台 UI

#### 5.1 ShopView 訂閱確認 Modal — `frontend/src/views/app/ShopView.vue`

**Line 31-33**：
```typescript
const showConfirmModal = ref(false)
const selectedPlan = ref<SubscriptionPlan | null>(null)
const autoRenewChecked = ref(true)  // ← 預設 true
```

**Line 126-131**：`selectPlan` 開啟 modal，重置 `autoRenewChecked.value = true`

**Line 404-407**：modal 內 checkbox
```vue
<label class="modal-card__check">
  <input type="checkbox" v-model="autoRenewChecked" />
  <span>到期後自動續訂</span>
</label>
```

**Line 133-166**：`confirmPurchase` — **不讀 `autoRenewChecked`**，POST `/subscriptions/orders` 只送 `plan_id` + `payment_method`。

**結論**：🟡 **Cosmetic dead UI**。checkbox 顯示但不影響訂單。對所有方案無條件顯示（trial 若進此流程也會看到「到期後自動續訂」勾選）。**仍是誤導 UX bug**。

#### 5.2 ShopView 付費會員區塊 — line 244-267

```vue
<section v-if="isPaid && currentSubscription" class="my-member">
  ...
  <button
    class="toggle-btn"
    :class="{ 'toggle-btn--on': currentSubscription.autoRenew }"
    @click="handleAutoRenewToggle"
  >
```

**Line 168-175**：`handleAutoRenewToggle` 呼叫 `toggleAutoRenew(newVal)` → `usePayment.ts:158` → PATCH `/subscriptions/me`

**結論**：🟠 **無 plan.is_trial 檢查**。trial sub 在此 section 一視同仁顯示 toggle，點擊會真的打 API（API 層無防護，問題 4）。

#### 5.3 SubscriptionView toggle — `frontend/src/views/app/settings/SubscriptionView.vue:75-80`

```vue
<span class="sub-setting__label">自動續訂</span>
<button :class="{ 'toggle-sm--on': currentSubscription.autoRenew }" ...>
```

同上問題，無 is_trial 條件。

#### 5.4 TypeScript type — `frontend/src/types/payment.ts:18-25`

```typescript
export interface CurrentSubscription {
  planType: string
  planName: string
  expiresAt: string
  autoRenew: boolean
  daysRemaining: number
}
```

**無 `isTrial` 欄位**。前端即使想加條件渲染也沒資料源。

#### 5.5 usePayment composable — `frontend/src/composables/usePayment.ts:60-89`

`fetchCurrentSubscription` 從 `/subscriptions/me` 拉資料，後端 payload **不含 `is_trial`**（見 Step 6.1）。

---

### Step 6 — 後端 mySubscription / Admin

#### 6.1 mySubscription payload — `backend/app/Services/PaymentService.php:341-351`

```php
return [
    'id' => $sub->id,
    'plan_id' => $sub->plan->slug,
    'plan_name' => $sub->plan->name,
    'status' => $sub->status,
    'auto_renew' => $sub->auto_renew,
    'started_at' => $sub->started_at->toISOString(),
    'expires_at' => $sub->expires_at->toISOString(),
    'days_remaining' => ...,
    'membership_level' => $sub->plan->membership_level,
];
```

**🔴 缺 `is_trial` 欄位**。即使 `$sub->plan->is_trial` 隨手可得（line 334 已 `with('plan')` eager load），payload 沒帶出去。前端 UI 條件渲染失去依據。

#### 6.2 Admin updateSubscriptionPlan — `backend/app/Http/Controllers/Api/V1/Admin/SystemControlController.php:450-488`

validate rules（line 457-468）**不接受 `is_trial`** → admin 後台不能把正式方案改為 trial 或反之。**OK**。

#### 6.3 Admin MemberDetailPage — `admin/src/pages/members/MemberDetailPage.tsx:422-443`

訂閱顯示為唯讀（`Descriptions.Item label="訂閱方案" / "訂閱狀態" / "到期日"`），**無 auto_renew toggle**。**OK**。

#### 6.4 Admin 後台搜尋確認

`grep -rn "auto_renew\|autoRenew" admin/src/` → 0 命中。Admin 後台無 auto_renew 控制入口。**OK**。

---

### Step 7 — 測試與守護

**已有覆蓋**：`backend/tests/Feature/Subscription/ExpireSubscriptionsTest.php:211-212`
```php
// ─── Case 9: trial activation → auto_renew=false (議題 2) ───
public function test_trial_activation_sets_auto_renew_false(): void
```
覆蓋**建立邏輯**（activateSubscription 強制 false）。

**缺漏覆蓋**：
- ❌ 無測試覆蓋 「PATCH /subscriptions/me 對 trial sub 應回 422」
- ❌ 無測試覆蓋「mySubscription 回傳含 is_trial 欄位」
- ❌ pre-merge-check **無**對 SubscriptionController::update 的 is_trial guard 守護
- ❌ pre-merge-check **無**對 getActiveSubscription payload 內 is_trial 欄位的守護

`grep -rn "is_trial\|trial.*auto_renew" backend/tests/` → 命中皆在 ExpireSubscriptionsTest，缺上述兩條。

---

### Step 8 — 實機驗證（staging tinker 唯讀）

```
user_id=1 (admin@mimeet.club)
membership_level=3.0
credit_card_verified_at=null
orders=0
subscriptions=0
total_subs_in_db=1（其他 user 的）
```

**`/subscriptions/me` 實機回傳**：`{"data":{"subscription":null}}`
**`/subscription/trial` 實機回傳**：`{trial_available: true, is_eligible: true, ...}`
**`/subscriptions/plans` 實機回傳**：plans 陣列 4 個（plan_weekly/monthly/quarterly/yearly），**不含 trial**；trial 在獨立 `trial` key。

---

### 觀察：admin 帳號為何 isPaid=true 但無 subscription

admin@mimeet.club 是測試 super_admin，user.membership_level 被手動設為 3.0（可能透過 mimeet:reset seed 或人工調整），但沒走過任何付款 → 沒 order、沒 subscription。

**對 ShopView 行為的影響**：
- `isPaid = membership_level >= 2` → **true**
- `currentSubscription = null`（API 回 null）
- `v-if="isPaid && currentSubscription"`（line 244）→ **false** → 付費會員區塊**不顯示**
- `v-if="!isPaid && trialInfo?.trial_available && trialInfo?.is_eligible"`（line 270）→ **false** → 體驗入口**不顯示**
- 方案 grid（line 286）→ 4 個正式方案卡片**顯示**（plan_weekly~yearly），**不含 trial**

**所以使用者看到的「體驗方案 + 自動續訂 toggle」應該不是 admin@mimeet.club 在 staging 此刻的實際畫面。** 可能來源：
- 截圖較早，當時 admin 有 trial sub（後被清掉）
- 看的是 confirm modal 內勾選 plan_yearly/quarterly 時的「到期後自動續訂」checkbox（看起來像 trial card，但其實是正式方案）
- 不同帳號截圖混入

**無論截圖實際對應哪個畫面，本報告列出的所有 toggle 暴露點都是真實 bug，需修。**

---

## 問題清單（依嚴重度排序）

### 🔴 #1 — PATCH /subscriptions/me 無 is_trial guard
- **描述**：任何持有 active trial sub 的用戶可透過此 endpoint 把 `auto_renew=true`，直接違反 PRD §4.4.3 / API-001 §10.5。
- **影響範圍**：所有 trial 用戶；API 層完全敞開。
- **觸發路徑**：trial 用戶 → PATCH /subscriptions/me `{auto_renew: true}` → DB 更新成功 → 到期被自動續訂、再次扣款
- **檔案**：`backend/app/Http/Controllers/Api/V1/SubscriptionController.php:137-163`
- **嚴重度**：🔴 Critical（規格違反、潛在誤扣款）

### 🔴 #2 — getActiveSubscription 不回傳 is_trial
- **描述**：`/subscriptions/me` payload 沒帶 `is_trial`，前端 UI 條件渲染失去依據。
- **影響範圍**：所有前端 toggle 顯示點（ShopView line 254、SubscriptionView line 75）都依賴此欄位才能正確隱藏。
- **觸發路徑**：trial sub 用戶開 /app/shop 或 /app/settings/subscription → toggle 無條件顯示
- **檔案**：`backend/app/Services/PaymentService.php:341-351`
- **嚴重度**：🔴 Critical（修 #1 後仍需此欄位才能修 UI）

### 🟠 #3 — ShopView 付費會員區塊 toggle 無 is_trial 條件
- **描述**：line 254-263 toggle 對所有 active sub 顯示，含 trial。
- **影響範圍**：trial sub 用戶（目前少；admin 例外狀態）
- **檔案**：`frontend/src/views/app/ShopView.vue:254-263`
- **嚴重度**：🟠 High

### 🟠 #4 — SubscriptionView toggle 無 is_trial 條件
- **描述**：同 #3，另一個 view。
- **檔案**：`frontend/src/views/app/settings/SubscriptionView.vue:75-80`
- **嚴重度**：🟠 High

### 🟡 #5 — ShopView 訂閱確認 Modal「到期後自動續訂」checkbox（dead UI）
- **描述**：line 404-407 checkbox 預設 checked，但 confirmPurchase 不讀。對所有方案顯示，誤導用戶。
- **影響範圍**：所有點任意方案進確認 modal 的用戶
- **檔案**：`frontend/src/views/app/ShopView.vue:33,128,404-407`
- **嚴重度**：🟡 Medium（無資料持久化影響，但 UX 不一致；trial 進入此 modal 會看到「自動續訂」更誤導）

### 🟡 #6 — TypeScript type CurrentSubscription 缺 isTrial
- **描述**：`frontend/src/types/payment.ts:18-25` 無 isTrial 欄位，配合 #2 修。
- **檔案**：`frontend/src/types/payment.ts:18-25`
- **嚴重度**：🟡 Medium（與 #2 配套）

### 🟡 #7 — usePayment composable 不 map is_trial
- **描述**：fetchCurrentSubscription（usePayment.ts:71-80）建構 CurrentSubscription 時未讀 is_trial。
- **檔案**：`frontend/src/composables/usePayment.ts:71-80`
- **嚴重度**：🟡 Medium（與 #2/#6 配套）

### 🔵 #8 — 測試守護缺漏
- **描述**：無測試覆蓋「PATCH 對 trial 應 422」與「mySubscription 帶 is_trial」。pre-merge-check 也沒對應 guard。
- **影響範圍**：未來 regression 無 CI 保護
- **嚴重度**：🔵 Low（治本層）

### ✅ #9 — 建立邏輯 force false（已正確）
- 不是 bug，列入確認清單
- `backend/app/Services/PaymentService.php:200` 正確、有測試覆蓋

### ✅ #10 — Admin 後台不暴露（已正確）
- updateSubscriptionPlan 不接受 is_trial、MemberDetailPage 無 auto_renew toggle、admin/src/ 無 auto_renew 任何引用

---

## 建議 fix 方向

| 選項 | 內容 | 利 | 弊 |
|---|---|---|---|
| **F1**（已實作 ✅） | 後端建立邏輯強制 `auto_renew=false`（PaymentService::activateSubscription line 200） | 已就位 | — |
| **F2** | 後端 PATCH /subscriptions/me 對 trial sub 回 422 | API 層深度防禦、防 IDOR/直接呼叫 | 需要前端配合錯誤處理 |
| **F3a** | 後端 getActiveSubscription payload 補 `is_trial` 欄位 | 為 F3b 提供資料 | 改前端 contract（小） |
| **F3b** | 前端 type CurrentSubscription + usePayment 映射補 isTrial；ShopView / SubscriptionView toggle 加 `v-if="!currentSubscription.isTrial"`；toggle 旁可加說明文字「體驗方案不續訂」 | UX 立刻乾淨 | 不解決 #5 dead UI checkbox |
| **F4** | ShopView 訂閱確認 Modal 移除「到期後自動續訂」checkbox（或對 selectedPlan.isTrial 隱藏 + 改文字「不自動續訂」唯讀提示） | 消除 dead UI、消除誤導 | 一點 UI 重排 |
| **F5** | Tinker / 一次性 query 清理既有 trial sub 的 `auto_renew=true`（staging 目前無此資料污染，可暫緩；production 上線前再評估） | 立刻修現況資料 | 目前 staging 確認無污染 → 此項可降優先 |
| **F6** | 補測試：（a）feature test for PATCH trial 應 422；（b）feature test mySubscription payload 含 is_trial；（c）pre-merge-check guard 確保 update method 含 is_trial 檢查 / payload 含 is_trial | 防未來 regression | 工時略增 |
| **F7** | 規格層：API-001 §10.9（PATCH /subscriptions/me）補上「對 trial sub 回 422」契約描述 | 三方規格一致 | 簡單文件改動 |

**推薦組合**：**F2 + F3a + F3b + F4 + F6 + F7**（後端 API guard + payload 補欄位 + 前端三個 UI 點修正 + 測試守護 + 規格同步）。F5 暫不執行，等 production 上線前複查。

---

## 修復計畫 checklist

- [x] **API contract 變更**：是
  - PATCH /subscriptions/me 對 trial 回 422（新增錯誤分支）
  - GET /subscriptions/me 回傳 `data.subscription.is_trial`（新增欄位，前端忽略則向下相容）
- [ ] **DB schema 變更**：否（既有 `subscription_plans.is_trial` 已足）
- [ ] **Queue / cache 影響**：否（無相關 job / cache）
- [ ] **Build 流程影響**：否（純 PHP + Vue 編譯）
- [x] **規格 docs 需更新**：是 — API-001 §10.9 + §7.1.3
- [x] **測試需新增**：
  - `backend/tests/Feature/Subscription/SubscriptionUpdateTrialGuardTest.php`（新檔）
  - `backend/tests/Feature/Subscription/MySubscriptionPayloadTest.php`（新檔，覆蓋 is_trial 欄位）
- [x] **pre-merge-check 守護**：新增 14ay
  - awk 切片 SubscriptionController::update method 區段，require `is_trial` 字樣
  - grep `getActiveSubscription` return array，require 含 `'is_trial' =>`
- [x] **Staging rollback 計畫**：標準 `bash scripts/staging-rollback.sh`（涉及 API contract 變更，依「API Contract 變更標準回滾流程」處理）
- [ ] **既有資料清理**：staging 無污染（tinker 確認 `auto_renew=true` 的 trial sub = 0）；production 上線前再複查

---

## 建議下一步（commit 切分）

依「API Contract 變更標準回滾流程」採「**單 PR atomic** 不拆」原則：

**Commit 1（單一 atomic commit，跨 backend + frontend + spec + tests）**：
1. `backend/app/Http/Controllers/Api/V1/SubscriptionController.php` — update 加 is_trial guard 回 422 `{ code: TRIAL_NOT_RENEWABLE, message: '體驗方案不支援自動續訂' }`
2. `backend/app/Services/PaymentService.php` — getActiveSubscription payload 補 `'is_trial' => $sub->plan->is_trial`
3. `frontend/src/types/payment.ts` — CurrentSubscription 加 `isTrial: boolean`
4. `frontend/src/composables/usePayment.ts` — fetchCurrentSubscription 映射 `isTrial: raw.is_trial ?? false`
5. `frontend/src/views/app/ShopView.vue` — line 254-263 加 `v-if="!currentSubscription.isTrial"`；line 404-407 加 `v-if="!selectedPlan?.isTrial"` 或重寫為唯讀提示
6. `frontend/src/views/app/settings/SubscriptionView.vue` — line 75-80 加 `v-if="!currentSubscription.isTrial"`
7. `docs/API-001_前台API規格書.md` — §10.9 加 422 trial 回應；§7.1.3 payload 加 is_trial
8. `backend/tests/Feature/Subscription/SubscriptionUpdateTrialGuardTest.php` — 新檔
9. `scripts/pre-merge-check.sh` — 14ay 新增

**部署**：標準 develop → main → staging-deploy.sh，無 migration 需 rollback 風險。

**驗收**：
- A. 一般訂閱用戶在 ShopView 仍看得到 toggle（regression check）
- B. trial 用戶（用 tinker 暫建一個 trial sub）登入後在 ShopView / SubscriptionView **看不到** toggle
- C. 直接 curl PATCH /subscriptions/me 對 trial sub → 422
- D. 訂閱確認 modal 不再顯示「到期後自動續訂」checkbox（或對 trial 不顯示，依擇定方案）

---

*本報告為唯讀診斷，未變更任何 code / spec / DB。等使用者拍板修法後再進階段二 commit。*
