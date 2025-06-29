<?php
// =========================================================================================
// 💥 فایل جدید 💥
// File: src/Utils/PaymentHelper.php
// Path: /src/Utils/PaymentHelper.php
// Description: 💳 این کلاس جدید و قدرتمند، تمام منطق مربوط به درگاه‌های پرداخت
// (پرفکت مانی، NowPayments و...) را به صورت متمرکز و تمیز مدیریت می‌کند.
// =========================================================================================

namespace Ddkate\Utils;

use Ddkate\Models\Setting;

class PaymentHelper {

    /**
     * فعال‌سازی و بررسی ووچر پرفکت مانی.
     * @param string $voucherNumber شماره ووچر
     * @param string $evCode کد فعال‌سازی ووچر
     * @return array نتیجه عملیات شامل وضعیت و مبلغ یا پیام خطا
     */
    public static function activatePerfectMoneyVoucher(string $voucherNumber, string $evCode): array {
        $settings = Setting::getAll();
        $accountId = $settings['perfectmoney_AccountID'] ?? null;
        $passphrase = $settings['perfectmoney_PassPhrase'] ?? null;
        $payeeAccount = $settings['perfectmoney_Payer_Account'] ?? null;

        if (!$accountId || !$passphrase || !$payeeAccount) {
            return ['success' => false, 'message' => 'اطلاعات حساب پرفکت مانی در تنظیمات ربات وارد نشده است.'];
        }
        
        $url = "https://perfectmoney.com/acct/ev_activate.asp?" . http_build_query([
            'AccountID' => $accountId,
            'PassPhrase' => $passphrase,
            'Payee_Account' => $payeeAccount,
            'ev_number' => $voucherNumber,
            'ev_code' => $evCode
        ]);

        $response = CurlHelper::sendRequest($url, 'GET', [], [], null, false);
        
        // تجزیه و تحلیل پاسخ HTML از پرفکت مانی
        if (strpos($response['raw_response'], 'Error:') !== false) {
             preg_match('/Error:(.*?)<br>/', $response['raw_response'], $matches);
             return ['success' => false, 'message' => trim($matches[1] ?? 'Unknown error from Perfect Money')];
        }
        
        preg_match_all('/<input name=\'(.*?)\' type=\'hidden\' value=\'(.*?)\'>/', $response['raw_response'], $matches);
        $result = array_combine($matches[1] ?? [], $matches[2] ?? []);
        $result['success'] = true;
        return $result;
    }
    
    /**
     * ایجاد یک فاکتور پرداخت جدید در درگاه NowPayments.
     */
    public static function createNowPaymentsInvoice(float $priceAmount, string $orderId, string $orderDescription): ?array {
        $apiKey = Setting::get('nowpayments_api_key');
        if (!$apiKey) return null;

        $url = 'https://api.nowpayments.io/v1/payment';
        $headers = ['x-api-key: ' . $apiKey, 'Content-Type: application/json'];
        $data = json_encode([
            'price_amount' => $priceAmount,
            'price_currency' => 'usd',
            'pay_currency' => 'trx', // یا هر ارز دیگری
            'order_id' => $orderId,
            'order_description' => $orderDescription,
        ]);

        return CurlHelper::sendRequest($url, 'POST', $data, $headers);
    }
    
    /**
     * دریافت وضعیت یک پرداخت از NowPayments.
     */
    public static function getNowPaymentsStatus(string $paymentId): ?array {
        $apiKey = Setting::get('nowpayments_api_key');
        if (!$apiKey) return null;

        $url = 'https://api.nowpayments.io/v1/payment/' . $paymentId;
        $headers = ['x-api-key: ' . $apiKey];
        
        return CurlHelper::sendRequest($url, 'GET', [], $headers);
    }

