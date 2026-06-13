# Azuriom PayTR Payment Gateway Plugin

This plugin is a high-security, modular PayTR payment gateway integration developed for **Azuriom CMS** (built on Laravel 12). It fully integrates with the Azuriom Shop plugin, allowing your players to securely make purchases via the PayTR iFrame API without leaving your website.

## ✨ Features

- **PayTR iFrame API Integration:** Players can securely pay with credit or debit cards through an embedded iFrame window.
- **Advanced Security Architecture:** Implements industry-standard security measures including double-spend protection, timing-safe hash comparisons, log sanitization, and input validation.
- **Multi-language Support:** Comes with built-in Turkish and English language files.
- **Intuitive Admin Panel:** Easily configure your Merchant ID, Merchant Key, and Merchant Salt directly from the admin dashboard.
- **Test & Production Modes:** Toggle between test mode and live mode with a single click.
- **Local Development (Localhost) Support:** Includes a dynamic fallback IP mechanism to bypass PayTR's public IP requirement during local development.

## 🔒 Security Hardening

To safeguard financial transactions, this plugin incorporates the following enterprise-grade security enhancements:

* **Timing-Safe Comparison (`hash_equals`):** HMAC signatures from PayTR callback requests are verified using timing-attack-resistant comparisons.
* **Double-Spend Protection:** Uses database transactions (`DB::transaction`) and row-locking (`lockForUpdate()`) to prevent concurrent duplicate notifications and fraudulent double-spending.
* **Amount & Currency Verification:** The exact amount returned by PayTR is cross-referenced with the database record down to the cent, and the currency is verified against `SUPPORTED_CURRENCIES` (e.g., TRY).
* **Input & Log Injection Protection:** All callback parameters are validated using alphanumeric regular expressions. Data is stripped of line breaks, control characters, and size-constrained before being written to log files (`sanitizeLogInput`).
* **Information Leakage Prevention:** Raw API error messages are never exposed to the end user; they are securely written to internal system logs instead.
* **Missing Payment Record Handling:** If a payment record cannot be found, the system still returns an "OK" response to PayTR to prevent infinite retry loops, while logging a critical warning for administrators.

## 🚀 Installation

### 1. File Structure
Ensure that the plugin files are placed under the `plugins/paytrpayment` directory of your Azuriom project.

### 2. Adding the Logo
To display the PayTR logo correctly on the checkout page, you need to add an `.svg` logo:
* Obtain a `paytr.svg` file.
* Place this file in the following directory: `plugins/paytrpayment/assets/img/paytr.svg`.

### 3. Activating the Plugin & Clearing Cache
After adding `"paytrpayment"` to your `plugins/plugins.json` file, you must clear Azuriom's plugin cache to make it visible in the admin dashboard. Run the following command in your terminal or delete the file via FTP:
```bash
rm bootstrap/cache/plugins.php
