# Audit Report — 訊息收回 / 刪除 / 銷毀機制
**執行日期：** 2026-05-17
**稽核者：** Claude Code
**規格來源：**
- PRD-001_MiMeet_約會產品需求規格書.md（§4.3.3、§4.4.8）
- DEV-001_技術架構規格書.md v1.5（§6.3.1 資料保留與銷毀機制）
- SDD-001_系統設計規格書.md（§9.1.5 資料生命週期管理）
- API-001_前台API規格書.md（§4.1.5、§4.1.6、§4.2.4 MessageRecalled）
- API-002_後台管理API規格書.md（§7 聊天記錄查詢 API）
- DEV-006_資料庫設計與遷移指南.md（§3.x messages 表）
- MiMeet_功能清單_MVP_vs_Phase2.md（F19 訊息回收）
- DEV-009_WebSocket事件規格書.md（MessageRecalled）

**程式碼基準：** `13b197b docs(audits): add full MVP/Phase 2 re-walk audit (2026-05-16)`（branch `develop`）

**總結：** 7 issues（🔴 1 / 🟠 3 / 🟡 2 / 🔵 1）+ 2 Symmetric

---

## 規格文件摘要（本輪讀到的）

- **PRD-001 §4.3.3 line 486**：「訊息回收：5 分鐘內可回收未讀訊息（僅付費會員可使用）」。**沒有**「使用者刪除個別訊息」這條需求。
- **PRD-001 §4.4.8 line 920**：帳號刪除流程提到「所有對話紀錄將在 30 天後永久刪除」（前端 UI 文案），與 DEV-001 設計的 180 天後台參數對照。
- **DEV-001 §6.3.1 line 395-421**：兩階段銷毀機制。第一階段「訊息刪除 → SoftDeletes（deleted_at 標記，前端不顯示）」；第二階段「ProcessGdprDeletions 每日掃描，超過 `data_retention_days` 的軟刪除訊息 → forceDelete()」。明確宣示「實際保留天數（180 天）確保平台具備足夠的安全審核與法遵調閱時間」。
- **SDD-001 §9.1.5 line 1517-1531**：與 DEV-001 §6.3.1 同義。
- **API-001 §4.1.5 line 1300-1326**：`DELETE /chats/{chat_id}/messages/{message_id}` 屬於「**回收**」（is_recalled=true、recalled_at），**不是刪除**。沒有另設個別訊息刪除端點。
- **API-002 §7 line 1259**：「**隱私保護**：已收回的訊息（is_recalled=true）**不顯示內容**，僅顯示佔位符『[已收回]』」。權限矩陣只說明 super_admin / admin / cs 是否能進入頁面，**未區分**已收回訊息對不同角色的可見度。
- **DEV-006 messages 表 line 441-444**：宣告 `is_recalled`、`recalled_at`、`is_deleted_by_sender`、`is_deleted_by_receiver` 四欄。**沒有** `deleted_at` 欄位於本表結構區（後續 migration 補加）。
- **MiMeet_功能清單_MVP_vs_Phase2.md line 56（F19）**：「訊息回收：5 分鐘內可回收未讀訊息（付費會員）」。**只列回收，沒列個別刪除**。

---

## 索引

🔴 Critical
- Issue #MD-001 — API-002 §7「admin 一律遮蔽 recalled 訊息」直接違反 DEV-001 §6.3.1 / SDD-001 §9.1.5 的兩階段銷毀設計目的（180 天 retention 對 admin 無實質意義）

🟠 High
- Issue #MD-002 — `Message::SoftDeletes` + `GdprService::purgeDeletedMessages` 是 dead code（沒有任何路徑會把訊息軟刪除）
- Issue #MD-003 — `messages.is_deleted_by_sender` / `is_deleted_by_receiver` 欄位 schema 預留但全系統 0 使用，schema 與規格雙邊都對「使用者個別刪除訊息」沒有結論
- Issue #MD-004 — DEV-001 §6.3.1 寫的「訊息刪除 → SoftDeletes」在實作端沒有任何進入點（沒有 DELETE 個別訊息的非回收 API、ChatService::softDelete 是針對 conversation 而非 message）

