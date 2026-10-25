# Sunucu yapılandırma kopyaları

Bu klasor **canli sunucu ayarlarinin surum kontrolu kopyasidir** (calisan
dosyalar degistirilmez; buradaki kopyalar referans olur).

| Canli dosya | Kopya |
|---|---|
| `/etc/nginx/fastpanel2-available/vexaris_net_usr/vexaris.net.conf` | `nginx-vexaris.net.conf` |
| `/etc/nginx/fastpanel2-sites/vexaris_net_usr/vexaris.net.includes` | `nginx-vexaris.net.includes` |
| `/etc/php/8.4/fpm/pool.d/vexaris.net.conf` | `php-fpm-vexaris.net.conf` |
| `/etc/nginx/conf.d/vx-edge-guard.conf` | `nginx-vx-edge-guard.conf` |
| `/etc/nginx/conf.d/vx-ratelimit.conf` | `nginx-vx-ratelimit.conf` |
| `/etc/nginx/fastpanel2-available/demo_vexaris_usr20/demo.vexaris.net.conf` | `nginx-demo.vexaris.net.conf` |
| `/etc/nginx/fastpanel2-available/vexaris_app_usr/vexaris.app.conf` | `nginx-vexaris.app.conf` |

## Degisiklik kurali (ZORUNLU)

1. Once `php-fpm8.3 -t` / `nginx -t` ile **dogrulama** yap.
2. Canli dosyayi degistir.
3. **Dogrulama gecmezse reload YAPMA** (asla `;` ile zincirleme — 21.10.2026'da
   boyle bir hata tum FPM havuzlarini dusurdu).
4. Reload sonrasi saglik kontrolu yap; bozulduysa **geri al**.

Hazir arac: `/srv/vexaris/safe-apply.sh <etiket> <hedef> <yeni> <dogrulama> <reload> <saglik>`
- dogrulama basarisiz -> geri alinir, reload YAPILMAZ
- saglik basarisiz -> geri alinir + tekrar reload

Yedekler: `/srv/vexaris/backups/<etiket>-<tarih>.bak`

## Cloudflare kalkanı + origin kalkanı (25.10.2026)

Detay: `kalkan-README.md` (nginx tarafı) + `cloudflare/README.md` (CF kuralları).
Kurulum/tekrar uygulama: `bash deploy/cloudflare/kalkan.sh` (token `secrets/cloudflare.env`).
Bot Fight Mode **bilerek kapalı** — lisans sunucusu ve mağaza replikaları challenge'a takılıp düşüyor.
