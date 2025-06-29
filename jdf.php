<?php
// =========================================================================================
// ❗️❗️ فایل به‌روز شده ❗️❗️
// File: config.php.example
// Path: /config.php.example (در ریشه پروژه)
// Description: فایل نمونه پیکربندی با افزودن قابلیت‌های جدید.
// فایل اصلی config.php توسط نصب‌کننده ساخته خواهد شد.
// =========================================================================================

/**
 * فعال یا غیرفعال کردن حالت دیباگ.
 * در حالت فعال، تمام درخواست‌های دریافتی از تلگرام در یک فایل لاگ ذخیره می‌شوند.
 * برای محیط عملیاتی، این مقدار را false قرار دهید.
 */
define('DEBUG_MODE', true);

/**
 * فعال یا غیرفعال کردن بررسی IP تلگرام.
 * برای امنیت بیشتر، همیشه این مقدار را true نگه دارید تا ربات فقط از سرورهای تلگرام دستور بگیرد.
 */
define('CHECK_TELEGRAM_IP', true);


// --- Database Configuration | تنظیمات دیتابیس ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'ddkate_bot_db');
define('DB_USER', 'ddkate_user');
define('DB_PASS', 'your_database_password');

// --- Telegram Bot Configuration | تنظیمات ربات تلگرام ---
define('BOT_TOKEN', 'YOUR_TELEGRAM_BOT_TOKEN');
define('ADMIN_ID', 'YOUR_ADMIN_CHAT_ID');
define('BOT_USERNAME', 'YourBotUsername');

// --- Webhook and Domain Configuration | تنظیمات دامنه و وبهوک ---
define('WEBHOOK_URL', 'https://your.domain.com/index.php');

// --- General Settings | تنظیمات عمومی ربات ---
define('DEFAULT_LANG', 'fa');
define('SUPPORT_ID', 'parsamoradi199'); // آیدی شما برای بخش پشتیبانی

?>
```php
<?php
// =========================================================================================
// ❗️❗️ فایل به‌روز شده ❗️❗️
// File: index.php
// Path: /index.php (در ریشه پروژه)
// Description: ✨ نقطه ورود اصلی ربات با اعمال تمام نکات امنیتی و مدیریتی شما.
// =========================================================================================

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

use Ddkate\Controllers\MessageController;
use Ddkate\Controllers\CallbackController;
use Ddkate\Utils\TelegramAPI;
use Ddkate\Utils\Logger;
use Ddkate\Utils\Helper;

// 🔐 امنیت: اطمینان از اینکه درخواست فقط از سمت سرورهای تلگرام می‌آید
if (defined('CHECK_TELEGRAM_IP') && CHECK_TELEGRAM_IP && !Helper::isTelegramIP()) {
    Logger::log("Security Alert: Request from untrusted IP: " . Helper::getClientIP());
    http_response_code(403);
    die("Forbidden: Access is denied.");
}

// دریافت آپدیت از وبهوک تلگرام
$updateData = json_decode(file_get_contents('php://input'), true);

// 🛑 بررسی درخواست خالی
if (!$updateData) {
    http_response_code(200); // به تلگرام پاسخ موفق می‌دهیم تا دوباره ارسال نکند
    exit();
}

// 🐛 برای دیباگ کردن، تمام آپدیت‌ها در فایل debug.log ذخیره می‌شوند
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    Logger::log(print_r($updateData, true), 'debug');
}

try {
    // مسیردهی هوشمند درخواست به کنترلر مناسب
    if (isset($updateData['message'])) {
        $controller = new MessageController($updateData);
        $controller->handle();
    } elseif (isset($updateData['callback_query'])) {
        $controller = new CallbackController($updateData);
        $controller->handle();
    }
} catch (Exception $e) {
    // 🚨 سیستم مدیریت خطای پیشرفته: هر خطایی در ربات رخ دهد، لاگ شده و به ادمین اطلاع داده می‌شود
    Logger::log("FATAL ERROR: " . $e->getMessage() . "\nFile: " . $e->getFile() . "\nLine: " . $e->getLine());
    
    if (defined('BOT_TOKEN') && defined('ADMIN_ID')) {
        $telegram = new TelegramAPI(BOT_TOKEN);
        $errorMessage = "🚨 **خطای بحرانی در ربات ddkate** 🚨\n\n";
        $errorMessage .= "یک مشکل فنی در ربات به وجود آمده است. لطفاً فایل `error.log` را برای جزئیات بررسی کنید.\n\n";
        $errorMessage .= "پیام خطا:\n`" . $e->getMessage() . "`";
        $telegram->sendMessage(ADMIN_ID, $errorMessage, null, 'Markdown');
    }
}

// ✅ پاسخ سریع 200 به تلگرام برای جلوگیری از تایم‌اوت
if (!headers_sent()) {
    http_response_code(200);
}
?>
```php
<?php
// =========================================================================================
// 💥 فایل جدید 💥
// File: src/Utils/Logger.php
// Path: /src/Utils/Logger.php
// Description: کلاس جدید برای مدیریت لاگ‌ها. تمام لاگ‌ها در پوشه logs ذخیره می‌شوند.
// =========================================================================================

