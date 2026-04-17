<div id="seoScoreWidget" class="stat-card" style="position: sticky; top: 100px; z-index: 10; padding: 20px; margin-bottom: 24px;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
        <h3 style="font-size: 16px; font-weight: 700; margin: 0;">SEO Skoru</h3>
        <span id="seoScoreValue" style="font-size: 20px; font-weight: 800; color: var(--primary);">0</span>
    </div>
    
    <div style="height: 8px; width: 100%; background: #e2e8f0; border-radius: 4px; overflow: hidden; margin-bottom: 15px;">
        <div id="seoScoreBar" style="height: 100%; width: 0%; background: var(--primary); transition: width 0.3s ease, background 0.3s ease;"></div>
    </div>
    
    <div id="seoScoreTips" style="font-size: 13px; color: var(--text-sec);">
        <p style="margin-bottom: 8px; display: flex; align-items: flex-start; gap: 8px;">
            <span style="color: #64748b;">ℹ️</span> 
            <span>Oluşturduğunuz turun Google aramalarında üst sıralara çıkması için aşağıdaki önerileri izleyin.</span>
        </p>
        <ul id="tipList" style="list-style: none; padding: 0; margin: 0;">
            <!-- Dinamik İpuçları Buraya Gelecek -->
        </ul>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const titleInput = document.querySelector('input[name="title"]');
    const descInput = document.querySelector('textarea[name="description"]');
    const imageInput = document.querySelector('input[name="image"]');
    
    function calculateSeoScore() {
        let score = 0;
        let tips = [];
        
        // 1. Başlık Kontrolü (25 Puan)
        const title = titleInput ? titleInput.value.trim() : '';
        if (title.length >= 40 && title.length <= 70) {
            score += 25;
        } else if (title.length > 0) {
            score += 10;
            tips.push('Başlığınız 40-70 karakter arası olmalı (Şu an: ' + title.length + ')');
        } else {
            tips.push('Dikkat çekici bir tur başlığı yazın');
        }
        
        // 2. Açıklama Kontrolü (40 Puan)
        const desc = descInput ? descInput.value.trim() : '';
        const descLength = desc.length;
        if (descLength >= 1000) {
            score += 40;
        } else if (descLength >= 500) {
            score += 30;
            tips.push('Açıklamayı 1000 karaktere tamamlarsanız daha iyi sonuç alırsınız');
        } else if (descLength >= 200) {
            score += 15;
            tips.push('Açıklama çok kısa, en az 500 karakter önerilir');
        } else {
            tips.push('Detaylı bir tur açıklaması ekleyin (min 200 karakter)');
        }
        
        // 3. Görsel Kontrolü (20 Puan)
        const hasImage = (imageInput && imageInput.files.length > 0) || document.getElementById('currentImg');
        if (hasImage) {
            score += 20;
        } else {
            tips.push('Turunuza en az bir kaliteli görsel ekleyin');
        }
        
        // 4. Detay Bilgiler (15 Puan) - Dahil Olanlar/Olmayanlar
        const included = document.querySelector('textarea[name="included"]');
        if (included && included.value.trim().length > 50) {
            score += 15;
        } else {
            tips.push('Nelerin dahil olduğunu detaylıca belirtin');
        }
        
        updateUI(score, tips);
    }
    
    function updateUI(score, tips) {
        const scoreVal = document.getElementById('seoScoreValue');
        const scoreBar = document.getElementById('seoScoreBar');
        const tipList = document.getElementById('tipList');
        
        scoreVal.textContent = score;
        scoreBar.style.width = score + '%';
        
        // Renk değişimi
        if (score < 40) scoreBar.style.background = '#ef4444'; // Kırmızı
        else if (score < 80) scoreBar.style.background = '#f59e0b'; // Turuncu
        else scoreBar.style.background = '#10b981'; // Yeşil
        
        tipList.innerHTML = '';
        tips.slice(0, 3).forEach(tip => {
            const li = document.createElement('li');
            li.style.marginBottom = '6px';
            li.style.display = 'flex';
            li.style.gap = '8px';
            li.innerHTML = '<span style="color: #f59e0b;">🔸</span>' + tip;
            tipList.appendChild(li);
        });
        
        if (tips.length === 0) {
            tipList.innerHTML = '<li style="color: #10b981; font-weight: 600;">✨ Harika! SEO uyumlu bir içerik oluşturdunuz.</li>';
        }
    }
    
    // Event Listeners
    [titleInput, descInput, document.querySelector('textarea[name="included"]')].forEach(el => {
        if (el) el.addEventListener('input', calculateSeoScore);
    });
    
    if (imageInput) imageInput.addEventListener('change', calculateSeoScore);
    
    // İlk hesaplama
    calculateSeoScore();
});
</script>