🟡 Medium
- Issue #MD-005 — `ChatLogController::search()` 在 SQL 階段就 `where('is_recalled', false)`，連 admin 也無法用關鍵字搜尋已收回訊息（即使日後規格改成可看，現有 SQL 仍會擋）
- Issue #MD-006 — `ChatLogTest::test_conversations_shows_recalled_message_placeholder` 把「admin 看不到原文」鎖死為測試 invariant，會反過來阻擋未來修復

🔵 Low
- Issue #MD-007 — `SystemSettingsSeeder` 預設 `data_retention_days=365`，但 `2026_04_10_000003` migration 又 updateOrInsert 設為 `180`，兩處數字不一致

✅ Symmetric
- F19 收回端點：規格 / 後端 / 前台一致
- DB schema：messages.is_recalled / recalled_at 欄位與 API 回傳一致

---

## Issue #MD-001 — admin 端遮蔽 recalled 訊息直接違反兩階段銷毀的設計目的

**規格位置 1：** docs/DEV-001_技術架構規格書.md §6.3.1 line 395-421
**規格內容 1：**
> 第一階段：軟刪除 / 隔離（用戶操作觸發）
>   ├─ 訊息刪除 → SoftDeletes（deleted_at 標記，前端不顯示）
> 第二階段：物理銷毀（排程 Job 執行）
>   └─ ProcessGdprDeletions（每日排程）
>       ├─ 超過 data_retention_days 的軟刪除訊息 → forceDelete()
> 設計原則：前端隱私權政策對用戶宣告最短 30 天保留期，實際保留天數由後台 `data_retention_days` 參數控制（預設 180 天），**確保平台具備足夠的安全審核與法遵調閱時間**。

**規格位置 2：** docs/API-002_後台管理API規格書.md §7 line 1259
**規格內容 2：**
> **隱私保護**：已收回的訊息（is_recalled=true）不顯示內容，僅顯示佔位符「[已收回]」。
> 所有查詢動作自動寫入 admin_operation_logs。

**程式碼位置：**
- backend/app/Http/Controllers/Api/Admin/ChatLogController.php:32（search 過濾 is_recalled）
- backend/app/Http/Controllers/Api/Admin/ChatLogController.php:123（conversations 把 content 置為 null）
- backend/app/Http/Controllers/Api/Admin/ChatLogController.php:209（export CSV 寫 `[已收回]`）
- backend/app/Http/Controllers/Api/Admin/ChatLogController.php:269（memberChatLogsExport 寫 `[已收回]`）
- backend/app/Http/Controllers/Api/Admin/ChatLogController.php:312（memberChatLogs 把 content 置為 null）
- backend/app/Http/Controllers/Api/Admin/ChatLogController.php:351（last_message preview 寫 `[已收回]`）

**程式碼現況：**
```php
// search()
$query = Message::with(['sender:id,nickname,avatar_url', 'conversation'])
    ->where('is_recalled', false)              // ← admin 連搜尋都搜不到
    ->where('content', 'LIKE', "%{$keyword}%")

// conversations()
'content' => $msg->is_recalled ? null : $msg->content,  // ← 對 super_admin 也吐 null

// export()
$msg->is_recalled ? '[已收回]' : $msg->content,         // ← CSV 也只給佔位符
```

**差異說明：**
- DEV-001 §6.3.1 / SDD-001 §9.1.5 明確宣示 retention 期內保留資料的目的是「安全審核與法遵調閱」，意即 **平台需要有人能在期內讀到原文**，否則保留就只是浪費儲存。
- API-002 §7 與實作把已收回訊息對「所有後台角色」都遮蔽，連 super_admin 都看不到。這實質上把 DEV-001 設計的兩階段銷毀降級為「立即匿名化」。
- 三方規格之間出現語意斷裂：DEV-001 / SDD-001 把 admin 視為調閱者；API-002 §7 把 admin 視為一般觀察者。
- 客訴 / 法遵調閱情境（如：用戶 A 收回不當訊息，用戶 B 截圖檢舉，super_admin 需要驗證原文）目前**完全無法處理**。

