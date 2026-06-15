# Azuriom - Turkish Payment (PaymentsTR) Plugin

[![Laravel Version](https://img.shields.io/badge/laravel-%5E12.0-red.svg)](https://laravel.com)
[![PHP Version](https://img.shields.io/badge/php-%5E8.2-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

Azuriom CMS için geliştirilmiş, Türkiye'deki en popüler 10 farklı Sanal POS ve ödeme kuruluşu altyapısını tek bir modülde birleştiren gelişmiş çoklu ödeme ağ geçidi (Payment Gateway) eklentisidir. 

Eski tekli entegrasyonların yerine gelen bu modül, gelişmiş güvenlik katmanları (Security Trait) ve ortak Nestpay soyutlama mimarisi ile hem kod temizliği hem de maksimum işlem güvenliği sağlar.

---

## 🚀 Özellikler & Desteklenen Entegrasyonlar

Modül, aşağıdaki 10 farklı ödeme yöntemini ve Sanal POS altyapısını yerel olarak destekler:

* **Ödeme Kuruluşları:** PayTR, Shopier, Paywant, İyzico, Papara
* **Banka Sanal POS (Nestpay / EST 3D Secure):** İş Bankası, Akbank, Ziraat Bankası
* **Özel Banka Altyapıları:** Kuveyt Türk, Garanti BBVA

### Mimarinin Güçlü Yönleri
* **Ortak Kalıtım (Inheritance):** Nestpay protokolünü kullanan bankalar (İşbank, Akbank, Ziraat) tek bir abstract sınıfı (`NestpayMethod`) miras alarak kod tekrarını önler.
* **Merkezi Güvenlik (Trait):** Tüm gateway'ler `HasSecurityFeatures` trait'ini kullanarak aynı yüksek güvenlik standartlarını paylaşır.

---

## 🔒 Gelişmiş Güvenlik Sertleştirmesi

Bu eklenti, canlı ortamda karşılaşılabilecek tüm manipülasyon ve dolandırıcılık (fraud) girişimlerine karşı sıfır tolerans prensibiyle tasarlanmıştır:

* **Tutar Manipülasyonu Koruması:** Callback (geri bildirim) esnasında gelen tutar, kuruş bazında `(int) round($payment->price * 100)` formülüyle veritabanındaki orijinal kayıtla eşleştirilir.
* **Zamanlama Güvenli Hash Karşılaştırması:** Tüm imza ve HMAC doğrulamalarında `===` yerine zamanlama saldırılarını (Timing Attack) engelleyen `hash_equals()` fonksiyonu kullanılır.
* **Nestpay HASHPARAMS Güvencesi:** Boş `HASHPARAMS` parametresi gönderilerek yapılan bypass açıkları tamamen kapatılmıştır. `microtime` yerine kriptografik olarak güvenli `random_bytes()` kullanılmıştır.
* **IP Sahteciliği (Spoofing) Koruması:** Sahte `X-Forwarded-For` başlıklarına güvenilmez; sadece gerçek ve doğrulanmış public IP adreslerini baz alan `getSafeServerIp()` metodunu içerir.
* **Double-Spend (Mükerrer Ödeme) Engelleme:** Eşzamanlı gelen mükerrer webhook bildirimlerini engellemek adına veritabanı işlemlerinde `DB::transaction` ile satır kilitleme (`lockForUpdate()`) uygulanır.
* **Fraud Filtre Yönetimi:** İyzico ve PayTR gibi dış API'lerin eksik veriler (örn: `N/A`, `0000`) nedeniyle istekleri reddetmesini önlemek için standartlara uygun akıllı "dummy" veriler üretilir.
* **XSS & JSON Enjeksiyon Koruması:** Sepet öğelerindeki tırnak, parantez ve HTML etiketleri `sanitizeBasketItemName()` ile temizlenerek API kırılmaları ve XSS riskleri önlenir.

---
