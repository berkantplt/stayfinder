{{--
    Aranabilir <select> (combobox). Native <select data-searchable> öğesini,
    yazdıkça daralan bir arama kutusuna dönüştürür. Select gizli kalır (isim,
    değer, validasyon ve JS-siz çalışma korunur); seçim onu günceller + 'change'
    olayı tetikler. Bu partial bir kez include edilmesi yeter (guard'lı).
--}}
@once
<style>
    .ss-wrap { position: relative; }
    .ss-input { width: 100%; cursor: text; background: #fff; }
    .ss-panel {
        position: absolute; top: calc(100% + 4px); left: 0; right: 0;
        background: #fff; border: 1px solid var(--border, #cbd5e1); border-radius: 10px;
        box-shadow: 0 12px 24px rgba(15,23,42,0.14); z-index: 90; max-height: 260px;
        overflow-y: auto; display: none; padding: 4px;
    }
    .ss-panel.open { display: block; }
    .ss-opt { padding: 8px 12px; border-radius: 8px; font-size: 14px; cursor: pointer; color: #334155; }
    .ss-opt:hover, .ss-opt.active { background: var(--accent-bg, #ecfeff); }
    .ss-empty { padding: 8px 12px; font-size: 13px; color: #94a3b8; }
</style>
<script>
(function () {
    if (window.enhanceSearchableSelect) return;

    function fold(s) {
        s = String(s == null ? '' : s).trim();
        var map = { 'İ':'i','I':'i','ı':'i','Ş':'s','ş':'s','Ğ':'g','ğ':'g','Ü':'u','ü':'u','Ö':'o','ö':'o','Ç':'c','ç':'c','Â':'a','â':'a' };
        s = s.replace(/[İIıŞşĞğÜüÖöÇçÂâ]/g, function (c) { return map[c] || c; });
        return s.toLowerCase();
    }

    window.enhanceSearchableSelect = function (select) {
        if (!select || select.dataset.ssEnhanced) return;
        select.dataset.ssEnhanced = '1';

        // value="" olan ilk option = placeholder / "temizle" seçeneği
        var options = [].slice.call(select.options).map(function (o) {
            return { value: o.value, label: o.textContent.trim() };
        });
        var placeholderOpt = options.find(function (o) { return o.value === ''; });
        var placeholder = placeholderOpt ? placeholderOpt.label : 'Seçin';
        var resetAfterSelect = select.dataset.ssReset === '1'; // "ekle" tipi seçiciler için

        var wrap = document.createElement('div');
        wrap.className = 'ss-wrap';
        select.parentNode.insertBefore(wrap, select);
        wrap.appendChild(select);
        select.style.display = 'none';

        var input = document.createElement('input');
        input.type = 'text';
        input.className = select.className + ' ss-input';
        input.autocomplete = 'off';
        input.placeholder = placeholder;

        var panel = document.createElement('div');
        panel.className = 'ss-panel';

        wrap.appendChild(input);
        wrap.appendChild(panel);

        function currentLabel() {
            var sel = options.find(function (o) { return o.value === select.value; });
            return (sel && sel.value !== '') ? sel.label : '';
        }

        function syncInput() {
            input.value = currentLabel();
        }

        function render(filter) {
            var needle = fold(filter || '');
            panel.innerHTML = '';
            var matches = options.filter(function (o) {
                if (o.value === '') return false; // placeholder listede gösterilmez
                return needle === '' || fold(o.label).indexOf(needle) !== -1;
            });
            if (matches.length === 0) {
                var empty = document.createElement('div');
                empty.className = 'ss-empty';
                empty.textContent = 'Sonuç yok';
                panel.appendChild(empty);
                return;
            }
            matches.forEach(function (o) {
                var el = document.createElement('div');
                el.className = 'ss-opt';
                el.textContent = o.label;
                el.addEventListener('mousedown', function (ev) {
                    ev.preventDefault(); // input blur'dan önce seçimi yakala
                    select.value = o.value;
                    // native select gibi davran: input + change (otomatik-submit eden formlar yakalasın)
                    select.dispatchEvent(new Event('input', { bubbles: true }));
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    close();
                    if (resetAfterSelect) {
                        input.value = '';
                    } else {
                        syncInput();
                    }
                });
                panel.appendChild(el);
            });
        }

        function open() { render(''); panel.classList.add('open'); }
        function close() { panel.classList.remove('open'); }

        input.addEventListener('focus', function () { input.value = ''; open(); });
        input.addEventListener('input', function () { render(input.value); panel.classList.add('open'); });
        input.addEventListener('blur', function () {
            setTimeout(function () {
                close();
                if (!resetAfterSelect) { syncInput(); } else { input.value = ''; }
            }, 120);
        });

        // Dışarıdan select.value değişirse (ör. içe aktarma) input'u güncelle
        select.addEventListener('change', function () {
            if (!resetAfterSelect) syncInput();
        });

        syncInput();
    };

    function initAll() {
        [].slice.call(document.querySelectorAll('select[data-searchable]')).forEach(window.enhanceSearchableSelect);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
</script>
@endonce
