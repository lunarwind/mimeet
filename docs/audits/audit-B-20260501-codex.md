# Audit Report B Round 2 — 用戶資料 / 搜尋 / 配對 / 訪客 / 收藏 / 封鎖

**執行日期：** 2026-05-01  
**稽核者：** Codex CLI（本機）  
**Agent ID：** codex  
**規格來源：**
- `docs/API-001_前台API規格書.md` §3、§3.1、§3.2.1、§3.6、§10.1、§10.2、§10.5
- `docs/PRD-001_MiMeet_約會產品需求規格書.md` §4.3.1
- `docs/DEV-004_後端架構與開發規範.md` 搜尋/配對相關段落
- `docs/UF-001_用戶流程圖.md` UF-04
**程式碼基準（Local）：** 3649850eaf789e4be62a92ae7afc4538edb2e152  
**工作樹狀態：** 開始前已有既存變更（`backend/app/Services/SmsService.php`、`docs/API-001_前台API規格書.md`、`docs/PRD-001_MiMeet_約會產品需求規格書.md`、`docs/audits/audit-A-20260501-claudecode.md`、`progress/index.html`、多個 untracked 檔案）。本輪未觸碰產品程式碼。  
**前次稽核：**
- `docs/audits/audit-B-20260422.md`
- `docs/audits/audit-B-20260424.md`
- `docs/audits/audit-B-20260427.md`
**總結：** 8 issues（🔴 0 / 🟠 3 / 🟡 4 / 🔵 1）+ 14 Symmetric

---

## 0. 前次 Issue 回歸狀態

| Issue | 前次等級 | 前次結論 | 本輪狀態 | 本輪證據 |
|---|---|---|---|---|
| B-001 / #B-001 `/users/recommendations` 缺失 | 🟠 High | route/controller/FE 均無推薦端點 | ❌ 未修 | API-001:819 仍定義；`routes/api.php:80-98` 無 recommendations |
| B-002 / settings membership 缺失 | 🟠 High | settings 無 `membership` 區塊 | ✅ 已修 | `UserController.php:535` 回傳 `membership`；`buildMembershipData()` 在 540-558 |
| B-003 / 相冊 vs 頭像槽位矛盾 | 🟡 Medium | §3.1 photos 與 §3.3 avatar slots 矛盾 | ⚠️ 部分修 | §3.3 已明確無 `/me/photos`；但 §3.1.3/3.1.4 仍定義 `/users/{id}/photos` |
| B-004 / settings privacy key 不一致 | 🟡 Medium | §3.1.5 寫 `stealth_mode/hide_last_active/read_receipt`，code 用 `show_*` | ❌ 未修 | API-001:704-708 vs `UserController.php:528-534` |
| B-005 / settings profile/account/verification 缺欄位 | 🟡 Medium | `uuid/role/age/phone_last4/advanced_verified` 等缺 | ❌ 未修 | API-001:677-703 vs `UserController.php:497-527` |
| B-006 / search response 缺 vip/distance/compatibility | 🟡 Medium | search response 不含規格欄位 | ❌ 未修 | API-001:789-798 vs `UserController.php:260-276` |
| B-007 / follow response 無 code/message | 🔵 Low | follow/unfollow 只回 data | ❌ 未修 | `UserController.php:684-687`, 696-699 |
| B-008 / job/introduction vs occupation/bio | 🔵 Low | show/settings 使用別名 | ❌ 未修 | API-001:687-689 vs `UserController.php:506-508`, 331-335 |
| B-009 / update route path | 🔵 Low | 規格 `/users/{id}`，實作 `/users/me` | ❌ 未修 | API-001:565 vs `routes/api.php:83` |
| #B2-001 show_in_search JSON 比對脆弱 | 🟡 Medium | key 缺失時被錯誤排除 | ❌ 未修 | `UserController.php:113-117` 仍 `JSON_EXTRACT(...) != 'false'` |
| #B2-002 following response 格式 | 🔵 Low | spec `data.users` vs code direct array | ❌ 未修 | API-001:2835-2849 vs `UserController.php:577-585` |
| #B2-003 visitors 不篩停權者 | 🔵 Low | join users 無 status/deleted filter | ❌ 未修 | `UserController.php:594-602` |
| #B2-004 `frontend/src/api/explore.ts` 不存在 | 🔵 Low | 搜尋 API 合併在 `users.ts` | ❌ 未修 | 檔案存在性檢查仍 ❌；`users.ts:35-51` 實作 search |
| B-003 / FCM Token 端點缺失 | 🟠 High | 20260422 時完全未實作 | ✅ 已修 | `routes/api.php:191-194` + `FcmTokenController.php:12-35` |