**等級：** 🔴 Critical（規格內部矛盾 + 設計目的失效）

**建議方案：**
- **Option A：規格明確化 — 維持「admin 完全遮蔽」**
  - 修 DEV-001 §6.3.1 / SDD-001 §9.1.5：把「為了安全審核與法遵調閱」這段拿掉，retention 改為純粹的「物理空間管理」用途（避免立即清空降低資料庫負擔，180 天後正式 forceDelete）。
  - 修 API-002 §7：補一句「即使 super_admin 也不可看到 recalled 原文，符合用戶隱私期望」。
  - 影響面：純文件，不需動程式碼。但要承認「收回」等於「對所有人立即不可見」。
- **Option B：實作修正 — admin（含 super_admin / admin）皆可看到 retention 期內原文**
  - 改 `ChatLogController` 五處：保留 `content` 原文，但回傳新增 `is_recalled / recalled_at` 旗標讓 UI 標示。
  - search() 拿掉 `where('is_recalled', false)`。
  - 改 API-002 §7 規格文字。
  - admin UI（`ChatLogsPage.tsx:441-446`）改為顯示原文 + 「（已被使用者收回）」標籤。
  - 影響面：跨棧 atomic 改動，**觸發 CLAUDE.md「API Contract 變更標準回滾流程」**（response 結構動到 content nullable→string、UI 渲染同步改）。
  - 修 `ChatLogTest::test_conversations_shows_recalled_message_placeholder`（見 MD-006）。
- **Option C：分級權限 — super_admin 看得到原文、admin 看遮蔽（仿 API-002 §10.D 的 `show_ip` 模式）**
  - controller 判斷 `auth()->user()->role === 'super_admin'` 決定是否回傳原文。
  - 回傳結構新增 `is_recalled_visible: bool`（給 admin UI 知道為何 content 是 null）。
  - 修 API-002 §7 規格、修權限矩陣加列「已收回訊息原文」一欄。
  - 修 `ChatLogsPage.tsx` 依角色顯示原文或佔位符。
  - 影響面：跨棧 atomic 改動，**觸發 API Contract 變更標準回滾流程**；需更新 pre-merge-check 守護「super_admin 看得到原文」測試以防退化。

**推薦：** **Option C**（分級權限）。理由：
- 與既有「super_admin 看得到 IP、admin 不能（API-002 §10.D）」設計風格一致，符合「最小驚訝原則」。
- 比 Option B 更符合用戶隱私期待（一般 admin 看不到 → 降低內部濫用風險）；比 Option A 保留法遵調閱能力（super_admin 仍可調閱）。
- DEV-001 §6.3.1 對「retention 期內保留資料給審核」的設計意圖最直接被滿足。

---

## Issue #MD-002 — `Message::SoftDeletes` + `purgeDeletedMessages` 是 dead code

**規格位置：** docs/DEV-001_技術架構規格書.md §6.3.1 line 395-410
**規格內容：**
> 第二階段：物理銷毀（排程 Job 執行）
>   └─ ProcessGdprDeletions（每日排程）
>       ├─ 超過 data_retention_days 的軟刪除訊息 → forceDelete()

**程式碼位置：**
- backend/app/Models/Message.php:11（`use SoftDeletes;`）
- backend/database/migrations/2026_04_10_000003_create_user_activity_logs_and_retention.php:28-32（為 messages 表加 deleted_at）
- backend/app/Services/GdprService.php:169-177（`purgeDeletedMessages`）
- backend/app/Console/Commands/ProcessGdprDeletions.php:40-41（Phase 3 呼叫 purgeDeletedMessages）

