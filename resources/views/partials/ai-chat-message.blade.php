@if($msg->role === 'user')
    <div class="msg msg-user" style="display:flex;justify-content:flex-end;margin-bottom:18px;">
        <div style="max-width:75%;background:#6366f1;color:#fff;padding:12px 16px;border-radius:18px 18px 4px 18px;font-size:14px;line-height:1.5;white-space:pre-wrap;">{{ $msg->content }}</div>
    </div>
@else
    <div class="msg msg-assistant" style="margin-bottom:24px;">
        <div style="display:flex;gap:10px;align-items:flex-start;margin-bottom:12px;">
            <div style="width:32px;height:32px;border-radius:50%;background:#eef2ff;display:flex;align-items:center;justify-content:center;flex-shrink:0;">🤖</div>
            <div style="flex:1;background:#fff;border:1px solid #e2e8f0;padding:14px 16px;border-radius:18px 18px 18px 4px;font-size:14px;line-height:1.6;color:#0f172a;white-space:pre-wrap;">{{ $msg->content ?: 'Sana uygun seçenekler aşağıda.' }}</div>
        </div>
        @php
            $resultIds = $msg->result_tour_ids ?? [];
            $msgScores = collect($msg->result_scores ?? [])->keyBy('tour_id');
        @endphp
        @if(!empty($resultIds))
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;margin-left:42px;">
                @foreach($resultIds as $tid)
                    @php($tour = $tours->get($tid))
                    @if($tour)
                        @php($compat = $msgScores->get($tid)['compatibility_score'] ?? null)
                        {{-- Canlı (JS) light kartla aynı yapı: rozet görsel üstünde, tek CTA "İncele →" --}}
                        <a href="{{ route('tours.show', $tour) }}" style="display:flex;flex-direction:column;background:#fff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;text-decoration:none;color:inherit;">
                            <div style="position:relative;width:100%;height:120px;background:#f1f5f9;flex-shrink:0;">
                                <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:26px;background:linear-gradient(135deg,#eef2ff,#ecfdf5);">🌍</div>
                                @if($tour->image)
                                    <img src="{{ $tour->image }}" alt="{{ $tour->title }}" loading="lazy" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:block;" onerror="this.remove()">
                                @endif
                                @if($compat !== null)
                                    <div style="position:absolute;top:8px;right:8px;background:rgba(255,255,255,0.92);padding:3px 9px;border-radius:10px;font-size:11px;font-weight:700;color:#0f766e;z-index:2;">%{{ round((float) $compat * 100) }} Uyumlu</div>
                                @endif
                            </div>
                            <div style="padding:12px;display:flex;flex-direction:column;flex:1;">
                                <div style="font-size:11px;color:#6366f1;font-weight:700;text-transform:uppercase;">{{ $tour->destination }}@if($tour->duration_days) • {{ $tour->duration_days }} Gün @endif</div>
                                <div style="font-size:14px;font-weight:700;margin-top:4px;color:#0f172a;line-height:1.3;">{{ $tour->title }}</div>
                                @if($tour->agency?->name)
                                    <div style="font-size:12px;color:#64748b;margin-top:2px;">{{ $tour->agency->name }}</div>
                                @endif
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:auto;padding-top:10px;border-top:1px solid #f1f5f9;gap:6px;">
                                    <div style="font-size:15px;font-weight:800;color:#0f172a;">{{ $tour->formatted_price }}</div>
                                    <div style="font-size:11px;font-weight:700;color:#fff;background:#6366f1;padding:6px 12px;border-radius:100px;white-space:nowrap;">İncele →</div>
                                </div>
                            </div>
                        </a>
                    @endif
                @endforeach
            </div>
        @endif
    </div>
@endif
