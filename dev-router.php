<?php
// Local dev only (php -S localhost:8000 dev-router.php): the built-in server ignores
// .htaccess, so this copies its /dashboard rules. Never deploy this file.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/dashboard/') {
    header('Location: /dashboard', true, 301);
    return true;
}
if ($path === '/dashboard') {
    header('Content-Type: text/html; charset=UTF-8');
    readfile(__DIR__ . '/dashboard.html');
    return true;
}
return false;
