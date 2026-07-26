#!/usr/bin/env bash
# Route smoke test — curls public/auth routes and asserts the HTTP status is
# in an acceptable set. Fails on any 500.
#
# Usage: BASE=http://localhost:8888/muhindo-app bash scripts/smoke.sh
set -u

BASE="${BASE:-http://localhost:8888/muhindo-app}"
FAIL=0

check() {
  local path="$1"; shift
  local allowed="$1"; shift
  local code
  code=$(curl -s -o /dev/null -w '%{http_code}' -L --max-redirs 0 "${BASE}${path}" 2>/dev/null)
  if [[ " ${allowed} " == *" ${code} "* ]]; then
    printf '  \033[32m✓\033[0m %-28s %s\n' "$path" "$code"
  else
    printf '  \033[31m✗\033[0m %-28s %s (allowed: %s)\n' "$path" "$code" "$allowed"
    FAIL=1
  fi
}

echo "Smoke testing ${BASE}"
echo "── Public ─────────────────────────────"
check "/"                 "200"
check "/courses"          "200"
check "/privacy"          "200"
check "/terms"             "200"
check "/login"            "200"
check "/register"         "200"
echo "── Auth-gated (expect redirect to login) ──"
check "/dashboard"        "302"
check "/admin"            "302 404"
check "/learn"            "302"
check "/portal"           "302"

if [[ $FAIL -eq 0 ]]; then
  echo -e "\n\033[32mAll routes healthy — no 500s.\033[0m"
else
  echo -e "\n\033[31mSome routes failed.\033[0m"; exit 1
fi
