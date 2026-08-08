<?php

$operationsEmails = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('DELAY_ALERT_EMAILS', ''))
)));

return [
    'enabled' => env('DELAY_ALERT_ENABLED', true),
    'notify_customer' => env('DELAY_ALERT_NOTIFY_CUSTOMER', true),
    'operations_emails' => $operationsEmails,
    'max_attempts' => (int) env('DELAY_ALERT_MAX_ATTEMPTS', 5),
    'retry_after_minutes' => (int) env('DELAY_ALERT_RETRY_AFTER_MINUTES', 60),
    'processing_timeout_minutes' => (int) env('DELAY_ALERT_PROCESSING_TIMEOUT_MINUTES', 5),
    'webhook' => [
        'url' => env('DELAY_ALERT_WEBHOOK_URL'),
        'secret' => env('DELAY_ALERT_WEBHOOK_SECRET'),
        'timeout' => (int) env('DELAY_ALERT_WEBHOOK_TIMEOUT', 10),
    ],
];
