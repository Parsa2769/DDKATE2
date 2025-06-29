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

        if (strtoupper($method) === 'POST' || strtoupper($method) === 'PUT') {
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

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (is_array($responseHeaders)) {
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $headerStr = substr($response, 0, $headerSize);
            $body = substr($response, $headerSize);
            $responseHeaders = explode("\r\n", trim($headerStr));
        } else {
            $body = $response;
        }
        
        curl_close($ch);
        
        $decoded_body = json_decode($body, true);

        // اگر پاسخ جیسون معتبر نباشد و کد خطا هم نداشته باشیم، خود متن پاسخ را برمی‌گردانیم
        if (json_last_error() !== JSON_ERROR_NONE && $http_code < 400) {
            return ['success' => true, 'raw_response' => $body];
        }

        return $decoded_body ?? ['success' => false, 'message' => 'Invalid JSON response from panel', 'http_code' => $http_code];
    }
}
?>
```php
<?php
// =========================================================================================
// File: src/Panels/PanelInterface.php
// Path: /src/Panels/PanelInterface.php
// Description: این فایل یک "قرارداد" یا الگو برای تمام کلاس‌های پنل است.
// هر پنل جدیدی که به پروژه اضافه شود، باید تمام توابع تعریف شده در اینجا را داشته باشد.
// =========================================================================================

namespace Ddkate\Panels;

interface PanelInterface {
    /**
     * برای ورود به پنل و دریافت توکن یا کوکی احراز هویت.
     */
    public function login(): bool;

    /**
     * برای ساخت یک کاربر جدید در پنل.
     */
    public function createUser(array $userData): array;

    /**
     * برای دریافت اطلاعات یک کاربر خاص از پنل.
     */
    public function getUser(string $username): ?array;

    /**
     * برای ویرایش اطلاعات یک کاربر موجود.
     */
    public function modifyUser(string $username, array $userData): array;
    
    /**
     * برای حذف یک کاربر از پنل.
     */
    public function deleteUser(string $username): bool;

    /**
     * برای ریست کردن حجم مصرفی یک کاربر.
     */
    public function resetUserTraffic(string $username): array;

    /**
     * برای دریافت آمار کلی سیستم (مصرف رم، پردازنده و...).
     */
    public function getSystemStats(): array;
}
?>
```php
<?php
// =========================================================================================
// File: src/Panels/MarzbanPanel.php
// Path: /src/Panels/MarzbanPanel.php
// Description: این کلاس بازنویسی شده توابع فایل قدیمی apipanel.php شماست که
// مخصوص پنل "مرزبان" نوشته شده است.
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
        $headers = ['Content-Type: application/x-www-form-urlencoded'];
        $response = \Ddkate\Utils\CurlHelper::sendRequest($url, 'POST', http_build_query($data), $headers);

        if (isset($response['access_token'])) {
            $this->token = $response['access_token'];
            return true;
        }
        return false;
    }

    private function getAuthHeaders(): array {
        if (!$this->token) {
            if (!$this->login()) {
                throw new \Exception("Marzban login failed.");
            }
        }
        return ['Authorization: Bearer ' . $this->token];
    }

    public function createUser(array $userData): array {
        $url = $this->baseUrl . '/api/user';
        // ساختار پیش‌فرض برای پراکسی‌ها در صورتی که در ورودی تعریف نشده باشند
        $proxies = $userData['proxies'] ?? ['vless' => new \stdClass()];
        
        $data = [
            'username' => $userData['username'],
            'proxies' => $proxies,
            'expire' => ($userData['days'] ?? 0) > 0 ? time() + ($userData['days'] * 86400) : 0,
            'data_limit' => ($userData['data_limit_gb'] ?? 0) * 1024 * 1024 * 1024,
            'on_hold_expire_duration' => $userData['on_hold_duration'] ?? 0
        ];
        return \Ddkate\Utils\CurlHelper::sendRequest($url, 'POST', json_encode($data), $this->getAuthHeaders());
    }

    public function getUser(string $username): ?array {
        $url = $this->baseUrl . '/api/user/' . $username;
        return \Ddkate\Utils\CurlHelper::sendRequest($url, 'GET', [], $this->getAuthHeaders());
    }

    public function modifyUser(string $username, array $userData): array {
        $url = $this->baseUrl . '/api/user/' . $username;
        return \Ddkate\Utils\CurlHelper::sendRequest($url, 'PUT', json_encode($userData), $this->getAuthHeaders());
    }

    public function deleteUser(string $username): bool {
        $url = $this->baseUrl . '/api/user/' . $username;
        $response = \Ddkate\Utils\CurlHelper::sendRequest($url, 'DELETE', [], $this->getAuthHeaders());
        return isset($response['detail']) && strpos($response['detail'], 'removed') !== false;
    }

    public function resetUserTraffic(string $username): array {
        $url = $this->baseUrl . '/api/user/' . $username . '/reset';
        return \Ddkate\Utils\CurlHelper::sendRequest($url, 'POST', [], $this->getAuthHeaders());
    }

    public function getSystemStats(): array {
        $url = $this->baseUrl . '/api/system';
        return \Ddkate\Utils\CurlHelper::sendRequest($url, 'GET', [], $this->getAuthHeaders());
    }

    public function revokeSubscription(string $username): array {
        $url = $this->baseUrl . '/api/user/' . $username . '/revoke_sub';
        return \Ddkate\Utils\CurlHelper::sendRequest($url, 'POST', [], $this->getAuthHeaders());
    }
}
?>
```php
<?php
// =========================================================================================
// File: src/Panels/SanaeiPanel.php
// Path: /src/Panels/SanaeiPanel.php
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
        $headers = ['Content-Type: application/x-www-form-urlencoded'];
        $responseHeaders = [];
        \Ddkate\Utils\CurlHelper::sendRequest($url, 'POST', http_build_query($data), $headers, $responseHeaders);

        foreach ($responseHeaders as $header) {
            if (stripos($header, 'Set-Cookie:') === 0) {
                preg_match('/Set-Cookie:\s*([^;]*)/i', $header, $matches);
                if (isset($matches[1])) {
                    $this->cookie = $matches[1];
                    return true;
                }
            }
        }
        return false;
    }
    
    private function getAuthHeaders(): array {
        if (!$this->cookie) {
            if (!$this->login()) {
                throw new \Exception("Sanaei/3x-ui panel login failed.");
            }
        }
        return ['Cookie: ' . $this->cookie];
    }
    
    public function createUser(array $userData): array {
        $inboundId = $userData['inbound_id'];
        $url = $this->baseUrl . "/panel/inbound/addClient/";
        
        $clientSettings = [
            "id" => \Ddkate\Utils\Helper::generateUUID(),
            "email" => $userData['username'],
            "totalGB" => ($userData['data_limit_gb'] ?? 0) * 1024 * 1024 * 1024,
            "expiryTime" => ($userData['days'] ?? 0) > 0 ? (time() + $userData['days'] * 86400) * 1000 : 0,
            "enable" => true,
        ];
        
        $data = [
            "id" => $inboundId,
            "settings" => json_encode(["clients" => [$clientSettings]])
        ];
        
        return \Ddkate\Utils\CurlHelper::sendRequest($url, 'POST', json_encode($data), $this->getAuthHeaders());
    }

    public function getUser(string $username): ?array {
        $listUrl = $this->baseUrl . '/panel/inbound/list';
        $inbounds = \Ddkate\Utils\CurlHelper::sendRequest($listUrl, 'GET', [], $this->getAuthHeaders());

        if (isset($inbounds['success']) && $inbounds['success']) {
            foreach ($inbounds['obj'] as $inbound) {
                $settings = json_decode($inbound['settings'], true);
                if (!empty($settings['clients'])) {
                    foreach ($settings['clients'] as $client) {
                        if (isset($client['email']) && $client['email'] === $username) {
                            $client['inbound_id'] = $inbound['id']; // افزودن آیدی اینباند برای استفاده‌های بعدی
                            return $client;
                        }
                    }
                }
            }
        }
        return null;
    }

    public function modifyUser(string $username, array $userData): array {
        $userInfo = $this->getUser($username);
        if (!$userInfo) {
            return ['success' => false, 'message' => 'User not found'];
        }

        $inboundId = $userInfo['inbound_id'];
        $url = $this->baseUrl . "/panel/inbound/{$inboundId}/updateClient/{$userInfo['id']}";
        
        return \Ddkate\Utils\CurlHelper::sendRequest($url, 'POST', json_encode($userData), $this->getAuthHeaders());
    }

    public function deleteUser(string $username): bool {
        $userInfo = $this->getUser($username);
        if (!$userInfo) {
            return false;
        }

        $inboundId = $userInfo['inbound_id'];
        $clientId = $userInfo['id'];
        $url = $this->baseUrl . "/panel/inbound/{$inboundId}/delClient/{$clientId}";
        
        $response = \Ddkate\Utils\CurlHelper::sendRequest($url, 'POST', [], $this->getAuthHeaders());
        return $response['success'] ?? false;
    }

    public function resetUserTraffic(string $username): array {
        $url = $this->baseUrl . "/panel/inbound/resetClientTraffic/{$username}";
        return \Ddkate\Utils\CurlHelper::sendRequest($url, 'POST', [], $this->getAuthHeaders());
    }

    public function getSystemStats(): array {
        $url = $this->baseUrl . '/server/status';
        return \Ddkate\Utils\CurlHelper::sendRequest($url, 'GET', [], $this->getAuthHeaders());
    }
}
?>
