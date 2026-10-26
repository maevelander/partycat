<?php
// Configuration template for the Party Cat API
// Copy this file to api/config.php and fill in your actual values.
// config.php contains secrets: it is gitignored and denied over HTTP by api/.htaccess.

return [
    // MySQL connection. On shared hosting the database is usually on the same server (127.0.0.1).
    'db_host' => '127.0.0.1',
    'db_name' => 'u000000000_partycat',
    'db_user' => 'u000000000_partycat',
    'db_pass' => 'your-database-password',

    // Public URL of the app, used in password reset emails
    'app_url' => 'https://partycat.app',

    // Sender address for password reset emails
    'mail_from' => 'Party Cat <noreply@partycat.app>',

    // Mailbox used to send password reset emails over authenticated SMTP (SSL)
    'smtp_host' => 'smtp.example.com',
    'smtp_port' => 465,
    'smtp_user' => 'noreply@partycat.app',
    'smtp_pass' => 'your-mailbox-password',

    // Timezone for counting visits per day on the analytics dashboard
    'timezone' => 'Australia/Perth',
    // Accounts allowed to view analytics.html
    'admin_emails' => ['you@example.com'],

    // Set to false only for local development over plain http://
    'secure_cookies' => true,
];
