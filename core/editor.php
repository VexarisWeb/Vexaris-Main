<?php
$GLOBALS['_editor_loaded'] ??= false;

function render_editor(string $id, string $content = '', string $height = '300px', string $name = ''): void {
    $cssId = preg_replace('/[^a-z0-9_-]/i', '', $id);
    if ($name === '') $name = $cssId;

    if (!empty($GLOBALS['_editor_loaded'])) {
        render_editor_instance($cssId, $content, $height, $name);
        return;
    }
    $GLOBALS['_editor_loaded'] = true;
?>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
<style>
.ed-wrap{background:#1e1e1e;border:1px solid #333;border-radius:6px;display:flex;flex-direction:column;position:relative;width:100%;}
.ed-tb{background:#232323;padding:6px 8px;display:flex;flex-shrink:0;align-items:center;border-bottom:1px solid #333;gap:2px;overflow-x:auto;min-height:40px;border-radius:6px 6px 0 0;white-space:nowrap;position:sticky;top:0;z-index:50;}
.ed-tb-grp{display:flex;align-items:center;margin-right:8px;flex-shrink:0;}
.ed-tb-grp:last-child{margin-left:auto;margin-right:0;}
.ed-btn{background:transparent;border:none;color:#d1d1d1;width:30px;height:30px;border-radius:4px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:13px;transition:background .15s;flex-shrink:0;}
.ed-btn:hover{background:#3a3a3a;}
.ed-btn.active{background:#444;}
.ed-btn .material-symbols-outlined,.ed-btn i{pointer-events:none;font-size:18px;}
.ed-btn-txt{font-family:serif;font-weight:700;font-size:15px;}
.ed-btn-i{font-family:serif;font-weight:700;font-size:15px;font-style:italic;}
.ed-btn-sep{width:1px;height:18px;background:#444;margin:0 6px;flex-shrink:0;}
.ed-drp{display:none;position:fixed;background:#2a2a2a;border:1px solid #333;border-radius:4px;padding:6px;box-shadow:0 5px 15px rgba(0,0,0,.5);z-index:100;gap:4px;flex-direction:row;width:max-content;}
.ed-drp.show{display:flex;}
.ed-drp-clr{width:210px;flex-direction:column;}
.ed-clr-grd{display:grid;grid-template-columns:repeat(7,1fr);gap:0;margin-bottom:8px;border-radius:2px;}
.ed-clr-sw{width:100%;aspect-ratio:1;cursor:pointer;border:none;transition:transform .1s;}
.ed-clr-sw:hover{transform:scale(1.15);z-index:10;box-shadow:0 0 5px rgba(0,0,0,.6);border-radius:2px;}
.ed-hex-lbl{font-size:11px;color:#888;margin-bottom:3px;}
.ed-hex-rw{display:flex;align-items:center;}
.ed-hex-rw input{flex:1;background:#1a1a1a;border:1px solid #333;color:#fff;padding:5px 8px;border-radius:3px;font-size:12px;outline:none;font-family:monospace;letter-spacing:1px;}
.ed-hex-rw input:focus{border-color:#3498db;}
.ed-hex-ok{background:#3498db;color:#fff;border:none;border-radius:3px;padding:5px 10px;margin-left:5px;cursor:pointer;font-size:12px;font-weight:700;}
.ed-hex-ok:hover{background:#2980b9;}
.ed-body-wrap{position:relative;overflow:hidden;flex:1;min-height:0;width:100%;}
.ed-body{padding:12px 16px;outline:none;font-size:14px;line-height:1.6;overflow-y:auto;user-select:text;-webkit-user-select:text;min-height:150px;max-height:62vh;box-sizing:border-box;width:100%;overscroll-behavior:contain;}
.ed-rsz{position:absolute;bottom:0;left:0;right:0;height:6px;cursor:ns-resize;background:transparent;z-index:5;}
.ed-rsz::after{content:'';position:absolute;bottom:2px;left:50%;transform:translateX(-50%);width:30px;height:3px;border-radius:2px;background:var(--dim,#555);opacity:.5;transition:opacity .2s;}
.ed-rsz:hover::after{opacity:1;}
.ed-body:empty:before{content:"Bir şeyler yazın...";color:#666;pointer-events:none;display:block;}
.ed-preview{background:#1a1a1a;}
.ed-body blockquote{border-left:4px solid #3498db;background:#252525;margin:10px 0;padding:10px 15px;color:#aaa;font-style:italic;border-radius:0 4px 4px 0;}
.ed-body a{color:#3498db;text-decoration:underline;}
.ed-body img{max-width:100%;border-radius:4px;margin:8px 0;}
.ed-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.6);z-index:99999;justify-content:center;align-items:center;}
.ed-modal.show{display:flex;}
.ed-modal-box{background:#1e1e1e;border:1px solid #333;border-radius:6px;padding:18px;width:90%;max-width:380px;box-shadow:0 10px 30px rgba(0,0,0,.8);}
.ed-modal-box h3{margin:0 0 12px;font-size:15px;border-bottom:1px solid #333;padding-bottom:8px;color:#d1d1d1;}
.ed-mg{margin-bottom:12px;}
.ed-mg label{display:block;margin-bottom:5px;font-size:12px;color:#aaa;}
.ed-mg input[type="text"],.ed-mg input[type="file"]{width:100%;box-sizing:border-box;background:#121212;border:1px solid #333;color:#fff;padding:8px 10px;border-radius:4px;outline:none;font-size:13px;}
.ed-mg input:focus{border-color:#3498db;}
.ed-tabs{display:flex;gap:12px;margin-bottom:12px;border-bottom:1px solid #333;}
.ed-tab{padding:4px 0;cursor:pointer;font-size:13px;border-bottom:2px solid transparent;color:#888;}
.ed-tab.active{color:#3498db;border-bottom:2px solid #3498db;}
.ed-tabc{display:none;}
.ed-tabc.active{display:block;}
.ed-modal-ft{display:flex;justify-content:flex-end;gap:8px;margin-top:20px;}
.ed-modal-btn{background:#232323;border:1px solid #333;color:#fff;padding:7px 14px;border-radius:4px;cursor:pointer;font-size:13px;}
.ed-modal-btn:hover{background:#3a3a3a;}
.ed-modal-btn.pri{background:#3498db;border-color:#3498db;font-weight:700;}
.ed-modal-btn.pri:hover{background:#2980b9;}
</style>
<?php
    render_editor_instance($cssId, $content, $height, $name);
}

function render_editor_instance(string $id, string $content, string $height, string $name): void {
    $rId = $id;
?>
<div class="ed-wrap" id="edWrap-<?= $rId ?>">
    <div class="ed-tb" id="edTb-<?= $rId ?>">
        <div class="ed-tb-grp">
            <button type="button" class="ed-btn ed-cmd" data-cmd="bold" title="Kalın"><span class="ed-btn-txt">B</span></button>
            <button type="button" class="ed-btn ed-cmd" data-cmd="italic" title="Eğik"><span class="ed-btn-i">I</span></button>
            <div style="position:relative;">
            <button type="button" class="ed-btn ed-drp-tg" data-target="ed-fs-<?= $rId ?>" title="Yazı Boyutu"><span>T<small>T</small></span></button>
            <div class="ed-drp" id="ed-fs-<?= $rId ?>" style="flex-direction:column;">
                <button type="button" class="ed-btn ed-fs-btn" data-size="13px" style="width:100%;justify-content:flex-start;font-size:11px;">Küçük (13px)</button>
                <button type="button" class="ed-btn ed-fs-btn" data-size="18px" style="width:100%;justify-content:flex-start;font-size:13px;">Orta (18px)</button>
                <button type="button" class="ed-btn ed-fs-btn" data-size="28px" style="width:100%;justify-content:flex-start;font-size:15px;">Büyük (28px)</button>
            </div>
            </div>
            <div style="position:relative;">
                <button type="button" class="ed-btn ed-drp-tg" data-target="ed-clr-<?= $rId ?>" title="Renk"><span class="material-symbols-outlined">palette</span></button>
                <div class="ed-drp ed-drp-clr" id="ed-clr-<?= $rId ?>">
                    <div class="ed-clr-grd">
                        <?php $clrs = ['#61BD6D','#1ABC9C','#54ACD2','#2C82C9','#9365B8','#475577','#CCCCCC','#41A85F','#00A885','#3D8EB9','#2969B0','#553982','#28324E','#000000','#F7DA64','#FBA026','#EB6B56','#E25041','#A38F84','#EFEFEF','#ffffff','#FAC51C','#F37934','#D14841','#B8312F','#7C706B']; foreach ($clrs as $c): ?>
                        <div class="ed-clr-sw" style="background:<?= $c ?>;" data-color="<?= $c ?>"></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="ed-hex-lbl">HEX Color</div>
                    <div class="ed-hex-rw"><input type="text" class="ed-hex-inp" placeholder="#FFFFFF"><button type="button" class="ed-hex-ok">OK</button></div>
                </div>
            </div>
            <div style="position:relative;">
                <button type="button" class="ed-btn ed-drp-tg" data-target="ed-txt-<?= $rId ?>" title="Diğer"><span class="material-symbols-outlined">more_vert</span></button>
                <div class="ed-drp" id="ed-txt-<?= $rId ?>">
                    <button type="button" class="ed-btn ed-cmd" data-cmd="strikeThrough" title="Üstü Çizili"><span class="material-symbols-outlined">strikethrough_s</span></button>
                    <button type="button" class="ed-btn ed-cmd" data-cmd="underline" title="Altı Çizili"><span class="material-symbols-outlined">format_underlined</span></button>
                    <button type="button" class="ed-btn ed-qck" data-type="code" title="Kod"><span class="material-symbols-outlined">terminal</span></button>
                    <button type="button" class="ed-btn ed-qck" data-type="spoiler" title="Spoiler"><span class="material-symbols-outlined">visibility_off</span></button>
                    <button type="button" class="ed-btn ed-modal-btn" data-modal="ed-md-<?= $rId ?>" title="Markdown Yapıştır"><span class="material-symbols-outlined">markdown</span></button>
                </div>
            </div>
        </div>
        <div class="ed-btn-sep"></div>
        <div class="ed-tb-grp">
            <div style="position:relative;">
                <button type="button" class="ed-btn ed-drp-tg" data-target="ed-al-<?= $rId ?>" title="Hizalama"><span class="material-symbols-outlined">format_align_left</span></button>
                <div class="ed-drp" id="ed-al-<?= $rId ?>">
                    <button type="button" class="ed-btn ed-cmd" data-cmd="justifyLeft" title="Sola"><span class="material-symbols-outlined">format_align_left</span></button>
                    <button type="button" class="ed-btn ed-cmd" data-cmd="justifyCenter" title="Ortala"><span class="material-symbols-outlined">format_align_center</span></button>
                    <button type="button" class="ed-btn ed-cmd" data-cmd="justifyRight" title="Sağa"><span class="material-symbols-outlined">format_align_right</span></button>
                </div>
            </div>
            <button type="button" class="ed-btn ed-cmd" data-cmd="outdent" title="Girinti Azalt"><span class="material-symbols-outlined">format_indent_decrease</span></button>
            <div style="position:relative;">
                <button type="button" class="ed-btn ed-drp-tg" data-target="ed-lst-<?= $rId ?>" title="Listeler"><span class="material-symbols-outlined">more_vert</span></button>
                <div class="ed-drp" id="ed-lst-<?= $rId ?>">
                    <button type="button" class="ed-btn ed-cmd" data-cmd="insertUnorderedList" title="Maddeli Liste"><span class="material-symbols-outlined">format_list_bulleted</span></button>
                    <button type="button" class="ed-btn ed-cmd" data-cmd="insertOrderedList" title="Numaralı Liste"><span class="material-symbols-outlined">format_list_numbered</span></button>
                </div>
            </div>
        </div>
        <div class="ed-btn-sep"></div>
        <div class="ed-tb-grp">
            <button type="button" class="ed-btn ed-modal-btn" data-modal="ed-img-<?= $rId ?>" title="Resim"><span class="material-symbols-outlined">image</span></button>
            <button type="button" class="ed-btn ed-modal-btn" data-modal="ed-lnk-<?= $rId ?>" title="Bağlantı"><i class="fa-solid fa-link"></i></button>
            <button type="button" class="ed-btn ed-modal-btn" data-modal="ed-emo-<?= $rId ?>" title="Emoji"><span class="material-symbols-outlined">sentiment_satisfied</span></button>
            <button type="button" class="ed-btn ed-cmd" data-cmd="formatBlock" data-tag="blockquote" title="Alıntı"><i class="fa-solid fa-quote-left"></i></button>
        </div>
        <div class="ed-tb-grp">
            <button type="button" class="ed-btn ed-cmd" data-cmd="undo" title="Geri Al"><span class="material-symbols-outlined">undo</span></button>
            <button type="button" class="ed-btn ed-cmd" data-cmd="redo" title="İleri Al"><span class="material-symbols-outlined">redo</span></button>
            <button type="button" class="ed-btn ed-cmd" data-cmd="removeFormat" title="Temizle"><span class="material-symbols-outlined">format_clear</span></button>
            <div class="ed-btn-sep"></div>
            <button type="button" class="ed-btn ed-prev" title="Önizleme" style="padding:0 10px;width:auto;font-weight:600;"><span class="material-symbols-outlined" style="margin-right:4px;">search</span>Önizleme</button>
        </div>
    </div>
    <div class="ed-body-wrap">
        <div class="ed-body" id="edBody-<?= $rId ?>" contenteditable="true" style="min-height:<?= htmlspecialchars($height) ?>"><?= $content ?></div>
        <div class="ed-rsz" data-for="edBody-<?= $rId ?>"></div>
    </div>
</div>
<textarea id="<?= $rId ?>" name="<?= htmlspecialchars($name) ?>" style="display:none;"></textarea>

<div class="ed-modal" id="ed-lnk-<?= $rId ?>">
    <div class="ed-modal-box">
        <h3>Bağlantı Ekle</h3>
        <div class="ed-mg"><label>URL</label><input type="text" class="ed-lnk-url" placeholder="https://"></div>
        <div class="ed-mg"><label>Görüntülenecek Metin</label><input type="text" class="ed-lnk-txt" placeholder="Tıklanabilir metin"></div>
        <div class="ed-modal-ft">
            <button type="button" class="ed-modal-btn ed-close-modal">İptal</button>
            <button type="button" class="ed-modal-btn pri ed-lnk-ok">Ekle</button>
        </div>
    </div>
</div>

<div class="ed-modal" id="ed-img-<?= $rId ?>">
    <div class="ed-modal-box">
        <h3>Resim Ekle</h3>
        <div class="ed-tabs">
            <div class="ed-tab active" data-tab="ed-imgurl-<?= $rId ?>">URL ile</div>
            <div class="ed-tab" data-tab="ed-imgupl-<?= $rId ?>">Yükle</div>
        </div>
        <div class="ed-tabc active" id="ed-imgurl-<?= $rId ?>">
            <div class="ed-mg"><label>Resim URL</label><input type="text" class="ed-img-src" placeholder="https://site.com/resim.png"></div>
        </div>
        <div class="ed-tabc" id="ed-imgupl-<?= $rId ?>">
            <div class="ed-mg"><label>Dosya Seç</label><input type="file" class="ed-img-file" accept="image/*"></div>
        </div>
        <div class="ed-mg"><label>Genişlik (opsiyonel)</label><input type="text" class="ed-img-w" placeholder="100% veya 300px"></div>
        <div class="ed-modal-ft">
            <button type="button" class="ed-modal-btn ed-close-modal">İptal</button>
            <button type="button" class="ed-modal-btn pri ed-img-ok">Ekle</button>
        </div>
    </div>
</div>

<div class="ed-modal" id="ed-emo-<?= $rId ?>">
    <div class="ed-modal-box">
        <h3>Emoji/Sembol Ekle</h3>
        <div class="ed-mg"><label>Emoji</label><input type="text" class="ed-emo-txt" placeholder="😊"></div>
        <div class="ed-modal-ft">
            <button type="button" class="ed-modal-btn ed-close-modal">İptal</button>
            <button type="button" class="ed-modal-btn pri ed-emo-ok">Ekle</button>
        </div>
    </div>
</div>

<div class="ed-modal" id="ed-md-<?= $rId ?>">
    <div class="ed-modal-box" style="max-width:520px">
        <h3>Markdown Yapıştır</h3>
        <div class="ed-mg"><label>Markdown</label><textarea class="ed-md-txt" rows="8" placeholder="# Başlık
**kalın** *eğik* [link](https://...)&#10;- liste&#10;```kod```" style="width:100%;min-height:140px;background:#121212;border:1px solid #333;color:#fff;padding:10px;border-radius:4px;font-family:monospace;font-size:13px;outline:none;resize:vertical"></textarea></div>
        <div class="ed-mg" style="font-size:11px;color:#888">Destek: başlık (# ## ###), **kalın**, *eğik*, [link](url), ![resim](url), `kod`, ```blok```, - liste, &gt; alıntı</div>
        <div class="ed-modal-ft">
            <button type="button" class="ed-modal-btn ed-close-modal">İptal</button>
            <button type="button" class="ed-modal-btn pri ed-md-ok">Ekle</button>
        </div>
    </div>
</div>

<script>
(function(){try{
var $=document.getElementById.bind(document),r='<?= $rId ?>',ed=$('edBody-'+r),wrap=$('edWrap-'+r);
if(!ed||!wrap)return;
var sr=null;
function ss(){var s=window.getSelection();if(s.rangeCount>0){var ra=s.getRangeAt(0);if(ed.contains(ra.commonAncestorContainer)||ed===ra.commonAncestorContainer)sr=ra;}}
function sr2(){if(sr){var s=window.getSelection();s.removeAllRanges();s.addRange(sr);}else ed.focus();}
['keyup','mouseup','focus'].forEach(function(ev){ed.addEventListener(ev,ss);});

wrap.querySelectorAll('.ed-drp-tg').forEach(function(b){b.addEventListener('click',function(e){
e.preventDefault();e.stopPropagation();ss();var t=b.getAttribute('data-target');
wrap.querySelectorAll('.ed-drp').forEach(function(m){if(m.id!==t)m.classList.remove('show');});
wrap.querySelectorAll('.ed-drp-tg').forEach(function(x){if(x.getAttribute('data-target')!==t)x.classList.remove('active');});
var d=$(t);if(d){d.classList.toggle('show');b.classList.toggle('active');if(d.classList.contains('show')){var br=b.getBoundingClientRect();d.style.left=Math.min(br.left,window.innerWidth-d.offsetWidth-8)+'px';d.style.top=(br.bottom+4)+'px';}}
});});

document.addEventListener('click',function(e){
if(!e.target.closest('.ed-wrap')){document.querySelectorAll('.ed-drp').forEach(function(m){m.classList.remove('show');});
document.querySelectorAll('.ed-drp-tg').forEach(function(b){b.classList.remove('active');});}
});

wrap.querySelectorAll('.ed-drp').forEach(function(m){m.addEventListener('click',function(e){e.stopPropagation();});});

wrap.querySelectorAll('.ed-cmd').forEach(function(b){b.addEventListener('click',function(e){
e.preventDefault();sr2();var c=b.getAttribute('data-cmd'),t=b.getAttribute('data-tag');
if(c==='formatBlock'&&t)document.execCommand(c,false,'<'+t+'>');else document.execCommand(c,false,t);ss();
});});

wrap.querySelectorAll('.ed-fs-btn').forEach(function(b){b.addEventListener('click',function(e){
e.preventDefault();sr2();ed.focus();var sz=b.getAttribute('data-size'),tx=window.getSelection().toString();
if(tx){var es=tx.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');document.execCommand('insertHTML',false,'<span style="font-size:'+sz+';">'+es+'</span>');}
ss();
});});

wrap.querySelectorAll('.ed-qck').forEach(function(b){b.addEventListener('click',function(e){
e.preventDefault();sr2();var tp=b.getAttribute('data-type'),tx=window.getSelection().toString()||(tp==='code'?'Kod':'Gizli');
if(tp==='code')document.execCommand('insertHTML',false,'<code style="background:#2d2d2d;color:#e06c75;padding:2px 5px;border-radius:4px;font-family:monospace;">'+tx+'</code>&nbsp;');
else if(tp==='spoiler')document.execCommand('insertHTML',false,'<span style="background:#000;color:#000;padding:0 5px;cursor:pointer;" onclick="this.style.color=\'#fff\'">'+tx+'</span>&nbsp;');
ss();
});});

var hi=wrap.querySelector('.ed-hex-inp'),ho=wrap.querySelector('.ed-hex-ok');
hi.addEventListener('input',function(){var c=hi.value;if(/^#([0-9A-F]{3}){1,2}$/i.test(c)){sr2();document.execCommand('foreColor',false,c);ss();}});
hi.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();ho.click();}else if(e.key==='Escape'){e.preventDefault();
var cd=$('ed-clr-'+r);cd.classList.remove('show');wrap.querySelector('[data-target="ed-clr-'+r+'"]').classList.remove('active');}});
ho.addEventListener('click',function(e){e.preventDefault();var c=hi.value;if(/^#([0-9A-F]{3}){1,2}$/i.test(c)){sr2();document.execCommand('foreColor',false,c);}
var cd=$('ed-clr-'+r);cd.classList.remove('show');wrap.querySelector('[data-target="ed-clr-'+r+'"]').classList.remove('active');ss();});
wrap.querySelectorAll('.ed-clr-sw').forEach(function(s){s.addEventListener('click',function(e){
e.preventDefault();e.stopPropagation();sr2();var c=s.getAttribute('data-color');hi.value=c.toUpperCase();
document.execCommand('foreColor',false,c);var cd=$('ed-clr-'+r);cd.classList.remove('show');
wrap.querySelector('[data-target="ed-clr-'+r+'"]').classList.remove('active');ss();
});});

var lm=$('ed-lnk-'+r),im=$('ed-img-'+r),em=$('ed-emo-'+r),mdm=$('ed-md-'+r),mods=[lm,im,em,mdm].filter(Boolean);
wrap.querySelectorAll('.ed-modal-btn[data-modal]').forEach(function(b){b.addEventListener('click',function(e){
e.preventDefault();ss();var m=$(b.getAttribute('data-modal'));
if(m&&m===lm&&lm){lm.querySelector('.ed-lnk-txt').value=window.getSelection().toString();lm.querySelector('.ed-lnk-url').value='';}
if(m)m.classList.add('show');
});});
document.querySelectorAll('.ed-close-modal').forEach(function(b){b.addEventListener('click',function(){mods.forEach(function(m){if(m)m.classList.remove('show');});sr2();});});
mods.forEach(function(m){if(m)m.addEventListener('click',function(e){if(e.target===m){mods.forEach(function(x){if(x)x.classList.remove('show');});sr2();}});});
if(lm){lm.querySelector('.ed-lnk-ok').addEventListener('click',function(){
var u=lm.querySelector('.ed-lnk-url').value.trim(),t=lm.querySelector('.ed-lnk-txt').value||u;
u=u.replace(/"/g,'%22');
t=t.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
if(u){mods.forEach(function(m){if(m)m.classList.remove('show');});sr2();document.execCommand('insertHTML',false,'<a href="'+u+'" target="_blank">'+t+'</a>&nbsp;');ss();}
});}
if(im){im.querySelectorAll('.ed-tab').forEach(function(t){t.addEventListener('click',function(){
var p=t.closest('.ed-modal-box');p.querySelectorAll('.ed-tab').forEach(function(x){x.classList.remove('active');});
p.querySelectorAll('.ed-tabc').forEach(function(x){x.classList.remove('active');});t.classList.add('active');
var tt=$(t.getAttribute('data-tab'));if(tt)tt.classList.add('active');
});});
im.querySelector('.ed-img-ok').addEventListener('click',function(){
var at=im.querySelector('.ed-tab.active');if(!at)return;var ti=at.getAttribute('data-tab'),w=im.querySelector('.ed-img-w').value,ws=w?'width:'+w+';':'';
mods.forEach(function(m){if(m)m.classList.remove('show');});
sr2();
if(ti==='ed-imgurl-'+r){var u=im.querySelector('.ed-img-src').value.trim().replace(/"/g,'%22');if(u)document.execCommand('insertHTML',false,'<img src="'+u+'" style="'+ws+'max-width:100%;border-radius:4px;" alt="">&nbsp;');}
else if(ti==='ed-imgupl-'+r){var fi=im.querySelector('.ed-img-file');if(fi.files&&fi.files[0]){var rd=new FileReader();rd.onload=function(ev){sr2();document.execCommand('insertHTML',false,'<img src="'+ev.target.result+'" style="'+ws+'max-width:100%;border-radius:4px;" alt="">&nbsp;');ss();};rd.readAsDataURL(fi.files[0]);}}
ss();
});}
if(em){em.querySelector('.ed-emo-ok').addEventListener('click',function(){
 var e2=em.querySelector('.ed-emo-txt').value;if(e2){mods.forEach(function(m){if(m)m.classList.remove('show');});sr2();document.execCommand('insertText',false,e2);ss();}
 });}
if(mdm){mdm.querySelector('.ed-md-ok').addEventListener('click',function(){
 var md=mdm.querySelector('.ed-md-txt').value; if(!md.trim()) return;
 function escH(s){return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
 var html=escH(md);
 html=html.replace(/```([\s\S]*?)```/g,function(_,c){return '<pre style="background:#2d2d2d;color:#e06c75;padding:10px;border-radius:4px;overflow-x:auto;margin:8px 0"><code>'+c.trim()+'</code></pre>';});
 html=html.replace(/`([^`]+)`/g,'<code style="background:#2d2d2d;color:#e06c75;padding:2px 5px;border-radius:4px;font-family:monospace;">$1</code>');
 html=html.replace(/^###\s+(.+)$/gm,'<h3 style="margin:8px 0;font-size:1.1rem">$1</h3>');
 html=html.replace(/^##\s+(.+)$/gm,'<h2 style="margin:10px 0;font-size:1.3rem;color:#00d4ff">$1</h2>');
 html=html.replace(/^#\s+(.+)$/gm,'<h1 style="margin:12px 0;font-size:1.6rem;font-weight:800">$1</h1>');
 html=html.replace(/^>\s+(.+)$/gm,'<blockquote style="border-left:4px solid #3498db;background:#252525;margin:10px 0;padding:10px 15px;color:#aaa;font-style:italic;border-radius:0 4px 4px 0;">$1</blockquote>');
 html=html.replace(/!\[([^\]]*)\]\(([^)]+)\)/g,'<img src="$2" alt="$1" style="max-width:100%;border-radius:4px;margin:8px 0">');
 html=html.replace(/\[([^\]]+)\]\(([^)]+)\)/g,'<a href="$2" target="_blank" style="color:#3498db;text-decoration:underline;">$1</a>');
 html=html.replace(/\*\*([^*]+)\*\*/g,'<strong>$1</strong>');
 html=html.replace(/(^|[^*])\*([^*\n]+)\*(?!\*)/g,'$1<em>$2</em>');
 html=html.replace(/^---$/gm,'<hr style="border:none;border-top:1px solid #333;margin:12px 0">');
 html=html.replace(/^(?:-|\*)\s+(.+)$/gm,'<li>$1</li>');
 html=html.replace(/(<li>.*<\/li>)/gs,function(m){return '<ul style="margin:8px 0 8px 20px">'+m+'</ul>';});
 html=html.replace(/^\d+\.\s+(.+)$/gm,'<li>$1</li>');
 html=html.split(/\n{2,}/).map(function(p){p=p.trim(); if(!p) return ''; if(/^<(h[1-3]|ul|ol|pre|blockquote|hr|img)/.test(p)) return p; return '<p style="margin:8px 0;line-height:1.6">'+p.replace(/\n/g,'<br>')+'</p>';}).join('');
 // Actually re-split after previous replaces: do paragraph wrapping on remaining
 // Fallback: if still plain, wrap
 if(!/<(h1|h2|h3|ul|ol|pre|blockquote)/.test(html) && html.indexOf('<p')===-1){ html='<p style="margin:8px 0;line-height:1.6">'+html.replace(/\n/g,'<br>')+'</p>'; }
 mods.forEach(function(m){if(m)m.classList.remove('show');}); sr2(); document.execCommand('insertHTML',false,html); ss(); mdm.querySelector('.ed-md-txt').value='';
});}
wrap.querySelector('.ed-prev').addEventListener('click',function(){
var ie=ed.getAttribute('contenteditable')==='true';ed.setAttribute('contenteditable',ie?'false':'true');ed.classList.toggle('ed-preview');
});
ed.addEventListener('dblclick',function(e){if(e.target.tagName==='IMG'){var nw=prompt('Resim genişliği:',e.target.style.width||'100%');if(nw)e.target.style.width=nw;}});

var rz=wrap.querySelector('.ed-rsz');
if(rz){var ri=rz.getAttribute('data-for'),rt=$(ri);
if(rt)!function(){var sy,sh;rz.addEventListener('mousedown',function(e){e.preventDefault();sy=e.clientY;sh=rt.offsetHeight;
document.body.style.cursor='ns-resize';document.body.style.userSelect='none';
function mv(ev){var dh=ev.clientY-sy;rt.style.minHeight='';rt.style.height=Math.max(100,sh+dh)+'px';}
function up(){document.removeEventListener('mousemove',mv);document.removeEventListener('mouseup',up);
document.body.style.cursor='';document.body.style.userSelect='';}
document.addEventListener('mousemove',mv);document.addEventListener('mouseup',up);});}();}
}catch(e){console.error('Editor:',e);}})();
</script>
<?php
}
