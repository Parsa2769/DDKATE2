<?php
// =========================================================================================
// File: run_cron.php
// Path: /run_cron.php (در ریشه پروژه)
// Description: این فایل، اسکریپت اجرایی کرون جاب شماست. شما باید در هاست یا سرور خود
// یک کرون جاب تنظیم کنید که هر 5 دقیقه این فایل را اجرا کند.
// مثال دستور کرون جاب: */5 * * * * /usr/bin/php /path/to/your/project/run_cron.php
// =========================================================================================

// جلوگیری از اجرای مستقیم از طریق مرورگر
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/Controllers/CronController.php';

echo "Cron job started at " . date('Y-m-d H:i:s') . "\n";

try {
    // یک نمونه از کنترلر کرون جاب می‌سازیم
    $cronController = new Ddkate\Controllers\CronController();
    
    // متد مربوط به پاکسازی کاربران تست منقضی شده را اجرا می‌کنیم
    echo "Cleaning up inactive test accounts...\n";
    $result = $cronController->cleanupInactiveTestAccounts();
    echo "Found {$result['checked']} test accounts. {$result['removed']} were removed.\n";
    
    // می‌توانید در آینده متدهای دیگری برای کارهای دیگر (مثل ارسال گزارش روزانه) به این فایل اضافه کنید
    // echo "Sending daily reports...\n";
    // $cronController->sendDailyReports();
    
} catch (Exception $e) {
    // ثبت هرگونه خطا در فایل لاگ برای بررسی‌های بعدی
    error_log("Cron job failed: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}

echo "Cron job finished at " . date('Y-m-d H:i:s') . "\n";
?>
```php
<?php
// =========================================================================================
// File: src/Controllers/CronController.php
// Path: /src/Controllers/CronController.php
// Description: این کلاس قلب تپنده کرون جاب شماست. تمام منطق‌های پیچیده مانند
// بررسی کاربران، اتصال به پنل‌ها و ارسال پیام در اینجا به صورت تمیز پیاده‌سازی شده است.
// =========================================================================================

namespace Ddkate\Controllers;

use Ddkate\Utils\Database;
use Ddkate\Utils\TelegramAPI;
use Ddkate\Panels\MarzbanPanel;
use Ddkate\Panels\SanaeiPanel;
// در صورت افزودن پنل‌های دیگر، آن‌ها را نیز اینجا اضافه کنید

class CronController {
    private \PDO $db;
    private TelegramAPI $telegram;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->telegram = new TelegramAPI(BOT_TOKEN);
    }

    /**
     * کاربران تست غیرفعال (منقضی شده یا تمام شده) را پیدا کرده،
     * به آن‌ها اطلاع‌رسانی می‌کند و سپس از پنل حذف می‌کند.
     * @return array گزارشی از تعداد کاربران بررسی شده و حذف شده.
     */
    public function cleanupInactiveTestAccounts(): array {
        // در دیتابیس جدید، ما یک جدول TestAccount جدا نداریم، بلکه سرویس‌های تست را در جدول invoices با نوع 'test' مشخص می‌کنیم
        $stmt = $this->db->prepare("
            SELECT i.id as invoice_id, i.panel_user_id, u.chat_id, p.type as panel_type, p.url, p.username as panel_user, p.password as panel_pass
            FROM invoices i
            JOIN users u ON i.user_id = u.id
            JOIN products pr ON i.product_id = pr.id
            JOIN panels p ON pr.panel_id = p.id
            WHERE pr.is_test_account = 1 AND i.status = 'active'
        ");
        $stmt->execute();
        $testAccounts = $stmt->fetchAll();
        
        $removedCount = 0;
        foreach ($testAccounts as $account) {
            try {
                // ساخت نمونه از کلاس پنل مربوطه
                $panel = $this->getPanelInstance($account['panel_type'], $account['url'], $account['panel_user'], $account['panel_pass']);
                if (!$panel) continue;

                $panelUser = $panel->getUser($account['panel_user_id']);

                // اگر کاربر در پنل وجود نداشت یا وضعیتش دیگر فعال نبود
                if (!$panelUser || (isset($panelUser['status']) && $panelUser['status'] !== 'active')) {
                    $status = $panelUser['status'] ?? 'not_found';
                    
                    // ارسال پیام به کاربر
                    $this->notifyUserOfTestExpiration($account['chat_id'], $account['panel_user_id'], $status);
                    
                    // حذف کاربر از پنل
                    $panel->deleteUser($account['panel_user_id']);
                    
                    // آپدیت وضعیت در دیتابیس ربات
                    $updateStmt = $this->db->prepare("UPDATE invoices SET status = ? WHERE id = ?");
                    $updateStmt->execute(['expired_test', $account['invoice_id']]);
                    
                    $removedCount++;
                }
            } catch (\Exception $e) {
                error_log("Cron Error for user {$account['panel_user_id']}: " . $e->getMessage());
                continue;
            }
        }

        return ['checked' => count($testAccounts), 'removed' => $removedCount];
    }

    /**
     * بر اساس نوع پنل، یک نمونه از کلاس مربوطه را برمی‌گرداند.
     */
    private function getPanelInstance(string $type, string $url, string $username, string $password): ?PanelInterface {
        switch ($type) {
            case 'marzban':
                return new MarzbanPanel($url, $username, $password);
            case 'sanaei':
                return new SanaeiPanel($url, $username, $password);
            // case 'alireza':
            //     return new AlirezaPanel($url, $username, $password);
            default:
                return null;
        }
    }

    /**
     * پیام مناسب را برای اطلاع‌رسانی به کاربر ارسال می‌کند.
     */
    private function notifyUserOfTestExpiration(int $chat_id, string $panel_user_id, string $reason): void {
        $message = "کاربر گرامی {$panel_user_id} 👋\n";
        
        if ($reason === 'expired') {
            $message .= "🕒 زمان اکانت تست شما به پایان رسید.";
        } elseif ($reason === 'limited') {
            $message .= "📦 حجم اکانت تست شما به پایان رسید.";
        } else {
            $message .= "❗️اکانت تست شما غیرفعال شد.";
        }
        
        $message .= "\n\nدر صورت رضایت از کیفیت سرویس، می‌توانید از طریق دکمه زیر نسبت به 🛍 خرید اشتراک اقدام فرمایید.";
        
        $keyboard = json_encode([
            'inline_keyboard' => [
                [['text' => '🛍 خرید اشتراک دائمی', 'callback_data' => 'buy_service']]
            ]
        ]);
        
        $this->telegram->sendMessage($chat_id, $message, $keyboard);
    }
}
?>