**程式碼現況：**
```php
// GdprService.php:172
public function purgeDeletedMessages(int $days): int
{
    return Message::onlyTrashed()
        ->where('deleted_at', '<=', now()->subDays($days))
        ->forceDelete();
}
```

**差異說明：**
- Message model 確實啟用 `SoftDeletes`，DB 也有 `deleted_at` 欄位。
- 但全 codebase 找不到 **任何呼叫 `$message->delete()` / `Message::destroy()`** 的地方：
  - `ChatService::recallMessage`（line 229）：只 `update(['is_recalled' => true])`，不呼叫 `delete()`
  - `ChatService::softDelete`（line 298）：作用於 Conversation，不是 Message
  - `ChatController::destroy`（line 488）：呼叫 `chatService->softDelete($conv, $user)`，仍是 Conversation
  - admin 端沒有「刪除訊息」的 endpoint
- 結論：`purgeDeletedMessages($days)` 每天執行都會回傳 0；`deleted_at` 永遠是 NULL。
- 這是「設計到一半被中止」的痕跡：DEV-001 §6.3.1 規劃了 SoftDeletes 流程，但前端 / API / 業務邏輯沒有對應的「軟刪除入口」。

**等級：** 🟠 High（基礎建設與設計意圖斷裂；給排程一個永遠回 0 的工作是潛在誤導）

**建議方案：**
- **Option A：補上「使用者刪除個別訊息」端點**（讓 SoftDeletes 有人用）
  - 新增 `DELETE /chats/{id}/messages/{messageId}` 但**改為 POST /chats/{id}/messages/{messageId}/delete**（避免與既有 recallMessage 衝突；既有 `DELETE` 路由已被 recall 佔用）。或 PRD / API-001 補規格區分「刪除（軟）」與「回收（is_recalled）」。
  - 需 PRD / API-001 / DEV-006 同步補上規格。
  - 影響面：跨棧改動。
- **Option B：移除 dead code，schema 也清理**
  - 移除 `Message::SoftDeletes` trait（保留 `deleted_at` 欄位但棄用，或寫 migration 刪欄位 — 風險較高，保留欄位較安全）。
  - 移除 `GdprService::purgeDeletedMessages` 與 `ProcessGdprDeletions` 的 Phase 3。
  - 修 DEV-001 §6.3.1 與 SDD-001 §9.1.5 把「訊息刪除 → SoftDeletes」這條移除，改為「訊息回收後 N 天內 forceDelete」（如果決定銷毀 recalled 訊息）或「訊息一律永久保留直到帳號匿名化」。
  - 影響面：純後端 + 規格文件。

**推薦：** **Option B**（清理 dead code），除非 PM 明確要 Option A。理由：
- F19 已經是合理的「使用者控制訊息可見度」入口（5 分鐘內收回）。再加一個「刪除」會讓 UX 模糊（用戶搞不清楚兩者差別）。
- 兩階段銷毀的真正主體應該是「recalled 訊息 N 天後 forceDelete」，而不是現在這個「沒人會觸發的 SoftDeletes」。如果 Option B 採行，可順便把 `purgeDeletedMessages` 改成 `purgeOldRecalledMessages`（清掉超過 retention 的 recalled 訊息），讓兩階段銷毀真正落地於 recall 流程。

---

## Issue #MD-003 — `is_deleted_by_sender` / `is_deleted_by_receiver` 欄位全系統 0 使用

**規格位置：** docs/DEV-006_資料庫設計與遷移指南.md §messages 表 line 443-444
**規格內容：**
```sql
is_deleted_by_sender   TINYINT(1) NOT NULL DEFAULT 0,
is_deleted_by_receiver TINYINT(1) NOT NULL DEFAULT 0,
```
（無 comment 說明這兩欄的用途）

