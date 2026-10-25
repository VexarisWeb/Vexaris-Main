# Cloudflare kalkanı (vexaris.net / vexaris.app)

25.10.2026'da kurulan L7 koruma. `vexaris.com.tr` zone'undaki kuralların uyarlanmış hâli.

## Zone kimlikleri
| zone | id |
|---|---|
| vexaris.net | `988f968b901c2d8c06377a86c9070417` |
| vexaris.app | `f65d59bf6fd80d6e188aa6a6f7207421` |
| vexaris.com.tr (referans) | `8e83494c3b45a9e313a108cd0714b99e` |

Hesap: `25261714cfc7dba67832dc808b0eecca` (tek hesap, 4 zone, hepsi **Free**).

## Token
`/root/.bashrc` içindeki `AWS_API_TOKEN` **Cloudflare API token'ıdır** (adı yanıltıcı, AWS ile ilgisi yok).
`cfat_` önekli, **account-owned** ("vexaris"), düzenleme yetkili. Bu yüzden `/user/tokens/verify`
**401** döner — token bozuk değil; o uç yalnız user-owned token kabul eder. Doğrulama `GET /zones` ile yapılır.

Kurulum için token `CF_API_TOKEN` olarak verilir:
```bash
set -a; . /srv/vexaris/secrets/cloudflare.env; set +a   # CF_API_TOKEN=...
bash deploy/cloudflare/kalkan.sh --dry-run               # önce kuru prova
bash deploy/cloudflare/kalkan.sh
```

## Kurallar
**Custom rules (iki zone'da aynı)**
1. `kalkan-1` — block: `/.git /.env /.aws /.ssh /config /core/ /includes/ /vendor/ /phpmyadmin /wp-admin /wp-content /wp-includes /xmlrpc /_profiler`, `== /wp-login.php /phpinfo.php /database.sql`
2. `kalkan-2` — block UA: `grafana k6, k6/, sqlmap, nikto, nuclei, masscan, nmap, apachebench, vegeta, locust, wrk, go-http-client`

**Rate limit (Free plan: zone başına 1 kural, `mitigation_timeout` zorunlu 10 sn)**
- `/assets/`, `/uploads/`, `/cdn-cgi/` hariç tüm yollar — `ip.src + cf.colo.id`, **120 istek / 10 sn**, block 10 sn.

**Managed ruleset**
- `Cloudflare Managed Free Ruleset` (`77454fe2d30c4220b5701f6fdfb893ba`, 38 kural, OWASP türevi) `execute` ile bağlı.

**Zone ayarları (vexaris.net)**
`ssl=strict`, `min_tls_version=1.2`, `always_use_https=on`, `early_hints=on`,
HSTS `max-age=31536000; includeSubDomains; preload=off; nosniff=on`.

**DOKUNULMAZ:** Bot Fight Mode / `fight_mode` **kapalı kalır** (lisans sunucusu ve mağaza replikaları
challenge'a takılıp düşüyor).

## Bilinen Free plan sınırları
- `ip.src` **ifade içinde** kullanılamaz (Advanced Rate Limiting gerekir) → worker IP'si (`45.141.202.76`)
  rate-limit ifadesinden hariç tutulamaz; hacmi düşük (dk'da birkaç istek) olduğu için sorun değil.
- `mitigation_timeout` yalnız 10 sn → sürekli flood'da kural 10 sn'de bir yeniden uygulanır;
  asıl yük **origin nginx** limitleriyle kesilir (`deploy/nginx/`).
- Managed ruleset override edilemez (paranoia seviyesi yok).

## Origin kalkanı ile ilişki
CF tek başına yetmez: origin IP'si (`85.203.42.12`) doğrudan erişilebilir olduğu sürece k6/benzeri yük
CF'yi atlar. Bu yüzden nginx `$vx_peer_deny` (yalnız CF kenarı + yerel/özel ağ; ACME istisna) ve
ufw 443 (yalnız CF CIDR'leri) birlikte kurulur. Detay: `deploy/nginx/vx-edge-guard.conf`.