---

## 1. Pass 完成記錄

| Pass | 範圍 | Issues 發現 |
|---|---|---|
| P1 路由層 | 19 個指定端點 vs routes/controller/FE | #B3-001, #B3-004, #B3-008 |
| P2 Request Payload | profile/privacy/settings/photos/search/FCM | #B3-002, #B3-004 |
| P3 Response Structure | pagination/user card/settings/follow/block | #B3-003, #B3-005 |
| P4 業務規則 | 30 天、OR NULL、stealth、visitors、favorites、sorting | #B3-006, #B3-007 |
| P5 Middleware / Security / Privacy | auth、blocked、stealth、FCM ownership | #B3-006 |
| P6 前端串接 | users/explore/visitors/favorites/blocked/settings | #B3-002, #B3-003, #B3-007 |
| P7 跨模組副作用 | follow/block/privacy/stealth/photo/fcm | #B3-006, #B3-007 |
| P11 掃描 | duplicate search, stealth, transformUser, F27 validate | #B3-008 |

**檔案存在性檢查原樣輸出：**

```text
✅ backend/app/Http/Controllers/Api/V1/UserController.php
❌ backend/app/Http/Controllers/Api/V1/SearchController.php
❌ backend/app/Http/Controllers/Api/V1/FollowController.php
❌ backend/app/Http/Controllers/Api/V1/VisitorController.php
❌ backend/app/Http/Controllers/Api/V1/BlockController.php
✅ backend/app/Http/Controllers/Api/V1/FcmTokenController.php
✅ backend/app/Http/Controllers/Api/V1/PrivacyController.php
✅ backend/app/Models/User.php
✅ backend/app/Models/UserBlock.php
✅ backend/app/Models/UserProfileVisit.php
❌ backend/app/Models/Favorite.php
✅ backend/app/Models/FcmToken.php
✅ frontend/src/api/users.ts
❌ frontend/src/api/explore.ts
❌ frontend/src/api/visitors.ts
❌ frontend/src/api/favorites.ts
✅ frontend/src/views/app/ExploreView.vue
❌ frontend/src/views/app/ProfileDetailView.vue
✅ frontend/src/views/app/VisitorsView.vue
✅ frontend/src/views/app/FavoritesView.vue
❌ frontend/src/views/app/settings/ProfileEditView.vue
✅ frontend/src/views/app/settings/BlockedView.vue
✅ frontend/src/types/explore.ts
```

---

## 2. Issues 索引（依等級排序）

### 🔴 Critical
（無）

### 🟠 High
- Issue #B3-001 — `GET /users/recommendations` 規格仍存在，但 route/controller/frontend 均未實作
- Issue #B3-002 — Explore 暱稱搜尋前端送 `nickname`，後端 search 未 validate/套用，搜尋框實際無效
- Issue #B3-003 — 封鎖名單頁讀 `data.users`，後端與 API-001 回 `data.blocked_users`，頁面會永遠空白

### 🟡 Medium
- Issue #B3-004 — §3.1 相冊端點與 §3.3 頭像槽位仍互相矛盾，且 sort/delete 端點缺失
- Issue #B3-005 — settings §3.1.5 response 仍與實作欄位不一致
- Issue #B3-006 — 訪客列表未排除停權/刪除訪客
- Issue #B3-007 — 誰來看我前端權限判斷與 UF-04 不一致（女性任意等級 / 男性付費）

