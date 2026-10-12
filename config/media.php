<?php

return [
    'chunk_size' => (int) env('MEDIA_CHUNK_SIZE_MB', 1) * 1024 * 1024,
    'max_size' => (int) env('MEDIA_MAX_SIZE_MB', 10240) * 1024 * 1024,
    'expires_hours' => (int) env('MEDIA_UPLOAD_EXPIRES_HOURS', 24),
    'temporary_disk' => env('MEDIA_TEMPORARY_DISK', 'local'),
    'default_disk' => env('MEDIA_DEFAULT_DISK', 'public'),
    'ffmpeg_binary' => env('MEDIA_FFMPEG_BINARY', 'ffmpeg'),
    'compression_enabled' => env('MEDIA_COMPRESSION_ENABLED', true),
    'image_quality' => (int) env('MEDIA_IMAGE_QUALITY', 80),
    'image_max_width' => (int) env('MEDIA_IMAGE_MAX_WIDTH', 1600),
    'video_crf' => (int) env('MEDIA_VIDEO_CRF', 28),
    'video_max_width' => (int) env('MEDIA_VIDEO_MAX_WIDTH', 1280),
    'compression_timeout' => (int) env('MEDIA_COMPRESSION_TIMEOUT_SECONDS', 120),

    'mime_types' => [
        'image/jpeg',
        'image/png',
        'image/webp',
        'video/mp4',
        'video/quicktime',
        'video/webm',
        'application/pdf',
    ],

    'collections' => [
        'product' => [
            'disk' => 'public',
            'mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
        ],
        'profile' => [
            'disk' => 'public',
            'mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
        ],
        'profile_photos' => [
            'disk' => 'public',
            'mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
        ],
        'payment_proof' => [
            'disk' => 'local',
            'mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
        ],
        'payment_receipts' => [
            'disk' => 'local',
            'mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
        ],
        'return_proof' => [
            'disk' => 'local',
            'mime_types' => [
                'image/jpeg',
                'image/png',
                'image/webp',
                'video/mp4',
                'video/quicktime',
                'video/webm',
            ],
        ],
        'general' => [
            'disk' => 'local',
            'mime_types' => [
                'image/jpeg',
                'image/png',
                'image/webp',
                'video/mp4',
                'video/quicktime',
                'video/webm',
                'application/pdf',
            ],
        ],
    ],

    'references' => [
        'ref_country' => ['country_flag'],
        'ref_bank' => ['bank_logo'],
        'warehouse' => ['warehouse_logo'],
        'site_administrator' => ['administrator_image'],
        'member' => ['member_identity_image', 'member_image'],
        'member_registration' => ['member_registration_identity_image'],
        'product' => ['product_image'],
        'stockist' => ['stockist_image'],
        'reward_annual' => ['reward_annual_image'],
        'trx_payment_transfer' => ['payment_transfer_receipt_file'],
        'trx_spread_payment' => ['trx_spread_payment_receipt_file'],
        'return' => ['return_attachment_image_url_json', 'return_attachment_video_url'],
    ],
];
