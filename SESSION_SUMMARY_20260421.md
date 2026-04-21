# SESSION_SUMMARY 2026-04-21

## 2026-04-21 修復：output_buffering 導致 API 回傳異常

### 症狀
後台系統設定「清空資料庫」按鈕無反應；
Artisan CLI 正常、前端 POST 回傳卻是 request body 而非 JSON。

### 根本原因
PHP container 的 `output_buffering=0`，導致 response body 被污染。
`Dockerfile.dev` 在 commit `981e3d6` (2026-04-15) 已修過，
但 container image 沒有 rebuild，fix 在 repo 存在卻未套用到 production，
靜默失效長達兩週。

### 永久修復（commit `bb72c5f` + `52d9cfd` → main `c2255b8`）
- 新增 `backend/docker/output-buffering.ini`（`output_buffering=4096`）
- `docker-compose.staging.yml` app service 新增 ro mount：
  `./backend/docker/output-buffering.ini:/usr/local/etc/php/conf.d/zzz-output-buffering.ini:ro`
- Droplet 執行：`docker compose -f docker-compose.staging.yml up -d --no-deps app`

### 復發診斷步驟
若再次出現「CLI 正常但 API 回傳髒資料」：
1. 檢查 `php -i | grep output_buffering`（container 內）
2. 確認 `output-buffering.ini` 有被 mount 進 container
3. `docker compose up -d --no-deps app` 重建

---

## 反覆發生問題（第四條）

| 原因 | 解法 |
|------|------|
| git merge 覆蓋修正 | 每次 merge 前跑 `pre-merge-check.sh` |
| rebuild 沒執行，舊 bundle 繼續服務 | OPS-006 強制每次 deploy 都 rebuild |
| 後端 snake_case 未映射到前端 camelCase | 所有 API 呼叫統一用 `mappers.ts` |
| ⭐ OPS 層 fix 進 repo 但 container 未 rebuild | `pre-merge-check` 檢查 ini/compose/Dockerfile 三者同步 |

---

## pre-merge-check.sh 目前共 16 項

新增第 14-16 項：

| # | 檢查項目 |
|---|---------|
| 14 | `backend/docker/output-buffering.ini` 檔案存在 |
| 15 | `docker-compose.staging.yml` 有 mount `output-buffering.ini` |
| 16 | `Dockerfile.dev` 含 `output_buffering=4096` |

---

## OPS 層檢查原則（未來擴充 pre-merge-check 參考）

所有「需要在 runtime 掛載 / COPY 到 container 才生效」的檔案，
必須同時驗證：
- ① 檔案本身存在於 repo
- ② `docker-compose` 有 mount 聲明
- ③ `Dockerfile` 有對應聲明

三者缺一，fix 就可能靜默失效。
