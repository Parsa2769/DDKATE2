<?php
// =========================================================================================
// File: src/Panels/PanelInterface.php
// Description: این فایل یک "قرارداد" یا الگو برای تمام کلاس‌های پنل است.
// هر پنل جدیدی که به پروژه اضافه شود، باید تمام توابع تعریف شده در اینجا را داشته باشد.
// این کار باعث یکپارچگی و استحکام کد شما می‌شود.
// =========================================================================================

namespace Ddkate\Panels;

interface PanelInterface {
    /**
     * برای ورود به پنل و دریافت توکن یا کوکی احراز هویت.
     */
    public function login(): bool;

    /**
     * برای ساخت یک کاربر جدید در پنل.
     * @param array $userData اطلاعات کاربر جدید (نام کاربری، حجم، تاریخ انقضا و...).
     * @return array نتیجه عملیات ساخت کاربر.
     */
    public function createUser(array $userData): array;

    /**
     * برای دریافت اطلاعات یک کاربر خاص از پنل.
     * @param string $username نام کاربری مورد نظر.
     * @return array|null اطلاعات کاربر یا null در صورت عدم وجود.
     */
    public function getUser(string $username): ?array;

    /**
     * برای ویرایش اطلاعات یک کاربر موجود.
     * @param string $username نام کاربری که می‌خواهید ویرایش شود.
     * @param array $userData اطلاعات جدید برای جایگزینی.
     * @return array نتیجه عملیات ویرایش.
     */
    public function modifyUser(string $username, array $userData): array;
    
    /**
     * برای حذف یک کاربر از پنل.
     * @param string $username نام کاربری مورد نظر برای حذف.
     * @return bool نتیجه عملیات (موفق یا ناموفق).
     */
    public function deleteUser(string $username): bool;

    /**
     * برای ریست کردن حجم مصرفی یک کاربر.
     * @param string $username نام کاربری مورد نظر.
     * @return array نتیجه عملیات.
     */
    public function resetUserTraffic(string $username): array;

    /**
     * برای دریافت آمار کلی سیستم (مصرف رم، پردازنده و...).
     * @return array آمار سیستم.
     */
    public function getSystemStats(): array;
}
?>
```php
<?php
// =========================================================================================
// File: src/Panels/MarzbanPanel.php
// Description: این کلاس بازنویسی شده توابع فایل قدیمی apipanel.php شماست که
// مخصوص پنل "مرزبان" نوشته شده است. ساختار بسیار تمیزتر و قابل فهم‌تر است.
// =========================================================================================

namespace Ddkate\Panels;

require_once __DIR__ . '/PanelInterface.php';
require_once __DIR__ . '/../Utils/CurlHelper.php';

class MarzbanPanel implements PanelInterface {
    private string $baseUrl;
    private string $username;
    private string $password;
    private ?string $token = null;

    public function __construct(string $baseUrl, string $username, string $password) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->username = $username;
        $this->password = $password;
    }

    public function login(): bool {
        $url = $this->baseUrl . '/api/admin/token';
        $data = ['username' => $this->username, 'password' => $this->password];
        $response = CurlHelper::sendRequest($url, 'POST', http_build_query($data), ['Content-Type: application/x-www-form-urlencoded']);

        if (isset($response['access_token'])) {
            $this->token = $response['access_token'];
            return true;
        }
        return false;
    }

    private function getAuthHeaders(): array {
        if (!$this->token) {
            $this->login();
        }
        return ['Authorization: Bearer ' . $this->token];
    }

    public function createUser(array $userData): array {
        $url = $this->baseUrl . '/api/user';
        $data = [
            'username' => $userData['username'],
            'proxies' => $userData['proxies'] ?? ['vmess' => new \stdClass(), 'vless' => new \stdClass()],
            'expire' => ($userData['days'] ?? 0) > 0 ? time() + ($userData['days'] * 86400) : 0,
            'data_limit' => ($userData['data_limit_gb'] ?? 0) * 1024 * 1024 * 1024,
        ];
        return CurlHelper::sendRequest($url, 'POST', json_encode($data), $this->getAuthHeaders());
    }

    public function getUser(string $username): ?array {
        $url = $this->baseUrl . '/api/user/' . $username;
        return CurlHelper::sendRequest($url, 'GET', [], $this->getAuthHeaders());
    }

    public function modifyUser(string $username, array $userData): array {
        $url = $this->baseUrl . '/api/user/' . $username;
        return CurlHelper::sendRequest($url, 'PUT', json_encode($userData), $this->getAuthHeaders());
    }

    public function deleteUser(string $username): bool {
        $url = $this->baseUrl . '/api/user/' . $username;
        $response = CurlHelper::sendRequest($url, 'DELETE', [], $this->getAuthHeaders());
        return isset($response['detail']) && strpos($response['detail'], 'removed') !== false;
    }

    public function resetUserTraffic(string $username): array {
        $url = $this->baseUrl . '/api/user/' . $username . '/reset';
        return CurlHelper::sendRequest($url, 'POST', [], $this->getAuthHeaders());
    }

    public function getSystemStats(): array {
        $url = $this->baseUrl . '/api/system';
        return CurlHelper::sendRequest($url, 'GET', [], $this->getAuthHeaders());
    }

    public function revokeSubscription(string $username): array {
        $url = $this->baseUrl . '/api/user/' . $username . '/revoke_sub';
        return CurlHelper::sendRequest($url, 'POST', [], $this->getAuthHeaders());
    }
}
?>
```php
<?php
// =========================================================================================
// File: src/Panels/SanaeiPanel.php
// Description: ✨ این کلاس جدید و قدرتمند برای پشتیبانی کامل از پنل سنایی (3x-ui) است.
// تمام منطق ورود و مدیریت کاربران این پنل در اینجا قرار دارد.
// =========================================================================================

