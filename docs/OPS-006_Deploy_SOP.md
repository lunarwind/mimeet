# OPS-006 Deploy SOP

## Every Deploy

### Step 1: Pre-merge check
```bash
bash scripts/pre-merge-check.sh
```
All checks must pass before merging.

### Step 2: Merge
```bash
git checkout develop && git pull
bash scripts/pre-merge-check.sh
git checkout main && git pull
git merge develop --no-ff -m "..."
git push origin main
git checkout develop
```

### Step 3: Droplet deploy
```bash
ssh root@188.166.229.100 '
cd /var/www/mimeet && git pull origin main

# Backend
docker exec -u www-data mimeet-app php artisan migrate --force
docker exec -u www-data mimeet-app php artisan config:cache
docker exec -u www-data mimeet-app php artisan route:cache

# Frontend (must rebuild every time)
cd /var/www/mimeet/frontend && npm run build

# Admin (must rebuild every time)
cd /var/www/mimeet/admin && npm run build

# Restart workers (pick up new code)
supervisorctl restart mimeet-worker:*
'
```

### Step 4: Smoke test
```bash
echo -n "Frontend: " && curl -s -o /dev/null -w "%{http_code}" https://mimeet.online
echo -n " Admin: " && curl -s -o /dev/null -w "%{http_code}" https://admin.mimeet.online
echo -n " API: " && curl -s -o /dev/null -w "%{http_code}" \
  -X POST https://api.mimeet.online/api/v1/auth/login \
  -H "Content-Type: application/json" -d '{"email":"x","password":"x"}'
echo ""
```
Expected: Frontend 200, Admin 200, API 401

## Common Issues

| Symptom | Root Cause | Fix |
|---------|-----------|-----|
| Fix reverted after merge | Old version on other branch | Run pre-merge-check.sh |
| Code deployed but UI unchanged | Frontend/admin not rebuilt | Always rebuild both |
| Fields blank or undefined | snake_case/camelCase mismatch | Map in API layer |
| API 500 after cache commands | root ran artisan (permission) | Use `-u www-data` |
| output_buffering reset | Container restart clears php.ini | Re-apply: `echo output_buffering=4096 > .../output_buffering.ini && kill -USR2 1` |
