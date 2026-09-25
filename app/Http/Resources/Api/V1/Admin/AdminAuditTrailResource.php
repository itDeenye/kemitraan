<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\ApiResource;
use App\Models\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use JsonException;

class AdminAuditTrailResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $auditTrail = $this->resource instanceof AuditTrail ? $this->resource : null;

        return [
            'id' => $this->value('id', 'audittrail_id'),
            'administrator' => [
                'id' => $this->value('admin_id', 'audittrail_admin_id'),
                'name' => $this->value('admin_name', 'audittrail_admin_name'),
            ],
            'menu_name' => $this->value('menu_name', 'audittrail_menu_name'),
            'description' => $this->value('description', 'audittrail_desc'),
            'action' => $this->value('action', 'audittrail_act'),
            'ip_address' => $this->value('ip_address', 'audittrail_ip_address'),
            'user_agent' => $this->value('user_agent', 'audittrail_user_agent'),
            'happened_at' => $this->value('happened_at', 'audittrail_datetime'),
            ...($auditTrail === null ? [] : [
                'payload' => $this->decodeAndSanitize($auditTrail->audittrail_payload),
                'results' => $this->decodeAndSanitize($auditTrail->audittrail_results),
            ]),
        ];
    }

    private function value(string $listField, string $modelField): mixed
    {
        return $this->resource instanceof AuditTrail
            ? $this->resource->getAttribute($modelField)
            : $this->resource->{$listField};
    }

    private function decodeAndSanitize(?string $json): mixed
    {
        if ($json === null || $json === '') {
            return null;
        }

        try {
            return $this->sanitize(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
        } catch (JsonException) {
            return null;
        }
    }

    private function sanitize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            if (is_string($key) && Str::contains(Str::lower($key), ['password', 'token', 'secret', 'pin'])) {
                $value[$key] = '[DISEMBUNYIKAN]';

                continue;
            }

            $value[$key] = $this->sanitize($item);
        }

        return $value;
    }
}
