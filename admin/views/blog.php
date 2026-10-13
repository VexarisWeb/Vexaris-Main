<?php
require_once __DIR__ . '/../../core/blog.php';
require_once __DIR__ . '/../../core/editor.php';
$allPosts = $pdo ? blog_list($pdo, 200) : [];
?>
<!-- BLOG -->
<div class="panel" style="margin-bottom:1.2rem">
    <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:center;justify-content:space-between">
        <div style="font-size:.88rem;color:var(--muted)">Toplam <strong style="color:var(--text)"><?= count($allPosts) ?></strong> yazı</div>
        <button class="btn btn-primary" onclick="openBlogModal(null)"><i class="fas fa-plus"></i> Yeni Yazı</button>
    </div>
</div>

<div class="panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:50px">#</th>
                    <th style="width:70px">Kapak</th>
                    <th>Başlık</th>
                    <th>Durum</th>
                    <th>Yazar</th>
                    <th>Oluşturulma</th>
                    <th style="width:320px;text-align:center">İşlemler</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($allPosts): foreach ($allPosts as $bp): ?>
                <tr id="blog-row-<?= (int)$bp['id'] ?>">
                    <td class="text-muted"><?= (int)$bp['id'] ?></td>
                    <td>
                        <?php if ($bp['cover_image']): ?>
                        <img src="<?= htmlspecialchars($bp['cover_image']) ?>" alt="" style="width:52px;height:34px;object-fit:cover;border-radius:6px;border:1px solid var(--border)">
                        <?php else: ?>
                        <span class="text-muted" style="font-size:.72rem">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="font-weight:600"><?= htmlspecialchars($bp['title']) ?></div>
                        <div class="text-muted" style="font-size:.75rem">/<?= htmlspecialchars($bp['slug']) ?></div>
                    </td>
                    <td><?php if ($bp['status'] === 'published'): ?><span class="badge badge-active"><i class="fas fa-check"></i> Yayında</span><?php else: ?><span class="badge badge-rev"><i class="fas fa-pause"></i> Taslak</span><?php endif; ?></td>
                    <td class="text-muted" style="font-size:.82rem"><?= htmlspecialchars($bp['author_name'] ?? '—') ?></td>
                    <td class="text-muted" style="font-size:.8rem"><?= date('d.m.Y H:i', strtotime($bp['created_at'])) ?></td>
                    <td style="text-align:center;white-space:nowrap">
                        <button onclick='openBlogModal(<?= htmlspecialchars(json_encode($bp), ENT_QUOTES, "UTF-8") ?>)' class="btn-tbl btn-tbl-info"><i class="fas fa-pen"></i> Düzenle</button>
                        <button onclick="openBlogStats(<?= (int)$bp['id'] ?>)" class="btn-tbl btn-tbl-info"><i class="fas fa-chart-column"></i> Analitik</button>
                        <a href="/<?= htmlspecialchars($bp['slug']) ?>" target="_blank" class="btn-tbl btn-tbl-info" style="text-decoration:none"><i class="fas fa-eye"></i> Gör</a>
                        <button onclick="deleteBlog(<?= (int)$bp['id'] ?>)" class="btn-tbl btn-tbl-del"><i class="fas fa-trash"></i> Sil</button>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="7" class="text-muted" style="text-align:center;padding:2.5rem">Henüz yazı yok. "Yeni Yazı" ile başlayın.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Blog Ekle/Düzenle Modal -->
