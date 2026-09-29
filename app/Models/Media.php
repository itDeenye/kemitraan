<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_UPLOADING = 'uploading';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    public const STATUS_ABORTED = 'aborted';

    protected $table = 'media';

    protected $primaryKey = 'media_id';

    public const CREATED_AT = 'media_created_datetime';

    public const UPDATED_AT = 'media_updated_datetime';

    protected $fillable = [
        'media_uuid',
        'media_uploader_type',
        'media_uploader_id',
        'media_collection',
        'media_original_name',
        'media_original_mime_type',
        'media_original_size',
        'media_mime_type',
        'media_size',
        'media_chunk_size',
        'media_total_chunks',
        'media_uploaded_chunks',
        'media_status',
        'media_disk',
        'media_path',
        'media_checksum',
        'media_error_message',
        'media_expires_at',
    ];

    protected $attributes = [
        'media_uploaded_chunks' => 0,
        'media_status' => self::STATUS_PENDING,
    ];

    public function getRouteKeyName(): string
    {
        return 'media_uuid';
    }

    protected function casts(): array
    {
        return [
            'media_original_size' => 'integer',
            'media_size' => 'integer',
            'media_chunk_size' => 'integer',
            'media_total_chunks' => 'integer',
            'media_uploaded_chunks' => 'integer',
            'media_expires_at' => 'datetime',
            'media_created_datetime' => 'datetime',
            'media_updated_datetime' => 'datetime',
        ];
    }
}
