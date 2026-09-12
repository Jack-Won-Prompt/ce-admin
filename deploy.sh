#!/usr/bin/env bash
# 운영 서버(~/www/lcpoint)에서 `bash deploy.sh` 로 실행하는 배포 스크립트.
set -euo pipefail

# 1. 스크립트가 있는 위치(저장소 루트)로 이동
cd "$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")"

COMPOSER_BIN=/usr/local/bin/composer
BRANCH=main
MAINTENANCE=0

# 2. 실패하거나 중간에 끊겨도(Ctrl+C, SSH 끊김, kill) 점검 모드가 남지 않게 한다.
#    끊긴 터미널에는 출력할 수 없으므로 여기서는 출력 실패를 무시한다.
bring_up() {
    local code=$?
    set +e
    if [ "$MAINTENANCE" -eq 1 ]; then
        php artisan up >/dev/null 2>&1
        echo "[deploy] 중단됨(exit ${code}) — 점검 모드를 해제했습니다." >&2 2>/dev/null
    fi
    exit "$code"
}
trap bring_up EXIT
trap 'exit 129' HUP
trap 'exit 130' INT
trap 'exit 143' TERM

BEFORE="$(git rev-parse --short HEAD)"

echo "[deploy] 3. 점검 모드를 켭니다 (php artisan down)"
MAINTENANCE=1
php artisan down

echo "[deploy] 4. 코드를 받습니다 (git pull origin ${BRANCH})"
git pull origin "$BRANCH"
AFTER="$(git rev-parse --short HEAD)"

echo "[deploy] 5. 의존성을 설치합니다 (composer install)"
"$COMPOSER_BIN" install --no-interaction --prefer-dist --optimize-autoloader

echo "[deploy] 6. 설정·라우트·뷰 캐시를 비웁니다"
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo "[deploy] 7. 마이그레이션을 실행합니다 (php artisan migrate --force)"
php artisan migrate --force

echo "[deploy] 8. 점검 모드를 해제합니다 (php artisan up)"
php artisan up
MAINTENANCE=0

# 새 마이그레이션 파일이 들어왔을 때만 migrate:rollback 을 롤백 절차에 넣는다.
# (들어온 게 없는데 rollback 하면 이전 배포의 마지막 batch 가 되돌려진다.)
ROLLBACK_MIGRATE=""
if [ -n "$(git diff --name-only "$BEFORE" "$AFTER" -- database/migrations)" ]; then
    ROLLBACK_MIGRATE="php artisan migrate:rollback --force && "
fi

if [ "$BEFORE" = "$AFTER" ]; then
    echo "[deploy] 완료: ${BEFORE} → ${AFTER} (새 커밋 없음, 롤백 불필요)"
else
    echo "[deploy] 완료: ${BEFORE} → ${AFTER} | 롤백: php artisan down && ${ROLLBACK_MIGRATE}git reset --hard ${BEFORE} && ${COMPOSER_BIN} install --no-interaction --prefer-dist --optimize-autoloader && php artisan config:clear && php artisan route:clear && php artisan view:clear && php artisan up"
fi
