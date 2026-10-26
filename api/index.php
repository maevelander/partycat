<?php
// Party Cat API
// A small JSON API over MySQL that replaces Supabase auth and REST.
// Every data query is scoped to the party owned by the logged-in user, which is
// what the Supabase RLS policies used to enforce.
//
// Requests: api/?action=<name>. GET actions read, POST actions take a JSON body.

declare(strict_types=1);

const SESSION_COOKIE = 'partycat_session';
const SESSION_DAYS = 30;
const RESET_TOKEN_MINUTES = 60;
const MIN_PASSWORD_LENGTH = 8;
const MAX_ITEMS_PER_LIST = 500;
const DEFAULT_PARTY_NAME = '😸 Party Time Meow!';
// Verified against when an email has no account, so failed logins take the same time either way
const DUMMY_PASSWORD_HASH = '$2y$12$Hkvnt6vjjtCkXZgLtHirTu1P.83mQtQNYaE0M588Y.2Hyh8iE59AO';

// Analytics dashboard windows, in days
const STATS_WINDOWS = [30, 60, 90, 180];
// One row per person per active day. Accounts are counted per user (across devices) and guests
// per browser. Visitors land in guest mode before logging in, so a browser that also logged in
// on a day counts only as an account visit that day.
const PEOPLE_BY_DAY = "
    SELECT DISTINCT 'account' AS mode, user_id AS person, day FROM visits WHERE mode = 'account'
    UNION ALL
    SELECT 'guest', g.visitor_id, g.day FROM visits g
    WHERE g.mode = 'guest' AND NOT EXISTS (
        SELECT 1 FROM visits a WHERE a.visitor_id = g.visitor_id AND a.day = g.day AND a.mode = 'account'
    )";

// Item tables and the fields a client may write to each
const ITEM_TABLES = [
    'guests' => ['name' => 'text', 'status' => 'status', 'count' => 'int'],
    'todos' => ['text' => 'text', 'completed' => 'bool'],
    'shopping_items' => ['text' => 'text', 'bought' => 'bool'],
];
const REQUIRED_ITEM_FIELD = ['guests' => 'name', 'todos' => 'text', 'shopping_items' => 'text'];

$config = require is_file(__DIR__ . '/config.local.php') ? __DIR__ . '/config.local.php' : __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

class ApiError extends Exception {}

function fail(int $status, string $message): never
{
    throw new ApiError($message, $status);
}

