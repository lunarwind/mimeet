# Decision Memo — Admin Membership Action Edge Cases

**狀態：** ✅ **已解決（2026-05-03）— 採 A1 + B1 弱化版（Cleanup PR-C）**
**建立日期：** 2026-05-03
**簽核日期：** 2026-05-03
**作者：** Claude Code（依男性 Lv2 規格查證 Phase A-C 揭露）
**程式碼基準（決議時）：** branch `develop` HEAD `17c11b8`
**實作 PR：** Cleanup PR-C（base level 改寫 + admin edge cases + 申訴頻率限制）
**相關規格：** PRD-001 §3.2 / §4.4.6、API-001 §10.8

---

## 簽核決議（2026-05-03）

### Edge A：admin verify_advanced 採 **A1**
同步寫 `credit_card_verified_at = now()` + `membership_level = 2`（男性）。
**理由**：admin 手動授權是賦予管理者的特權，子系統故障時可即時 fallback。
admin grant 等同於系統認可的「信用卡可用」，與正常 NT$100 驗證寫入相同欄位。
實作：`AdminController::moderateUser` action='verify_advanced'。

### Edge B：admin unverify_advanced 採 **B1 弱化版**
同步清 `credit_card_verified_at = null` + `membership_level = 1`（男性），
**但** base level 推導擴充後，已有 paid payment 的 user 仍會回到 Lv2。
**理由**：Lv2 本質改定義為「信用卡付款方式可用」（PR-C），付款歷史也是
可用證明，不應被 admin action 抹除。要真正限制 user 應走停權流程。
admin UI 已在 confirm modal 顯式提示此 trade-off。
實作：`AdminController::moderateUser` action='unverify_advanced' + `MemberDetailPage.tsx`。

### 配套：base level 重新定義（PR-C 主軸）
`User::getBaseMembershipLevel()` 對男性 Lv2 條件擴充：
- 條件 A：`credit_card_verified_at IS NOT NULL`
- 條件 B：`exists(payments where paid_at IS NOT NULL)`（涵蓋 paid + refunded + refund_failed）
任一成立即為 Lv2。

### 配套：申訴頻率限制
同停權期間最多 3 次申訴（自 `user.suspended_at` 起算）。
詳見 PRD §4.4.6「申訴頻率限制」。

---

## 背景

訂閱到期降級 PR (`17c11b8`) 實作 `User::getBaseMembershipLevel()`，
推導用戶「不靠訂閱應有的等級」。Phase A-C 規格查證確認 base level
邏輯與 PRD §3.2 規格的「正常路徑」**完全一致**（情境 1）。

但揭露兩個 admin action 的資料不對稱問題：admin 手動升降級時
**只動 `membership_level`，不動底層的 ground truth 欄位**
（`credit_card_verified_at` / `user_verifications.status` 等），
導致與 base level 推導機制行為不一致。

本次 PR **不處理** admin actions（屬獨立議題且影響面廣），獨立記錄此備忘。

---

## Edge A — Admin `verify_advanced` 不寫 ground truth 欄位

### 場景

- Admin 後台呼叫 `PATCH /api/v1/admin/members/{id}` with `action='verify_advanced'`
- 程式碼路徑：`AdminController::moderateUser` line 407-410

```php
} elseif ($action === 'verify_advanced') {
    $target = $user->gender === 'female' ? 1.5 : 2;
    if ((float) $user->membership_level < $target) {
        $user->forceFill(['membership_level' => $target])->save();
    }
}
```

- **僅寫 `membership_level`**，不寫 `credit_card_verified_at`（男性）或建立 `user_verifications` 紀錄（女性）

### 後續影響

1. user 買訂閱 → `membership_level` 升至 3
2. 訂閱到期 → `subscriptions:expire` 跑
3. `User::getBaseMembershipLevel()` 看 `credit_card_verified_at=null` → 推導為 Lv1
4. **admin 的 Lv2 grant 永久消失**（user 從 Lv3 直接降到 Lv1，跳過 Lv2）

### 處理選項