<div id="blogModal" onclick="if(event.target===this)closeBlogModal()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(6px);z-index:9000;align-items:center;justify-content:center">
    <div style="background:#0d1b3e;border:1px solid rgba(0,212,255,.25);border-radius:20px;padding:2rem;max-width:1100px;width:95%;position:relative;max-height:92vh;overflow-y:auto">
        <button onclick="closeBlogModal()" style="position:absolute;top:1rem;right:1rem;background:rgba(255,255,255,.07);border:1px solid var(--border);border-radius:8px;color:var(--text);width:30px;height:30px;cursor:pointer;font-size:.95rem;display:flex;align-items:center;justify-content:center"><i class="fas fa-xmark"></i></button>
        <h3 style="font-size:1rem;font-weight:800;margin-bottom:1rem;color:#fff"><i class="fas fa-pen-nib" style="color:var(--cyan);margin-right:8px"></i><span id="blogModalTitle">Yeni Yazı</span></h3>
        <div style="display:flex;gap:.5rem;margin-bottom:1.2rem;flex-wrap:wrap">
            <button class="blog-tab-btn blog-tab-active" id="blogTabContentBtn" onclick="switchBlogTab('content')"><i class="fas fa-file-lines"></i> İçerik</button>
            <button class="blog-tab-btn" id="blogTabSeoBtn" onclick="switchBlogTab('seo')"><i class="fas fa-magnifying-glass-chart"></i> SEO</button>
        </div>

        <div id="blogTabContent" style="display:block">
            <input type="hidden" id="blogId">
            <div class="two-col" style="gap:1rem">
                <div class="form-field">
                    <label>Başlık <span style="color:#ff7070">*</span></label>
                    <input id="blogTitle" placeholder="Yazı başlığı"
                        style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.6rem .85rem;font-size:.9rem;outline:none;font-family:'Inter',sans-serif">
                </div>
                <div class="form-field">
                    <label>Slug (URL) <span class="text-muted" style="text-transform:none;font-weight:400">— boş bırakılırsa başlıktan üretilir</span></label>
                    <input id="blogSlug" placeholder="ornek-yazi"
                        style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.6rem .85rem;font-size:.9rem;outline:none;font-family:'Inter',sans-serif">
                </div>
            </div>
            <div class="form-field">
                <label>Kapak Görseli</label>
                <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap">
                    <input type="file" id="blogCoverFile" accept="image/*"
                        style="width:100%;max-width:300px;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.5rem;font-size:.85rem;font-family:'Inter',sans-serif">
                    <button type="button" class="btn-tbl btn-tbl-info" onclick="uploadBlogCover()"><i class="fas fa-upload"></i> Yükle</button>
                    <img id="blogCoverPrev" src="" alt="" style="display:none;width:120px;height:70px;object-fit:cover;border-radius:8px;border:1px solid var(--border)">
                    <input type="hidden" id="blogCover">
                </div>
                <p class="text-muted" style="font-size:.75rem;margin-top:.3rem">JPG, PNG, WEBP veya GIF. En fazla 10 MB.</p>
            </div>
            <div class="form-field">
                <label>Özet <span class="text-muted" style="text-transform:none;font-weight:400">— boş bırakılırsa içerikten otomatik üretilir</span></label>
                <textarea id="blogExcerpt" rows="2" placeholder="Yazının kısa özeti"
                    style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.6rem .85rem;font-size:.9rem;outline:none;font-family:'Inter',sans-serif;resize:vertical"></textarea>
            </div>
            <div class="form-field">
                <label>İçerik</label>
                <?php render_editor('blog-content', '', '340px'); ?>
            </div>
            <div style="display:flex;align-items:center;gap:8px;margin:1rem 0">
                <input type="checkbox" id="blogPublished" checked style="accent-color:var(--cyan)">
                <label for="blogPublished" style="font-size:.85rem;color:var(--text)">Yayında (taslak ise sitede görünmez)</label>
            </div>
        </div>

        <div id="blogTabSeo" style="display:none">
            <div class="alert-msg" id="blogSeoHint" style="margin-bottom:1rem">Bu bölümdeki alanlar boş bırakılırsa yazı başlığı, özet ve kapak görselinden otomatik üretilir.</div>
            <div class="two-col" style="gap:1rem">
                <div class="form-field">
                    <label>Meta Başlık <span class="text-muted" style="text-transform:none;font-weight:400">— en fazla 60 karakter önerilir</span></label>
                    <input id="blogMetaTitle" placeholder="SEO başlığı"
                        style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.6rem .85rem;font-size:.9rem;outline:none;font-family:'Inter',sans-serif">
                    <div class="text-muted" style="font-size:.72rem;margin-top:.25rem"><span id="blogMetaTitleCount">0</span> karakter</div>
                </div>
                <div class="form-field">
                    <label>Meta Açıklama <span class="text-muted" style="text-transform:none;font-weight:400">— en fazla 160 karakter önerilir</span></label>
                    <textarea id="blogMetaDesc" rows="2" placeholder="SEO açıklaması"
                        style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.6rem .85rem;font-size:.9rem;outline:none;font-family:'Inter',sans-serif;resize:vertical"></textarea>
                    <div class="text-muted" style="font-size:.72rem;margin-top:.25rem"><span id="blogMetaDescCount">0</span> karakter</div>
                </div>
            </div>
            <div class="two-col" style="gap:1rem">
                <div class="form-field">
                    <label>Anahtar Kelimeler</label>
                    <input id="blogKeywords" placeholder="e-ticaret, php, magaza"
                        style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.6rem .85rem;font-size:.9rem;outline:none;font-family:'Inter',sans-serif">
                </div>
                <div class="form-field">
                    <label>Canonical URL <span class="text-muted" style="text-transform:none;font-weight:400">— boşsa otomatik</span></label>
                    <input id="blogCanonical" placeholder="/ornek-yazi"
                        style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.6rem .85rem;font-size:.9rem;outline:none;font-family:'Inter',sans-serif">
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:8px;margin:.6rem 0 1.2rem">
                <input type="checkbox" id="blogNoindex" style="accent-color:var(--cyan)">
                <label for="blogNoindex" style="font-size:.85rem;color:var(--text)">Arama motorlarında dizinleme yasak (noindex)</label>
            </div>
            <div class="form-field" style="border-top:1px solid var(--border);padding-top:1rem;margin-bottom:.5rem">
                <label style="color:var(--cyan)"><i class="fab fa-facebook" style="margin-right:6px"></i>Open Graph (Sosyal Medya Paylaşımı)</label>
            </div>
            <div class="two-col" style="gap:1rem">
                <div class="form-field">
                    <label>OG Başlık</label>
                    <input id="blogOgTitle" placeholder="Sosyal medyada görünecek başlık"
                        style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.6rem .85rem;font-size:.9rem;outline:none;font-family:'Inter',sans-serif">
                </div>
                <div class="form-field">
                    <label>OG Açıklama</label>
                    <textarea id="blogOgDesc" rows="2" placeholder="Sosyal medyada görünecek açıklama"
                        style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.6rem .85rem;font-size:.9rem;outline:none;font-family:'Inter',sans-serif;resize:vertical"></textarea>
                </div>
            </div>
            <div class="form-field">
                <label>OG Görseli <span class="text-muted" style="text-transform:none;font-weight:400">— boşsa kapak görseli kullanılır</span></label>
                <input id="blogOgImage" placeholder="/uploads/blog/ornek-gorsel.jpg"
                    style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.6rem .85rem;font-size:.9rem;outline:none;font-family:'Inter',sans-serif">
            </div>
        </div>

        <div class="alert-msg" id="blogAlert" style="margin-bottom:1rem"></div>
        <button id="blogSaveBtn" onclick="saveBlog()" style="width:100%;padding:12px;background:linear-gradient(135deg,#00d4ff,#0a7dff);border:none;border-radius:12px;color:#041a35;font-size:.95rem;font-weight:800;cursor:pointer;font-family:inherit">Kaydet</button>
    </div>
