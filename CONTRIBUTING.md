# Katkı akışı (mainsite / vexaris.net)

## Altın kural
`master`'a **doğrudan push yok**. Her değişiklik branch → commit → push → **Pull Request** ile gelir.

## Akış
1. Güncel master'dan branch aç:
   ```bash
   git fetch origin
   git switch -c fix/seo-baslik-uzunlugu origin/master
   ```
   Branch adları: `feat/…`, `fix/…`, `chore/…`, `docs/…`
2. Değişikliği yap, `php -l` ile doğrula, commit et:
   ```bash
   php -l degisen/dosya.php
   git add -A && git commit -m "fix(seo): title uzunluklari"
   ```
3. Branch'i gönder ve PR aç:
   ```bash
   git push -u origin HEAD
   gh pr create --base master --fill        # gh oturumu açıksa
   ```
   `gh` yoksa/oturum kapalıysa push çıktısındaki **compare** linkinden PR açılır:
   `https://github.com/VexarisWeb/Vexaris-Main/compare/master...BRANCH?expand=1`
4. CI (`PHP Lint`) yeşil olmadan merge edilmez.
5. Merge sonrası branch'i sil, yerelde `git switch master && git pull`.

## CI ne yapıyor
`.github/workflows/php-lint.yml` — PR'daki **değişen** PHP dosyalarında:
- `php -l` sözdizimi kontrolü
- UTF-8 **BOM** kontrolü (BOM yasak)

## GitHub'da bir kez yapılacak ayarlar
Settings → Branches → **Add branch protection rule** → `master`:
- ✅ Require a pull request before merging (1 onay)
- ✅ Require status checks to pass → `php -l + BOM kontrolu`
- ✅ Require conversation resolution
- ✅ Do not allow force pushes
- ✅ Do not allow deletions

## Commit mesajı
Conventional prefix: `feat:`, `fix:`, `chore:`, `docs:`, `refactor:`. Kısa ve ne yaptığını söyleyen mesaj.

## Sırlar
Şifre, token, `.env`, `install/config.php` asla commitlenmez. Şüphede: `git diff --cached | grep -iE "(pass|secret|key|token)"`.
