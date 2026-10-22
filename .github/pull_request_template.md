# Pull Request

## Ne değişti
<!-- Kısa özet: hangi dosya/akış etkilendi -->

## Neden
<!-- Bağlantı: issue, hata kaydı veya kullanıcı isteği -->

## Test
<!-- Yapılan doğrulama; uygulanmayanları da yaz -->
- [ ] `php -l` (değişen tüm PHP dosyaları)
- [ ] İlgili sayfa/endpoint canlı veya yerel doğrulandı
- [ ] Varsa testler çalıştırıldı (`/srv/vexaris/tests/*`)
- [ ] Regresyon riski düşük / geri alma yolu belli

## Risk ve geri alma
<!-- Veritabanı, migration, config, cron etkisi varsa yaz -->

## Kontrol listesi
- [ ] `master` yerine kendi branch'imde çalıştım
- [ ] Kullanıcı verisi `e()` / `htmlspecialchars` ile basılıyor
- [ ] Sorgular prepared statement
- [ ] Sır (şifre, anahtar, token) commitlenmedi
- [ ] Commit mesajı conventional prefix'li (`feat:`, `fix:`, `chore:`)
