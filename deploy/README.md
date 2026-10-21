# Sunucu yapılandırma kopyaları

Bu klasor **canli sunucu ayarlarinin surum kontrolu kopyasidir** (calisan
dosyalar degistirilmez; buradaki kopyalar referans olur).

| Canli dosya | Kopya |
|---|---|
| `/etc/nginx/fastpanel2-available/vexaris_net_usr/vexaris.net.conf` | `nginx-vexaris.net.conf` |
| `/etc/nginx/fastpanel2-sites/vexaris_net_usr/vexaris.net.includes` | `nginx-vexaris.net.includes` |
| `/etc/php/8.3/fpm/pool.d/vexaris.net.conf` | `php-fpm-vexaris.net.conf` |

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
