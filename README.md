# MiMeet

台灣高端交友平台 — Phase 1 MVP

## 技術架構

| 層級 | 技術 |
|---|---|
| 前台 | Vue 3 + TypeScript + Tailwind CSS |
| 後台 | React 18 + Ant Design 5 |
| 後端 | Laravel 10 + PHP 8.2 |
| 資料庫 | MySQL 8.0 + Redis 7.0 |
| 容器化 | Docker + Docker Compose |

## 專案結構
mimeet/
├── frontend/     # Vue.js 3 前台
├── admin/        # React 18 後台
├── backend/      # Laravel 10 後端
├── docker/       # Docker 設定
├── progress/     # 進度追蹤頁
└── .vscode/      # VS Code 設定

## 本地開發啟動

### 前置需求

- Docker Engine 25+
- Node.js 22 LTS（建議用 nvm）
- Git 2.40+

### 前台啟動
```bash
cd frontend
npm install
npm run dev
# http://localhost:5173
```

### 後台啟動
```bash
cd admin
npm install
npm run dev
# http://localhost:5174
```

### 後端啟動（Docker）
```bash
docker compose up -d
docker compose exec backend php artisan migrate
docker compose exec backend php artisan db:seed
# API: http://localhost:8000
```

## 開發規範

- 分支策略：[DEV-007](docs/DEV-007_Git工作流程與程式碼審查規範.md)
- Commit 格式：`feat(scope): description`
- PR 必須通過 lint + type-check

## 進度追蹤

[Phase 1 開發進度](https://mimeet-progress.netlify.app)