function respond(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function db(): PDO
{
    global $config;
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
            $config['db_user'],
            $config['db_pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        $pdo->exec("SET time_zone = '+00:00'");
    }
    return $pdo;
}

function query(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function is_uuid(mixed $value): bool
{
    return is_string($value) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1;
}

function input(): array
{
    static $body = null;
    if ($body === null) {
        $decoded = json_decode(file_get_contents('php://input') ?: '', true);
        $body = is_array($decoded) ? $decoded : [];
    }
    return $body;
}

function text_input(string $key, int $maxLength, bool $required = true): ?string
{
    $value = input()[$key] ?? null;
    if ($value === null && !$required) {
        return null;
    }
    if (!is_string($value) || trim($value) === '') {
        fail(400, "Missing $key");
    }
    $value = trim($value);
    if (mb_strlen($value) > $maxLength) {
        fail(400, "$key is too long");
    }
    return $value;
}

function email_input(): string
{
    $email = strtolower(text_input('email', 255));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fail(400, 'Please enter a valid email address');
    }
    return $email;
}

function password_input(): string
{
    $password = input()['password'] ?? null;
    if (!is_string($password) || strlen($password) < MIN_PASSWORD_LENGTH) {
        fail(400, 'Password should be at least ' . MIN_PASSWORD_LENGTH . ' characters');
    }
    if (strlen($password) > 200) {
        fail(400, 'Password is too long');
    }
    return $password;
}

// Today's date (plus an offset in days) in the timezone used for visit counting
function local_day(int $offsetDays = 0): string
{
    global $config;
    $now = new DateTimeImmutable('now', new DateTimeZone($config['timezone'] ?? 'UTC'));
    return $now->modify("$offsetDays day")->format('Y-m-d');
}

function iso_date(?string $mysqlDate): ?string
{
    if ($mysqlDate === null) {
        return null;
    }
    return (new DateTimeImmutable($mysqlDate, new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z');
}

// ---------------------------------------------------------------------------
// Rate limiting
// ---------------------------------------------------------------------------

// The client to rate limit. IPv6 addresses are grouped by /64, because one person
// usually controls a whole /64 and could otherwise rotate addresses within it.
function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        return inet_ntop(substr(inet_pton($ip), 0, 8) . str_repeat("\0", 8)) . '/64';
    }
    return $ip;
}

function record_attempt(string $action, ?string $email): void
{
    query('INSERT INTO auth_attempts (action, ip, email) VALUES (?, ?, ?)', [$action, client_ip(), $email]);
    query('DELETE FROM auth_attempts WHERE created_at < UTC_TIMESTAMP() - INTERVAL 2 DAY');
}

// Whether recent attempts at an action have reached a limit for this client,
// for this email address, or across the whole site
function is_throttled(string $action, ?string $email, int $windowMinutes, int $maxPerIp, int $maxPerEmail = PHP_INT_MAX, int $maxTotal = PHP_INT_MAX): bool
{
    $recent = "action = ? AND created_at > UTC_TIMESTAMP() - INTERVAL $windowMinutes MINUTE";
    $ipCount = query("SELECT COUNT(*) FROM auth_attempts WHERE $recent AND ip = ?", [$action, client_ip()])->fetchColumn();
    $emailCount = $email === null ? 0 : query("SELECT COUNT(*) FROM auth_attempts WHERE $recent AND email = ?", [$action, $email])->fetchColumn();
    $totalCount = $maxTotal === PHP_INT_MAX ? 0 : query("SELECT COUNT(*) FROM auth_attempts WHERE $recent", [$action])->fetchColumn();
    return $ipCount >= $maxPerIp || $emailCount >= $maxPerEmail || $totalCount >= $maxTotal;
}

function throttle(string $action, ?string $email, int $windowMinutes, int $maxPerIp, int $maxPerEmail = PHP_INT_MAX, int $maxTotal = PHP_INT_MAX): void
{
    if (is_throttled($action, $email, $windowMinutes, $maxPerIp, $maxPerEmail, $maxTotal)) {
        fail(429, 'Too many attempts. Please wait a while and try again.');
    }
}

// Signup and reset forms carry a hidden "website" field that people never see or fill in;
// simple bots that fill in every field give themselves away
function is_bot(): bool
{
    $honeypot = input()['website'] ?? '';
    return !is_string($honeypot) || $honeypot !== '';
}

// ---------------------------------------------------------------------------
// Sessions
// ---------------------------------------------------------------------------

function set_session_cookie(string $value, int $expires): void
{
    global $config;
    setcookie(SESSION_COOKIE, $value, [
        'expires' => $expires,
        'path' => '/',
        'secure' => $config['secure_cookies'],
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function start_session(string $userId): void
{
    $token = bin2hex(random_bytes(32));
    query(
        'INSERT INTO sessions (token_hash, user_id, expires_at) VALUES (?, ?, UTC_TIMESTAMP() + INTERVAL ' . SESSION_DAYS . ' DAY)',
        [hash('sha256', $token), $userId]
    );
    query('DELETE FROM sessions WHERE expires_at < UTC_TIMESTAMP()');
    set_session_cookie($token, time() + SESSION_DAYS * 86400);
}

function end_session(): void
{
    $token = $_COOKIE[SESSION_COOKIE] ?? null;
    if (is_string($token)) {
        query('DELETE FROM sessions WHERE token_hash = ?', [hash('sha256', $token)]);
    }
    set_session_cookie('', time() - 3600);
}

function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        $token = $_COOKIE[SESSION_COOKIE] ?? null;
        if (is_string($token) && $token !== '') {
            $user = query(
                'SELECT u.id, u.email FROM sessions s JOIN users u ON u.id = s.user_id
                 WHERE s.token_hash = ? AND s.expires_at > UTC_TIMESTAMP()',
                [hash('sha256', $token)]
            )->fetch() ?: null;
        }
    }
    return $user;
}

function require_user(): array
{
    return current_user() ?? fail(401, 'Please log in again');
}

function public_user(array $user): array
{
    global $config;
    return [
        'id' => $user['id'],
        'email' => $user['email'],
        'is_admin' => in_array($user['email'], $config['admin_emails'], true),
    ];
}

// ---------------------------------------------------------------------------
// Parties and items
// ---------------------------------------------------------------------------

function create_party(string $userId): void
{
    query('INSERT INTO parties (id, user_id, name, description) VALUES (?, ?, ?, ?)', [uuid(), $userId, DEFAULT_PARTY_NAME, 'My awesome party!']);
}

// Each user works on their oldest party, created on demand
function user_party(): array
{
    $user = require_user();
    $sql = 'SELECT id, name, description, created_at FROM parties WHERE user_id = ? ORDER BY created_at LIMIT 1';
    $party = query($sql, [$user['id']])->fetch();
    if (!$party) {
        create_party($user['id']);
        $party = query($sql, [$user['id']])->fetch();
    }
    $party['created_at'] = iso_date($party['created_at']);
    return $party;
}

function item_table(): string
{
    $type = input()['type'] ?? $_GET['type'] ?? null;
    if (!is_string($type) || !isset(ITEM_TABLES[$type])) {
        fail(400, 'Unknown item type');
    }
    return $type;
}

// Validated column => value pairs from the request body for an item table
function item_fields(string $table): array
{
    $fields = [];
    foreach (ITEM_TABLES[$table] as $field => $kind) {
        if (!array_key_exists($field, input())) {
            continue;
        }
        $value = input()[$field];
        $fields[$field] = match ($kind) {
            'text' => text_input($field, 1000),
            'bool' => is_bool($value) ? (int) $value : fail(400, "$field must be true or false"),
            'int' => is_int($value) && $value >= 1 && $value <= 1000 ? $value : fail(400, "$field must be a number"),
            'status' => in_array($value, ['pending', 'yes', 'no'], true) ? $value : fail(400, 'Invalid status'),
        };
    }
    return $fields;
}

function item_out(string $table, array $row): array
{
    foreach (ITEM_TABLES[$table] as $field => $kind) {
        if ($kind === 'bool') {
            $row[$field] = (bool) $row[$field];
        } elseif ($kind === 'int') {
            $row[$field] = (int) $row[$field];
        }
    }
    $row['created_at'] = iso_date($row['created_at']);
    $row['updated_at'] = iso_date($row['updated_at']);
    return $row;
}

function find_item(string $table, string $id, string $partyId): ?array
{
    $row = query("SELECT * FROM $table WHERE id = ? AND party_id = ?", [$id, $partyId])->fetch();
    return $row ? item_out($table, $row) : null;
}

function send_reset_email(string $email, string $token): void
{
    global $config;
    $link = rtrim($config['app_url'], '/') . '/?reset=' . $token;
    $body = "Hi there,\n\n"
        . "Someone asked to reset the password for your Party Cat account. "
        . "Click the link below to choose a new password:\n\n$link\n\n"
        . "The link expires in " . RESET_TOKEN_MINUTES . " minutes. "
        . "If you didn't ask for this, you can ignore this email.\n\n"
        . "😸 Party Cat\n";

    // Sent through the app's mailbox over authenticated SMTP, so it passes SPF and DKIM
    require_once __DIR__ . '/lib/PHPMailer/Exception.php';
    require_once __DIR__ . '/lib/PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/lib/PHPMailer/SMTP.php';
    preg_match('/^\s*(.*?)\s*<(.+)>\s*$/', $config['mail_from'], $from);
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $config['smtp_host'];
        $mail->Port = $config['smtp_port'];
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->SMTPAuth = true;
        $mail->Username = $config['smtp_user'];
        $mail->Password = $config['smtp_pass'];
        $mail->Timeout = 15;
        $mail->CharSet = PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
        $mail->setFrom($from[2] ?? $config['smtp_user'], $from[1] ?? '');
        $mail->addAddress($email);
        $mail->Subject = 'Reset your Party Cat password';
        $mail->Body = $body;
        $mail->send();
    } catch (PHPMailer\PHPMailer\Exception $e) {
        error_log('Party Cat: failed to send password reset email: ' . $mail->ErrorInfo);
    }
}

// ---------------------------------------------------------------------------
// Actions
// ---------------------------------------------------------------------------

$actions = [
    'session' => ['GET', function () {
        $user = current_user();
        respond(['user' => $user ? public_user($user) : null]);
    }],

    'signup' => ['POST', function () {
        $email = email_input();
        $password = password_input();
        if (is_bot()) {
            fail(400, 'Something went wrong. Please try again.');
        }
        // Per client: 5 an hour. Site-wide: 30 an hour, which stops bulk account creation from many IPs
        throttle('signup', null, 60, 5, PHP_INT_MAX, 30);
        record_attempt('signup', $email);
        if (query('SELECT 1 FROM users WHERE email = ?', [$email])->fetchColumn()) {
            fail(409, 'An account with this email already exists. Try logging in instead.');
        }
        $id = uuid();
        query('INSERT INTO users (id, email, password_hash) VALUES (?, ?, ?)', [$id, $email, password_hash($password, PASSWORD_DEFAULT)]);
        create_party($id);
        start_session($id);
        respond(['user' => public_user(['id' => $id, 'email' => $email])]);
    }],

    'login' => ['POST', function () {
        $email = email_input();
        $password = input()['password'] ?? '';
        throttle('login', $email, 15, 20, 8);
        $user = query('SELECT id, email, password_hash FROM users WHERE email = ?', [$email])->fetch();
        $valid = password_verify(is_string($password) ? $password : '', $user['password_hash'] ?? DUMMY_PASSWORD_HASH);
        if (!$user || !$valid) {
            record_attempt('login', $email);
            fail(401, 'Invalid email or password');
        }
        // Accounts migrated from Supabase use $2a$ bcrypt hashes; upgrade them on login
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            query('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
        start_session($user['id']);
        respond(['user' => public_user($user)]);
    }],

    'logout' => ['POST', function () {
        end_session();
        respond(['ok' => true]);
    }],

    'forgot_password' => ['POST', function () {
        $email = email_input();
        if (is_bot()) {
            respond(['ok' => true]);
        }
        // Keeps the form from being used to flood anyone's inbox, and keeps the whole site well
        // under the mailbox's daily sending limit (about 300), so real resets always get through
        throttle('forgot', $email, 60, 5, 3, 30);
        throttle('forgot', $email, 1440, 10, 5, 100);
        record_attempt('forgot', $email);
        $userId = query('SELECT id FROM users WHERE email = ?', [$email])->fetchColumn();
        if ($userId) {
            // Only the newest reset link works; links that expired over a day ago are deleted
            query('UPDATE password_resets SET used_at = UTC_TIMESTAMP() WHERE user_id = ? AND used_at IS NULL', [$userId]);
            query('DELETE FROM password_resets WHERE expires_at < UTC_TIMESTAMP() - INTERVAL 1 DAY');
            $token = bin2hex(random_bytes(32));
            query(
                'INSERT INTO password_resets (token_hash, user_id, expires_at) VALUES (?, ?, UTC_TIMESTAMP() + INTERVAL ' . RESET_TOKEN_MINUTES . ' MINUTE)',
                [hash('sha256', $token), $userId]
            );
            send_reset_email($email, $token);
        }
        // Same response either way, so the form can't be used to discover accounts
        respond(['ok' => true]);
    }],

    'reset_password' => ['POST', function () {
        $token = input()['token'] ?? null;
        $password = password_input();
        $reset = is_string($token) ? query(
            'SELECT token_hash, user_id FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > UTC_TIMESTAMP()',
            [hash('sha256', $token)]
        )->fetch() : false;
        if (!$reset) {
            fail(400, 'This reset link has expired or was already used. Please request a new one.');
        }
        query('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $reset['user_id']]);
        query('UPDATE password_resets SET used_at = UTC_TIMESTAMP() WHERE user_id = ? AND used_at IS NULL', [$reset['user_id']]);
        query('DELETE FROM sessions WHERE user_id = ?', [$reset['user_id']]);
        start_session($reset['user_id']);
        $user = query('SELECT id, email FROM users WHERE id = ?', [$reset['user_id']])->fetch();
        respond(['user' => public_user($user)]);
    }],

    'party' => ['GET', function () {
        respond(['party' => user_party()]);
    }],

    'update_party' => ['POST', function () {
        $party = user_party();
        $name = text_input('name', 255);
        query('UPDATE parties SET name = ? WHERE id = ?', [$name, $party['id']]);
        respond(['party' => user_party()]);
    }],

    'clear_party' => ['POST', function () {
        $party = user_party();
        db()->beginTransaction();
        foreach (array_keys(ITEM_TABLES) as $table) {
            query("DELETE FROM $table WHERE party_id = ?", [$party['id']]);
        }
        query('UPDATE parties SET name = ? WHERE id = ?', [DEFAULT_PARTY_NAME, $party['id']]);
        db()->commit();
        respond(['party' => user_party()]);
    }],

    'items' => ['GET', function () {
        $table = item_table();
        $party = user_party();
        $rows = query("SELECT * FROM $table WHERE party_id = ? ORDER BY created_at DESC", [$party['id']])->fetchAll();
        respond(['items' => array_map(fn ($row) => item_out($table, $row), $rows)]);
    }],

    'create_item' => ['POST', function () {
        $table = item_table();
        $party = user_party();
        $fields = item_fields($table);
        if (!isset($fields[REQUIRED_ITEM_FIELD[$table]])) {
            fail(400, 'Missing ' . REQUIRED_ITEM_FIELD[$table]);
        }
        if (query("SELECT COUNT(*) FROM $table WHERE party_id = ?", [$party['id']])->fetchColumn() >= MAX_ITEMS_PER_LIST) {
            fail(400, 'This list is full (' . MAX_ITEMS_PER_LIST . ' items). Delete some items to add more.');
        }
        $id = uuid();
        $columns = array_merge(['id', 'party_id'], array_keys($fields));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        query(
            "INSERT INTO $table (" . implode(', ', $columns) . ") VALUES ($placeholders)",
            array_merge([$id, $party['id']], array_values($fields))
        );
        respond(['item' => find_item($table, $id, $party['id'])]);
    }],

    'update_item' => ['POST', function () {
        $table = item_table();
        $party = user_party();
        $id = input()['id'] ?? null;
        $fields = item_fields($table);
        if (!is_uuid($id) || !$fields) {
            fail(400, 'Nothing to update');
        }
        $assignments = implode(', ', array_map(fn ($column) => "$column = ?", array_keys($fields)));
        query(
            "UPDATE $table SET $assignments WHERE id = ? AND party_id = ?",
            array_merge(array_values($fields), [$id, $party['id']])
        );
        respond(['item' => find_item($table, $id, $party['id']) ?? fail(404, 'Item not found')]);
    }],

    'delete_item' => ['POST', function () {
        $table = item_table();
        $party = user_party();
        $id = input()['id'] ?? null;
        if (!is_uuid($id)) {
            fail(400, 'Invalid id');
        }
        query("DELETE FROM $table WHERE id = ? AND party_id = ?", [$id, $party['id']]);
        respond(['ok' => true]);
    }],

    'visit' => ['POST', function () {
        $visitorId = input()['visitor_id'] ?? null;
        if (!is_uuid($visitorId)) {
            fail(400, 'Invalid visitor');
        }
        $visitorId = strtolower($visitorId);
        $user = current_user();
        if (!query('SELECT 1 FROM visits WHERE visitor_id = ? LIMIT 1', [$visitorId])->fetchColumn()) {
            // Cap the new visitor IDs one client can create per day, so nobody can inflate the numbers
            if (is_throttled('new_visitor', null, 1440, 20)) {
                respond(['ok' => true]);
            }
            record_attempt('new_visitor', null);
        }
        query(
            'INSERT IGNORE INTO visits (visitor_id, day, mode, user_id) VALUES (?, ?, ?, ?)',
            [$visitorId, local_day(), $user ? 'account' : 'guest', $user['id'] ?? null]
        );
        respond(['ok' => true]);
    }],

    'stats' => ['GET', function () {
        if (!public_user(require_user())['is_admin']) {
            fail(403, 'This account does not have access to analytics');
        }
        $people = PEOPLE_BY_DAY;
        $windows = [];
        foreach (STATS_WINDOWS as $days) {
            $start = local_day(-($days - 1));
            // People active in the window; returning means active on 2 or more days in total
            $rows = query(
                "SELECT mode, COUNT(*) AS users, SUM(active_days >= 2) AS returning_users FROM (
                     SELECT mode, person, COUNT(*) AS active_days FROM ($people) p
                     GROUP BY mode, person HAVING MAX(day) >= ?
                 ) active GROUP BY mode",
                [$start]
            )->fetchAll();
            $counts = array_column($rows, null, 'mode');
            $windows[] = [
                'days' => $days,
                'start' => $start,
                'guests' => (int) ($counts['guest']['users'] ?? 0),
                'accounts' => (int) ($counts['account']['users'] ?? 0),
                'returning_guests' => (int) ($counts['guest']['returning_users'] ?? 0),
                'returning_accounts' => (int) ($counts['account']['returning_users'] ?? 0),
            ];
        }
        $daily = query(
            "SELECT day, mode, COUNT(*) AS users FROM ($people) p WHERE day >= ? GROUP BY day, mode ORDER BY day",
            [local_day(-(max(STATS_WINDOWS) - 1))]
        )->fetchAll();
        $since = query('SELECT MIN(day) FROM visits')->fetchColumn() ?: null;
        respond(['today' => local_day(), 'since' => $since, 'windows' => $windows, 'daily' => $daily]);
    }],
];

// ---------------------------------------------------------------------------
// Dispatch
// ---------------------------------------------------------------------------

try {
    $action = $_GET['action'] ?? '';
    if (!is_string($action) || !isset($actions[$action])) {
        fail(404, 'Unknown action');
    }
    [$method, $handler] = $actions[$action];
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        fail(405, 'Method not allowed');
    }
    // Writes must be JSON: a cross-site form can't send that without a CORS preflight,
    // which this API never grants. Together with SameSite=Lax cookies this blocks CSRF.
    if ($method === 'POST' && !str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        fail(415, 'Expected a JSON body');
    }
    $handler();
} catch (ApiError $e) {
    respond(['error' => $e->getMessage()], $e->getCode());
} catch (Throwable $e) {
    error_log('Party Cat API error: ' . $e->getMessage());
    respond(['error' => 'Something went wrong. Please try again.'], 500);
}
