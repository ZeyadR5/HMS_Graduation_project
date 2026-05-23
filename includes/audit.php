<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('hms_audit_trim')) {
    function hms_audit_trim($value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        return substr($value, 0, $maxLength);
    }
}

if (!function_exists('hms_audit_ip_address')) {
    function hms_audit_ip_address(): ?string
    {
        $candidates = [
            $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (!$candidate) {
                continue;
            }

            $value = trim(explode(',', $candidate)[0]);
            if ($value !== '') {
                return hms_audit_trim($value, 45);
            }
        }

        return null;
    }
}

if (!function_exists('hms_audit_actor_defaults')) {
    function hms_audit_actor_defaults(): array
    {
        $role = $_SESSION['role'] ?? null;
        $actorId = null;

        if ($role === 'Patient') {
            $actorId = $_SESSION['uid'] ?? null;
        } elseif (isset($_SESSION['id'])) {
            $actorId = $_SESSION['id'];
        }

        return [
            'role' => hms_audit_trim($role ?: 'Guest', 50),
            'id' => $actorId !== null ? (string)$actorId : null,
            'name' => hms_audit_trim($_SESSION['username'] ?? null, 255),
            'login' => hms_audit_trim($_SESSION['login'] ?? null, 255),
        ];
    }
}

if (!function_exists('hms_audit_sanitize_payload')) {
    function hms_audit_is_sensitive_key(string $key): bool
    {
        $key = strtolower($key);
        $sensitiveParts = [
            'password',
            'pass',
            'pwd',
            'token',
            'secret',
            'cookie',
            'session',
        ];

        foreach ($sensitiveParts as $part) {
            if (str_contains($key, $part)) {
                return true;
            }
        }

        return false;
    }

    function hms_audit_sanitize_payload($value, int $depth = 0)
    {
        if ($depth > 4) {
            return '[MAX_DEPTH]';
        }

        if (is_array($value)) {
            $clean = [];
            $count = 0;

            foreach ($value as $key => $item) {
                if ($count >= 50) {
                    $clean['__truncated__'] = 'More values omitted';
                    break;
                }

                $safeKey = is_string($key) ? $key : (string)$key;
                if (hms_audit_is_sensitive_key($safeKey)) {
                    $clean[$safeKey] = '[REDACTED]';
                } else {
                    $clean[$safeKey] = hms_audit_sanitize_payload($item, $depth + 1);
                }

                $count++;
            }

            return $clean;
        }

        if (is_object($value)) {
            return '[OBJECT]';
        }

        if (is_bool($value) || $value === null || is_int($value) || is_float($value)) {
            return $value;
        }

        return hms_audit_trim((string)$value, 500);
    }

    function hms_audit_file_payload(): array
    {
        if (empty($_FILES) || !is_array($_FILES)) {
            return [];
        }

        $files = [];
        foreach ($_FILES as $field => $file) {
            if (!is_array($file)) {
                continue;
            }

            $files[$field] = [
                'name' => hms_audit_trim($file['name'] ?? null, 255),
                'type' => hms_audit_trim($file['type'] ?? null, 100),
                'size' => isset($file['size']) ? (int)$file['size'] : null,
                'error' => isset($file['error']) ? (int)$file['error'] : null,
            ];
        }

        return $files;
    }
}

if (!function_exists('hms_audit_ensure_table')) {
    function hms_audit_has_column(mysqli $connect, string $table, string $column): bool
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        $result = $connect->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");

        return $result && $result->num_rows > 0;
    }

    function hms_audit_ensure_table(mysqli $connect): void
    {
        static $ready = false;

        if ($ready || $connect->connect_errno) {
            return;
        }

        $connect->query("CREATE TABLE IF NOT EXISTS audit_logs (
            id INT(11) NOT NULL AUTO_INCREMENT,
            action_key VARCHAR(100) NOT NULL,
            entity_type VARCHAR(50) DEFAULT NULL,
            entity_id VARCHAR(50) DEFAULT NULL,
            description VARCHAR(255) NOT NULL,
            details_json LONGTEXT DEFAULT NULL,
            actor_role VARCHAR(50) DEFAULT NULL,
            actor_id VARCHAR(50) DEFAULT NULL,
            actor_name VARCHAR(255) DEFAULT NULL,
            actor_login VARCHAR(255) DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            request_method VARCHAR(10) DEFAULT NULL,
            request_uri VARCHAR(255) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_action_key (action_key),
            KEY idx_actor_role (actor_role),
            KEY idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $missingColumns = [
            'action_key' => "ALTER TABLE audit_logs ADD COLUMN action_key VARCHAR(100) NOT NULL DEFAULT 'legacy.action'",
            'entity_type' => "ALTER TABLE audit_logs ADD COLUMN entity_type VARCHAR(50) DEFAULT NULL",
            'entity_id' => "ALTER TABLE audit_logs ADD COLUMN entity_id VARCHAR(50) DEFAULT NULL",
            'description' => "ALTER TABLE audit_logs ADD COLUMN description VARCHAR(255) NOT NULL DEFAULT 'Audit event'",
            'details_json' => "ALTER TABLE audit_logs ADD COLUMN details_json LONGTEXT DEFAULT NULL",
            'actor_role' => "ALTER TABLE audit_logs ADD COLUMN actor_role VARCHAR(50) DEFAULT NULL",
            'actor_id' => "ALTER TABLE audit_logs ADD COLUMN actor_id VARCHAR(50) DEFAULT NULL",
            'actor_name' => "ALTER TABLE audit_logs ADD COLUMN actor_name VARCHAR(255) DEFAULT NULL",
            'actor_login' => "ALTER TABLE audit_logs ADD COLUMN actor_login VARCHAR(255) DEFAULT NULL",
            'ip_address' => "ALTER TABLE audit_logs ADD COLUMN ip_address VARCHAR(45) DEFAULT NULL",
            'request_method' => "ALTER TABLE audit_logs ADD COLUMN request_method VARCHAR(10) DEFAULT NULL",
            'request_uri' => "ALTER TABLE audit_logs ADD COLUMN request_uri VARCHAR(255) DEFAULT NULL",
            'user_agent' => "ALTER TABLE audit_logs ADD COLUMN user_agent VARCHAR(255) DEFAULT NULL",
            'created_at' => "ALTER TABLE audit_logs ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
        ];

        foreach ($missingColumns as $column => $alterSql) {
            if (!hms_audit_has_column($connect, 'audit_logs', $column)) {
                $connect->query($alterSql);
            }
        }

        $ready = true;
    }
}

