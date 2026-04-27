# Audit-D Round 2 — 訂閱 / 付費 / 媒體 / 點數

> 先讀 prompts/audit/_common.md。

## 規格範圍
- docs/API-001 §7（支付訂閱）
- docs/API-001 §10.3（取消訂閱申請）
- docs/API-001 §10.5（體驗價）
- docs/API-001 §10.9（自動續訂）
- docs/API-001 §11（點數系統 F40）
- docs/API-001 §3.5（媒體上傳 / 隔離區）
- docs/DEV-011（金流與發票整合規格書）
- docs/PRD-001 §4.2.5（訂閱）§4.4.3（體驗價）
- docs/UF-001 UF-07（訂閱付費）

## 前次稽核
- docs/audits/audit-D-20260424.md

## 程式碼範圍

```bash
# 訂閱 / 付費
backend/app/Http/Controllers/Api/V1/SubscriptionController.php
backend/app/Http/Controllers/Api/V1/PaymentCallbackController.php
backend/app/Http/Controllers/Api/V1/CheckoutController.php  # 若存在
backend/app/Services/PaymentService.php
backend/app/Services/ECPayService.php
backend/app/Services/SubscriptionService.php
backend/app/Models/Order.php
backend/app/Models/Subscription.php
backend/app/Models/SubscriptionPlan.php

# 點數
backend/app/Http/Controllers/Api/V1/PointController.php
backend/app/Services/PointService.php
backend/app/Models/PointPackage.php
backend/app/Models/PointOrder.php
backend/app/Models/PointTransaction.php

# 媒體
backend/app/Http/Controllers/Api/V1/MediaController.php
backend/app/Services/GdprService.php  # quarantine 邏輯

# 設定
backend/config/ecpay.php
backend/config/services.php

# 前端
frontend/src/api/subscriptions.ts
frontend/src/api/points.ts
frontend/src/api/media.ts
frontend/src/views/app/ShopView.vue
frontend/src/views/app/TrialView.vue
frontend/src/views/app/SubscriptionView.vue
frontend/src/views/app/PointsView.vue
frontend/src/types/payment.ts
```

## 規格端點清單（P1）
- GET /subscriptions/plans
- POST /subscriptions/orders（建立訂單，回傳 payment_url）
- GET /subscriptions/me（我的訂閱）
- PATCH /subscriptions/me（toggle auto_renew）
- POST /subscriptions/cancel-request
- GET /subscription/trial、POST /subscription/trial/purchase（限購一次）
- POST /payments/ecpay/notify（CheckMacValue 驗證）
- POST/GET /points/packages、/points/balance、/points/history、/points/purchase
- POST /points/super-like、/points/stealth、/points/broadcasts
- POST/DELETE /uploads（媒體上傳 + quarantine 移動）

## 模組特有檢查

### P4 業務規則對照
| 規則 | 規格值 | 怎麼驗 |
|---|---|---|
| 訂閱方案 4 種 | 週/月/季/年 | `grep -rn "plan_weekly\|plan_monthly\|plan_quarterly\|plan_yearly" backend/` |
| 體驗價限購一次 | 是 | `grep -n "TRIAL_ALREADY_USED\|is_trial" backend/app/Services/PaymentService.php` |
| 訂閱到期自動降級 | 是 | `grep -rn "SubscriptionExpired\|expired" backend/app/Console/` |
| ECPay CheckMacValue | SHA256 + 7 組 .NET 替換 | `grep -nA 30 "generateCheckMacValue" backend/app/Services/ECPayService.php` |
| 發票 AES-128-CBC | PKCS7 + Base64 | `grep -nA 10 "issueInvoice\|aes-128-cbc" backend/app/Services/ECPayService.php` |
| 對帳欄位 | trade_no/payment_date/payment_type/invoice_no | `grep -rn "ecpay_trade_no\|invoice_no" backend/app/` |
| 環境切換 | sandbox/production via system_settings | `grep -n "ecpay_environment" backend/app/Services/ECPayService.php` |
| 媒體隔離區 | quarantine/{date}/ | `grep -nA 5 "quarantine" backend/app/Services/GdprService.php` |
| 點數消費規則 | 7 個 system_settings keys | `grep -rn "point_cost_\|broadcast_user_" backend/` |

### P11 模組特有
```bash
# 訂單建立邏輯是否散落多處
grep -rn "Order::create\|new Order" backend/app/

# ECPay 環境字串是否硬編碼
grep -rn "'sandbox'\|'production'" backend/app/Services/ECPayService.php backend/config/

# 是否有舊的 MockController / mock 路由殘留（規格已說正式環境直跳 ECPay）
grep -rn "mock\|Mock" backend/app/Http/Controllers/Api/V1/

# 點數加減邏輯是否多處（PointService vs Controller 直寫）
grep -rn "points_balance\s*[-+]=\|increment.*point\|decrement.*point" backend/app/

# 媒體上傳的 mime 驗證是否一致
grep -rn "image|file|max:" backend/app/Http/Controllers/Api/V1/MediaController.php backend/app/Http/Controllers/Api/V1/ChatController.php
```

## 重點關注（前次 Round 1）
- D-002：路由 /subscriptions/me vs /subscription/status 哪個生效
- D-003：取消訂閱回應格式
- D-005：trial 回應結構
- D-007：cancel-request request body 結構
- D-003 (新)：DELETE /uploads 是否實作
