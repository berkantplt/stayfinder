{{--
    Kalkış şehri (zorunlu, 81 il) + durak şehirler (yol üstünde yolcu alınan iller).
    Beklenen değişkenler: $cityOptions (il listesi), $selectedDepartureCity, $selectedStopCities (array)
--}}
@include('partials.searchable-select')
<div class="form-row" style="align-items:start;">
    <div class="form-group" style="margin-bottom:0;">
        <label>Kalkış Şehri * <span style="font-weight:400;color:var(--text-muted);font-size:12px;">(otobüsün kalktığı il)</span></label>
        <select name="departure_city" id="departureCitySelect" data-searchable required>
            <option value="">İl seçin</option>
            @foreach($cityOptions as $city)
                <option value="{{ $city }}" {{ $selectedDepartureCity === $city ? 'selected' : '' }}>{{ $city }}</option>
            @endforeach
        </select>
    </div>
    <div class="form-group" style="margin-bottom:0;">
        <label>Durak Şehirler <span style="font-weight:400;color:var(--text-muted);font-size:12px;">(yol üstünde yolcu alınan iller)</span></label>
        <select id="stopCityPicker" data-searchable data-ss-reset="1">
            <option value="">+ Durak şehir ekle</option>
            @foreach($cityOptions as $city)
                <option value="{{ $city }}">{{ $city }}</option>
            @endforeach
        </select>
        <div id="stopCitiesChips" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;"></div>
        <div id="stopCitiesHidden"></div>
    </div>
</div>

<script>
    (function () {
        var initialStops = @json(array_values($selectedStopCities ?? []));
        var picker = document.getElementById('stopCityPicker');
        var chipsWrap = document.getElementById('stopCitiesChips');
        var hiddenWrap = document.getElementById('stopCitiesHidden');
        var departureSelect = document.getElementById('departureCitySelect');
        var stops = [];

        function render() {
            chipsWrap.innerHTML = '';
            hiddenWrap.innerHTML = '';
            stops.forEach(function (city, i) {
                var chip = document.createElement('span');
                chip.style.cssText = 'display:inline-flex;align-items:center;gap:6px;background:var(--accent-bg);border-radius:999px;padding:4px 10px;font-size:13px;';
                chip.textContent = city;
                var x = document.createElement('button');
                x.type = 'button';
                x.textContent = '×';
                x.style.cssText = 'border:none;background:none;cursor:pointer;font-size:15px;line-height:1;color:var(--text-muted);';
                x.onclick = function () { stops.splice(i, 1); render(); };
                chip.appendChild(x);
                chipsWrap.appendChild(chip);

                var inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = 'stop_cities[]';
                inp.value = city;
                hiddenWrap.appendChild(inp);
            });
        }

        function addStop(city) {
            if (!city) return;
            if (departureSelect && departureSelect.value === city) return; // kalkış şehri durak olamaz
            if (stops.indexOf(city) !== -1) return;
            stops.push(city);
            render();
        }

        picker.addEventListener('change', function () {
            addStop(this.value);
            this.value = '';
        });

        // Kalkış şehri seçilince, aynı il durakta varsa çıkar
        if (departureSelect) {
            departureSelect.addEventListener('change', function () {
                var dc = this.value;
                stops = stops.filter(function (c) { return c !== dc; });
                render();
            });
        }

        // Dışarıdan (içe aktarma) doldurmak için
        window.setStopCities = function (list) {
            stops = [];
            (Array.isArray(list) ? list : []).forEach(function (c) { addStop(c); });
        };

        (initialStops || []).forEach(addStop);
    })();
</script>