if (!function_exists('hms_audit_log')) {
    function hms_audit_log(mysqli $connect, string $actionKey, array $context = []): void
    {
        if ($connect->connect_errno || trim($actionKey) === '') {
            return;
        }

        hms_audit_ensure_table($connect);

        $actor = array_merge(hms_audit_actor_defaults(), $context['actor'] ?? []);
        $details = $context['details'] ?? null;
        $detailsJson = null;
        $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

        if (defined('JSON_PARTIAL_OUTPUT_ON_ERROR')) {
            $jsonFlags |= JSON_PARTIAL_OUTPUT_ON_ERROR;
        }

        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $jsonFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }

        if (is_array($details) && $details !== []) {
            $detailsJson = json_encode($details, $jsonFlags);
        } elseif (is_string($details) && trim($details) !== '') {
            $detailsJson = trim($details);
        }

        $description = $context['description'] ?? ucwords(str_replace(['.', '_'], ' ', $actionKey));

        $stmt = $connect->prepare("
            INSERT INTO audit_logs (
                action_key,
                entity_type,
                entity_id,
                description,
                details_json,
                actor_role,
                actor_id,
                actor_name,
                actor_login,
                ip_address,
                request_method,
                request_uri,
                user_agent
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            return;
        }

        $entityType = hms_audit_trim($context['entity_type'] ?? null, 50);
        $entityId = hms_audit_trim($context['entity_id'] ?? null, 50);
        $description = hms_audit_trim($description, 255) ?? 'Audit event';
        $detailsJson = hms_audit_trim($detailsJson, 65000);
        $actorRole = hms_audit_trim($actor['role'] ?? null, 50);
        $actorId = hms_audit_trim($actor['id'] ?? null, 50);
        $actorName = hms_audit_trim($actor['name'] ?? null, 255);
        $actorLogin = hms_audit_trim($actor['login'] ?? null, 255);
        $ipAddress = hms_audit_trim($context['ip_address'] ?? hms_audit_ip_address(), 45);
        $requestMethod = hms_audit_trim($context['request_method'] ?? ($_SERVER['REQUEST_METHOD'] ?? null), 10);
        $requestUri = hms_audit_trim($context['request_uri'] ?? ($_SERVER['REQUEST_URI'] ?? $_SERVER['PHP_SELF'] ?? null), 255);
        $userAgent = hms_audit_trim($context['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? null), 255);
        $actionKey = hms_audit_trim($actionKey, 100) ?? 'unknown.action';

        $stmt->bind_param(
            "sssssssssssss",
            $actionKey,
            $entityType,
            $entityId,
            $description,
            $detailsJson,
            $actorRole,
            $actorId,
            $actorName,
            $actorLogin,
            $ipAddress,
            $requestMethod,
            $requestUri,
            $userAgent
        );
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('hms_audit_auto_log_request')) {
    function hms_audit_auto_log_request(mysqli $connect): void
    {
        static $logged = false;

        if ($logged || $connect->connect_errno) {
            return;
        }

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $requestUri = $_SERVER['REQUEST_URI'] ?? $_SERVER['PHP_SELF'] ?? '/';
        $path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
        $queryData = hms_audit_sanitize_payload($_GET ?? []);
        $postData = hms_audit_sanitize_payload($_POST ?? []);
        $fileData = hms_audit_file_payload();

        if ($method === 'GET') {
            $actionKey = empty($queryData) ? 'page.view' : 'request.get';
        } elseif ($method === 'POST') {
            $actionKey = 'request.post';
        } else {
            $actionKey = 'request.' . strtolower($method);
        }

        $details = [
            'path' => $path,
        ];

        if (!empty($queryData)) {
            $details['query'] = $queryData;
        }

        if (!empty($postData)) {
            $details['post'] = $postData;
        }

        if (!empty($fileData)) {
            $details['files'] = $fileData;
        }

        if (!empty($_SERVER['HTTP_REFERER'])) {
            $details['referer'] = hms_audit_trim($_SERVER['HTTP_REFERER'], 255);
        }

        hms_audit_log($connect, $actionKey, [
            'entity_type' => 'request',
            'entity_id' => basename($path) ?: $path,
            'description' => $method . ' ' . $path,
            'details' => $details,
        ]);

        $logged = true;
    }
}
