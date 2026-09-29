<?php

namespace App\Http\Requests\Api\V1\Admin\System;

use App\Models\Config;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use JsonException;

class SaveConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'value' => ['present'],
            'type' => ['sometimes', 'string', 'in:string,integer,json,boolean'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $config = $this->route('config');
                $type = $this->input('type');

                if (! is_string($type) && $config instanceof Config) {
                    $type = $config->config_type;
                }

                if (is_string($type) && ! $this->valueMatchesType($type, $this->input('value'))) {
                    $validator->errors()->add('value', 'Nilai konfigurasi tidak sesuai dengan tipe yang dipilih.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'value' => 'nilai konfigurasi',
            'type' => 'tipe konfigurasi',
        ];
    }

    private function valueMatchesType(string $type, mixed $value): bool
    {
        return match ($type) {
            'integer' => is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1),
            'boolean' => is_bool($value) || in_array($value, [0, 1, '0', '1', 'true', 'false'], true),
            'json' => is_array($value) || $this->isValidJson($value),
            default => is_null($value) || is_scalar($value),
        };
    }

    private function isValidJson(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        try {
            json_decode($value, true, 512, JSON_THROW_ON_ERROR);

            return true;
        } catch (JsonException) {
            return false;
        }
    }
}
