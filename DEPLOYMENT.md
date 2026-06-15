# Deployment & Operasyon Rehberi (Plesk)

Bu proje **Plesk Git push-to-deploy** ile yayınlanır (GitHub Actions yok).
Derlenmiş frontend asset'leri (`public/build/`) repoya commit'lidir — sunucuda
`npm` gerekmez. Frontend (JS/CSS) değişikliğinde lokalde `npm run build` çalıştırıp
`public/build/` çıktısını commit'leyin.

---

## 1. ZORUNLU: Cron kurulumu (tek satır)

Tüm zamanlanmış görevler **ve queue worker** tek bir cron satırına bağlıdır.
Bu satır yoksa: AI aramada yeni turlar görünmez (embedding üretilmez), döviz
kurları donar, abonelik bildirimleri gitmez, analitik tablolar sınırsız büyür.

**Plesk Paneli → Websites & Domains → Scheduled Tasks → Add Task:**

- **Task type:** Run a command
- **Command:**
  ```
  /opt/plesk/php/8.2/bin/php /var/www/vhosts/<DOMAIN>/httpdocs/artisan schedule:run >> /dev/null 2>&1
  ```
- **Run:** Cron style → `* * * * *` (her dakika)

> PHP binary yolu kuruluma göre değişebilir. SSH'da `which php` veya
> `ls /opt/plesk/php/` ile doğrulayın. Proje yolu da Plesk'teki gerçek
> document root'un BİR ÜSTÜ olmalıdır (artisan dosyasının bulunduğu dizin).

**Doğrulama:** Kurulumdan birkaç dakika sonra SSH'da:
```bash
php artisan schedule:list        # görevler listelenmeli
tail storage/logs/laravel.log    # hata olmamalı
```

### Zamanlanmış görev envanteri

| Saat | Görev | Ne yapar |
|---|---|---|
| Her dakika | `queue:work --stop-when-empty` | Kuyruğu boşaltır (embedding, AI zenginleştirme, bilgi tabanı) |
| 03:00 | `app:sync-knowledge-base` | RAG bilgi tabanını tazeler |
| 03:15 | `app:cancel-stale-pending-orders` | 24 saatlik yarım ödemeleri iptal eder + kişisel veri temizler |
| 03:30 | bildirim pruning | 90 günden eski okunmuş bildirim/duyuruları siler |
| 04:00 | `app:prune-analytics` | tour_views/clicks 180g, ai_search_logs 90g, price_histories 365g |
| 08:00 | `app:expire-category-subscriptions` | Süresi dolan abonelikleri kapatır + yenileme hatırlatması |
| 16:00 | `app:update-currency-rates` | TCMB kurları + tours.price_try yeniden hesap |

**Queue worker nasıl çalışıyor?** Plesk'te supervisor/daemon olmadığı için worker
scheduler içinden her dakika başlar, kuyruktaki tüm işleri bitirir ve kendini
kapatır. Kalıcı süreç yoktur → çökecek/restart edilecek bir şey yoktur. İşler en
fazla ~1 dakika gecikmeyle işlenir.

---

## 2. İlk kurulum / her deploy sonrası adımlar

```bash
cd /var/www/vhosts/<DOMAIN>/httpdocs

# Her deploy sonrası:
/opt/plesk/php/8.2/bin/php artisan migrate --force
/opt/plesk/php/8.2/bin/php artisan config:cache
/opt/plesk/php/8.2/bin/php artisan route:cache
/opt/plesk/php/8.2/bin/php artisan view:cache

# Sadece İLK kurulumda:
/opt/plesk/php/8.2/bin/php artisan storage:link          # avatar/görsel upload'ları için ŞART
/opt/plesk/php/8.2/bin/php artisan app:update-currency-rates  # seed kurlar yerine gerçek TCMB kurları
```

> Plesk Git "Additional deployment actions" bölümüne deploy-sonrası komutları
> ekleyebilirsiniz; böylece her push'ta otomatik koşarlar.

---

## 3. ⚠️ APP_KEY — ASLA kaybetmeyin / değiştirmeyin

`agency_category_orders.buyer_snapshot` (TC kimlik dahil alıcı bilgileri)
**APP_KEY ile şifrelidir**. Key kaybolur veya değişirse bu veriler kalıcı
olarak okunamaz hale gelir.

- `.env`'deki `APP_KEY` değerini güvenli bir yere (şifre kasası) yedekleyin.
- Production'da **asla** `php artisan key:generate` çalıştırmayın.
- `composer setup` script'i içinde `key:generate` vardır — production'da
  `composer setup` KULLANMAYIN, yukarıdaki deploy adımlarını kullanın.
- DB yedeğini başka ortama taşırken APP_KEY'i de birlikte taşıyın.
- Key rotasyonu gerekirse: önce eski key ile çözüp yeni key ile yeniden
  şifreleyen bir komut yazılmalı, sonra key değiştirilmelidir.

---

## 4. .env üretim kontrol listesi

```env
APP_ENV=production
APP_DEBUG=false                 # kesinlikle false
APP_URL=https://<DOMAIN>        # iyzico callback'leri için doğru olmalı

DB_*=...                        # MySQL bilgileri
QUEUE_CONNECTION=database
CACHE_STORE=redis               # Redis varsa; yoksa database
SESSION_DRIVER=database

OPENAI_API_KEY=...              # AI arama için zorunlu

IYZICO_MODE=production          # canlıya geçişte sandbox → production
IYZICO_API_KEY=...
IYZICO_SECRET_KEY=...

# Mail: BİLİNÇLİ olarak log modunda (entegrasyon ileride yapılacak).
# MailerSend vb. bağlanınca sadece şu satırlar değişecek:
MAIL_MAILER=log
# MAIL_MAILER=smtp + MAIL_HOST/PORT/USERNAME/PASSWORD/FROM_ADDRESS
```

---

## 5. Sorun giderme

| Belirti | Muhtemel neden | Kontrol |
|---|---|---|
| Yeni tur AI aramada çıkmıyor | Worker çalışmıyor → embedding yok | `SELECT COUNT(*) FROM jobs;` — birikiyorsa cron'u kontrol et |
| Kurlar/fiyat filtresi sapıyor | Scheduler çalışmıyor | `SELECT * FROM currency_rates;` → `fetched_at` bugün mü? |
| Abonelik bildirimi gitmiyor | Scheduler çalışmıyor | `php artisan schedule:list` + cron satırı |
| Avatar/görseller 404 | storage symlink yok | `php artisan storage:link` |
| Şifre sıfırlama maili gitmiyor | Mail driver `log` (bilinçli) | Link `storage/logs/laravel.log` içinde |
| Başarısız job'lar | İş 3 denemede patlamış | `SELECT * FROM failed_jobs;` → `php artisan queue:retry all` |

**Hızlı sağlık kontrolü (SSH):**
```bash
php artisan schedule:list                 # 7 görev listelenmeli
php artisan tinker --execute="echo 'jobs: '.DB::table('jobs')->count().' / failed: '.DB::table('failed_jobs')->count();"
```
`jobs` sayısı sürekli artıyorsa worker çalışmıyor demektir.
