{{--
    Tur görsel galerisi: çoklu yükleme + URL'den içe aktarma, sürükle-sırala, sil.
    İlk görsel kapaktır. Yüklenen dosyalar anında geçici uç noktaya gider, /storage
    yoluna döner; URL'den gelenler uzak URL olarak durur. Hepsi sıralı gallery[]
    gizli alanlarıyla gönderilir; kayıtta uzaklar indirilir.

    Beklenen: $initialImages (mevcut /storage yolları dizisi)
--}}
<div class="form-group">
    <label>Tur Görselleri <span style="font-weight:400;color:var(--text-muted);font-size:12px;">(ilk görsel kapak — sürükleyerek sırala, her format kabul)</span></label>

    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px;">
        <label class="btn btn-outline btn-sm" style="cursor:pointer;margin:0;">
            + Görsel Yükle
            <input type="file" id="galleryFileInput" accept="image/*" multiple style="display:none;">
        </label>
        <span id="galleryStatus" style="font-size:12px;color:var(--text-muted);"></span>
    </div>

    <div id="galleryGrid" style="display:flex;flex-wrap:wrap;gap:10px;"></div>
    <div id="galleryHidden"></div>
</div>

<script>
(function () {
    var uploadUrl = @json(route('agency.tours.image.upload'));
    var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    var grid = document.getElementById('galleryGrid');
    var hidden = document.getElementById('galleryHidden');
    var statusEl = document.getElementById('galleryStatus');
    var fileInput = document.getElementById('galleryFileInput');
    var MAX = 12;

    var items = (@json(array_values($initialImages ?? []))) || [];
    var dragIndex = null;

    function syncHidden() {
        hidden.innerHTML = '';
        items.forEach(function (val) {
            var inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'gallery[]';
            inp.value = val;
            hidden.appendChild(inp);
        });
    }

    function render() {
        grid.innerHTML = '';
        items.forEach(function (val, i) {
            var cell = document.createElement('div');
            cell.draggable = true;
            cell.dataset.index = String(i);
            cell.style.cssText = 'position:relative;width:110px;height:110px;border-radius:10px;overflow:hidden;border:1px solid var(--border-light);background:#f1f5f9;cursor:grab;';

            var img = document.createElement('img');
            img.src = val;
            img.alt = '';
            img.loading = 'lazy';
            img.style.cssText = 'width:100%;height:100%;object-fit:cover;display:block;pointer-events:none;';
            img.onerror = function () { cell.style.background = '#fee2e2'; img.style.display = 'none'; };
            cell.appendChild(img);

            if (i === 0) {
                var badge = document.createElement('span');
                badge.textContent = 'Kapak';
                badge.style.cssText = 'position:absolute;top:4px;left:4px;background:rgba(15,23,42,0.75);color:#fff;font-size:10px;font-weight:700;padding:2px 6px;border-radius:6px;';
                cell.appendChild(badge);
            }

            var del = document.createElement('button');
            del.type = 'button';
            del.textContent = '×';
            del.title = 'Kaldır';
            del.style.cssText = 'position:absolute;top:4px;right:4px;width:22px;height:22px;border:none;border-radius:50%;background:rgba(220,38,38,0.92);color:#fff;font-size:15px;line-height:1;cursor:pointer;';
            del.onclick = function () { items.splice(i, 1); render(); syncHidden(); };
            cell.appendChild(del);

            cell.addEventListener('dragstart', function () { dragIndex = i; cell.style.opacity = '0.4'; });
            cell.addEventListener('dragend', function () { dragIndex = null; cell.style.opacity = '1'; });
            cell.addEventListener('dragover', function (e) { e.preventDefault(); });
            cell.addEventListener('drop', function (e) {
                e.preventDefault();
                if (dragIndex === null || dragIndex === i) return;
                var moved = items.splice(dragIndex, 1)[0];
                items.splice(i, 0, moved);
                render(); syncHidden();
            });

            grid.appendChild(cell);
        });
        updateStatus();
    }

    function updateStatus() {
        statusEl.textContent = items.length ? (items.length + '/' + MAX + ' görsel') : 'Henüz görsel yok.';
    }

    function addItem(val) {
        if (!val) return false;
        if (items.length >= MAX) { statusEl.textContent = 'En fazla ' + MAX + ' görsel eklenebilir.'; return false; }
        if (items.indexOf(val) !== -1) return false;
        items.push(val);
        return true;
    }

    function uploadFile(file) {
        return new Promise(function (resolve) {
            var fd = new FormData();
            fd.append('image', file);
            fetch(uploadUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: fd })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
                .then(function (res) { resolve(res.body && res.body.ok ? res.body.path : null); })
                .catch(function () { resolve(null); });
        });
    }

    fileInput.addEventListener('change', function () {
        var files = [].slice.call(this.files || []);
        this.value = '';
        if (!files.length) return;
        statusEl.textContent = 'Yükleniyor…';
        var chain = Promise.resolve();
        files.forEach(function (file) {
            chain = chain.then(function () {
                if (items.length >= MAX) return;
                return uploadFile(file).then(function (path) {
                    if (path) { addItem(path); render(); syncHidden(); }
                });
            });
        });
        chain.then(function () { updateStatus(); });
    });

    // İçe aktarmadan çağrılır: URL listesini galeriye ekler (mevcutları korur)
    window.setGalleryImages = function (urls) {
        (Array.isArray(urls) ? urls : []).forEach(function (u) { addItem(u); });
        render(); syncHidden();
    };

    render();
    syncHidden();
})();
</script>