### 🔵 Low
- Issue #B3-008 — DEV-004 範例仍指向 `SearchController` + `/search/users`，現況集中在 `UserController::search` + `/users/search`

### ✅ Symmetric（14 條）
- `GET /users/search` route 存在並掛 `auth:sanctum`, `throttle:api`。
- 搜尋預設排除 30 天未活躍用戶，保留 `last_active_at IS NULL`。
- F27 數值/精確/JSON 篩選大多採 `OR NULL`，未填 profile 不被排除。
- `stealth_until` 未來時間會從 search 排除。
- 搜尋雙向排除 blocked/blocker IDs。
- 搜尋排序實作「完整度 → credit_score → last_active_at」。
- `POST /me/fcm-token` 與 `DELETE /me/fcm-token` 已存在，且 destroy 限定 `user_id + token`。
- `PATCH /me/privacy` 已實作單項 key/value 更新，key whitelist 與 API-001 §10.10 一致。
- `POST /users/{id}/follow` 有自我收藏防護與 500 上限。
- `DELETE /users/{id}/follow` route/controller/FE path 一致。
- `GET /users/me/following` 使用統一 `meta.page/per_page/total/last_page`。
- `GET /users/me/visitors` 回傳 90 天訪客與 `meta` 分頁。
- `POST /users/{id}/block`、`DELETE /users/{id}/block` route/controller/spec 一致。
- FCM Token model/controller 存在，20260422 的 FCM 缺失已修。

---

## 3. Issue 詳情

### Issue #B3-001
**Pass：** P1, P3, P7  
**規格位置：** `docs/API-001_前台API規格書.md:818`；`docs/PRD-001_MiMeet_約會產品需求規格書.md:366`  
**規格內容：**
```text
GET /api/v1/users/recommendations
limit: 10
refresh: true|false
配對分數 = 誠信分數權重(40%) + 偏好匹配權重(30%) + 活躍度權重(20%) + 照片吸引力權重(10%)
```
**程式碼位置：** `backend/routes/api.php:80`；`frontend/src/api/users.ts:35`  
**程式碼現況：**
```php
Route::prefix('users')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/search', [UserController::class, 'search']);
    Route::get('/me/following', [UserController::class, 'following']);
    Route::get('/me/visitors', [UserController::class, 'visitors']);
    Route::get('/{id}', [UserController::class, 'show']);
});
```
**差異說明：** 規格與 PRD 仍定義推薦/配對端點與演算法，但 route/controller/frontend 只有 search，沒有 recommendations。這是 20260422/20260424 的 High issue 延續。  
**等級：** 🟠 High  
**建議方案：**
- Option A：實作 `/users/recommendations`，先用規則排序輸出 `match_score/match_reasons`。
- Option B：API-001/PRD 標 Phase 2，Explore 只宣告 search，不宣告推薦。
**推薦：** B 短期；A 作為配對功能正式開發項。

### Issue #B3-002
**Pass：** P2, P6  
**規格位置：** `docs/UF-001_用戶流程圖.md:236`  
**規格內容：**
```text
FILTER_OPT -->|輸入暱稱| SEARCH[即時搜尋結果]
```
**程式碼位置：** `frontend/src/composables/useExplore.ts:31`; `backend/app/Http/Controllers/Api/V1/UserController.php:75`  
**程式碼現況：**
```ts
...(f.search ? { nickname: f.search } : {}),
```
```php
$request->validate([
    'gender' => 'sometimes|in:male,female',
    'age_min' => 'sometimes|integer|min:18',
    'location' => 'sometimes|string',
    // 無 nickname
]);
```
**差異說明：** ExploreView 的搜尋框會送 `nickname`，但後端 search 未 validate 也未將 nickname 套到 query。Laravel validator 不會阻擋多餘 query，因此前端看似搜尋成功但結果未被暱稱過濾。  
**等級：** 🟠 High  
**建議方案：**
- Option A：後端 `search()` 補 `nickname` validation + `where('nickname','LIKE',...)`。
- Option B：前端移除暱稱搜尋 UI，只保留現有地區/條件篩選。
**推薦：** A，UF-04 明確要求即時搜尋，且改動範圍小。

