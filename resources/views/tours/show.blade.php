@extends('layouts.app')
@section('title', $tour->title . ' — StayFinder')

@section('content')
<div class="container">
    <div class="section">
        {{-- Breadcrumb --}}
        <div style="font-size:13px;color:var(--text-muted);margin-bottom:20px;">
            <a href="{{ route('home') }}" style="color:var(--accent);">Ana Sayfa</a> ›
            <a href="{{ route('tours.index') }}" style="color:var(--accent);">Turlar</a> ›
            {{ $tour->title }}
        </div>

        <div class="detail-grid">
            {{-- Left: Tour Info --}}
            <div>
                @if($tour->image)
                    <img src="{{ $tour->image }}" alt="{{ $tour->title }}" style="width:100%;height:300px;object-fit:cover;border-radius:var(--radius-lg);margin-bottom:20px;">
                @endif

                <h1 style="font-size:24px;font-weight:800;letter-spacing:-0.5px;margin-bottom:10px;line-height:1.3;">{{ $tour->title }}</h1>

                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:16px;">
                    @if($tour->category)
                        <a href="{{ route('tours.index', ['category' => $tour->category->slug]) }}" class="badge badge-accent" style="text-decoration:none;">{{ $tour->category->icon }} {{ $tour->category->name }}</a>
                    @endif
                    <span class="badge badge-accent">📍 {{ $tour->destination }}</span>
                    <span class="badge badge-accent">⏱ {{ $tour->duration_days }} gün</span>
                </div>

                {{-- Tour Dates --}}
                @if($tour->dates->count())
                <div style="margin-bottom:20px;">
                    <div style="font-size:13px;font-weight:600;color:var(--text-muted);margin-bottom:8px;">📅 Kalkış Tarihleri</div>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;">
                        @foreach($tour->dates->where('departure_date', '>=', now()) as $date)
                        <div style="background:var(--accent-bg);border-radius:var(--radius);padding:8px 14px;font-size:13px;">
                            <span style="font-weight:600;">{{ $date->departure_date->format('d M Y') }}</span>
                            <span style="color:var(--text-muted);margin:0 3px;">→</span>
                            <span style="font-weight:600;">{{ $date->return_date->format('d M Y') }}</span>
                            @if($date->label)
                                <span class="badge badge-accent" style="font-size:10px;margin-left:4px;">{{ $date->label }}</span>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>
                @elseif($tour->departure_date)
                <div style="margin-bottom:16px;">
                    <span class="badge badge-accent">📅 {{ $tour->departure_date->format('d M Y') }} — {{ $tour->return_date?->format('d M Y') }}</span>
                </div>
                @endif

                @if($tour->description)
                    <p style="color:var(--text-sec);line-height:1.8;margin-bottom:24px;font-size:15px;">{{ $tour->description }}</p>
                @endif

                @if($tour->included)
                    <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:20px;margin-bottom:16px;">
                        <h3 style="font-size:15px;font-weight:700;margin-bottom:10px;">✅ Dahil Olanlar</h3>
                        <ul style="list-style:none;">
                            @foreach(explode("\n", $tour->included) as $item)
                                <li style="padding:4px 0;color:var(--text-sec);font-size:14px;">• {{ trim($item) }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if($tour->excluded)
                    <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:20px;margin-bottom:16px;">
                        <h3 style="font-size:15px;font-weight:700;margin-bottom:10px;">❌ Dahil Olmayanlar</h3>
                        <ul style="list-style:none;">
                            @foreach(explode("\n", $tour->excluded) as $item)
                                <li style="padding:4px 0;color:var(--text-sec);font-size:14px;">• {{ trim($item) }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            {{-- Right: Pricing & Agency --}}
            <div class="detail-sidebar">
                {{-- Favorite button --}}
                @auth
                @php $isFav = auth()->user()->hasFavorited($tour); @endphp
                <form method="POST" action="{{ route('favorites.toggle', $tour) }}" style="margin-bottom:12px;">
                    @csrf
                    <button type="submit" style="width:100%;padding:11px;border:1.5px solid {{ $isFav ? '#ef4444' : 'var(--border)' }};border-radius:10px;background:{{ $isFav ? '#fef2f2' : 'var(--white)' }};color:{{ $isFav ? '#ef4444' : 'var(--text-sec)' }};font-family:var(--font);font-size:14px;font-weight:600;cursor:pointer;transition:all .2s;">
                        {{ $isFav ? '❤️ Favorilerden Çıkar' : '🤍 Favorilere Ekle' }}
                    </button>
                </form>
                @endauth

                {{-- Main Price Card --}}
                @php $campaign = $tour->activeCampaign; @endphp
                <div style="background:var(--white);border:2px solid {{ $campaign ? 'var(--green)' : 'var(--accent)' }};border-radius:var(--radius-lg);padding:24px;margin-bottom:16px;">
                    @if($campaign)
                        {{-- Campaign active --}}
                        <div style="margin-bottom:6px;">
                            <span class="badge badge-green">🏷️ {{ $campaign->label }}</span>
                        </div>
                        <div style="margin:10px 0 4px;">
                            <span style="text-decoration:line-through;color:var(--text-muted);font-size:16px;">{{ $tour->formatted_price }}</span>
                            <span class="price-tag cheapest" style="font-size:32px;margin-left:8px;color:var(--green);">{{ $campaign->formatted_discount_price }}</span>
                            <span class="price-sm"> / kişi</span>
                        </div>
                        {{-- Countdown --}}
                        <div id="campaign-countdown" style="background:linear-gradient(135deg,#fef3c7,#fde68a);border-radius:var(--radius);padding:10px 14px;margin:12px 0 8px;text-align:center;">
                            <div style="font-size:11px;font-weight:600;color:#92400e;margin-bottom:4px;">⏰ Kampanya Bitiyor</div>
                            <div id="countdown-timer" style="font-size:18px;font-weight:800;color:#d97706;font-variant-numeric:tabular-nums;"></div>
                        </div>
                        <script>
                        (function(){
                            var end = new Date("{{ $campaign->ends_at->toIso8601String() }}").getTime();
                            var el = document.getElementById('countdown-timer');
                            function tick(){
                                var now = Date.now(), diff = end - now;
                                if(diff <= 0){ el.textContent = 'Süre doldu!'; return; }
                                var d = Math.floor(diff/86400000), h = Math.floor((diff%86400000)/3600000),
                                    m = Math.floor((diff%3600000)/60000), s = Math.floor((diff%60000)/1000);
                                el.textContent = (d > 0 ? d + 'g ' : '') + h + 's ' + m + 'dk ' + s + 'sn';
                                setTimeout(tick, 1000);
                            }
                            tick();
                        })();
                        </script>
                    @else
                        <div style="margin-bottom:4px;">
                            <span class="badge badge-green">🏆 En Ucuz</span>
                        </div>
                        <div style="margin:12px 0 4px;">
                            <span class="price-tag cheapest" style="font-size:32px;">{{ $tour->formatted_price }}</span>
                            <span class="price-sm"> / kişi</span>
                        </div>
                    @endif
                    <div style="margin-bottom:16px;">
                        <a href="{{ route('agencies.show', $tour->agency) }}" style="color:var(--accent);font-weight:600;font-size:15px;">{{ $tour->agency->name }}</a>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:10px;">
                        @php $mainUrl = $tour->tour_url ?: $tour->agency->website_url; @endphp
                        @if($mainUrl)
                            <a href="{{ route('tour.redirect', $tour) }}" target="_blank" class="btn btn-primary" style="width:100%;">🌐 Tura Git →</a>
                        @endif
                        @if($tour->agency->phone)
                            <a href="tel:{{ $tour->agency->phone }}" class="btn btn-outline" style="width:100%;">📞 {{ $tour->agency->phone }}</a>
                        @endif
                        @if($tour->agency->email)
                            <a href="mailto:{{ $tour->agency->email }}" class="btn btn-outline" style="width:100%;">✉️ E-posta Gönder</a>
                        @endif
                    </div>
                </div>

                {{-- Other Agencies --}}
                @if($otherOffers->count())
                <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius-lg);padding:20px;">
                    <h3 style="font-size:15px;font-weight:700;margin-bottom:14px;">Diğer Acentalar</h3>
                    <div style="display:flex;flex-direction:column;gap:10px;">
                        @foreach($otherOffers as $offer)
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:12px;background:var(--bg);border-radius:10px;">
                            <div>
                                <div style="font-weight:600;font-size:14px;">{{ $offer->agency->name }}</div>
                                <div style="font-size:12px;color:var(--text-muted);">
                                    @php $diff = round((($offer->price - $tour->price) / $tour->price) * 100); @endphp
                                    @if($diff > 0) +%{{ $diff }} daha pahalı @endif
                                </div>
                            </div>
                            <div style="text-align:right;">
                                <div class="price-tag" style="font-size:16px;">{{ $offer->formatted_price }}</div>
                                @php $offerUrl = $offer->tour_url ?: $offer->agency->website_url; @endphp
                                @if($offerUrl)
                                    <a href="{{ route('tour.redirect', $offer) }}" target="_blank" style="font-size:12px;color:var(--accent);font-weight:600;">Tura Git →</a>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>

        {{-- Reviews --}}
        <div style="margin-top:40px;">
            <div style="display:flex;align-items:center;gap:16px;margin-bottom:24px;">
                <h2 style="font-size:20px;font-weight:700;">Yorumlar</h2>
                @if($avgRating)
                    <div style="display:flex;align-items:center;gap:6px;background:var(--accent-bg);border-radius:20px;padding:4px 14px;">
                        <span style="color:#f59e0b;font-size:16px;">★</span>
                        <span style="font-weight:700;color:var(--accent);">{{ $avgRating }}</span>
                        <span style="font-size:13px;color:var(--text-muted);">({{ $reviews->count() }} yorum)</span>
                    </div>
                @else
                    <span style="font-size:13px;color:var(--text-muted);">Henüz yorum yok</span>
                @endif
            </div>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            {{-- Write Review Form --}}
            @auth
                @if(!$userReview)
                <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:20px;margin-bottom:24px;">
                    <h3 style="font-size:15px;font-weight:700;margin-bottom:14px;">Yorum Yaz</h3>
                    <form method="POST" action="{{ route('reviews.store', $tour) }}">
                        @csrf
                        @if($errors->any())
                            <div class="alert alert-error">{{ $errors->first() }}</div>
                        @endif
                        <div class="form-group">
                            <label>Puanınız</label>
                            <div style="display:flex;gap:8px;">
                                @for($i = 1; $i <= 5; $i++)
                                <label style="cursor:pointer;font-size:24px;" title="{{ $i }} yıldız">
                                    <input type="radio" name="rating" value="{{ $i }}" style="display:none;" {{ old('rating') == $i ? 'checked' : ($i == 5 ? 'checked' : '') }}>
                                    <span style="color:#f59e0b;">★</span>
                                </label>
                                @endfor
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Yorumunuz</label>
                            <textarea name="comment" placeholder="Bu tur hakkında deneyiminizi paylaşın..." rows="3">{{ old('comment') }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Yorum Yap</button>
                    </form>
                </div>
                @endif
            @else
                <div style="background:var(--accent-bg);border-radius:var(--radius);padding:16px;margin-bottom:24px;text-align:center;">
                    <a href="{{ route('login') }}" style="color:var(--accent);font-weight:600;">Giriş yapın</a> ve bu tur hakkında yorum yapın.
                </div>
            @endauth

            {{-- Reviews List --}}
            @forelse($reviews as $review)
            <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:18px;margin-bottom:10px;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:38px;height:38px;background:var(--accent-bg);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--accent);">{{ mb_substr($review->user->name, 0, 1) }}</div>
                        <div>
                            <div style="font-weight:600;font-size:14px;">{{ $review->user->name }}</div>
                            <div style="font-size:12px;color:var(--text-muted);">{{ $review->created_at->diffForHumans() }}</div>
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div style="color:#f59e0b;font-size:14px;">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</div>
                        @if(auth()->check() && auth()->id() === $review->user_id)
                            <form method="POST" action="{{ route('reviews.destroy', $review) }}" style="display:inline;">
                                @csrf @method('DELETE')
                                <button type="submit" style="font-size:12px;color:#dc2626;background:none;border:none;cursor:pointer;font-family:var(--font);">Sil</button>
                            </form>
                        @endif
                    </div>
                </div>
                <p style="font-size:14px;color:var(--text-sec);line-height:1.7;">{{ $review->comment }}</p>
            </div>
            @empty
                <div style="text-align:center;padding:32px;color:var(--text-muted);">
                    <div style="font-size:36px;margin-bottom:8px;">⭐</div>
                    Henüz yorum yapılmamış. İlk yorumu sen yap!
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection

