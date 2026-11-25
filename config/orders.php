<?php

$timeoutMinutes = env('DNY_PAYMENT_UPLOAD_TIMEOUT_MINUTES', 1440);

return [
    'payment_upload_timeout_minutes' => filter_var(
        is_bool($timeoutMinutes) ? null : $timeoutMinutes,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]],
    ) ?: 1440,
];
