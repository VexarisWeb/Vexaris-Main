<?php
$allProducts = $pdo ? $pdo->query("SELECT * FROM products ORDER BY created_at DESC")->fetchAll() : [];
?>
<!-- ÜRÜNLER -->
<div class="panel" style="margin-bottom:1.2rem">
    <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:center;justify-content:space-between">
        <div style="font-size:.88rem;color:var(--muted)">Toplam <strong style="color:var(--text)"><?= count($allProducts) ?></strong> ürün</div>
        <button class="btn btn-primary" onclick="openProductModal(null)"><i class="fas fa-plus"></i> Yeni Ürün Ekle</button>
    </div>
</div>

<div class="panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:50px">#</th>
                    <th>Ürün Adı</th>
                    <th>Fiyat</th>
                    <th>Dosya</th>
                    <th>Boyut</th>
                    <th>Durum</th>
                    <th>Oluşturulma</th>
                    <th style="width:220px;text-align:center">İşlemler</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($allProducts): foreach ($allProducts as $pr): ?>
                <tr id="prod-row-<?= (int)$pr['id'] ?>">
                    <td class="text-muted"><?= (int)$pr['id'] ?></td>
                    <td style="font-weight:600"><?= htmlspecialchars($pr['name']) ?></td>
                    <td style="font-weight:700;color:var(--cyan);font-size:.85rem;white-space:nowrap"><?= ((float)($pr['price'] ?? 0)) > 0 ? number_format((float)$pr['price'], 2) . ' ' . strtoupper($pr['currency'] !== '' ? $pr['currency'] : 'USD') : '<span class="text-muted">—</span>' ?></td>
                    <td class="text-muted" style="font-size:.82rem"><?= htmlspecialchars($pr['file_name']) ?></td>
                    <td class="text-muted" style="font-size:.82rem"><?= htmlspecialchars($pr['file_size']) ?></td>
                    <td><?php if ((int)$pr['is_active'] === 1): ?><span class="badge badge-active"><i class="fas fa-check"></i> Aktif</span><?php else: ?><span class="badge badge-rev"><i class="fas fa-pause"></i> Pasif</span><?php endif; ?></td>
                    <td class="text-muted" style="font-size:.8rem"><?= date('d.m.Y H:i', strtotime($pr['created_at'])) ?></td>
                    <td style="text-align:center;white-space:nowrap">
                        <button onclick='openProductModal(<?= htmlspecialchars(json_encode($pr), ENT_QUOTES, "UTF-8") ?>)' class="btn-tbl btn-tbl-info"><i class="fas fa-pen"></i> Düzenle</button>
                        <button onclick="deleteProduct(<?= (int)$pr['id'] ?>)" class="btn-tbl btn-tbl-del"><i class="fas fa-trash"></i> Sil</button>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="8" class="text-muted" style="text-align:center;padding:2.5rem">Henüz ürün yok. "Yeni Ürün Ekle" ile başlayın.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Ürün Ekle/Düzenle Modal -->