### Issue #B3-003
**Pass：** P3, P6  
**規格位置：** `docs/API-001_前台API規格書.md:924`  
**規格內容：**
```json
{
  "success": true,
  "data": {
    "blocked_users": [
      { "id": 789, "nickname": "某用戶", "avatar": "...", "blocked_at": "2025-01-10T08:00:00Z" }
    ]
  }
}
```
**程式碼位置：** `backend/app/Http/Controllers/Api/V1/UserController.php:751`; `frontend/src/views/app/settings/BlockedView.vue:25`  
**程式碼現況：**
```php
return response()->json([
    'success' => true,
    'data' => ['blocked_users' => $blocks],
]);
```
```ts
const res = await client.get('/me/blocked-users')
blockedUsers.value = (res.data?.data?.users ?? []).map((u) => ({
  id: u.id, nickname: u.nickname, avatar: u.avatar_url, blockedAt: u.blocked_at,
}))
```
**差異說明：** 後端與規格回 `blocked_users`，但前端讀 `data.users`；後端欄位是 `avatar` alias，前端讀 `avatar_url`。結果封鎖名單頁會顯示空狀態或缺頭像。  
**等級：** 🟠 High  
**建議方案：**
- Option A：修前端讀 `data.blocked_users` 並使用 `avatar`。
- Option B：後端額外回 `users` alias 與 `avatar_url`，維持相容。
**推薦：** A，規格與後端已一致，錯在前端串接。

### Issue #B3-004
**Pass：** P1, P2, P7  
**規格位置：** `docs/API-001_前台API規格書.md:600`; `docs/API-001_前台API規格書.md:862`  
**規格內容：**
```text
POST /api/v1/users/{user_id}/photos
DELETE /api/v1/users/{user_id}/photos/{photo_id}
相冊上限 6 張；PUT /api/v1/me/photos/{photo_id}/set-avatar

§3.3: 無獨立 /me/photos 端點，無 sort 操作。
```
**程式碼位置：** `backend/routes/api.php:85`; `backend/app/Http/Controllers/Api/V1/UserController.php:362`  
**程式碼現況：**
```php
Route::post('/me/photos', [UserController::class, 'uploadPhoto']);
Route::get('/me/avatars', [UserController::class, 'getAvatarSlots']);
Route::post('/me/avatars', [UserController::class, 'uploadAvatar']);
Route::patch('/me/avatars/active', [UserController::class, 'setActiveAvatar']);
Route::delete('/me/avatars', [UserController::class, 'deleteAvatar']);
```
**差異說明：** API-001 仍同時存在完整相冊與 avatar_slots 兩套模型。Prompt P1 要求 `DELETE /me/photos`、`PATCH /me/photos/sort`，但 code 只有 `POST /users/me/photos` 與 `/users/me/avatars*`。VerifyView 也使用 `/users/me/photos` 作為驗證照前置上傳。  
**等級：** 🟡 Medium  
**建議方案：**
- Option A：規格正式廢止 §3.1 photos CRUD，將 `/users/me/photos` 定義為通用上傳/驗證照前置 endpoint，主頭像管理只走 avatar slots。
- Option B：實作完整 `user_photos` 表、sort/delete/set-avatar，保留相冊產品。
**推薦：** A，現有前後端已採 avatar slots，完整相冊是另一個功能。