    /**
     * دریافت آخرین نرخ ارز از یک صرافی ایرانی (مثال: بیت پین).
     */
    public static function getIranExchangeRates(): array {
        $rates = ['TRX' => 0, 'USDT' => 0];
        try {
            $response = CurlHelper::sendRequest('https://api.bitpin.ir/v1/mkt/currencies/');
            if ($response && isset($response['results'])) {
                foreach ($response['results'] as $currency) {
                    if ($currency['code'] === 'TRX') {
                        $rates['TRX'] = (float) $currency['price_info']['price'];
                    } elseif ($currency['code'] === 'USDT') {
                        $rates['USDT'] = (float) $currency['price_info']['price'];
                    }
                }
            }
        } catch (\Exception $e) {
            Logger::log('Could not fetch exchange rates: ' . $e->getMessage());
        }
        return $rates;
    }
}
?>
```php
<?php
// =========================================================================================
// ❗️❗️ فایل به‌روز شده ❗️❗️
// File: src/Utils/Helper.php
// Path: /src/Utils/Helper.php
// Description: توابع عمومی جدید از functions.php به این کلاس کمکی اضافه شدند.
// =========================================================================================

namespace Ddkate\Utils;

use Ddkate\Models\Invoice;
use Ddkate\Models\Setting;

class Helper {
    
    public static function formatBytes(int $bytes, int $precision = 2): string {
        if ($bytes <= 0) return '0 بایت';
        $base = log($bytes, 1024);
        $suffixes = ['بایت', 'کیلوبایت', 'مگابایت', 'گیگابایت', 'ترابایت'];
        $power = floor($base);
        return round(pow(1024, $base - $power), $precision) . ' ' . $suffixes[$power];
    }

    public static function generateUUID(): string {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * 💥 تابع جدید 💥
     * برای ساخت نام کاربری بر اساس متدهای مختلف.
     * جایگزین تابع generateUsername در فایل قدیمی.
     */
    public static function generateUsername(string $method, int $chat_id, string $telegram_username, ?string $custom_text = ''): string {
        $randomString = bin2hex(random_bytes(3));

        switch ($method) {
            case 'id_random':
                return $chat_id . '_' . $randomString;
            case 'user_random':
                $username = preg_replace('/[^a-zA-Z0-9]/', '', $telegram_username);
                return ($username ?: 'user') . '_' . $randomString;
            case 'user_sequential':
                $count = Invoice::countForUser($chat_id) + 1;
                $username = preg_replace('/[^a-zA-Z0-9]/', '', $telegram_username);
                return ($username ?: 'user') . '_' . $count;
            case 'custom_random':
                $custom_prefix = Setting::get('custom_username_prefix', 'ddkate');
                return $custom_prefix . '_' . $randomString;
            case 'custom_text':
                return preg_replace('/[^a-zA-Z0-9]/', '', $custom_text);
            default:
                return 'user' . time();
        }
    }

    /**
     * 💥 تابع جدید 💥
     * برای بررسی اینکه آیا IP درخواست از سمت سرورهای تلگرام است یا خیر.
     */
    public static function isTelegramIP(): bool {
        $telegram_ip_ranges = [
            ['lower' => '149.154.160.0', 'upper' => '149.154.175.255'],
            ['lower' => '91.108.4.0',    'upper' => '91.108.7.255']
        ];
        $ip = self::getClientIP();
        $ip_dec = ip2long($ip);
        if ($ip_dec === false) return false;

        foreach ($telegram_ip_ranges as $range) {
            if ($ip_dec >= ip2long($range['lower']) && $ip_dec <= ip2long($range['upper'])) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * 💥 تابع جدید 💥
     * برای دریافت IP واقعی کاربر، حتی اگر پشت پراکسی باشد.
     */
    public static function getClientIP(): string {
        return $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
?>
```php
<?php
// =========================================================================================
// 💥 فایل جدید 💥
// File: src/Models/Model.php
// Path: /src/Models/Model.php
// Description: 🏛️ این یک کلاس پایه برای تمام مدل‌های دیگر است. توابع عمومی کار با
// دیتابیس در اینجا قرار می‌گیرند تا از تکرار کد جلوگیری شود.
// =========================================================================================

namespace Ddkate\Models;

use Ddkate\Utils\Database;
use PDO;

abstract class Model {
    protected static string $tableName;
    protected static string $primaryKey = 'id';

    /**
     * یک رکورد را بر اساس کلید اصلی پیدا می‌کند.
     */
    public static function find(int $id): ?array {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM " . static::$tableName . " WHERE " . static::$primaryKey . " = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * تمام رکوردهای یک جدول را برمی‌گرداند.
     */
    public static function all(): array {
        $db = Database::getInstance();
        $stmt = $db->query("SELECT * FROM " . static::$tableName);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * یک فیلد خاص از یک رکورد را آپدیت می‌کند.
     * این تابع جایگزین تابع update قدیمی شماست.
     */
    public static function update(int $id, string $field, $value): bool {
        $db = Database::getInstance();
        // برای جلوگیری از SQL Injection نام فیلد را پاکسازی می‌کنیم
        $safe_field = preg_replace('/[^a-zA-Z0-9_]/', '', $field);
        $stmt = $db->prepare("UPDATE " . static::$tableName . " SET {$safe_field} = ? WHERE " . static::$primaryKey . " = ?");
        return $stmt->execute([$value, $id]);
    }

    // می‌توانید توابع دیگری مانند delete, create و... را نیز به این کلاس اضافه کنید.
}
?>