**程式碼位置：**
- backend/database/migrations/2026_04_08_000002_create_messages_table.php:24-25（schema 宣告）
- backend/app/Models/Message.php:25-26, 34-35（fillable + casts）
- backend/tests/ frontend/src/ admin/src/：**0 處讀寫**（grep 確認）

**程式碼現況：** 全 codebase 從未 SET 也未 WHERE 過這兩欄。

**差異說明：**
- 從欄位命名看，原意應該是「sender / receiver 個別把訊息從自己視窗刪除（但對方還看得到）」這種「per-user 隱藏」設計。
- 但規格端（PRD / API-001 / API-002 / DEV-001）**完全沒提**這個功能。
- 與 conversation 層的 `deleted_by_a` / `deleted_by_b`（ChatService::softDelete 在用）平行但更細緻；前者實作了，後者沒有。
- schema bloat：欄位 + index 維護成本，但無業務價值。

**等級：** 🟠 High（schema / spec / 實作三方不一致，且方向未定）

**建議方案：**
- **Option A：實作 per-user 訊息隱藏**（需 PRD 補規格 + 跨棧改動）
- **Option B：刪欄位 + 修 DEV-006**（寫 migration drop columns，修 Message model fillable/casts）

**推薦：** **Option B**。理由：
- 「per-user 隱藏訊息」不在 MVP / Phase 2 功能清單裡，YAGNI。
- 留著兩個未用欄位會讓未來 audit 反覆出現「這是不是 bug？」的提問。
- 觸發條件：API Contract 變更標準回滾流程不適用（純 DB schema 純後端，無 API contract 改動，但**有 migration**——需做 migration safety 評估）。

---

## Issue #MD-004 — 「訊息刪除」流程在實作端沒有任何進入點

**規格位置：** docs/DEV-001_技術架構規格書.md §6.3.1 line 401
**規格內容：**
> 第一階段：軟刪除 / 隔離（用戶操作觸發）
>   ├─ 訊息刪除 → SoftDeletes（deleted_at 標記，前端不顯示）

**程式碼位置：**
- backend/routes/api.php:145（DELETE /chats/{id}/messages/{messageId} → 走 recallMessage）
- backend/app/Http/Controllers/Api/V1/ChatController.php:488-507（destroy 操作 conversation 而非 message）
- backend/app/Services/ChatService.php:298-307（softDelete 操作 conversation 而非 message）

**程式碼現況：**
```php
// routes/api.php:145
Route::delete('/{id}/messages/{messageId}', [ChatController::class, 'recallMessage'])
    ->middleware('membership:3');

// ChatService.php:298
public function softDelete(int $conversationId, int $userId): void
{
    $conversation = Conversation::findOrFail($conversationId);
    if ($conversation->user_a_id === $userId) {
        $conversation->update(['deleted_by_a' => 1]);
    } else {
        $conversation->update(['deleted_by_b' => 1]);
    }
}
```

**差異說明：**
- DEV-001 §6.3.1 在「軟刪除」階段列了「訊息刪除」，但實作端沒有對應的 API 或業務動作。
- 唯一接近的：F19「回收」走 `is_recalled` 旗標，不是 `deleted_at`。
- Conversation 層的 `deleted_by_a / b` 是「整段對話從我這邊消失」而非「個別訊息軟刪除」。
- 結合 MD-002：DEV-001 §6.3.1 描述的「訊息刪除 → SoftDeletes」整條流程都是孤兒。

**等級：** 🟠 High（規格描述了一條不存在的流程）

**建議方案：** 與 MD-002 合併處理。如選 MD-002 Option B（清理 dead code），則 DEV-001 §6.3.1 也跟著改寫；如選 MD-002 Option A（補實作），則本 issue 自然解決。

---

## Issue #MD-005 — `ChatLogController::search()` 連 admin 也搜不到已收回訊息

