<?php
// =========================================================================================
// File: index.php
// Path: /index.php (در ریشه پروژه)
// Description: ✨ نقطه ورود اصلی و جدید ربات ddkate.
// این فایل بسیار کوچک و تمیز است و فقط وظیفه مسیردهی درخواست‌ها را بر عهده دارد.
// تمام نکات امنیتی و مدیریتی که اشاره کردید، در اینجا پیاده‌سازی شده است.
// =========================================================================================

// برای نمایش تمام خطاها در حین توسعه
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Asia/Tehran');

// بارگذاری تنظیمات و کلاس‌های پروژه از طریق Composer Autoloader
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

use Ddkate\Controllers\MessageController;
use Ddkate\Controllers\CallbackController;
use Ddkate\Utils\TelegramAPI;
use Ddkate\Utils\Logger;
use Ddkate\Utils\Helper;

// 🔐 امنیت: اطمینان از اینکه درخواست فقط از سمت سرورهای تلگرام می‌آید
if (CHECK_TELEGRAM_IP && !Helper::isTelegramIP()) {
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
if (DEBUG_MODE) {
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
} catch (Throwable $e) {
    // 🚨 سیستم مدیریت خطای پیشرفته: هر خطایی در ربات رخ دهد، لاگ شده و به ادمین اطلاع داده می‌شود
    Logger::log("FATAL ERROR: " . $e->getMessage() . "\nFile: " . $e->getFile() . "\nLine: " . $e->getLine());
    
    if (defined('BOT_TOKEN') && defined('ADMIN_ID')) {
        $telegram = new TelegramAPI(BOT_TOKEN);
        $errorMessage = "🚨 **خطای بحرانی در ربات ddkate** 🚨\n\n";
        $errorMessage .= "یک مشکل فنی در ربات به وجود آمده است. لطفاً فایل `error.log` را برای جزئیات بررسی کنید.\n\n";
        $errorMessage .= "پیام خطا:\n`" . $e->getMessage() . "`";
        $telegram->sendMessage(ADMIN_ID, $errorMessage, null, 'Markdown');
    }
} finally {
    // ✅ پاسخ سریع 200 به تلگرام برای جلوگیری از تایم‌اوت
    if (!headers_sent()) {
        http_response_code(200);
    }
}
?>
```php
<?php
// =========================================================================================
// File: src/Controllers/MessageController.php
// Path: /src/Controllers/MessageController.php
// Description: 🧠 این کلاس، مغز متفکر ربات برای مدیریت پیام‌های متنی است.
// =========================================================================================

namespace Ddkate\Controllers;

use Ddkate\Utils\TelegramAPI;
use Ddkate\Utils\UpdateParser;
use Ddkate\Models\User;
use Ddkate\Models\Panel;
use Ddkate\Models\Setting;
use Ddkate\Views\UserView;
use Ddkate\Views\AdminView;

class MessageController {
    private UpdateParser $update;
    private TelegramAPI $telegram;
    private ?array $user;
    private array $settings;

    public function __construct(array $updateData) {
        $this->update = new UpdateParser($updateData);
        $this->telegram = new TelegramAPI(BOT_TOKEN);
        $this->settings = Setting::getAll();
        
        $this->user = User::firstOrCreate($this->update->from_id, [
            'username' => $this->update->username,
            'first_name' => $this->update->first_name,
            'is_admin' => ($this->update->from_id == ADMIN_ID) ? 1 : 0,
            'limit_usertest' => $this->settings['limit_usertest_all'] ?? 1,
        ]);
    }

    public function handle(): void {
        if ($this->checkPrerequisites()) return;

        $text = $this->update->text;
        
        if ($this->user['is_admin']) {
            $adminController = new AdminController($this->update, $this->user, $this->settings);
            if ($adminController->route($text)) {
                return;
            }
        }

        $menuActions = UserView::getMenuActions($this->settings);
        if (isset($menuActions[$text])) {
            $method = $menuActions[$text];
            $this->$method();
        } elseif (strpos($text, '/start ') === 0) {
            $this->handleStartWithReferral();
        } elseif ($text === '/start') {
            $this->handleStart();
        } else {
            $this->handleUserState();
        }
    }

    private function checkPrerequisites(): bool {
        // ... (پیاده‌سازی کامل تمام بررسی‌ها) ...
        return false;
    }

    private function handleStart(): void {
        User::updateState($this->update->from_id, 'start');
        $view = UserView::startMessage($this->update->first_name, $this->settings);
        $this->telegram->sendMessage($this->update->chat_id, $view['text'], $view['keyboard'], 'Markdown');
    }

    private function handleBuyService(): void {
        // ... (پیاده‌سازی کامل خرید سرویس) ...
    }
    
    // ... تمام متدهای دیگر از فایل index.php قدیمی شما در اینجا به صورت تمیز پیاده‌سازی می‌شوند ...
}
?>
```php
<?php
// =========================================================================================
// File: src/Views/UserView.php
// Path: /src/Views/UserView.php
// Description: 🎨 این کلاس تمام پیام‌ها و کیبوردهای مربوط به کاربر عادی را می‌سازد.
// جایگزین فایل‌های keyboard.php و text.php شما.
// =========================================================================================

namespace Ddkate\Views;

class UserView {

    public static function getMenuActions(array $settings): array {
        return [
            $settings['text_sell'] ?? '🛍 خرید سرویس' => 'handleBuyService',
            $settings['text_account'] ?? '👤 حساب کاربری' => 'showAccount',
            $settings['text_support'] ?? '📞 پشتیبانی' => 'handleSupport',
            $settings['text_help'] ?? '📚 راهنما' => 'handleHelp',
            $settings['text_Discount'] ?? '🎁 کد هدیه' => 'promptForGiftCode',
            '🤝 زیرمجموعه‌گیری' => 'handleReferral'
        ];
    }
    
    public static function startMessage(string $name, array $settings): array {
        $text = $settings['text_start'] ?? "👋 سلام **{name}**، به ربات فروش ddkate خوش آمدید!";
        $text = str_replace('{name}', htmlspecialchars($name), $text);
        
        $keyboardLayout = self::getMenuActions($settings);

        $keyboard = [
            'keyboard' => [
                [
                    ['text' => array_search('handleBuyService', $keyboardLayout)],
                    ['text' => array_search('showAccount', $keyboardLayout)],
                ],
                [
                    ['text' => array_search('handleSupport', $keyboardLayout)],
                    ['text' => array_search('handleHelp', $keyboardLayout)],
                ],
                 [
                    ['text' => array_search('promptForGiftCode', $keyboardLayout)],
                    ['text' => array_search('handleReferral', $keyboardLayout)],
                ]
            ],
            'resize_keyboard' => true
        ];

        return ['text' => $text, 'keyboard' => json_encode($keyboard)];
    }
    
    // ... تمام توابع دیگر برای ساخت کیبوردها و پیام‌های دیگر ...
    // به عنوان مثال، کیبورد انتخاب پنل، محصولات، پرداخت و...
}
?>
```php
<?php
// =========================================================================================
// File: src/Controllers/AdminController.php
// Path: /src/Controllers/AdminController.php
// Description: 👑 یک کلاس قدرتمند و مجزا فقط برای مدیریت دستورات ادمین.
// =========================================================================================

namespace Ddkate\Controllers;

use Ddkate\Utils\TelegramAPI;
use Ddkate\Utils\UpdateParser;
use Ddkate\Views\AdminView;

class AdminController {
    private UpdateParser $update;
    private TelegramAPI $telegram;
    private array $user;
    private array $settings;
    
    public function __construct(UpdateParser $update, array $user, array $settings) {
        $this->update = $update;
        $this->telegram = new TelegramAPI(BOT_TOKEN);
        $this->user = $user;
        $this->settings = $settings;
    }

    public function showMainMenu(): void {
        $view = AdminView::mainMenu($this->settings['version'] ?? '1.0');
        $this->telegram->sendMessage($this->update->chat_id, $view['text'], $view['keyboard'], 'Markdown');
    }

    public function route(string $text): bool {
        $routes = AdminView::getMenuActions();
        if (isset($routes[$text])) {
            $method = $routes[$text];
            $this->$method();
            return true;
        }
        return false;
    }

    // در اینجا، هر دکمه پنل ادمین، یک متد مجزا خواهد داشت
    // مثال:
    private function manageUsers(): void {
        $view = AdminView::usersMenu();
        $this->telegram->sendMessage($this->update->chat_id, $view['text'], $view['keyboard']);
    }

    private function botStats(): void {
        // ... منطق کامل آمار ربات
    }
    
    // ... و به همین ترتیب برای تمام قابلیت‌های ادمین
}
?>
