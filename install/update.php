<?php
// =========================================================================================
// File: src/Controllers/AdminController.php (متد جدید برای آپدیت)
// Path: /src/Controllers/AdminController.php
// Description: این متد جدید به کلاس کنترلر ادمین شما اضافه خواهد شد.
// این متد جایگزین کامل فایل update.php قدیمی است.
// =========================================================================================

namespace Ddkate\Controllers;

// ... (use statements for other classes)
use Ddkate\Utils\Logger;
use Ddkate\Utils\Helper;
use ZipArchive;

class AdminController {
    // ... (properties and constructor from previous steps)

    /**
     * 🚀 شروع فرآیند آپدیت ربات از ریپازیتوری گیت‌هاب.
     * این متد جایگزین کامل فایل update.php است.
     */
    public function handleUpdate(): void {
        $this->telegram->sendMessage($this->update->chat_id, "⏳ در حال بررسی برای نسخه جدید... لطفاً صبور باشید.");

        try {
            // آدرس ریپازیتوری شما از تنظیمات خوانده می‌شود
            $repoUrl = $this->settings['github_repo_url'] ?? 'Parsa2769/DDKATE2';
            $apiUrl = "https://api.github.com/repos/{$repoUrl}/releases/latest";
            
            $latestRelease = Helper::sendGitHubRequest($apiUrl);
            if (!$latestRelease || !isset($latestRelease['tag_name'])) {
                throw new \Exception("امکان دریافت اطلاعات آخرین نسخه از گیت‌هاب وجود ندارد.");
            }

            $latestVersion = $latestRelease['tag_name'];
            $currentVersion = $this->settings['version'] ?? '1.0';

            if (version_compare($currentVersion, $latestVersion, '>=')) {
                $this->telegram->sendMessage($this->update->chat_id, "✅ ربات شما به آخرین نسخه (`{$currentVersion}`) مجهز است و نیازی به آپدیت ندارد.");
                return;
            }

            $this->telegram->sendMessage($this->update->chat_id, "✅ نسخه جدید `{$latestVersion}` یافت شد! شروع فرآیند آپدیت...");

            // مرحله ۱: دانلود فایل فشرده
            $zipUrl = $latestRelease['zipball_url'];
            $zipPath = __DIR__ . '/../../update.zip';
            $downloaded = Helper::downloadFile($zipUrl, $zipPath);
            if (!$downloaded) {
                throw new \Exception("خطا در دانلود فایل آپدیت.");
            }
            $this->telegram->sendMessage($this->update->chat_id, "📥 فایل آپدیت با موفقیت دانلود شد.");

            // مرحله ۲: استخراج فایل فشرده
            $extractPath = __DIR__ . '/../../update_temp/';
            $zip = new ZipArchive;
            if ($zip->open($zipPath) === TRUE) {
                $zip->extractTo($extractPath);
                $zip->close();
                $this->telegram->sendMessage($this->update->chat_id, "📦 فایل‌ها با موفقیت از حالت فشرده خارج شدند.");
            } else {
                throw new \Exception("خطا در استخراج فایل فشرده.");
            }
            unlink($zipPath); // حذف فایل زیپ پس از استخراج

            // مرحله ۳: جابجایی فایل‌های جدید
            // اولین پوشه داخل فایل زیپ معمولا نام ریپازیتوری و تگ نسخه را دارد
            $unzippedFolderName = scandir($extractPath)[2]; // Get the actual folder name
            $sourceDir = $extractPath . $unzippedFolderName . '/';
            $destinationDir = __DIR__ . '/../../';

            Helper::moveFiles($sourceDir, $destinationDir, ['config.php', 'logs/']); // فایل کانفیگ و لاگ‌ها را نگه می‌داریم
            $this->telegram->sendMessage($this->update->chat_id, "🚚 فایل‌های جدید با موفقیت جایگزین شدند.");
            
            // مرحله ۴: آپدیت شماره نسخه در دیتابیس
            Setting::set('version', $latestVersion);

            // پاکسازی پوشه موقت
            Helper::deleteDirectory($extractPath);

            $this->telegram->sendMessage($this->update->chat_id, "🎉 **آپدیت با موفقیت کامل شد!**\nربات شما اکنون به نسخه **{$latestVersion}** ارتقا یافته است.");

        } catch (\Exception $e) {
            Logger::log("Update Failed: " . $e->getMessage());
            $this->telegram->sendMessage($this->update->chat_id, "❌ **عملیات آپدیت ناموفق بود!**\n\n**دلیل خطا:**\n" . $e->getMessage());
        }
    }
}
?>
```php
<?php
// =========================================================================================
// ❗️❗️ متدهای جدید برای فایل Helper.php ❗️❗️
// File: src/Utils/Helper.php
// Path: /src/Utils/Helper.php
// Description: توابع کمکی جدید برای فرآیند آپدیت به این کلاس اضافه شده‌اند.
// =========================================================================================

namespace Ddkate\Utils;

class Helper {
    // ... (توابع قبلی مانند formatBytes, generateUUID, isTelegramIP و...)
    
    /**
     * 💥 تابع جدید 💥
     * برای ارسال درخواست به API گیت‌هاب.
     */
    public static function sendGitHubRequest(string $url): ?array {
        $headers = [
            'User-Agent: ddkate-Bot-Updater',
            'Accept: application/vnd.github.v3+json'
        ];
        return CurlHelper::sendRequest($url, 'GET', [], $headers);
    }
    
    /**
     * 💥 تابع جدید 💥
     * برای دانلود یک فایل از یک URL.
     */
    public static function downloadFile(string $url, string $destination): bool {
        $options = [
            CURLOPT_FILE => is_resource($destination) ? $destination : fopen($destination, 'w'),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_URL => $url,
            CURLOPT_FAILONERROR => true,
            CURLOPT_USERAGENT => 'ddkate-Bot-Updater'
        ];

        $ch = curl_init();
        curl_setopt_array($ch, $options);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    /**
     * 💥 تابع جدید 💥
     * برای جابجایی فایل‌ها از یک پوشه به پوشه دیگر.
     */
    public static function moveFiles(string $source, string $destination, array $exclude = []): void {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $targetPath = $destination . '/' . $files->getSubPathName();
            $shouldExclude = false;
            foreach ($exclude as $excludePath) {
                if (strpos($targetPath, $excludePath) !== false) {
                    $shouldExclude = true;
                    break;
                }
            }
            if ($shouldExclude) continue;

            if ($file->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
            } else {
                rename($file, $targetPath);
            }
        }
    }

    /**
     * 💥 تابع جدید 💥
     * برای حذف کامل یک پوشه و محتویات آن.
     */
    public static function deleteDirectory(string $dir): bool {
        if (!is_dir($dir)) return false;
        
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()){
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        return rmdir($dir);
    }
}
?>
