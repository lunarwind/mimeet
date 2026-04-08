# [DEV-007] MiMeet Git 工作流程與程式碼審查規範

**文檔版本：** v1.0  
**適用範疇：** 所有開發人員  
**更新日期：** 2026年3月

---

## 1. 分支策略

採用 **GitHub Flow** 的簡化版本，適合小型團隊快速迭代：

```
main           ──●──────────────●──────────────●──── (生產環境)
                 │              │              │
develop        ──●──●──●──●────●──●──●───────●──── (測試環境)
                    │    │             │
feature/*      ─────●────●             │
                                       │
hotfix/*       ────────────────────────●
```

| 分支 | 說明 | 保護 |
|------|------|------|
| `main` | 生產環境，永遠可部署 | 需 PR + Review |
| `develop` | 整合測試分支 | 需 PR |
| `feature/{ticket-id}-{描述}` | 功能開發 | 無 |
| `fix/{ticket-id}-{描述}` | Bug 修正 | 無 |
| `hotfix/{描述}` | 緊急生產修復（直接從 main 開分支） | 無 |

---

## 2. 分支命名規範

```bash
# 功能開發
git checkout -b feature/MM-001-user-registration
git checkout -b feature/MM-015-anonymous-chat

# Bug 修正
git checkout -b fix/MM-032-credit-score-not-updating

# 緊急修復
git checkout -b hotfix/payment-webhook-failing

# 文件更新
git checkout -b docs/update-api-spec
```

---

## 3. Commit 規範（Conventional Commits）

格式：`{type}({scope}): {description}`

| type | 用途 |
|------|------|
| `feat` | 新功能 |
| `fix` | Bug 修正 |
| `refactor` | 重構（不改變功能） |
| `test` | 新增/修改測試 |
| `docs` | 文件更新 |
| `chore` | 建置工具、設定更新 |
| `perf` | 效能優化 |
| `style` | 程式碼格式（不影響邏輯） |

```bash
# 範例
git commit -m "feat(auth): add email verification flow"
git commit -m "fix(credit-score): auto-suspend not triggered when score equals 0"
git commit -m "feat(chat): implement unread badge with WebSocket updates"
git commit -m "test(subscription): add trial purchase eligibility tests"
git commit -m "docs(api): add anonymous chat endpoints documentation"
git commit -m "chore(deps): upgrade Laravel to 10.48"
```

---

## 4. Pull Request 規範

### 4.1 PR 標題

與 Commit 格式相同：`feat(auth): add email verification flow`

### 4.2 PR 描述模板（`.github/pull_request_template.md`）

```markdown
## 變更說明
<!-- 簡要說明這個 PR 做了什麼 -->

## 相關 Issue
- Closes #(issue number)

## 變更類型
- [ ] 新功能（feat）
- [ ] Bug 修正（fix）
- [ ] 重構（refactor）
- [ ] 文件更新（docs）

## 測試說明
<!-- 說明如何測試此次變更 -->
- [ ] 新增單元測試
- [ ] 新增功能測試
- [ ] 手動測試通過

## 注意事項
<!-- 有特殊注意事項（如需執行 migration、更新 .env 等）在此說明 -->
- [ ] 需執行 `php artisan migrate`
- [ ] 需更新 `.env` 變數：`NEW_ENV_VAR=...`
- [ ] 需執行 `npm install`

## 截圖（如有 UI 變更）
```

### 4.3 Merge 規範

- 所有 PR 合併至 `develop` 需至少 **1 人** Approve
- 合併至 `main` 需至少 **1 人** Approve + 測試環境通過
- 使用 **Squash and Merge**（保持 main 分支歷史整潔）

---

## 5. Code Review 規範

### 5.1 Reviewer 職責

**必須檢查：**
- [ ] 程式碼邏輯是否正確，是否有明顯 Bug
- [ ] 是否符合本文件的架構與命名規範
- [ ] 是否有適當的錯誤處理（try/catch、validation）
- [ ] 是否有安全漏洞（SQL injection、XSS、未授權存取）
- [ ] 是否有對應的測試

**應該檢查：**
- [ ] 是否有過度複雜的邏輯可以簡化
- [ ] 是否有重複代碼可以抽取
- [ ] 命名是否清晰易懂
- [ ] 是否有必要的程式碼注釋

**不應要求：**
- 個人風格偏好（應由 ESLint / PHP CS Fixer 自動處理）
- 無關此 PR 範疇的修改（另開 Issue 追蹤）

### 5.2 Review 回應準則

- **必改**：功能錯誤、安全問題、破壞測試
- **建議**：效能優化、更佳設計模式（可接受不改）
- **可選**：風格偏好（需說明是個人建議，不強制）

---

## 6. 程式碼品質設定

### 6.1 PHP CS Fixer（後端）

```php
// .php-cs-fixer.php
<?php
return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'trailing_comma_in_multiline' => true,
    ])
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in(__DIR__ . '/app')
            ->in(__DIR__ . '/tests')
    );
```

```bash
# 檢查格式（不修改）
docker compose exec backend vendor/bin/php-cs-fixer fix --dry-run

# 自動修正
docker compose exec backend vendor/bin/php-cs-fixer fix
```

### 6.2 ESLint + Prettier（前端/後台）

```json
// .eslintrc.cjs
module.exports = {
  root: true,
  extends: [
    'eslint:recommended',
    '@vue/eslint-config-typescript',
    'prettier'
  ],
  rules: {
    'no-console': 'warn',
    'no-debugger': 'error',
    '@typescript-eslint/no-explicit-any': 'warn'
  }
}
```

```json
// .prettierrc
{
  "semi": false,
  "singleQuote": true,
  "tabWidth": 2,
  "trailingComma": "es5",
  "printWidth": 100
}
```

---

## 7. 開發流程 SOP

```
1. 確認需求（看 PRD / Issue）
       ↓
2. 建立 feature branch
   git checkout develop && git pull
   git checkout -b feature/MM-XXX-description
       ↓
3. 開發與自測
   - 撰寫程式碼
   - 撰寫或更新測試
   - 確認 lint / type-check 通過
   - docker compose exec backend php artisan test
       ↓
4. 推送並開 PR
   git push origin feature/MM-XXX-description
   → 在 GitHub 開 Pull Request 至 develop
       ↓
5. Code Review
   → 指定 1 位 Reviewer
   → 根據 Review 意見修改
       ↓
6. Merge to develop
   → Squash and Merge
   → 自動部署至 Staging 環境
       ↓
7. QA 驗證（在 Staging 上）
       ↓
8. Release（週期性，非每 PR 都 release）
   → develop merge to main
   → 自動部署生產環境
       ↓
9. 刪除 feature branch
```