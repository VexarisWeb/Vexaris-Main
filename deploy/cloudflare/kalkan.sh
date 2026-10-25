#!/usr/bin/env bash
# vexaris.net + vexaris.app Cloudflare kalkanini kurar (idempotent).
# Token: /srv/vexaris/secrets/cloudflare.env -> CF_API_TOKEN (bashrc'deki AWS_API_TOKEN ile ayni deger)
# Kullanim: ./kalkan.sh [--dry-run]
set -u
DRY=0
[ "${1:-}" = "--dry-run" ] && DRY=1

[ -f /srv/vexaris/secrets/cloudflare.env ] && set -a && . /srv/vexaris/secrets/cloudflare.env && set +a
T="${CF_API_TOKEN:-}"
[ -z "$T" ] && { echo "CF_API_TOKEN yok"; exit 1; }

API=https://api.cloudflare.com/client/v4
Z_NET=${Z_NET:-988f968b901c2d8c06377a86c9070417}
Z_APP=${Z_APP:-f65d59bf6fd80d6e188aa6a6f7207421}
MR=${MR:-77454fe2d30c4220b5701f6fdfb893ba}

put() {
  local zone="$1" phase="$2" body="$3" label="$4"
  if [ "$DRY" = "1" ]; then echo "[dry] $label"; return 0; fi
  local out; out=$(curl -s -X PUT "$API/zones/$zone/rulesets/phases/$phase/entrypoint" \
    -H "Authorization: Bearer $T" -H 'Content-Type: application/json' --data "$body")
  echo "$out" | python3 -c 'import json,sys;d=json.load(sys.stdin);print("  ok" if d.get("success") else "  FAIL "+json.dumps(d.get("errors")))'
}

guard_paths='not cf.client.bot and (
  starts_with(lower(http.request.uri.path), "/.git")
  or starts_with(lower(http.request.uri.path), "/.env")
  or starts_with(lower(http.request.uri.path), "/.aws")
  or starts_with(lower(http.request.uri.path), "/.ssh")
  or starts_with(lower(http.request.uri.path), "/config")
  or starts_with(lower(http.request.uri.path), "/core/")
  or starts_with(lower(http.request.uri.path), "/includes/")
  or starts_with(lower(http.request.uri.path), "/vendor/")
  or starts_with(lower(http.request.uri.path), "/phpmyadmin")
  or starts_with(lower(http.request.uri.path), "/wp-admin")
  or starts_with(lower(http.request.uri.path), "/wp-content")
  or starts_with(lower(http.request.uri.path), "/wp-includes")
  or starts_with(lower(http.request.uri.path), "/xmlrpc")
  or starts_with(lower(http.request.uri.path), "/_profiler")
  or lower(http.request.uri.path) == "/wp-login.php"
  or lower(http.request.uri.path) == "/phpinfo.php"
  or lower(http.request.uri.path) == "/database.sql"
)'

bad_ua='not cf.client.bot and (
  lower(http.user_agent) contains "grafana k6"
  or lower(http.user_agent) contains "k6/"
  or lower(http.user_agent) contains "sqlmap"
  or lower(http.user_agent) contains "nikto"
  or lower(http.user_agent) contains "nuclei"
  or lower(http.user_agent) contains "masscan"
  or lower(http.user_agent) contains "nmap"
  or lower(http.user_agent) contains "apachebench"
  or lower(http.user_agent) contains "vegeta"
  or lower(http.user_agent) contains "locust"
  or lower(http.user_agent) contains "wrk"
  or lower(http.user_agent) contains "go-http-client"
)'

custom=$(python3 -c '
import json,sys
print(json.dumps({"rules":[
 {"description":"kalkan-1: uzanti/panel path blocklist","expression":sys.argv[1],"action":"block","enabled":True},
 {"description":"kalkan-2: yuk-testi & scanner UA block","expression":sys.argv[2],"action":"block","enabled":True}]}, ensure_ascii=False))' "$guard_paths" "$bad_ua")

rl_expr='not cf.client.bot
and not starts_with(http.request.uri.path, "/assets/")
and not starts_with(http.request.uri.path, "/uploads/")
and not starts_with(http.request.uri.path, "/cdn-cgi/")'
rl=$(python3 -c '
import json,sys
print(json.dumps({"rules":[{"description":"kalkan-rl: dinamik istek/IP 120 per 10s -> block 10s","expression":sys.argv[1],"action":"block","enabled":True,
 "ratelimit":{"characteristics":["ip.src","cf.colo.id"],"period":10,"requests_per_period":120,"mitigation_timeout":10}}]}))' "$rl_expr")

managed=$(python3 -c '
import json,sys
print(json.dumps({"rules":[{"description":"kalkan-managed: Cloudflare Managed Free Ruleset (WAF)","expression":"true","action":"execute","enabled":True,
 "action_parameters":{"id":sys.argv[1],"version":"latest"}}]}))' "$MR")

for zn in "vexaris.net:$Z_NET" "vexaris.app:$Z_APP"; do
  name=${zn%%:*}; zone=${zn##*:}
  echo "== $name"
  put "$zone" http_request_firewall_custom "$custom" "$name custom rules"
  put "$zone" http_ratelimit "$rl" "$name rate limit"
  put "$zone" http_request_firewall_managed "$managed" "$name managed ruleset"
done

echo "== zone ayarlari (vexaris.net)"
if [ "$DRY" = "0" ]; then
  for kv in 'ssl:strict' 'min_tls_version:1.2' 'always_use_https:on' 'early_hints:on'; do
    k=${kv%%:*}; v=${kv##*:}
    printf '  %-16s' "$k"
    curl -s -X PATCH "$API/zones/$Z_NET/settings/$k" -H "Authorization: Bearer $T" \
      -H 'Content-Type: application/json' --data "{\"value\":\"$v\"}" \
      | python3 -c 'import json,sys;d=json.load(sys.stdin);print("ok" if d.get("success") else "FAIL")'
  done
fi
echo "Bot Fight Mode'a DOKUNULMAZ (lisans sunucusu/replika riski)."