### Issue #B3-005
**Pass：** P3  
**規格位置：** `docs/API-001_前台API規格書.md:672`  
**規格內容：**
```json
"profile": { "uuid": "...", "role": "sweetie", "age": 23, "occupation": "軟體工程師", "bio": "..." },
"account": { "phone_last4": "8888" },
"verification": { "advanced_verified": true, "advanced_verified_at": "..." },
"privacy_settings": { "stealth_mode": false, "hide_last_active": false, "read_receipt": true }
```
**程式碼位置：** `backend/app/Http/Controllers/Api/V1/UserController.php:497`  
**程式碼現況：**
```php
'profile' => [
    'id' => $user->id,
    'nickname' => $user->nickname ?? '',
    'gender' => $user->gender ?? '',
    'birth_date' => $user->birth_date?->toDateString(),
    'job' => $user->occupation ?? '',
    'introduction' => $user->bio ?? '',
],
'verification' => [
    'membership_level' => (float) $user->membership_level,
],
'privacy_settings' => $user->privacy_settings ?? [
    'show_online_status' => true,
    'allow_profile_visits' => true,
    'show_in_search' => true,
],
```
**差異說明：** `membership` 已修，但 §3.1.5 仍列多個實作沒有的欄位，且 privacy keys 與 §10.10/實作不一致。設定頁若依 §3.1.5 型別開發會讀不到資料。  
**等級：** 🟡 Medium  
**建議方案：**
- Option A：修 API-001 §3.1.5，以 `job/introduction/show_*` 與現有欄位為準，移除未實作欄位。
- Option B：後端補齊 `uuid/role/age/phone_last4/advanced_verified(_at)` 並改 privacy key。
**推薦：** A+B 混合：privacy key 應跟 §10.10/實作同步；`phone_last4/advanced_verified` 可評估補回。

### Issue #B3-006
**Pass：** P4, P5, P7  
**規格位置：** `docs/UF-001_用戶流程圖.md:273`  
**規格內容：**
```text
VP1[顯示 90 天內訪客列表]
```
**程式碼位置：** `backend/app/Http/Controllers/Api/V1/UserController.php:594`  
**程式碼現況：**
```php
$query = UserProfileVisit::where('user_profile_visits.visited_user_id', $userId)
    ->where('user_profile_visits.created_at', '>=', $since)
    ->join('users', 'users.id', '=', 'user_profile_visits.visitor_id')
    ->select('users.id', 'users.nickname', 'users.avatar_url', ...);
```
**差異說明：** visitors list join users 後沒有 `users.status='active'` 或 soft-delete 過濾；停權/刪除者若曾造訪仍可出現在「誰來看我」。這延續 #B2-003。  
**等級：** 🟡 Medium  
**建議方案：**
- Option A：在 visitors query 補 `where('users.status','active')` 與 `whereNull('users.deleted_at')`。
- Option B：規格明確允許歷史訪客保留，但前端需遮蔽停權/刪除帳號細節。
**推薦：** A，與搜尋/探索排除非 active 用戶一致。

### Issue #B3-007
**Pass：** P4, P6  
**規格位置：** `docs/UF-001_用戶流程圖.md:273`  
**規格內容：**
```text
VP2 -->|女性任何等級| VP3[直接查看對方資料]
VP2 -->|付費男性| VP3
VP2 -->|未付費男性| VP4[顯示升級提示 Modal]
```
**程式碼位置：** `frontend/src/views/app/VisitorsView.vue:10`  
**程式碼現況：**
```ts
const isPaid = computed(() => (authStore.user?.membership_level ?? 0) >= 2)
function goProfile(id: number) {
  if (!isPaid.value) return
  router.push(`/app/profiles/${id}`)
}
```
**差異說明：** 前端只看 `membership_level >= 2`，沒有 gender 分支。女性 Lv0/Lv1 會被鎖住，男性 Lv2 會被放行；與 UF-04「女性任何等級 / 付費男性」不一致。  
**等級：** 🟡 Medium  
**建議方案：**
- Option A：前端改為 `gender === 'female' || isPaidMale`，並明確定義 paid level。
- Option B：更新 UF-04，改為所有性別都需 membership_level >= 2。
**推薦：** A，符合現有 UF。