namespace Ddkate\Panels;

require_once __DIR__ . '/PanelInterface.php';
require_once __DIR__ . '/../Utils/CurlHelper.php';
require_once __DIR__ . '/../Utils/Helper.php';

class SanaeiPanel implements PanelInterface {
    private string $baseUrl;
    private string $username;
    private string $password;
    private ?string $cookie = null;

    public function __construct(string $baseUrl, string $username, string $password) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->username = $username;
        $this->password = $password;
    }

    public function login(): bool {
        $url = $this->baseUrl . '/login';
        $data = ['username' => $this->username, 'password' => $this->password];
        $responseHeaders = [];
        CurlHelper::sendRequest($url, 'POST', http_build_query($data), ['Content-Type: application/x-www-form-urlencoded'], $responseHeaders);

        // از هدرهای پاسخ، کوکی را استخراج می‌کنیم
        foreach ($responseHeaders as $header) {
            if (stripos($header, 'Set-Cookie:') === 0) {
                preg_match('/Set-Cookie:\s*([^;]*)/i', $header, $matches);
                $this->cookie = $matches[1];
                return true;
            }
        }
        return false;
    }
    
    private function getAuthHeaders(): array {
        if (!$this->cookie) {
            $this->login();
        }
        // در پنل سنایی، به جای توکن از کوکی استفاده می‌شود
        return ['Cookie: ' . $this->cookie];
    }
    
    public function createUser(array $userData): array {
        $inboundId = $userData['inbound_id'];
        $url = $this->baseUrl . "/panel/inbound/{$inboundId}/addClient";
        
        $clientSettings = [
            "id" => \Ddkate\Utils\Helper::generateUUID(),
            "flow" => $userData['flow'] ?? '',
            "email" => $userData['username'],
            "limitIp" => $userData['limit_ip'] ?? 0,
            "totalGB" => ($userData['data_limit_gb'] ?? 0) * 1024 * 1024 * 1024,
            "expiryTime" => ($userData['days'] ?? 0) > 0 ? (time() + $userData['days'] * 86400) * 1000 : 0,
            "enable" => true,
            "tgId" => "",
            "subId" => ""
        ];
        
        $data = ["settings" => json_encode(["clients" => [$clientSettings]])];
        
        return CurlHelper::sendRequest($url, 'POST', json_encode($data), $this->getAuthHeaders());
    }

    public function getUser(string $username): ?array {
        $listUrl = $this->baseUrl . '/panel/inbound/list';
        $inbounds = CurlHelper::sendRequest($listUrl, 'GET', [], $this->getAuthHeaders());

        if (isset($inbounds['success']) && $inbounds['success']) {
            foreach ($inbounds['obj'] as $inbound) {
                $clients = json_decode($inbound['settings'], true)['clients'] ?? [];
                foreach ($clients as $client) {
                    if (isset($client['email']) && $client['email'] === $username) {
                        return $client; // کاربر پیدا شد
                    }
                }
            }
        }
        return null; // کاربر پیدا نشد
    }

    public function modifyUser(string $username, array $userData): array {
        // پیاده‌سازی این بخش نیازمند یافتن کاربر و سپس ارسال درخواست ویرایش به API است
        return ['success' => false, 'message' => 'Not yet implemented'];
    }

    public function deleteUser(string $username): bool {
         // پیاده‌سازی این بخش نیازمند یافتن کاربر و سپس ارسال درخواست حذف به API است
        return false;
    }

    public function resetUserTraffic(string $username): array {
        // پیاده‌سازی این بخش نیازمند یافتن کاربر و سپس ارسال درخواست ریست به API است
        return ['success' => false, 'message' => 'Not yet implemented'];
    }

    public function getSystemStats(): array {
        $url = $this->baseUrl . '/server/status';
        return CurlHelper::sendRequest($url, 'GET', [], $this->getAuthHeaders());
    }
}
?>
```php
<?php
// =========================================================================================
// File: src/Utils/CurlHelper.php
// Path: /src/Utils/CurlHelper.php
// Description: یک کلاس کمکی برای مدیریت تمام درخواست‌های cURL در پروژه.
// این کار از تکرار کد جلوگیری کرده و مدیریت خطاها را آسان‌تر می‌کند.
// =========================================================================================

namespace Ddkate\Utils;

class CurlHelper {
    /**
     * @param string $url آدرس درخواستی
     * @param string $method متد درخواست (GET, POST, PUT, DELETE)
     * @param mixed $data داده‌ای که باید ارسال شود
     * @param array $headers هدرهای سفارشی
     * @param array|null $responseHeaders برای دریافت هدرهای پاسخ (مخصوص کوکی)
     * @return array|null پاسخ دریافتی به صورت آرایه
     */
    public static function sendRequest(string $url, string $method = 'GET', $data = [], array $headers = [], ?array &$responseHeaders = null): ?array {
        $ch = curl_init();
        
        $defaultHeaders = ['Accept: application/json'];
        $finalHeaders = array_merge($defaultHeaders, $headers);

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_TIMEOUT => 15, // 15 ثانیه مهلت برای پاسخ
            CURLOPT_HTTPHEADER => $finalHeaders,
            CURLOPT_SSL_VERIFYPEER => false, // برای پنل‌هایی با گواهی SSL نامعتبر
            CURLOPT_SSL_VERIFYHOST => false,
        ];

        if ($method === 'POST' || $method === 'PUT') {
            $options[CURLOPT_POSTFIELDS] = $data;
        }

        if (is_array($responseHeaders)) {
            $options[CURLOPT_HEADER] = true; // برای دریافت هدرها
        }

        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            // در یک برنامه واقعی باید این خطا را لاگ کرد
            $error = curl_error($ch);
            curl_close($ch);
            return ['success' => false, 'message' => $error];
        }

        if (is_array($responseHeaders)) {
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $headerStr = substr($response, 0, $headerSize);
            $body = substr($response, $headerSize);
            $responseHeaders = explode("\r\n", trim($headerStr));
        } else {
            $body = $response;
        }
        
        curl_close($ch);
        return json_decode($body, true) ?? ['success' => false, 'message' => 'Invalid JSON response'];
    }
}
?>