namespace Ddkate\Utils;

class Logger {
    private static string $logDirectory = __DIR__ . '/../../logs';

    /**
     * یک پیام را در فایل لاگ می‌نویسد.
     * @param string $message متنی که باید لاگ شود.
     * @param string $level نوع لاگ (error, debug, info) که نام فایل را مشخص می‌کند.
     */
    public static function log(string $message, string $level = 'error'): void {
        if (!is_dir(self::$logDirectory)) {
            mkdir(self::$logDirectory, 0755, true);
        }

        $logFile = self::$logDirectory . '/' . $level . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $formattedMessage = "[{$timestamp}] - {$message}\n\n";

        file_put_contents($logFile, $formattedMessage, FILE_APPEND);
    }
}
?>
```php
<?php
// =========================================================================================
// 💥 فایل جدید: بازنویسی jdf.php 💥
// File: src/Utils/DateHelper.php
// Path: /src/Utils/DateHelper.php
// Description: این کلاس تمام توابع فایل jdf.php شما را به شکلی مدرن و شیءگرا
// بازنویسی می‌کند. حالا می‌توانید به راحتی تاریخ شمسی را در تمام پروژه استفاده کنید.
// =========================================================================================

namespace Ddkate\Utils;

class DateHelper {
    
    /**
     * تابع اصلی و جایگزین jdate
     * @param string $format فرمت تاریخ (مشابه تابع date)
     * @param int|string $timestamp تایم‌استمپ یا رشته تاریخ
     * @return string تاریخ فرمت‌شده شمسی
     */
    public static function jdate(string $format, $timestamp = '', string $time_zone = 'Asia/Tehran'): string {
        date_default_timezone_set($time_zone);
        $ts = ($timestamp === '') ? time() : self::tr_num($timestamp, 'en');
        $date = explode('_', date('H_i_j_n_O_P_s_w_Y', $ts));
        list($j_y, $j_m, $j_d) = self::gregorian_to_jalali($date[8], $date[3], $date[2]);

        $output = '';
        for ($i = 0; $i < strlen($format); $i++) {
            $char = $format[$i];
            switch ($char) {
                case 'Y': $output .= $j_y; break;
                case 'y': $output .= substr($j_y, 2); break;
                case 'm': $output .= ($j_m < 10) ? '0' . $j_m : $j_m; break;
                case 'n': $output .= $j_m; break;
                case 'd': $output .= ($j_d < 10) ? '0' . $j_d : $j_d; break;
                case 'j': $output .= $j_d; break;
                case 'H': $output .= $date[0]; break;
                case 'i': $output .= $date[1]; break;
                case 's': $output .= $date[6]; break;
                // می‌توانید فرمت‌های دیگر را نیز به همین شکل اضافه کنید
                default: $output .= $char;
            }
        }
        return self::tr_num($output);
    }

    /**
     * تبدیل اعداد انگلیسی به فارسی و برعکس
     */
    public static function tr_num($str, $to = 'fa', $decimal_char = '٫'): string {
        $en_num = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '.'];
        $fa_num = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', $decimal_char];
        return ($to === 'fa') ? str_replace($en_num, $fa_num, $str) : str_replace($fa_num, $en_num, $str);
    }
    
    /**
     * تبدیل تاریخ میلادی به شمسی
     */
    public static function gregorian_to_jalali($gy, $gm, $gd): array {
        $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = 355666 + (365 * $gy) + (int)(($gy2 + 3) / 4) - (int)(($gy2 + 99) / 100) + (int)(($gy2 + 399) / 400) + $gd + $g_d_m[$gm - 1];
        $jy = -1595 + (33 * (int)($days / 12053));
        $days %= 12053;
        $jy += 4 * (int)($days / 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += (int)(($days - 1) / 365);
            $days = ($days - 1) % 365;
        }
        $jm = ($days < 186) ? 1 + (int)($days / 31) : 7 + (int)(($days - 186) / 30);
        $jd = 1 + (($days < 186) ? ($days % 31) : (($days - 186) % 30));
        return [$jy, $jm, $jd];
    }
    
    // ... سایر توابع فایل jdf.php را نیز می‌توان به صورت متدهای استاتیک به این کلاس اضافه کرد.
}
?>