</div>

<!-- Blog Analitik Modal (tam ekran) -->
<div id="blogStatsModal" onclick="if(event.target===this)closeBlogStats()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(6px);z-index:9500;align-items:center;justify-content:center">
    <div style="background:#0d1b3e;border:1px solid rgba(0,212,255,.25);border-radius:20px;padding:2rem;max-width:1050px;width:95%;position:relative;max-height:92vh;overflow-y:auto">
        <button onclick="closeBlogStats()" style="position:absolute;top:1rem;right:1rem;background:rgba(255,255,255,.07);border:1px solid var(--border);border-radius:8px;color:var(--text);width:30px;height:30px;cursor:pointer;font-size:.95rem;display:flex;align-items:center;justify-content:center"><i class="fas fa-xmark"></i></button>
        <h3 style="font-size:1rem;font-weight:800;margin-bottom:1.4rem;color:#fff"><i class="fas fa-chart-column" style="color:var(--cyan);margin-right:8px"></i>Yazı Analitiği — <span id="blogStatsTitle">…</span></h3>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:1.5rem">
            <div style="background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:14px;padding:1.2rem;text-align:center">
                <div style="font-size:1.7rem;font-weight:900;color:var(--cyan)" id="blogStatTotal">0</div>
                <div class="text-muted" style="font-size:.78rem;margin-top:.25rem">Toplam Görüntülenme</div>
            </div>
            <div style="background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:14px;padding:1.2rem;text-align:center">
                <div style="font-size:1.7rem;font-weight:900;color:#a78bfa" id="blogStatUnique">0</div>
                <div class="text-muted" style="font-size:.78rem;margin-top:.25rem">Tekil IP</div>
            </div>
            <div style="background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:14px;padding:1.2rem;text-align:center">
                <div style="font-size:1.7rem;font-weight:900;color:#34d399" id="blogStatAvg">0 sn</div>
                <div class="text-muted" style="font-size:.78rem;margin-top:.25rem">Ortalama Okuma Süresi</div>
            </div>
        </div>

        <div class="form-field" style="margin-bottom:1rem">
            <label>Son 14 Gün Görüntülenme</label>
            <div id="blogStatChart" style="display:flex;align-items:flex-end;gap:6px;height:140px;padding:1rem;background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:12px"></div>
        </div>

        <div class="form-field">
            <label>Son Ziyaretçiler</label>
            <div class="table-wrap" style="margin-top:.5rem">
                <table id="blogStatRecent">
                    <thead><tr><th>IP</th><th>Kullanıcı</th><th>Giriş</th><th>Ayrılış</th><th>Süre</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
    .blog-tab-btn { background:rgba(255,255,255,.05);border:1px solid var(--border);color:var(--muted);border-radius:10px;padding:.55rem 1.1rem;font-size:.85rem;font-weight:600;cursor:pointer;font-family:inherit;transition:all .2s }
    .blog-tab-btn:hover { border-color:rgba(0,212,255,.4);color:var(--text) }
    .blog-tab-active { background:linear-gradient(135deg,#00d4ff,#0a7dff);color:#041a35;border-color:transparent;font-weight:800 }
    #blogStatChart .bs-bar { display:flex;flex-direction:column;align-items:center;gap:4px;flex:1;min-width:0 }
    #blogStatChart .bs-bar-fill { width:100%;max-width:26px;background:linear-gradient(180deg,#00d4ff,#0a7dff);border-radius:5px 5px 0 0;min-height:2px;transition:height .4s }
    #blogStatChart .bs-bar-date { font-size:.62rem;color:var(--muted);white-space:nowrap }
    #blogStatChart .bs-bar-count { font-size:.68rem;color:var(--cyan);font-weight:700 }
</style>

<script>
let blogEditId = null;

function switchBlogTab(tab) {
    document.getElementById('blogTabContent').style.display = tab === 'content' ? 'block' : 'none';
    document.getElementById('blogTabSeo').style.display = tab === 'seo' ? 'block' : 'none';
    document.getElementById('blogTabContentBtn').classList.toggle('blog-tab-active', tab === 'content');
    document.getElementById('blogTabSeoBtn').classList.toggle('blog-tab-active', tab === 'seo');
}

function openBlogModal(post) {
    blogEditId = post ? parseInt(post.id) : null;
    switchBlogTab('content');
    document.getElementById('blogModalTitle').textContent = post ? 'Yazıyı Düzenle' : 'Yeni Yazı';
    document.getElementById('blogId').value = post ? post.id : '';
    document.getElementById('blogTitle').value = post ? post.title : '';
    document.getElementById('blogSlug').value = post ? post.slug : '';
    document.getElementById('blogExcerpt').value = post ? (post.excerpt || '') : '';
    document.getElementById('blogPublished').checked = !post || post.status === 'published';
    document.getElementById('blogCover').value = post ? (post.cover_image || '') : '';
    const prev = document.getElementById('blogCoverPrev');
    if (post && post.cover_image) { prev.src = post.cover_image; prev.style.display = 'block'; }
    else { prev.src = ''; prev.style.display = 'none'; }
    document.getElementById('blogCoverFile').value = '';
    const body = document.getElementById('edBody-blog-content');
    if (body) body.innerHTML = post ? (post.content || '') : '';
    document.getElementById('blogMetaTitle').value = post ? (post.meta_title || '') : '';
    document.getElementById('blogMetaDesc').value = post ? (post.meta_description || '') : '';
    document.getElementById('blogKeywords').value = post ? (post.keywords || '') : '';
    document.getElementById('blogCanonical').value = post ? (post.canonical || '') : '';
    document.getElementById('blogNoindex').checked = !!(post && post.noindex == 1);
    document.getElementById('blogOgTitle').value = post ? (post.og_title || '') : '';
    document.getElementById('blogOgDesc').value = post ? (post.og_description || '') : '';
    document.getElementById('blogOgImage').value = post ? (post.og_image || '') : '';
    updateBlogSeoCounts();
    const al = document.getElementById('blogAlert');
    if (al) { al.className = 'alert-msg'; al.style.display = 'none'; }
    document.getElementById('blogModal').style.display = 'flex';
    if (!post && blogDraftRestore() && al) {
        al.className = 'alert-msg alert-ok'; al.style.display = 'block';
        al.innerHTML = '<i class="fas fa-check-circle"></i> Kaydedilmemiş taslağınız geri yüklendi. <a href="#" onclick="blogDraftClear();closeBlogModal();return false;" style="color:inherit;text-decoration:underline">Vazgeç</a>';
    }
}

(function(){
    let draftT = null;
    document.addEventListener('input', function(e){
        if (e.target && (e.target.id === 'edBody-blog-content' || e.target.id === 'blogTitle' || e.target.id === 'blogExcerpt')) {
            clearTimeout(draftT);
            draftT = setTimeout(blogDraftSave, 2500);
        }
    });
})();

function closeBlogModal() {
    document.getElementById('blogModal').style.display = 'none';
    blogEditId = null;
}

function updateBlogSeoCounts() {
    const t = document.getElementById('blogMetaTitle');
    const d = document.getElementById('blogMetaDesc');
    document.getElementById('blogMetaTitleCount').textContent = t.value.length;
    document.getElementById('blogMetaDescCount').textContent = d.value.length;
}
document.getElementById('blogMetaTitle').addEventListener('input', updateBlogSeoCounts);
document.getElementById('blogMetaDesc').addEventListener('input', updateBlogSeoCounts);

async function uploadBlogCover() {
    const fi = document.getElementById('blogCoverFile');
    if (!fi.files || !fi.files[0]) { alert('Önce bir görsel seçin.'); return; }
    const fd = new FormData();
    fd.append('action', 'blog_cover_upload');
    fd.append('cover', fi.files[0]);
    try {
        const res  = await fetch('/api/blog', { method:'POST', headers:{'X-CSRF-Token': VX_CSRF}, body: fd });
        const data = await res.json();
        if (data.ok) {
            document.getElementById('blogCover').value = data.url;
            const prev = document.getElementById('blogCoverPrev');
            prev.src = data.url; prev.style.display = 'block';
        } else {
            alert(data.msg || 'Yüklenemedi.');
        }
    } catch(e) { alert('Bağlantı hatası.'); }
}

function blogDraftSave() {
    try {
        const body = document.getElementById('edBody-blog-content');
        localStorage.setItem('vx_blog_draft', JSON.stringify({
            title: document.getElementById('blogTitle').value,
            slug: document.getElementById('blogSlug').value,
            excerpt: document.getElementById('blogExcerpt').value,
            content: body ? body.innerHTML : '',
            cover: document.getElementById('blogCover').value,
            status: document.getElementById('blogPublished').checked ? 'published' : 'draft',
            ts: Date.now()
        }));
    } catch (e) {}
}
function blogDraftRestore() {
    let d = null;
    try { d = JSON.parse(localStorage.getItem('vx_blog_draft') || 'null'); } catch (e) {}
    if (!d || !d.content) return false;
    document.getElementById('blogTitle').value = d.title || '';
    document.getElementById('blogSlug').value = d.slug || '';
    document.getElementById('blogExcerpt').value = d.excerpt || '';
    document.getElementById('blogCover').value = d.cover || '';
    document.getElementById('blogPublished').checked = d.status !== 'draft';
    const prev = document.getElementById('blogCoverPrev');
    if (d.cover) { prev.src = d.cover; prev.style.display = 'block'; }
    const body = document.getElementById('edBody-blog-content');
    if (body) body.innerHTML = d.content;
    return true;
}
function blogDraftClear() {
    try { localStorage.removeItem('vx_blog_draft'); } catch (e) {}
}

async function saveBlog() {
    const al = document.getElementById('blogAlert');
    al.className = 'alert-msg'; al.style.display = 'none';
    const body = document.getElementById('edBody-blog-content');
    const htmlContent = body ? body.innerHTML : '';
    if (htmlContent.length > 2500000) {
        al.className = 'alert-msg alert-err'; al.style.display = 'block';
        al.innerHTML = '<i class="fas fa-circle-exclamation"></i> İçerik çok büyük (~' + Math.round(htmlContent.length / 1048576) + ' MB). Resimleri dosyadan gömerek eklediyseniz silip URL ile ekleyin, ardından tekrar deneyin.';
        return;
    }
    const fd = new FormData();
    fd.append('action', 'blog_save');
    fd.append('id', blogEditId || 0);
    fd.append('title', document.getElementById('blogTitle').value);
    fd.append('slug', document.getElementById('blogSlug').value);
    fd.append('excerpt', document.getElementById('blogExcerpt').value);
    fd.append('content', htmlContent);
    fd.append('cover_image', document.getElementById('blogCover').value);
    fd.append('status', document.getElementById('blogPublished').checked ? 'published' : 'draft');
    fd.append('meta_title', document.getElementById('blogMetaTitle').value);
    fd.append('meta_description', document.getElementById('blogMetaDesc').value);
    fd.append('keywords', document.getElementById('blogKeywords').value);
    fd.append('canonical', document.getElementById('blogCanonical').value);
    fd.append('noindex', document.getElementById('blogNoindex').checked ? '1' : '0');
    fd.append('og_title', document.getElementById('blogOgTitle').value);
    fd.append('og_description', document.getElementById('blogOgDesc').value);
    fd.append('og_image', document.getElementById('blogOgImage').value);
    const btn = document.getElementById('blogSaveBtn');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Kaydediliyor...';
    try {
        const res  = await fetch('/api/blog', { method:'POST', headers:{'X-CSRF-Token': VX_CSRF}, body: fd });
        const data = await res.json().catch(() => ({}));
        if (data.ok) {
            blogDraftClear();
            al.className = 'alert-msg alert-ok'; al.style.display = 'block';
            al.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.msg;
            closeBlogModal();
            setTimeout(() => location.reload(), 600);
        } else if (res.status === 403) {
            blogDraftSave();
            al.className = 'alert-msg alert-err'; al.style.display = 'block';
            al.innerHTML = '<i class="fas fa-circle-exclamation"></i> Oturumunuz sona ermiş (Geçersiz istek). Yazdıklarınız taslak olarak saklandı; giriş yenileniyor...';
            setTimeout(() => location.reload(), 1800);
        } else {
            al.className = 'alert-msg alert-err'; al.style.display = 'block';
            al.innerHTML = '<i class="fas fa-circle-exclamation"></i> ' + (data.msg || 'Hata oluştu.');
        }
    } catch(e) {
        al.className = 'alert-msg alert-err'; al.style.display = 'block';
        al.innerHTML = '<i class="fas fa-circle-exclamation"></i> Bağlantı hatası.';
    } finally {
        btn.disabled = false;
        btn.innerHTML = orig;
    }
}

async function deleteBlog(id) {
    if (!confirm('Bu yazı silinecek. Devam edilsin mi?')) return;
    const fd = new FormData();
    fd.append('action', 'blog_delete');
    fd.append('id', id);
    try {
        const res  = await fetch('/api/blog', { method:'POST', headers:{'X-CSRF-Token': VX_CSRF}, body: fd });
        const data = await res.json();
        if (data.ok) {
            const r = document.getElementById('blog-row-' + id);
            if (r) { r.style.transition = 'opacity .3s'; r.style.opacity = '0'; setTimeout(()=>r.remove(),300); }
        } else {
            alert(data.msg || 'Silinemedi.');
        }
    } catch(e) { alert('Bağlantı hatası.'); }
}

async function openBlogStats(id) {
    const modal = document.getElementById('blogStatsModal');
    modal.style.display = 'flex';
    document.getElementById('blogStatsTitle').textContent = '…';
    document.getElementById('blogStatTotal').textContent = '0';
    document.getElementById('blogStatUnique').textContent = '0';
    document.getElementById('blogStatAvg').textContent = '0 sn';
    document.getElementById('blogStatChart').innerHTML = '';
    const tb = document.querySelector('#blogStatRecent tbody');
    tb.innerHTML = '<tr><td colspan="5" class="text-muted" style="text-align:center;padding:1.2rem"><i class="fas fa-spinner fa-spin"></i> Yükleniyor...</td></tr>';
    const fd = new FormData();
    fd.append('action', 'blog_stats');
    fd.append('id', id);
    try {
        const res  = await fetch('/api/blog', { method:'POST', headers:{'X-CSRF-Token': VX_CSRF}, body: fd });
        const data = await res.json();
        if (!data.ok) {
            tb.innerHTML = '<tr><td colspan="5" class="text-muted" style="text-align:center;padding:1.2rem">' + (data.msg || 'Yüklenemedi.') + '</td></tr>';
            return;
        }
        const st = data.stats;
        document.getElementById('blogStatsTitle').textContent = data.post.title;
        document.getElementById('blogStatTotal').textContent = st.total;
        document.getElementById('blogStatUnique').textContent = st.unique_ip;
        const avg = st.avg_duration;
        document.getElementById('blogStatAvg').textContent = avg > 0 ? Math.floor(avg / 60) + ' dk ' + (avg % 60) + ' sn' : '0 sn';
        const chart = document.getElementById('blogStatChart');
        const max = Math.max(1, ...st.chart.map(c => c.count));
        chart.innerHTML = st.chart.map(c =>
            '<div class="bs-bar" title="' + c.date + ' — ' + c.count + '"><span class="bs-bar-count">' + c.count + '</span><div class="bs-bar-fill" style="height:' + Math.max(3, Math.round(c.count / max * 90)) + 'px"></div><span class="bs-bar-date">' + c.date + '</span></div>'
        ).join('');
        tb.innerHTML = st.recent.length ? st.recent.map(r =>
            '<tr><td class="text-muted" style="font-size:.8rem">' + (r.ip || '—') + '</td><td class="text-muted" style="font-size:.8rem">' + (r.username || '—') + '</td><td class="text-muted" style="font-size:.8rem">' + (r.entered_at ? r.entered_at.replace('T',' ').substring(0,16) : '—') + '</td><td class="text-muted" style="font-size:.8rem">' + (r.left_at ? r.left_at.replace('T',' ').substring(0,16) : '—') + '</td><td class="text-muted" style="font-size:.8rem">' + (r.duration_sec > 0 ? Math.floor(r.duration_sec/60) + ' dk ' + (r.duration_sec%60) + ' sn' : '—') + '</td></tr>'
        ).join('') : '<tr><td colspan="5" class="text-muted" style="text-align:center;padding:1.2rem">Henüz ziyaret kaydı yok.</td></tr>';
    } catch(e) {
        tb.innerHTML = '<tr><td colspan="5" class="text-muted" style="text-align:center;padding:1.2rem">Bağlantı hatası.</td></tr>';
    }
}

function closeBlogStats() {
    document.getElementById('blogStatsModal').style.display = 'none';
}
</script>