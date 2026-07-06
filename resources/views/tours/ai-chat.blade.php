@extends('layouts.app')
@section('title', 'AI Tatil Danışmanı')

@section('content')
<style>
    .ai-chat-layout { display:grid; grid-template-columns:260px 1fr; gap:24px; align-items:start; }
    /* Mobil: sabit 260px sidebar ekranı kullanılmaz hale getiriyordu — tek kolon,
       konuşma listesi gizli (Yeni Konuşma butonu sohbet başlığından erişilir) */
    @media (max-width: 900px) {
        .ai-chat-layout { grid-template-columns: 1fr; }
        .ai-chat-sidebar { display: none; }
        .ai-chat-panel { height: calc(100vh - 120px) !important; min-height: 480px !important; }
    }
</style>
<div class="container" style="padding-top:90px;padding-bottom:60px;">
    <div class="ai-chat-layout">

        <aside class="ai-chat-sidebar" style="position:sticky;top:90px;">
            <div class="stat-card" style="padding:18px;">
                <div style="font-size:13px;color:#64748b;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;">Konuşmalar</div>
                <a href="{{ route('ai.search') }}" class="btn btn-outline" style="width:100%;justify-content:center;margin-bottom:14px;">+ Yeni Konuşma</a>
                @if($recentConversations->isEmpty())
                    <div style="font-size:13px;color:#94a3b8;">Henüz konuşma yok. Aşağıdaki sohbeti başlatarak ilk turunu bul.</div>
                @else
                    <div style="display:flex;flex-direction:column;gap:6px;">
                        @foreach($recentConversations as $conv)
                            <a href="{{ route('ai.search.show', $conv->uuid) }}"
                               style="padding:10px 12px;border-radius:10px;font-size:13px;color:#334155;text-decoration:none;background:{{ $conversation && $conversation->id === $conv->id ? '#eef2ff' : 'transparent' }};border:1px solid {{ $conversation && $conversation->id === $conv->id ? '#c7d2fe' : 'transparent' }};">
                                <div style="font-weight:600;line-height:1.3;">{{ \Illuminate\Support\Str::limit($conv->title ?? 'Konuşma', 40) }}</div>
                                <div style="font-size:11px;color:#94a3b8;margin-top:2px;">{{ $conv->last_message_at?->diffForHumans() }}</div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </aside>

        <div class="stat-card ai-chat-panel" style="padding:0;overflow:hidden;display:flex;flex-direction:column;height:calc(100vh - 140px);min-height:600px;">
            <div style="padding:16px 24px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;gap:12px;">
                <div style="font-size:22px;">🤖</div>
                <div>
                    <div style="font-weight:800;color:#0f172a;font-size:16px;">AI Tatil Danışmanı</div>
                    <div style="font-size:12px;color:#64748b;">Ne aradığını anlat — bütçe, tarih, ruh hali. Konuşma ilerledikçe seçimi daraltayım.</div>
                </div>
            </div>

            <div id="chat-messages" aria-live="polite" style="flex:1;overflow-y:auto;padding:24px;background:#fafafa;">
                @if($messages->isEmpty())
                    <div style="max-width:540px;margin:32px auto;text-align:center;">
                        <div style="font-size:42px;margin-bottom:8px;">✨</div>
                        <h2 style="font-size:20px;font-weight:800;color:#0f172a;margin-bottom:8px;">Sana özel bir tatil bulalım</h2>
                        <p style="color:#64748b;font-size:14px;line-height:1.6;margin-bottom:20px;">Aşağıya tatilden ne beklediğini yaz; bütçe, ruh hali, ay, ülke - hepsini birden veya parça parça söyleyebilirsin. Mesaj geldikçe seçimi daraltırım.</p>
                        <div style="display:flex;flex-wrap:wrap;gap:8px;justify-content:center;">
                            <button type="button" class="btn btn-outline btn-sm" data-suggest>Eylül'de Avrupa'da kültür turu, 30K bütçe</button>
                            <button type="button" class="btn btn-outline btn-sm" data-suggest>Kalabalıktan kaçayım, doğa olsun, 5 gün</button>
                            <button type="button" class="btn btn-outline btn-sm" data-suggest>Yurt içi, plaj, vize istemiyorum</button>
                        </div>
                    </div>
                @else
                    @foreach($messages as $msg)
                        @include('partials.ai-chat-message', ['msg' => $msg, 'tours' => $tours])
                    @endforeach
                @endif
            </div>

            <div style="padding:16px 24px;border-top:1px solid #e2e8f0;background:#fff;">
                <form id="chat-form" style="display:flex;gap:10px;align-items:flex-end;">
                    <input type="hidden" id="conversation-uuid" value="{{ $conversation?->uuid }}">
                    <textarea id="chat-input" rows="1" placeholder="Mesajını yaz..." required aria-label="Mesajınız"
                        style="flex:1;padding:12px 14px;border:1px solid #e2e8f0;border-radius:12px;font-size:14px;resize:none;outline:none;font-family:inherit;line-height:1.4;max-height:120px;"></textarea>
                    <button type="submit" id="chat-send" class="btn btn-primary" aria-label="Mesajı gönder" style="padding:12px 20px;flex-shrink:0;">Gönder</button>
                </form>
                <div style="font-size:11px;color:#94a3b8;margin-top:8px;">Enter ile gönder, Shift+Enter ile yeni satır.</div>
            </div>
        </div>
    </div>
