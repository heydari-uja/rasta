<?php

function e($value): string
{
    if ($value === null) {
        return '';
    }
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function uri($reservedUrl, $class, $method, $methodField = 'GET')
{
    $currentUrl = explode('?', currentUrl())[0];
    $currentUrl = str_replace(CURRENT_DOMAIN, '', $currentUrl);
    $currentUrl = trim($currentUrl, '/ ');
    $currentUrlArray = array_values(array_filter(explode('/', $currentUrl)));

    $reservedUrl = trim($reservedUrl, '/ ');
    $reservedUrlArray = array_values(array_filter(explode('/', $reservedUrl)));

    if (count($currentUrlArray) !== count($reservedUrlArray) || methodField() !== $methodField) {
        return false;
    }

    $parameters = [];
    for ($key = 0; $key < count($currentUrlArray); $key++) {
        if (
            ($reservedUrlArray[$key][0] ?? '') === '{'
            && ($reservedUrlArray[$key][strlen($reservedUrlArray[$key]) - 1] ?? '') === '}'
        ) {
            $parameters[] = $currentUrlArray[$key];
        } elseif ($currentUrlArray[$key] !== $reservedUrlArray[$key]) {
            return false;
        }
    }

    if (in_array(methodField(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        $request = requestPayload();
        if (!empty($_FILES)) {
            $request = array_merge($request, $_FILES);
        }
        $parameters = array_merge([$request], $parameters);
    }

    $object = new $class();
    call_user_func_array([$object, $method], $parameters);
    exit;
}

function asset($src)
{
    return trim(CURRENT_DOMAIN, '/') . '/public/' . trim($src, '/');
}

function url($path)
{
    return trim(CURRENT_DOMAIN, '/') . '/' . trim($path, '/ ');
}

function currentUrl()
{
    return currentDomain() . ($_SERVER['REQUEST_URI'] ?? '/');
}

function methodField()
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function requestPayload(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    $method = methodField();
    if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        $raw = file_get_contents('php://input');
        if ($raw !== false && $raw !== '') {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $cache = $json;
            }
        }
        if (!empty($_POST)) {
            $cache = array_merge($cache, $_POST);
        }
    }
    if (!empty($_GET)) {
        $cache = array_merge($_GET, $cache);
    }
    return $cache;
}

function displayError($status)
{
    if ($status) {
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');
        error_reporting(E_ALL);
    } else {
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        error_reporting(0);
    }
}

displayError(DISPLAY_ERROR);

global $flashMessage;
if (isset($_SESSION['flash_message'])) {
    $flashMessage = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

function flash($name, $value = null)
{
    if ($value === null) {
        global $flashMessage;
        return $flashMessage[$name] ?? '';
    }
    $_SESSION['flash_message'][$name] = $value;
}

if (isset($_SESSION['old'])) {
    unset($_SESSION['temporary_old']);
    $_SESSION['temporary_old'] = $_SESSION['old'];
    unset($_SESSION['old']);
}

$_SESSION['old'] = array_merge($_GET ?? [], $_POST ?? []);

function old($name)
{
    return $_SESSION['temporary_old'][$name] ?? null;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(array $request = []): void
{
    $token = $request['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        echo 'CSRF token mismatch';
        exit;
    }
}

function expectsJson(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return str_contains($accept, 'application/json') || str_contains($accept, 'json');
}

function jsonResponse($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError(string $message, array $errors = [], int $status = 422): void
{
    jsonResponse(['message' => $message, 'errors' => (object) $errors], $status);
}

function abortJson(int $status, string $message): void
{
    jsonResponse(['message' => $message], $status);
}

function abortHttp(int $status, string $message = ''): void
{
    http_response_code($status);
    if ($message !== '') {
        echo $message;
    }
    exit;
}

function redirectTo(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function validationErrors(array $errors): void
{
    $_SESSION['flash_message']['errors'] = $errors;
}

function fieldError(string $field): string
{
    global $flashMessage;
    $errors = $flashMessage['errors'] ?? [];
    $msg = $errors[$field] ?? '';
    if (!$msg) {
        return '';
    }
    return '<small class="field-error">' . e(is_array($msg) ? ($msg[0] ?? '') : $msg) . '</small>';
}

function hasError(string $field): bool
{
    global $flashMessage;
    return !empty($flashMessage['errors'][$field]);
}

function memberInitials(string $name): string
{
    $parts = array_values(array_filter(preg_split('/\s+/u', $name) ?: []));
    $letters = array_map(fn($p) => mb_substr($p, 0, 1), array_slice($parts, 0, 2));
    return implode('', $letters);
}

function assetHead(): string
{
    return '<link rel="stylesheet" href="' . e(asset('app/css/app.css')) . '">' .
        '<script type="module" src="' . e(asset('app/js/app.js')) . '"></script>';
}

function currentMemberId(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function currentMember(): ?array
{
    static $member = false;
    if ($member === false) {
        $id = currentMemberId();
        if (!$id) {
            $member = null;
        } else {
            $db = new \Database\Database();
            $member = $db->select('SELECT * FROM members WHERE id = ?', [$id])->fetch() ?: null;
        }
    }
    return $member;
}

function setCurrentMemberId(?int $id): void
{
    if ($id === null) {
        unset($_SESSION['user_id']);
    } else {
        $_SESSION['user_id'] = $id;
    }
}

function regenerateSession(): void
{
    session_regenerate_id(true);
}

function invalidateSession(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function renderTemplate(string $path, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    require BASE_PATH . '/template/' . $path;
}

function uiSelect(string $model, string $options, string $placeholder = 'انتخاب کنید', string $change = ''): void
{
    $placeholderJs = json_encode($placeholder, JSON_UNESCAPED_UNICODE);
    require BASE_PATH . '/template/app/components/ui-select.php';
}

function datePicker(string $model, string $label = 'انتخاب تاریخ'): void
{
    require BASE_PATH . '/template/app/components/date-picker.php';
}

function dd(mixed $data, $v = false)
{
    echo "<pre style='background-color: #000000;padding: 1rem;color: #ffffff;'>";
    if ($v) {
        var_dump($data);
    } else {
        print_r($data);
    }
    exit();
}