<div id="productModal" onclick="if(event.target===this)closeProductModal()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(6px);z-index:9000;align-items:center;justify-content:center">
    <div style="background:#0d1b3e;border:1px solid rgba(0,212,255,.25);border-radius:20px;padding:2rem;max-width:520px;width:92%;position:relative;max-height:88vh;overflow-y:auto">
        <button onclick="closeProductModal()" style="position:absolute;top:1rem;right:1rem;background:rgba(255,255,255,.07);border:1px solid var(--border);border-radius:8px;color:var(--text);width:30px;height:30px;cursor:pointer;font-size:.95rem;display:flex;align-items:center;justify-content:center"><i class="fas fa-xmark"></i></button>
        <h3 style="font-size:1rem;font-weight:800;margin-bottom:1.4rem;color:#fff"><i class="fas fa-box-open" style="color:var(--cyan);margin-right:8px"></i><span id="productModalTitle">Yeni Ürün</span></h3>
        <input type="hidden" id="productId">
        <div class="form-field">
            <label>Ürün Adı <span style="color:#ff7070">*</span></label>
            <input id="productName" placeholder="Örn: Vexaris Pro Script"
                style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.6rem .85rem;font-size:.9rem;outline:none;font-family:'Inter',sans-serif">
        </div>
        <div class="form-field">
            <label>Açıklama</label>
            <textarea id="productDesc" rows="3" placeholder="Kullanıcıya gösterilecek açıklama"
                style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.6rem .85rem;font-size:.9rem;outline:none;font-family:'Inter',sans-serif;resize:vertical"></textarea>
        </div>
        <div class="form-field">
            <label>Fiyat (Whop ile satış) — 0 = satılık değil</label>
            <input id="productPrice" type="number" min="0" step="0.01" placeholder="Örn: 29.99"
                style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.6rem .85rem;font-size:.9rem;outline:none;font-family:'Inter',sans-serif">
            <p style="font-size:.75rem;color:var(--muted);margin-top:.3rem">Para birimi: Ayarlar → Whop → Currency (varsayılan USD).</p>
        </div>
        <div class="form-field">
            <label>Dosya (max 256 MB)</label>
            <input type="file" id="productFile"
                style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.5rem;font-size:.85rem;font-family:'Inter',sans-serif">
            <p style="font-size:.75rem;color:var(--muted);margin-top:.3rem">Düzenleme sırasında boş bırakılırsa mevcut dosya korunur.</p>
        </div>
        <div style="display:flex;align-items:center;gap:8px;margin:1rem 0">
            <input type="checkbox" id="productActive" checked style="accent-color:var(--cyan)">
            <label for="productActive" style="font-size:.85rem;color:var(--text)">Aktif (kullanıcılar görebilir/indirebilir)</label>
        </div>
        <div class="alert-msg" id="productAlert" style="margin-bottom:1rem"></div>
        <button id="productSaveBtn" onclick="saveProduct()" style="width:100%;padding:12px;background:linear-gradient(135deg,#00d4ff,#0a7dff);border:none;border-radius:12px;color:#041a35;font-size:.95rem;font-weight:800;cursor:pointer;font-family:inherit">Kaydet</button>
    </div>
</div>

<script>
let productEditId = null;

function openProductModal(product) {
    productEditId = product ? parseInt(product.id) : null;
    document.getElementById('productModalTitle').textContent = product ? 'Ürünü Düzenle' : 'Yeni Ürün';
    document.getElementById('productId').value = product ? product.id : '';
    document.getElementById('productName').value = product ? product.name : '';
    document.getElementById('productDesc').value = product ? (product.description || '') : '';
    document.getElementById('productPrice').value = product && product.price ? product.price : '';
    document.getElementById('productFile').value = '';
    document.getElementById('productActive').checked = product ? parseInt(product.is_active) === 1 : true;
    const al = document.getElementById('productAlert');
    if (al) { al.className = 'alert-msg'; al.style.display = 'none'; }
    document.getElementById('productModal').style.display = 'flex';
}

function closeProductModal() {
    document.getElementById('productModal').style.display = 'none';
    productEditId = null;
}

async function saveProduct() {
    const al = document.getElementById('productAlert');
    al.className = 'alert-msg'; al.style.display = 'none';
    const fd = new FormData();
    fd.append('action', productEditId ? 'update' : 'create');
    fd.append('id', productEditId || 0);
    fd.append('name', document.getElementById('productName').value);
    fd.append('description', document.getElementById('productDesc').value);
    fd.append('price', document.getElementById('productPrice').value || '0');
    fd.append('is_active', document.getElementById('productActive').checked ? '1' : '0');
    const file = document.getElementById('productFile').files[0];
    if (file) fd.append('file', file);
    const btn = document.getElementById('productSaveBtn');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Kaydediliyor...';
    try {
        const res  = await fetch('/api/products', { method:'POST', headers:{'X-CSRF-Token': VX_CSRF}, body: fd });
        const data = await res.json();
        if (data.ok) {
            al.className = 'alert-msg alert-ok'; al.style.display = 'block';
            al.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.msg;
            closeProductModal();
            setTimeout(() => location.reload(), 600);
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

async function deleteProduct(id) {
    if (!confirm('Bu ürün silinecek. Kullanıcıya tanımlı kayıtlar da temizlenir. Devam edilsin mi?')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    try {
        const res  = await fetch('/api/products', { method:'POST', headers:{'X-CSRF-Token': VX_CSRF}, body: fd });
        const data = await res.json();
        if (data.ok) {
            const r = document.getElementById('prod-row-' + id);
            if (r) { r.style.transition = 'opacity .3s'; r.style.opacity = '0'; setTimeout(()=>r.remove(),300); }
        } else {
            alert(data.msg || 'Silinemedi.');
        }
    } catch(e) { alert('Bağlantı hatası.'); }
}
</script>
