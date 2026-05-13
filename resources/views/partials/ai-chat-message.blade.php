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
                        <a href="{{ route('tours.show', $tour) }}" style="display:flex;flex-direction:column;background:#fff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;text-decoration:none;color:inherit;">
                            @if($tour->image)
                                <img src="{{ $tour->image }}" alt="{{ $tour->title }}" style="width:100%;height:120px;object-fit:cover;">
                            @else
                                <div style="width:100%;height:120px;background:#f1f5f9;"></div>
                            @endif
                            <div style="padding:12px;display:flex;flex-direction:column;gap:6px;">
                                <div style="font-size:11px;color:#6366f1;font-weight:700;text-transform:uppercase;">{{ $tour->destination }}</div>
                                <div style="font-size:14px;font-weight:700;color:#0f172a;line-height:1.3;">{{ $tour->title }}</div>
                                <div style="font-size:12px;color:#64748b;">{{ $tour->agency?->name }} @if($tour->duration_days) · {{ $tour->duration_days }} gün @endif</div>
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:4px;">
                                    <div style="font-size:15px;font-weight:800;color:#0f172a;">{{ number_format((float) $tour->price, 0, ',', '.') }} {{ $tour->currency }}</div>
                                    @if($compat !== null)
                                        <div style="font-size:11px;color:#0f766e;font-weight:700;background:#ecfdf5;padding:2px 8px;border-radius:8px;">%{{ round((float) $compat * 100) }} uyumlu</div>
                                    @endif
                                </div>
                            </div>
                        </a>
                    @endif
                @endforeach
            </div>
        @endif
    </div>
@endif