### Issue #B3-008
**Pass：** P11  
**規格位置：** `docs/DEV-004_後端架構與開發規範.md:196`  
**規格內容：**
```php
Route::get('users/me/following', [FollowController::class, 'following']);
Route::get('users/me/visitors', [VisitorController::class, 'index']);
Route::get('search/users', [SearchController::class, 'users']);
```
**程式碼位置：** `backend/routes/api.php:80`; 檔案存在性檢查  
**程式碼現況：**
```php
Route::prefix('users')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/search', [UserController::class, 'search']);
    Route::get('/me/following', [UserController::class, 'following']);
    Route::get('/me/visitors', [UserController::class, 'visitors']);
});
```
`SearchController.php`、`FollowController.php`、`VisitorController.php` 均不存在。  
**差異說明：** 這不直接破壞功能，但 DEV-004 的架構範例與實作 drift，且 audit prompt 仍按拆分 controller 搜尋。後續新開發者容易在錯檔案補功能。  
**等級：** 🔵 Low  
**建議方案：**
- Option A：更新 DEV-004，正式承認 B 模組集中於 `UserController`。
- Option B：拆 controller，將 search/follow/visitor/block 移到獨立 controller。
**推薦：** A，現有功能集中且穩定，拆分沒有立即收益。

---

## 4. 行動優先序

| 優先 | 動作 | 對象 |
|---|---|---|
| P1 | 修 Explore nickname search 與 BlockedView key mismatch | FE / BE |
| P2 | 決定 recommendations 是 Phase 2 還是先做簡化版 endpoint | PM / BE |
| P3 | 統一 photos/avatar_slots 規格，避免驗證照、頭像、相冊三者混淆 | PM / 架構 |
| P4 | 修 visitors 停權過濾與性別/付費 gating | BE / FE |
| P5 | 同步 DEV-004 controller/route 範例與 settings response schema | Docs |

## 5. 下次 Audit 建議

- 下次先查 `BlockedView` 與 nickname search 是否修復，這兩個是最直接的用戶可見壞點。
- 若 recommendations 繼續不做，請在 API-001/PRD 明確標 Phase 2，避免每輪重複報 High。
- 若保留 `/users/me/photos` 給驗證照，請把它從「相冊管理」改名為「通用照片上傳」或統一走 `/uploads`。

---

## 附錄 A — P1 端點逐條檢查

| Method | Path | Route 是否存在 | Controller method | Middleware | 前端 API 是否存在 | 狀態 | 備註 |
|---|---|---|---|---|---|---|---|
| GET | /me | ✅ | `AuthController::me`, `UserController::me` | `auth:sanctum` | store `getMe` | ✅ | `/auth/me` 與 `/users/me` 皆存在 |
| PATCH | /me | ✅ as `/users/me` | `UserController::update` | `auth:sanctum`, `throttle:api` | AccountView direct client | ⚠️ | API-001 §3.1.2 寫 `/users/{id}` |
| PATCH | /me/privacy | ✅ | `PrivacyController::update` | `auth:sanctum` | AccountView direct client | ✅ | §10.10 key/value |
| PATCH | /me/settings | ❌ | N/A | N/A | N/A | ❌ | 實作只有 `GET /users/me/settings` |
| POST | /me/photos | ✅ as `/users/me/photos` | `UserController::uploadPhoto` | `auth:sanctum`, `throttle:api` | VerifyView direct client | ⚠️ | 與 §3.3「無 /me/photos」矛盾 |
| DELETE | /me/photos | ❌ | N/A | N/A | N/A | ❌ | avatar delete 是 `/users/me/avatars` |
| PATCH | /me/photos/sort | ❌ | N/A | N/A | N/A | ❌ | avatar slots 無 sort |
| GET | /users/search | ✅ | `UserController::search` | `auth:sanctum`, `throttle:api` | `searchUsers()` | ⚠️ | nickname search 未套用 |
| GET | /users/recommendations | ❌ | N/A | N/A | N/A | ❌ | #B3-001 |
| GET | /users/{id} | ✅ | `UserController::show` | `auth:sanctum`, `throttle:api` | `fetchUserProfile()` | ✅ | route exists |
| POST | /users/{id}/follow | ✅ | `UserController::follow` | `auth:sanctum`, `throttle:api` | `favoriteUser()` | ✅ | 500 limit |
| DELETE | /users/{id}/follow | ✅ | `UserController::unfollow` | `auth:sanctum`, `throttle:api` | `unfavoriteUser()` | ✅ | route exists |
| GET | /users/me/following | ✅ | `UserController::following` | `auth:sanctum`, `throttle:api` | FavoritesView direct client | ⚠️ | spec `data.users` vs code direct array |
| GET | /users/me/visitors | ✅ | `UserController::visitors` | `auth:sanctum`, `throttle:api` | VisitorsView direct client | ⚠️ | 停權過濾/gating issues |
| POST | /users/{id}/block | ✅ | `UserController::block` | `auth:sanctum`, `throttle:api` | `blockUser()` | ✅ | route exists |
| DELETE | /users/{id}/block | ✅ | `UserController::unblock` | `auth:sanctum`, `throttle:api` | `unblockUser()` | ✅ | route exists |
| GET | /me/blocks | ❌ | N/A | N/A | N/A | ⚠️ | API-001 實際為 `/me/blocked-users` |
| POST | /me/fcm-token | ✅ | `FcmTokenController::store` | `auth:sanctum` | 未見集中 API wrapper | ✅ | direct endpoint exists |
| DELETE | /me/fcm-token | ✅ | `FcmTokenController::destroy` | `auth:sanctum` | 未見集中 API wrapper | ✅ | 限 `user_id + token` |

