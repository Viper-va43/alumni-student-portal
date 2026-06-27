<?php
require_once __DIR__ . '/../../includes/functions.php';

function where2go_mobile_security_headers(string $methods = 'GET, POST, OPTIONS'): void
{
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: ' . $methods);
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Mobile-Token');
    header('Content-Type: application/json; charset=utf-8');

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function where2go_mobile_security_reply(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function where2go_mobile_security_token_hash(string $token): string
{
    return hash('sha256', $token);
}

function where2go_mobile_security_ensure_token_table(): void
{
    static $ready = false;

    if ($ready) {
        return;
    }

    $conn = db_connect();
    $sql = "
        CREATE TABLE IF NOT EXISTS mobile_api_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NOT NULL,
            token_hash CHAR(64) NOT NULL UNIQUE,
            label VARCHAR(60) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_used_at DATETIME NULL,
            expires_at DATETIME NOT NULL,
            revoked_at DATETIME NULL,
            KEY idx_mobile_api_tokens_customer (customer_id),
            KEY idx_mobile_api_tokens_hash (token_hash),
            CONSTRAINT fk_mobile_api_tokens_customer
                FOREIGN KEY (customer_id) REFERENCES customers(Customer_ID)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    if (!$conn->query($sql)) {
        where2go_mobile_security_reply(500, [
            'ok' => false,
            'message' => 'Mobile authentication is temporarily unavailable.',
        ]);
    }

    $ready = true;
}

function where2go_mobile_security_issue_token(int $customerId, string $label = 'mobile'): array
{
    if ($customerId <= 0) {
        where2go_mobile_security_reply(500, [
            'ok' => false,
            'message' => 'Account token could not be created.',
        ]);
    }

    where2go_mobile_security_ensure_token_table();

    $conn = db_connect();
    $token = bin2hex(random_bytes(32));
    $tokenHash = where2go_mobile_security_token_hash($token);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
    $stmt = $conn->prepare('
        INSERT INTO mobile_api_tokens (customer_id, token_hash, label, expires_at)
        VALUES (?, ?, ?, ?)
    ');

    if (!$stmt) {
        where2go_mobile_security_reply(500, [
            'ok' => false,
            'message' => 'Account token could not be created.',
        ]);
    }

    $stmt->bind_param('isss', $customerId, $tokenHash, $label, $expiresAt);

    if (!$stmt->execute()) {
        where2go_mobile_security_reply(500, [
            'ok' => false,
            'message' => 'Account token could not be created.',
        ]);
    }

    $stmt->close();

    return [
        'token' => $token,
        'expiresAt' => $expiresAt,
    ];
}

function where2go_mobile_security_request_token(): string
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authorization = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? ($headers['Authorization'] ?? $headers['authorization'] ?? '');

    if (preg_match('/^Bearer\s+(.+)$/i', trim((string) $authorization), $matches)) {
        return trim((string) $matches[1]);
    }

    return trim((string) (
        $_SERVER['HTTP_X_MOBILE_TOKEN']
        ?? ($headers['X-Mobile-Token'] ?? $headers['x-mobile-token'] ?? '')
    ));
}

function where2go_mobile_security_auth_context(): ?array
{
    $token = where2go_mobile_security_request_token();

    if ($token === '') {
        return null;
    }

    where2go_mobile_security_ensure_token_table();

    $conn = db_connect();
    $tokenHash = where2go_mobile_security_token_hash($token);
    $stmt = $conn->prepare('
        SELECT t.customer_id, c.Email
        FROM mobile_api_tokens t
        INNER JOIN customers c ON c.Customer_ID = t.customer_id
        WHERE t.token_hash = ?
          AND t.revoked_at IS NULL
          AND t.expires_at > NOW()
        LIMIT 1
    ');

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $tokenHash);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return null;
    }

    $update = $conn->prepare('
        UPDATE mobile_api_tokens
        SET last_used_at = NOW()
        WHERE token_hash = ?
          AND (last_used_at IS NULL OR last_used_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE))
        LIMIT 1
    ');

    if ($update) {
        $update->bind_param('s', $tokenHash);
        $update->execute();
        $update->close();
    }

    return [
        'customerId' => (int) $row['customer_id'],
        'email' => (string) ($row['Email'] ?? ''),
    ];
}

function where2go_mobile_security_require_customer(?int $requestedCustomerId = null): array
{
    $auth = where2go_mobile_security_auth_context();

    if (!$auth) {
        where2go_mobile_security_reply(401, [
            'ok' => false,
            'message' => 'Login or register before continuing.',
        ]);
    }

    $ownedCustomerId = (int) $auth['customerId'];

    if ($requestedCustomerId !== null && $requestedCustomerId > 0 && $requestedCustomerId !== $ownedCustomerId) {
        where2go_mobile_security_reply(403, [
            'ok' => false,
            'message' => 'This account token cannot access that customer.',
        ]);
    }

    return $auth;
}
