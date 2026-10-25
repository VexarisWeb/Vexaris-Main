# nginx kopyaları (canlı sunucu)

Bu dosyalar `/etc/nginx` altındaki **canlı** yapılandırmanın birebir kopyasıdır.
Web'den erişilemez (`location ^~ /deploy/ { deny all; }`).

| dosya | canlı yol |
|---|---|
| `vexaris.net.conf` | `/etc/nginx/fastpanel2-available/vexaris_net_usr/vexaris.net.conf` |
| `demo.vexaris.net.conf` | `/etc/nginx/fastpanel2-available/demo_vexaris_usr20/demo.vexaris.net.conf` |
| `vexaris.app.conf` | `/etc/nginx/fastpanel2-available/vexaris_app_usr/vexaris.app.conf` |
| `vx-edge-guard.conf` | `/etc/nginx/conf.d/vx-edge-guard.conf` |
| `vx-ratelimit.conf` | `/etc/nginx/conf.d/vx-ratelimit.conf` |

## Origin kalkanı (25.10.2026)
`$vx_peer_deny` = 1 ise sunucu **444** döner. Karar `$realip_remote_addr` (real_ip modülünden
**önceki** gerçek TCP eşi) üzerinden verilir; yani `CF-Connecting-IP` başlığı taklit edilerek atlatılamaz.

```nginx
geo $realip_remote_addr $vx_peer_ok { ... CF 15 v4 + 7 v6 + 127/8 + 10/8 + 172.16/12 + 192.168/16
                                      + ::1 + fe80::/10 ... }
map "$vx_peer_ok:$request_uri" $vx_peer_deny {
    default 1;
    "~^1:" 0;
    "~^0:/\.well-known/acme-challenge/" 0;   # Let's Encrypt HTTP-01
}
```
Server bloklarında: `if ($vx_peer_deny) { return 444; }`

Ek origin limitleri (`vexaris.net`):
- `limit_conn vxconn 20` (bağlantı/IP), `client_header_timeout 10s`, `client_body_timeout 20s`,
  `keepalive_requests 200`.
- `/api/` + `/callback/` PHP'leri için ayrı `limit_req zone=vxapi burst=30 nodelay` (60 r/m).
- ufw: 443 yalnız CF CIDR'lerine açık; **80 açık kalır** (ACME HTTP-01 + nginx zaten 444'lüyor).

## Geri alma
```bash
/srv/vexaris/host/vx-edge-rollback.sh --force   # nginx conf yedeği + edge-guard kaldır
/srv/vexaris/host/vx-ufw-rollback.sh --force    # ufw allow 443/tcp (herkese aç)
```
Kurulum sırasında 10 dk / 7 dk'lık otomatik geri alma zamanlayıcıları çalıştı; onay sentinelleri
`/srv/vexaris/.edge-guard-confirmed` ve `/srv/vexaris/.ufw-guard-confirmed`.

## Doğrulama
```bash
curl -o /dev/null -w '%{http_code}\n' --resolve vexaris.net:443:188.114.96.0 https://vexaris.net/  # 200
curl -m 8 -o /dev/null -w '%{http_code}\n' --resolve vexaris.net:443:85.203.42.12 https://vexaris.net/ # 000/444
curl -o /dev/null -w '%{http_code}\n' -A 'grafana k6' --resolve vexaris.net:443:188.114.96.0 https://vexaris.net/ # 403
```
Dikkat: bu makinede `/etc/hosts` `vexaris.net -> 10.50.1.14` yazar; test ederken `--resolve` şart,
yoksa istek CF'yi atlar ve sonuç yanıltır.
