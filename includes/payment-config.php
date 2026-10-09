<?php
require_once __DIR__ . '/env.php';

/**
 * Payment Configuration
 * =====================
 * Configure your payment gateway credentials here.
 * 
 * Supported channels:
 *   - Fawry (via Paymob)
 *   - E-Wallets (via Paymob)
 *   - InstaPay (manual verification)
 *
 * To get Paymob credentials:
 * To get Fawaterak credentials:
 *   1. Register at https://fawaterk.com
 *   2. Get your API key from Dashboard > API Integration
 */

// ============================================================
// GENERAL SETTINGS
// ============================================================
define('HMS_PAYMENT_ENABLED', true);
define('HMS_DEPOSIT_PERCENTAGE', 0.30); // 30% of consultation fee
define('HMS_CURRENCY', 'EGP');

// Demo mode: simulates payment without real gateway calls
define('HMS_PAYMENT_DEMO_MODE', false); 

// Base URL for callbacks (no trailing slash)
define('HMS_BASE_URL', 'http://81.10.14.216:8020');

// ============================================================
// FAWATERAK CONFIGURATION
// ============================================================
define('FAWATERAK_API_KEY', hms_env('FAWATERAK_API_KEY', ''));
define('FAWATERAK_FAWRY_ID', '2'); // Standard Fawry ID in Fawaterak
define('FAWATERAK_WALLET_ID', '3'); // Standard Wallet ID in Fawaterak

// ============================================================
// INSTAPAY CONFIGURATION (Manual verification)
// ============================================================
define('INSTAPAY_IPA_ADDRESS', ''); // Your InstaPay IPA (e.g., hospital@instapay)
define('INSTAPAY_ACCOUNT_NAME', 'Echo Medical Center'); // Display name

// ============================================================
// HELPER FUNCTIONS
// ============================================================

/**
 * Calculate deposit amount for a given consultation fee
 */
function hms_calculate_deposit(int $consultancyFees): int
{
    return (int)ceil($consultancyFees * HMS_DEPOSIT_PERCENTAGE);
}

/**
 * Calculate remaining amount after deposit
 */
function hms_calculate_remaining(int $consultancyFees, int $depositPaid, int $additionalPaid = 0, int $discount = 0): int
{
    return max(0, $consultancyFees - $depositPaid - $additionalPaid - $discount);
}

/**
 * Get human-readable channel name
 */
function hms_payment_channel_label(string $channel): string
{
    return match ($channel) {
        'fawry' => 'Fawry',
        'instapay' => 'InstaPay',
        'ewallet' => 'E-Wallet',
        'cash' => 'Cash',
        'visa' => 'Visa',
        default => ucfirst($channel),
    };
}

/**
 * Check if a payment channel is enabled
 */
function hms_is_channel_enabled(string $channel): bool
{
    if (!HMS_PAYMENT_ENABLED) return false;
    if (HMS_PAYMENT_DEMO_MODE) return true;

    return match ($channel) {
        'fawry' => FAWATERAK_API_KEY !== '',
        'ewallet' => FAWATERAK_API_KEY !== '',
        'instapay' => INSTAPAY_IPA_ADDRESS !== '',
        default => true,
    };
}
