<?php
// =========================================================================================
// File: run_cron.php
// Path: /run_cron.php (در ریشه پروژه)
// Description: این فایل اجرایی کرون جاب شماست که باید روی سرور زمان‌بندی شود.
// این نسخه به‌روز شده است تا هر دو وظیفه (پاکسازی تست و ارسال هشدار) را مدیریت کند.
// =========================================================================================

// جلوگیری از اجرای مستقیم از طریق مرورگر
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/Controllers/CronController.php';

echo "Cron Runner Started: " . date('Y-m-d H:i:s') . "\n";

try {
    $cronController = new Ddkate\Controllers\CronController();
    
    // وظیفه اول: پاکسازی اکانت‌های تست غیرفعال (اجرا در هر بار)
    echo "Task: Cleaning up inactive test accounts...\n";
    $testResult = $cronController->cleanupInactiveTestAccounts();
    echo "Result: Checked {$testResult['checked']} test accounts. Removed {$testResult['removed']}.\n";
    
    // وظیفه دوم: ارسال هشدارهای انقضا (فقط یک بار در روز اجرا می‌شود)
    // شما می‌توانید این را برای هر ساعت یا هر زمان دیگری تنظیم کنید.
    echo "Task: Sending expiration warnings...\n";
    $warningResult = $cronController->sendExpirationWarnings();
    echo "Result: Checked {$warningResult['checked']} active services. Sent {$warningResult['notifications_sent']} warnings. Deactivated {$warningResult['deactivated']} expired services.\n";

} catch (Exception $e) {
    // ثبت هرگونه خطا در فایل لاگ برای بررسی‌های بعدی
    error_log("Cron Runner Failed: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}

echo "Cron Runner Finished: " . date('Y-m-d H:i:s') . "\n";
?>
```
```php
<?php
// =========================================================================================
// File: src/Controllers/CronController.php
// Path: /src/Controllers/CronController.php
// Description: این نسخه به‌روز شده‌ی کنترلر کرون جاب است که منطق فایل Cron_Daily.php
// شما به صورت یک متد جدید و بهینه به آن اضافه شده است.
// =========================================================================================

namespace Ddkate\Controllers;

use Ddkate\Utils\Database;
use Ddkate\Utils\TelegramAPI;
use Ddkate\Panels\MarzbanPanel;
use Ddkate\Panels\SanaeiPanel;
use Ddkate\Panels\PanelInterface; // اطمینان از وجود اینترفیس

class CronController {
    private \PDO $db;
    private TelegramAPI $telegram;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->telegram = new TelegramAPI(BOT_TOKEN);
    }

    /**
     * متد جدید: برای ارسال هشدارهای انقضا (حجم و زمان) و غیرفعال کردن سرویس‌های منقضی.
     * این متد جایگزین کامل فایل Cron_Daily.php شماست.
     */
    public function sendExpirationWarnings(): array {
        $stmt = $this->db->prepare("
            SELECT i.id as invoice_id, i.status as invoice_status, i.panel_user_id, 
                   u.chat_id, 
                   pr.name as product_name,
                   p.id as panel_id, p.type as panel_type, p.url, p.username as panel_user, p.password as panel_pass
            FROM invoices i
            JOIN users u ON i.user_id = u.id
            JOIN products pr ON i.product_id = pr.id
            JOIN panels p ON pr.panel_id = p.id
            WHERE i.status = 'active'
        ");
        $stmt->execute();
        $activeServices = $stmt->fetchAll();

        $notificationCount = 0;
        $deactivatedCount = 0;

        foreach ($activeServices as $service) {
            try {
                $panel = $this->getPanelInstance($service['panel_type'], $service['url'], $service['panel_user'], $service['panel_pass']);
                if (!$panel) continue;

                $panelUser = $panel->getUser($service['panel_user_id']);

                if (!$panelUser || !isset($panelUser['status'])) {
                    // اگر کاربر در پنل وجود نداشت، سرویس را در دیتابیس غیرفعال می‌کنیم
                    $this->deactivateService($service['invoice_id'], 'not_found_on_panel');
                    continue;
                }
                
                // 1. بررسی وضعیت کاربر در پنل
                if ($panelUser['status'] !== 'active') {
                    $this->deactivateService($service['invoice_id'], $panelUser['status']);
                    $deactivatedCount++;
                    continue;
                }

                // 2. ارسال هشدار نزدیک بودن به اتمام حجم (کمتر از 1 گیگ)
                $data_limit = $panelUser['data_limit'] ?? 0;
                $used_traffic = $panelUser['used_traffic'] ?? 0;
                $remaining_bytes = $data_limit - $used_traffic;
                
                if ($data_limit > 0 && $remaining_bytes <= 1073741824) { // 1 GB
                    $remaining_volume_str = \Ddkate\Utils\Helper::formatBytes($remaining_bytes);
                    $message = "کاربر گرامی، از حجم سرویس شما فقط **{$remaining_volume_str}** باقی مانده است.\n\nنام کاربری: `{$service['panel_user_id']}`\nنام سرویس: {$service['product_name']}";
                    $this->telegram->sendMessage($service['chat_id'], $message, null, 'Markdown');
                    $notificationCount++;
                }
                
                // 3. ارسال هشدار نزدیک بودن به اتمام زمان (کمتر از 2 روز)
                $expire_timestamp = $panelUser['expire'] ?? 0;
                if ($expire_timestamp > 0) {
                    $remaining_seconds = $expire_timestamp - time();
                    if ($remaining_seconds > 0 && $remaining_seconds <= 172800) { // 48 hours
                        $remaining_days = floor($remaining_seconds / 86400) + 1;
                        $message = "کاربر گرامی، فقط **{$remaining_days} روز** از اعتبار سرویس شما باقی مانده است. لطفاً جهت تمدید اقدام نمایید.\n\nنام کاربری: `{$service['panel_user_id']}`";
                        $this->telegram->sendMessage($service['chat_id'], $message, null, 'Markdown');
                        $notificationCount++;
                    }
                }

            } catch (\Exception $e) {
                error_log("Cron Warning Error for invoice {$service['invoice_id']}: " . $e->getMessage());
                continue;
            }
        }
        
        return [
            'checked' => count($activeServices), 
            'notifications_sent' => $notificationCount,
            'deactivated' => $deactivatedCount
        ];
    }
    
    /**
     * این متد سرویس را در دیتابیس ربات غیرفعال (منقضی) می‌کند.
     */
    private function deactivateService(int $invoice_id, string $reason): void {
        $stmt = $this->db->prepare("UPDATE invoices SET status = ? WHERE id = ?");
        $stmt->execute([$reason, $invoice_id]);
    }
    
    /**
     * این متد از پاسخ قبلی است و برای پاکسازی اکانت‌های تست استفاده می‌شود.
     */
    public function cleanupInactiveTestAccounts(): array {
        // ... (کد این متد از پاسخ قبلی در اینجا قرار می‌گیرد)
        return ['checked' => 0, 'removed' => 0]; // به عنوان نمونه
    }

    /**
     * بر اساس نوع پنل، یک نمونه از کلاس مربوطه را برمی‌گرداند.
     */
    private function getPanelInstance(string $type, string $url, string $username, string $password): ?PanelInterface {
        try {
            switch ($type) {
                case 'marzban':
                    return new MarzbanPanel($url, $username, $password);
                case 'sanaei':
                    return new SanaeiPanel($url, $username, $password);
                default:
                    return null;
            }
        } catch (\Exception $e) {
            error_log("Failed to create panel instance: " . $e->getMessage());
            return null;
        }
    }
}
?>