**規格位置：** docs/API-002_後台管理API規格書.md §7.1 line 1264-1293（全站關鍵字搜尋）
**規格內容：**
- 7.1 沒有明文說「不能搜尋 recalled」。§7 line 1259 的「隱私保護」段只說「不顯示內容」，不是「不能搜尋」。

**程式碼位置：** backend/app/Http/Controllers/Api/Admin/ChatLogController.php:32
**程式碼現況：**
```php
$query = Message::with(['sender:id,nickname,avatar_url', 'conversation'])
    ->where('is_recalled', false)              // ← 過濾在 SQL 階段
    ->where('content', 'LIKE', "%{$keyword}%")
```

**差異說明：**
- 規格只說「不顯示內容」，但實作把 recalled 訊息整筆從搜尋結果中濾掉。
- 後果：即使 super_admin 知道有不當訊息發生（從用戶檢舉得知關鍵字），也搜不到那筆訊息的 metadata（誰、何時、給誰）。
- 與 conversations() / memberChatLogs() 不一致（後兩者會回傳 recalled 訊息的 meta，只是把 content 設 null）。

**等級：** 🟡 Medium（規格未禁止；後台稽核工具能力受限）

**建議方案：**
- **Option A**：拿掉 `where('is_recalled', false)`，改在輸出時把 content 改為佔位符；同時把 keyword match 限制改成「matched on now-recalled content」標籤。
- **Option B**：保留現狀但補規格說明「全站關鍵字搜尋不含已收回訊息」。

**推薦：** **Option A**，與 MD-001 一併考量；若 MD-001 採 Option C 分級權限，本 issue 應同步給 super_admin 搜得到 content（admin 搜不到原文但仍看得到 metadata）。

---

## Issue #MD-006 — 測試把「admin 看不到原文」鎖死為 invariant

**規格位置：** N/A（測試本身是規格的固化）
**程式碼位置：** backend/tests/Feature/Admin/ChatLogTest.php:94-129
**程式碼現況：**
```php
public function test_conversations_shows_recalled_message_placeholder(): void
{
    // ...
    Message::create([
        'content' => '這是秘密訊息',
        'is_recalled' => true,
        // ...
    ]);
    $response = $this->withAdminAuth($admin)->getJson(...);
    $messages = $response->json('data.messages');
    $this->assertNull($messages[0]['content']);            // ← 鎖死
    $this->assertTrue($messages[0]['is_recalled']);
}
```

**差異說明：**
- 若採 MD-001 Option B / C 修復，此測試會 fail，需同步改寫。
- 目前測試本身合乎現行 API-002 §7 規格，但會阻擋規格往「admin 看得到原文」演進。
- 沒有對應的負向測試（如「super_admin 一定看得到原文」）—— 因此沒有守護分級權限的 invariant。

**等級：** 🟡 Medium（規格演進障礙）

**建議方案：** 隨 MD-001 處理；若採 Option C 則改寫為兩個案例：
1. `test_super_admin_sees_recalled_content_in_retention_period`
2. `test_regular_admin_sees_placeholder_for_recalled_content`

---

## Issue #MD-007 — `data_retention_days` 預設值兩處不一致

**規格位置：** docs/DEV-001_技術架構規格書.md §6.3.1 line 416
**規格內容：** 「實際保留天數：`data_retention_days`（預設 180 天）」

**程式碼位置：**
- backend/database/migrations/2026_04_10_000003_create_user_activity_logs_and_retention.php:38（updateOrInsert value 180）
- backend/database/seeders/SystemSettingsSeeder.php:31（value 365）

**程式碼現況：**
```php
// migration
['value' => '180', 'description' => '資料保留天數...']

// seeder
['key_name' => 'data_retention_days', 'value' => '365', ..., 'description' => '用戶活動日誌保留天數']
```

**差異說明：**
- migration 與 seeder 對同一個 key 寫不同預設值，且 description 也不同（一個說「資料保留」、一個說「活動日誌保留」）。
- 跑 `mimeet:reset --force` 後最終值取決於執行順序（seeder 後跑會覆蓋 migration）。
- 規格寫 180，實際若 seeder 後跑則生效為 365。

