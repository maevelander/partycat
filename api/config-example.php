<?php
// Configuration template for the Party Cat API
// Copy this file to api/config.php and fill in your actual values.
// config.php contains secrets: it is gitignored and denied over HTTP by api/.htaccess.

return [
    // MySQL / MariaDB connection
    'db_host' => '127.0.0.1',
    'db_name' => 'partycat',
    'db_user' => 'partycat',
    'db_pass' => 'your-database-password',

    // Public URL where the app is served, used in password reset emails
    'app_url' => 'https://example.com',

    // Sender address for password reset emails
    'mail_from' => 'Party Cat <noreply@example.com>',

    // Mailbox used to send password reset emails over authenticated SMTP (SSL)
    'smtp_host' => 'smtp.example.com',
    'smtp_port' => 465,
    'smtp_user' => 'noreply@example.com',
    'smtp_pass' => 'your-mailbox-password',

    // Timezone for counting visits per day on the analytics dashboard
    'timezone' => 'UTC',
    // Accounts allowed to view analytics.html (leave empty to switch the dashboard off)
    'admin_emails' => [],

    // Set to false only for local development over plain http://
    'secure_cookies' => true,
];
