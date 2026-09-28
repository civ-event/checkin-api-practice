#!/usr/bin/env bash
# 用系统自带的 bash 解释本脚本
# 并发签到演示：先 join 拿 activityUserToken，再并发打两次 clock-in，期望最多 1 次 success。
#
#   chmod +x scripts/concurrent_clock_in.sh
#   ./scripts/concurrent_clock_in.sh
set -euo pipefail
# -e：命令失败立即退出；-u：未定义变量报错；pipefail：管道任一失败算失败

BASE_URL="${BASE_URL:-http://localhost:18080}"  # API 根地址，可环境变量覆盖
ACTIVITY_ID="${ACTIVITY_ID:-1}"                 # 活动 ID
CHECK_DAY="${CHECK_DAY:-1}"                     # 要签的天

# 假登录：拿到 activityUserToken
token_json="$(curl -s -X POST "$BASE_URL/api-auth/activity/join" \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  -d 'accessToken=token-player-1001&serverId=s2&roleId=r200')"

TOKEN="$(php -r 'echo json_decode(stream_get_contents(STDIN))->activityUserToken ?? "";' <<<"$token_json")"

if [[ -z "$TOKEN" ]]; then
  echo "FAIL: cannot get token"
  echo "$token_json"
  exit 1
fi

echo "got token for activity=${ACTIVITY_ID}"

tmp1="$(mktemp)"  # 临时文件存第 1 个响应
tmp2="$(mktemp)"  # 临时文件存第 2 个响应
trap 'rm -f "$tmp1" "$tmp2"' EXIT  # 脚本结束（含异常）时删除临时文件

# 后台并发发第 1 个 clock-in
curl -s -X POST "$BASE_URL/api-front/activity/monthly-check-in/clock-in" \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  -H "activity-user-token: ${TOKEN}" \
  -d "activity_id=${ACTIVITY_ID}&check_day=${CHECK_DAY}" >"$tmp1" &
pid1=$!  # 记下后台进程 PID

# 后台并发发第 2 个 clock-in（尽量同时）
curl -s -X POST "$BASE_URL/api-front/activity/monthly-check-in/clock-in" \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  -H "activity-user-token: ${TOKEN}" \
  -d "activity_id=${ACTIVITY_ID}&check_day=${CHECK_DAY}" >"$tmp2" &
pid2=$!

wait "$pid1" "$pid2"  # 等两个 curl 都结束

echo "=== response 1 ==="
cat "$tmp1"  # 打印响应 1
echo
echo "=== response 2 ==="
cat "$tmp2"  # 打印响应 2
echo

success=0  # 统计含 "status":"success" 的次数
for f in "$tmp1" "$tmp2"; do
  if grep -q '"status":"success"' "$f"; then
    success=$((success + 1))  # 命中则 +1
  fi
done

echo "success_count=${success}"
if [[ "$success" -le 1 ]]; then
  echo "PASS: at most one success"  # 锁生效：最多一次成功
  exit 0
fi

echo "FAIL: more than one success"  # 两次都成功说明锁没挡住
exit 1