**等級：** 🔵 Low（不影響功能，但會讓 audit / 法遵調查時數字對不上）

**建議方案：** 對齊為 180，並修 seeder 的 description 改為「資料保留天數（180 天，DEV-001 §6.3.1）」。

---

## ✅ Symmetric

### F19 訊息回收（前端 + 後端 + 規格三方對齊）

- **規格依據：**
  - PRD-001 §4.3.3 line 486：5 分鐘內 / 未讀 / 付費會員
  - MiMeet_功能清單_MVP_vs_Phase2.md line 56（F19）
  - API-001 §4.1.5 line 1300-1326（路由、條件、回應）
  - DEV-009 §MessageRecalled line 206-221（WebSocket 事件）

- **實作對照：**
  - 路由：backend/routes/api.php:145（middleware:3 限制付費會員）
  - 後端條件：backend/app/Services/ChatService.php:229-261（sender / 5min / unread / 已回收 四道防線）
  - 廣播：`MessageRecalled` 事件（ChatService.php:255）
  - 前端 UI：frontend/src/components/chat/MessageBubble.vue:52-61（canRecall computed 同四條件 + Lv3）
  - 前端顯示：MessageBubble.vue:81-83 顯示「訊息已收回」佔位符

- **結論：** sender / 5min / unread / 付費會員四條件三方完全一致；前端 canRecall 守護與後端 service throw 條件對位；廣播流程符合 DEV-009 規格。✅

### Messages 表 is_recalled / recalled_at 欄位（schema + model + API 一致）

- **規格依據：** DEV-006 §3.x messages 表 line 441-442
- **實作對照：**
  - migration：backend/database/migrations/2026_04_08_000002_create_messages_table.php:22-23
  - model：backend/app/Models/Message.php:23-24, 33, 38（fillable + casts datetime）
  - API 回傳：ChatService.php:90-100（getMessages 含 `is_recalled` / 收回時 content/image_url 設 null）

- **結論：** schema → ORM → API 三層欄位名一致、型別一致。✅

---

## 補充：未發現的功能（明確列出缺口）

| 規格期望 | 實作狀態 | 備註 |
|---|---|---|
| 使用者刪除個別訊息（PRD / API-001 沒寫，但 DEV-001 §6.3.1 與 messages 表 schema 暗示） | ❌ 未實作 | 整個系統沒有「個別訊息軟刪除」入口 |
| 使用者收回訊息（F19） | ✅ 已實作 | 5 分鐘 / 未讀 / Lv3 三條件 |
| 收回訊息原文於 DB 保留 | ✅ 保留 | ChatService::recallMessage 只 set flag，不清 content |
| super admin 可看到 recalled 原文 | ❌ 未實作 | ChatLogController 對所有 admin 角色一律遮蔽（見 MD-001） |
| super admin 可看到 deleted 原文 | N/A | 沒有「使用者刪除個別訊息」這個動作，自然沒有 deleted 訊息 |
| 排程 forceDelete recalled 訊息 | ❌ 未實作 | `purgeDeletedMessages` 只清 `Message::onlyTrashed()`，但永遠空集合（見 MD-002） |
| 銷毀期限 retention 設定 | ⚠️ 部分實作 | `data_retention_days` 存在於 SystemSetting，但只作用於活動日誌與隔離區檔案，不作用於訊息 |
| F19 收回 API 測試覆蓋 | ❌ 未覆蓋 | grep 全 backend/tests/ 找不到 RecallMessage 的 endpoint 測試（只有 ChatLogTest 對 admin 端的 placeholder assert） |

---

*本報告完，依 CLAUDE.md「修改前必做」原則，下一步建議與業主/PM 確認採用 MD-001 的 Option A / B / C 中哪一個；該選擇將決定 MD-002 / MD-005 / MD-006 的修復方向。*