</div>

<template id="msg-template-user">
    <div class="msg msg-user" style="display:flex;justify-content:flex-end;margin-bottom:18px;">
        <div style="max-width:75%;background:#6366f1;color:#fff;padding:12px 16px;border-radius:18px 18px 4px 18px;font-size:14px;line-height:1.5;white-space:pre-wrap;overflow-wrap:anywhere;"></div>
    </div>
</template>

<template id="msg-template-assistant">
    <div class="msg msg-assistant" style="margin-bottom:24px;">
        <div style="display:flex;gap:10px;align-items:flex-start;margin-bottom:12px;">
            <div style="width:32px;height:32px;border-radius:50%;background:#eef2ff;display:flex;align-items:center;justify-content:center;flex-shrink:0;">🤖</div>
            <div style="flex:1;background:#fff;border:1px solid #e2e8f0;padding:14px 16px;border-radius:18px 18px 18px 4px;font-size:14px;line-height:1.6;color:#0f172a;white-space:pre-wrap;overflow-wrap:anywhere;" data-comment></div>
        </div>
        <div data-tours style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;margin-left:42px;"></div>
    </div>
</template>

<script>
(function() {
    const form = document.getElementById('chat-form');
    const input = document.getElementById('chat-input');
    const messages = document.getElementById('chat-messages');
    const uuidInput = document.getElementById('conversation-uuid');
    const messageRoute = @json(route('ai.search.message'));
    const streamRoute = @json(route('ai.search.message.stream'));
    const showRouteBase = @json(rtrim(route('ai.search'), '/'));
    const csrfToken = @json(csrf_token());

    document.querySelectorAll('[data-suggest]').forEach(btn => {
        btn.addEventListener('click', () => {
            input.value = btn.textContent.trim();
            input.focus();
            autoResize();
        });
    });

    function autoResize() {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 120) + 'px';
    }
    input.addEventListener('input', autoResize);
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            form.requestSubmit();
        }
    });

    function clearWelcome() {
        const welcome = messages.querySelector('div[style*="32px auto"]');
        if (welcome) welcome.remove();
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]);
    }

    function appendUser(content) {
        clearWelcome();
        const tpl = document.getElementById('msg-template-user');
        const node = tpl.content.cloneNode(true);
        node.querySelector('div > div').textContent = content;
        messages.appendChild(node);
        messages.scrollTop = messages.scrollHeight;
    }

    function appendAssistant(content, tours, isClarification) {
        clearWelcome();
        const tpl = document.getElementById('msg-template-assistant');
        const node = tpl.content.cloneNode(true);
        const commentEl = node.querySelector('[data-comment]');
        commentEl.textContent = content || 'Sana uygun seçenekler aşağıda.';
        if (isClarification) {
            commentEl.style.background = '#fffbeb';
            commentEl.style.borderColor = '#fde68a';
            commentEl.style.color = '#78350f';
            commentEl.textContent = '🤔  ' + commentEl.textContent;
        }
        const grid = node.querySelector('[data-tours]');

        if (isClarification) {
            // Soru sorulduğunda tur kartı boş bırakılır, "soru" ipucu gösterilir
            messages.appendChild(node);
            messages.scrollTop = messages.scrollHeight;
            return;
        }

        if (tours && tours.length) {
            tours.forEach(t => {
                const card = document.createElement('a');
                card.href = t.url;
                card.style.cssText = 'display:flex;flex-direction:column;background:#fff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;text-decoration:none;color:inherit;transition:transform .15s,box-shadow .15s;';
                card.onmouseenter = () => { card.style.transform = 'translateY(-2px)'; card.style.boxShadow = '0 4px 12px rgba(0,0,0,.08)'; };
                card.onmouseleave = () => { card.style.transform = 'none'; card.style.boxShadow = 'none'; };

                const img = t.image ? `<img src="${escapeHtml(t.image)}" style="width:100%;height:120px;object-fit:cover;">` : '<div style="width:100%;height:120px;background:#f1f5f9;"></div>';
                const compat = t.compatibility_score != null ? `<div style="font-size:11px;color:#0f766e;font-weight:700;background:#ecfdf5;padding:2px 8px;border-radius:8px;display:inline-block;">%${Math.round(t.compatibility_score * 100)} uyumlu</div>` : '';

                card.innerHTML = `
                    ${img}
                    <div style="padding:12px;display:flex;flex-direction:column;gap:6px;">
                        <div style="font-size:11px;color:#6366f1;font-weight:700;text-transform:uppercase;">${escapeHtml(t.destination || '-')}</div>
                        <div style="font-size:14px;font-weight:700;color:#0f172a;line-height:1.3;">${escapeHtml(t.title || 'Tur')}</div>
                        <div style="font-size:12px;color:#64748b;">${escapeHtml(t.agency_name || '')} ${t.duration_days ? '· ' + t.duration_days + ' gün' : ''}</div>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:4px;">
                            <div style="font-size:15px;font-weight:800;color:#0f172a;">${Number(t.price || 0).toLocaleString('tr-TR')} ${escapeHtml(t.currency || 'TL')}</div>
                            ${compat}
                        </div>
                    </div>
                `;
                grid.appendChild(card);
            });
        } else {
            grid.innerHTML = '<div style="font-size:13px;color:#94a3b8;padding:8px 0;">Bu kriterlere uygun bir tur bulamadım — bütçeyi, tarihi veya tercihi değiştirip dener misin?</div>';
        }

        messages.appendChild(node);
        messages.scrollTop = messages.scrollHeight;
    }

    function showLoading() {
        const div = document.createElement('div');
        div.id = 'chat-loading';
        div.style.cssText = 'display:flex;gap:10px;align-items:center;color:#64748b;font-size:13px;margin-bottom:18px;';
        div.innerHTML = '<div style="width:32px;height:32px;border-radius:50%;background:#eef2ff;display:flex;align-items:center;justify-content:center;">🤖</div><div>Düşünüyorum...</div>';
        messages.appendChild(div);
        messages.scrollTop = messages.scrollHeight;
    }
    function hideLoading() {
        document.getElementById('chat-loading')?.remove();
    }

    // Streaming asistan balonu yarat — chunk geldikçe textContent append edilir
    function startStreamingAssistant(isClarification) {
        clearWelcome();
        const tpl = document.getElementById('msg-template-assistant');
        const node = tpl.content.cloneNode(true);
        const commentEl = node.querySelector('[data-comment]');
        commentEl.textContent = isClarification ? '🤔 ' : '';
        if (isClarification) {
            commentEl.style.background = '#fffbeb';
            commentEl.style.borderColor = '#fde68a';
            commentEl.style.color = '#78350f';
        }
        const grid = node.querySelector('[data-tours]');
        const wrapper = node.firstElementChild;
        messages.appendChild(node);
        messages.scrollTop = messages.scrollHeight;
        return { commentEl, grid, wrapper };
    }

    function renderTourCards(grid, tours, logId) {
        if (!tours || !tours.length) return;
        tours.forEach(t => {
            const wrap = document.createElement('div');
            wrap.style.cssText = 'position:relative;display:flex;flex-direction:column;background:#fff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;transition:transform .15s,box-shadow .15s,opacity .35s;';
            wrap.dataset.tourId = String(t.id);

            const link = document.createElement('a');
            link.href = t.url;
            link.style.cssText = 'display:flex;flex-direction:column;text-decoration:none;color:inherit;';
            link.onmouseenter = () => { wrap.style.transform = 'translateY(-2px)'; wrap.style.boxShadow = '0 4px 12px rgba(0,0,0,.08)'; };
            link.onmouseleave = () => { wrap.style.transform = 'none'; wrap.style.boxShadow = 'none'; };
            const img = t.image ? `<img src="${escapeHtml(t.image)}" style="width:100%;height:120px;object-fit:cover;">` : '<div style="width:100%;height:120px;background:#f1f5f9;"></div>';
            const compat = t.compatibility_score != null ? `<div style="font-size:11px;color:#0f766e;font-weight:700;background:#ecfdf5;padding:2px 8px;border-radius:8px;display:inline-block;">%${Math.round(t.compatibility_score * 100)} uyumlu</div>` : '';
            const overBudget = t.over_budget ? '<div style="font-size:11px;color:#92400e;font-weight:700;background:#fffbeb;border:1px solid #fde68a;padding:2px 8px;border-radius:8px;display:inline-block;">bütçe üstü</div>' : '';
            const reason = t.reason ? `<div style="font-size:11.5px;color:#0f766e;font-style:italic;line-height:1.4;">✨ ${escapeHtml(t.reason)}</div>` : '';
            const nextDep = t.next_departure ? `<div style="font-size:11px;color:#64748b;">📅 En yakın kalkış: ${escapeHtml(t.next_departure)}</div>` : '';
            // Bütçe kurtarıcı: başka tarihte bütçeye giren fiyat varsa yeşil çip
            const flexDate = t.flex_date ? `<div style="font-size:11px;color:#166534;font-weight:700;background:#ecfdf5;border:1px solid #bbf7d0;padding:2px 8px;border-radius:8px;display:inline-block;">🟢 ${escapeHtml(t.flex_date.date)} — ${escapeHtml(t.flex_date.price)} bütçende</div>` : '';
            link.innerHTML = `
                ${img}
                <div style="padding:12px;display:flex;flex-direction:column;gap:6px;">
                    <div style="font-size:11px;color:#6366f1;font-weight:700;text-transform:uppercase;">${escapeHtml(t.destination || '-')}</div>
                    <div style="font-size:14px;font-weight:700;color:#0f172a;line-height:1.3;">${escapeHtml(t.title || 'Tur')}</div>
                    <div style="font-size:12px;color:#64748b;">${escapeHtml(t.agency_name || '')} ${t.duration_days ? '· ' + t.duration_days + ' gün' : ''}</div>
                    ${nextDep}
                    ${reason}
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:4px;gap:6px;flex-wrap:wrap;">
                        <div style="font-size:15px;font-weight:800;color:#0f172a;">${Number(t.price || 0).toLocaleString('tr-TR')} ${escapeHtml(t.currency || 'TL')}</div>
                        <div style="display:flex;gap:4px;">${overBudget}${compat}</div>
                    </div>
                    ${flexDate}
                </div>
            `;
            wrap.appendChild(link);

            if (logId) {
                wrap.appendChild(buildRejectControl(wrap, t.id, logId));
            }
            grid.appendChild(wrap);
        });
    }

    function buildRejectControl(cardWrap, tourId, logId) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.title = 'Bu öneri uymaz';
        btn.textContent = '✕';
        btn.style.cssText = 'position:absolute;top:8px;right:8px;width:26px;height:26px;border-radius:50%;border:none;background:rgba(15,23,42,0.6);color:#fff;cursor:pointer;font-size:14px;line-height:1;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(4px);';

        const popup = document.createElement('div');
        popup.style.cssText = 'display:none;position:absolute;top:40px;right:8px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:8px;box-shadow:0 8px 24px rgba(0,0,0,.12);z-index:5;flex-direction:column;gap:4px;min-width:170px;';

        const reasons = [
            ['too_expensive', '💸 Çok pahalı'],
            ['wrong_destination', '🗺️ Yanlış destinasyon'],
            ['wrong_vibe', '🎭 Tarzıma uymadı'],
            ['other', '🤷 Diğer'],
        ];
        reasons.forEach(([key, label]) => {
            const opt = document.createElement('button');
            opt.type = 'button';
            opt.textContent = label;
            opt.style.cssText = 'background:transparent;border:none;text-align:left;padding:8px 10px;border-radius:8px;cursor:pointer;font-size:13px;color:#334155;';
            opt.onmouseenter = () => { opt.style.background = '#f1f5f9'; };
            opt.onmouseleave = () => { opt.style.background = 'transparent'; };
            opt.onclick = (e) => {
                e.stopPropagation();
                e.preventDefault();
                rejectTour(cardWrap, tourId, key, logId);
            };
            popup.appendChild(opt);
        });

        btn.onclick = (e) => {
            e.stopPropagation();
            e.preventDefault();
            popup.style.display = popup.style.display === 'flex' ? 'none' : 'flex';
        };

        // Card wrapper'a popup ekle (button + popup)
        const container = document.createElement('div');
        container.appendChild(btn);
        container.appendChild(popup);
        return container;
    }

    async function rejectTour(cardWrap, tourId, reason, logId) {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const res = await fetch(`/yapay-zeka-arama/${logId}/reddet`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ tour_id: tourId, reason }),
            });
            if (!res.ok) {
                const data = await res.json().catch(() => ({}));
                console.warn('Reject failed:', data.error || res.status);
                return;
            }
            // Fade-out + remove
            cardWrap.style.opacity = '0';
            cardWrap.style.transform = 'scale(0.95)';
            setTimeout(() => { cardWrap.remove(); }, 350);
        } catch (err) {
            console.error('Reject error', err);
        }
    }

    // Akıllı kaydırma: kullanıcı yukarıda eski mesajları okuyorsa akış onu
    // zorla en alta çekmesin; dibe yakınsa takip etsin.
    function scrollIfNearBottom() {
        const nearBottom = messages.scrollHeight - messages.scrollTop - messages.clientHeight < 140;
        if (nearBottom) messages.scrollTop = messages.scrollHeight;
    }

    // Hafif markdown: LLM yorumundaki **kalın** ham yıldızlarla görünmesin
    // (önce escape edilir — XSS güvenli)
    function mdLite(text) {
        return escapeHtml(text).replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    }

    function friendlyHttpError(status) {
        if (status === 429) return 'Biraz hızlı gidiyoruz 🙂 Birkaç saniye bekleyip tekrar gönderir misin?';
        if (status === 419) return 'Oturumun yenilenmiş — sayfayı yenileyip (F5) tekrar dener misin?';
        return 'Sunucuda bir sorun oluştu (HTTP ' + status + ') — lütfen tekrar dene.';
    }

    let sending = false;
    const sendBtn = document.getElementById('chat-send');

    function setSending(on) {
        sending = on;
        input.disabled = on;
        if (sendBtn) { sendBtn.disabled = on; sendBtn.textContent = on ? '...' : 'Gönder'; }
        if (!on) input.focus();
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (sending) return; // çift gönderim koruması
        const text = input.value.trim();
        if (!text) return;

        appendUser(text);
        input.value = '';
        autoResize();
        showLoading();
        setSending(true);

        // İstemci bekçisi: 75 sn boyunca hiç veri gelmezse isteği iptal et —
        // sonsuz "Düşünüyorum..." asılı kalmasın
        const controller = new AbortController();
        let watchdog = setTimeout(() => controller.abort(), 75000);
        const resetWatchdog = () => {
            clearTimeout(watchdog);
            watchdog = setTimeout(() => controller.abort(), 75000);
        };

        try {
            const res = await fetch(streamRoute, {
                method: 'POST',
                signal: controller.signal,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'text/event-stream',
                },
                body: JSON.stringify({
                    message: text,
                    conversation_uuid: uuidInput.value || null,
                }),
            });

            if (!res.ok) {
                hideLoading();
                appendAssistant('⚠️ ' + friendlyHttpError(res.status), []);
                return;
            }

            const reader = res.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';
            let assistant = null;
            let isClarification = false;
            let loadingHidden = false;
            let doneReceived = false;
            let errorShown = false;
            const ensure = () => {
                if (!loadingHidden) { hideLoading(); loadingHidden = true; }
                if (!assistant) assistant = startStreamingAssistant(isClarification);
            };

            const onEvent = (eventName, data) => {
                if (eventName === 'search') {
                    // uuid HER ZAMAN senkronize: oturum yenilenirse (ör. login) sunucu
                    // yeni konuşma açar; eski uuid ile devam edilirse her mesaj ayrı
                    // konuşmaya düşüyor ve bağlam hiç birikmiyordu
                    if (data.conversation_uuid && uuidInput.value !== data.conversation_uuid) {
                        uuidInput.value = data.conversation_uuid;
                        window.history.replaceState({}, '', showRouteBase + '/' + data.conversation_uuid);
                    }
                } else if (eventName === 'tours') {
                    ensure();
                    // Yeni format: {log_id, items}. Eski format: array (backward compat)
                    const items = Array.isArray(data) ? data : (data.items || []);
                    const logId = (data && typeof data === 'object' && !Array.isArray(data)) ? data.log_id : null;
                    // Gevşetme notu: sonuçlar neden birebir eşleşme değil, kullanıcı görsün
                    if (data && data.relaxation_note) {
                        const note = document.createElement('div');
                        note.style.cssText = 'margin-left:42px;margin-bottom:8px;font-size:12.5px;color:#92400e;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:8px 12px;';
                        note.textContent = 'ℹ️ ' + data.relaxation_note;
                        assistant.grid.parentElement.insertBefore(note, assistant.grid);
                    }
                    renderTourCards(assistant.grid, items, logId);
                    // 7'den fazla eşleşme varsa tam listeye bağlantı ver
                    if (data && data.all_results_url) {
                        const more = document.createElement('a');
                        more.href = data.all_results_url;
                        more.style.cssText = 'display:block;margin:10px 0 0 42px;font-size:13px;font-weight:700;color:#6366f1;text-decoration:none;';
                        more.textContent = '→ Eşleşen ' + (data.total_matches || 'tüm') + ' turun tamamını gör';
                        assistant.grid.parentElement.appendChild(more);
                    }
                    scrollIfNearBottom();
                } else if (eventName === 'comment') {
                    if (data.is_clarification === true) isClarification = true;
                    ensure();
                    if (data.delta) {
                        assistant.commentEl.textContent += data.delta;
                        scrollIfNearBottom();
                    }
                } else if (eventName === 'compare') {
                    ensure();
                    // Deterministik kıyas tablosu (sayılar sunucudan, LLM üretmedi)
                    const tbl = document.createElement('div');
                    tbl.style.cssText = 'margin:0 0 10px 42px;overflow-x:auto;background:#fff;border:1px solid #e2e8f0;border-radius:12px;';
                    let html = '<table style="border-collapse:collapse;font-size:13px;min-width:420px;width:100%;">';
                    html += '<tr><th style="padding:10px 12px;"></th>' + (data.columns || []).map(c =>
                        `<th style="padding:10px 12px;text-align:left;"><a href="${escapeHtml(c.url)}" style="color:#6366f1;text-decoration:none;font-weight:700;">${escapeHtml(c.title)}</a></th>`
                    ).join('') + '</tr>';
                    (data.rows || []).forEach(row => {
                        html += `<tr style="border-top:1px solid #f1f5f9;"><td style="padding:8px 12px;color:#64748b;font-weight:600;white-space:nowrap;">${escapeHtml(row.label)}</td>` +
                            (row.values || []).map(v => `<td style="padding:8px 12px;color:#0f172a;">${escapeHtml(v)}</td>`).join('') + '</tr>';
                    });
                    html += '</table>';
                    tbl.innerHTML = html;
                    assistant.grid.parentElement.insertBefore(tbl, assistant.grid);
                    scrollIfNearBottom();
                } else if (eventName === 'suggestions') {
                    ensure();
                    // Devam önerisi çipleri — tıklayınca mesaj olarak gönderilir
                    const wrap = document.createElement('div');
                    wrap.style.cssText = 'display:flex;flex-wrap:wrap;gap:8px;margin:10px 0 0 42px;';
                    (data.items || []).forEach(text => {
                        const chip = document.createElement('button');
                        chip.type = 'button';
                        chip.className = 'btn btn-outline btn-sm';
                        chip.textContent = text;
                        chip.onclick = () => {
                            input.value = text;
                            form.requestSubmit();
                            wrap.remove();
                        };
                        wrap.appendChild(chip);
                    });
                    assistant.wrapper.parentElement ? assistant.wrapper.parentElement.appendChild(wrap) : messages.appendChild(wrap);
                    scrollIfNearBottom();
                } else if (eventName === 'done') {
                    doneReceived = true;
                    if (data.is_clarification) {
                        // Geç gelen clarification flag, varsa stilini güncelle
                        if (assistant && !assistant.commentEl.style.background) {
                            assistant.commentEl.style.background = '#fffbeb';
                            assistant.commentEl.style.borderColor = '#fde68a';
                            assistant.commentEl.style.color = '#78350f';
                        }
                    }
                    // Akış bitti: **kalın** işaretlerini görsel kalına çevir (escape'li, güvenli)
                    if (assistant && assistant.commentEl.textContent.includes('**')) {
                        assistant.commentEl.innerHTML = mdLite(assistant.commentEl.textContent);
                    }
                    if (!loadingHidden) { hideLoading(); loadingHidden = true; }
                } else if (eventName === 'error') {
                    errorShown = true;
                    if (!loadingHidden) { hideLoading(); loadingHidden = true; }
                    appendAssistant('⚠️ ' + (data.message || 'Bir hata oluştu'), []);
                }
            };

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;
                resetWatchdog();
                buffer += decoder.decode(value, { stream: true });
                let sepIdx;
                while ((sepIdx = buffer.indexOf('\n\n')) !== -1) {
                    const chunk = buffer.slice(0, sepIdx);
                    buffer = buffer.slice(sepIdx + 2);
                    let eventName = 'message', dataStr = '';
                    for (const line of chunk.split('\n')) {
                        if (line.startsWith('event:')) eventName = line.slice(6).trim();
                        else if (line.startsWith('data:')) dataStr += line.slice(5).trim();
                    }
                    if (!dataStr) continue;
                    try { onEvent(eventName, JSON.parse(dataStr)); }
                    catch (e) { console.warn('SSE parse', e, dataStr); }
                }
            }

            if (!loadingHidden) hideLoading();

            // SESSİZ ÖLÜM koruması: akış 'done' olmadan bittiyse kullanıcı boşlukta
            // kalmasın — durumu söyle ve tekrar denemesini iste
            if (!doneReceived && !errorShown) {
                appendAssistant('⚠️ Bağlantı beklenmedik şekilde kesildi — yanıt eksik kalmış olabilir. Mesajını tekrar gönderebilirsin.', []);
            }
        } catch (err) {
            hideLoading();
            if (err && err.name === 'AbortError') {
                appendAssistant('⚠️ Yanıt çok uzun sürdü, isteği durdurdum — lütfen tekrar dene.', []);
            } else {
                appendAssistant('⚠️ Bağlantı hatası — internetini kontrol edip tekrar dener misin?', []);
            }
        } finally {
            clearTimeout(watchdog);
            setSending(false);
        }
    });

    @if(!empty($initialQuery))
        input.value = @json($initialQuery);
        autoResize();
        setTimeout(() => form.requestSubmit(), 200);
    @endif

    messages.scrollTop = messages.scrollHeight;
})();
</script>
@endsection