| 選項 | 說明 | 優點 | 缺點 |
|---|---|---|---|
| **A1** | admin `verify_advanced` 同步寫 ground truth 欄位（男寫 `credit_card_verified_at=now()`，女建立 `user_verifications` row with `status='approved'`） | 與正常付款路徑資料一致 | admin grant 與真實驗證在資料層無區別，未來無法區分「真驗證」vs「admin 強升」 |
| **A2** | 新增 `users.admin_granted_level_at` 欄位記錄 admin 手動 grant 時間，base level 邏輯讀此欄位作為次要判定 | 保留資料區別性 | 多一個欄位 + migration + base level 多一條判定 |
| **A3** | 規格層面禁止 admin 手動 grant Lv2/Lv1.5（移除 `verify_advanced` action），只能透過真實驗證流程 | 最簡潔 | admin 失去緊急處理能力（如測試帳號開通） |

---

## Edge B — Admin `unverify_advanced` 不清 ground truth 欄位

### 場景

- 用戶正常 CC 驗證 → `credit_card_verified_at` 已寫入
- Admin 後台呼叫 `action='unverify_advanced'` 想撤回 Lv2
- 程式碼路徑：`AdminController::moderateUser` line 412-416

```php
} elseif ($action === 'unverify_advanced') {
    $current = (float) $user->membership_level;
    if ($current === 1.5 || $current === 2.0) {
        $user->forceFill(['membership_level' => 1])->save();
    }
}
```

- **僅寫 `membership_level=1`**，不清 `credit_card_verified_at`（男性）或改 `user_verifications.status='rejected'`（女性）

### 後續影響

1. user 買訂閱 → `membership_level` 升至 3
2. 訂閱到期 → `subscriptions:expire` 跑
3. `User::getBaseMembershipLevel()` 看 `credit_card_verified_at` 還在 → 推導為 Lv2
4. **admin 的 unverify 失效**（user 被「自動還原」回 Lv2）

### 處理選項

| 選項 | 說明 | 優點 | 缺點 |
|---|---|---|---|
| **B1** | admin `unverify_advanced` 同步清 ground truth 欄位（男清 `credit_card_verified_at=null`，女改 `user_verifications.status='rejected'` + 設 `reject_reason='admin_revoked'`） | 撤回乾淨且可追溯 | 用戶之後可重新驗證（NT$100 退款歷史會有兩筆，需 PM 確認商業規則） |
| **B2** | base level 邏輯改為「綜合判定」：除了看 ground truth 欄位，還要檢查 admin 是否曾下過 unverify（讀 `admin_operation_logs`） | 不動 admin action | base level 邏輯複雜化、效能下降（每次降級都要查 log 表） |
| **B3** | 新增 `users.advanced_verification_revoked_at` 旗標欄位，base level 邏輯先看此旗標 | 折衷方案 | 多一個欄位 + migration |

---

## 受影響範圍（staging 2026-05-03 查證）

```
=== 男性會員等級分佈 ===
Lv1.0 : 1 users (cc_verified: 0)

=== 規格 vs 實作差異 ===
  Male Lv2+ but credit_card_verified_at IS NULL: 0
  Male with cc_verified but level < 2 (admin-revoked): 0
```

**目前 staging 0 user 受影響**，但 production launch 前必須解決。

---

## 待決議事項

1. **PM 拍板**：「admin 手動升 Lv2/Lv1.5」是否為合法操作？
   - 若**是合法**：採 A1 或 A2 補資料一致性
   - 若**不合法**：採 A3 移除 action（搭配「請 user 走真實驗證」的 admin UI 提示）
2. **Edge B 處理選項**：B1 / B2 / B3
3. 是否需要補資料同步遷移（將既有 admin grant 的 user 補上 ground truth 欄位）？

---

## 不在此次處理的理由

1. base level 邏輯本身與 PRD 規格一致（情境 1），無 spec violation
2. Edge A/B 屬 admin path 設計選擇而非 spec gap
3. 影響面跨多個 controller / model / migration，需獨立 sprint 評估
4. staging 目前 0 user 受影響，無緊急性

---

## 相關文件

- PRD-001 §3.2「實作對應」表（本次同步補強）
- DEV-005 §10.4 排程任務一覽
- 訂閱降級 PR commit `17c11b8`
- 男性 Lv2 規格查證報告（本次 conversation 留檔）