## 附錄 B — P4 業務規則對照

| 規則 | 規格值 | 實作值 | 出處 | 狀態 |
|---|---|---|---|---|
| 搜尋預設只顯示 30 天內活動 | 是 | `subDays(30)`，保留 null | `UserController.php:107-111` | ✅ |
| F27 篩選未填欄位不排除 | OR NULL | range/exact/json 多數 `orWhereNull` | `UserController.php:151-231` | ✅ |
| 隱身用戶不出現搜尋 | `stealth_until <= now` 或 null | 已套用 | `UserController.php:119-123` | ✅ |
| 訪客紀錄忽略停權者 | 是 | 無 `users.status` filter | `UserController.php:594-602` | ❌ |
| 收藏上限 | 500 | count >= 500 擋 | `UserController.php:671-676` | ✅ |
| 看訪客需付費（男）/ 不需（女） | female 任意；male paid | FE 只看 `membership_level >= 2` | `VisitorsView.vue:10` | ❌ |
| 排序 | 完整度 → credit_score → last_active_at | 已實作 | `UserController.php:237-243` | ✅ |

## 附錄 C — P11 掃描記錄

```text
grep -rn "function search" backend/app/Http/Controllers/Api/V1/
backend/app/Http/Controllers/Api/V1/ChatController.php:378: public function searchMessages(...)
backend/app/Http/Controllers/Api/V1/UserController.php:73: public function search(...)

grep -rn "stealth_until|isStealthActive|stealth_mode" backend/app/
UserController.php:119-123 search 排除 stealth_until
UserController.php:311-315 訪客記錄檢查 privacy stealth_mode + isStealthActive()
UserBroadcastController.php:210 廣播排除 stealth_until
StealthController.php 多處管理 stealth_until
User.php:138 isStealthActive()

grep -rn "transformUser" frontend/src/
frontend/src/api/users.ts:48 users.map(transformUser)
frontend/src/api/users.ts:99 function transformUser(raw: RawApiUser): ExploreUser

grep -nA 30 "validate" backend/app/Http/Controllers/Api/V1/UserController.php | grep -E "height|education|style|dating_budget|relationship_goal|smoking|drinking|car_owner|availability"
update validator: height, education, style, dating_budget, relationship_goal, smoking, drinking, car_owner, availability
search validator: min_height/max_height, education, style, dating_budget, relationship_goal, smoking, drinking, car_owner, availability
```

## Self-check

- [x] Header 包含完整 commit hash、Agent ID、規格來源、前次稽核
- [x] 前次 issue 全部回歸
- [x] P1 端點全部逐條列出
- [x] P4 業務規則全部逐條列出
- [x] P11 掃描有原始輸出與判讀
- [x] 每個 issue 有規格證據 + 程式碼證據
- [x] 每個 issue 有 Option A/B + 推薦
- [x] Symmetric 至少 10 條
- [x] 報告檔名為 `docs/audits/audit-B-20260501-codex.md`
- [x] 未修改產品程式碼
