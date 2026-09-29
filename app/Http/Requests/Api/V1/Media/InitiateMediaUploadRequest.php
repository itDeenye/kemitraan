<?php

namespace App\Http\Requests\Api\V1\Media;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class InitiateMediaUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'collection' => ['required', 'string', 'max:100'],
            'filename' => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'string', 'max:100'],
            'size' => ['required', 'integer', 'min:1', 'max:'.config('media.max_size')],
            'checksum' => ['nullable', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/i'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $allowedMimeTypes = config(
                    "media.collections.{$this->input('collection')}.mime_types",
                    config('media.mime_types', [])
                );

                if (! in_array($this->input('mime_type'), $allowedMimeTypes, true)) {
                    $validator->errors()->add('mime_type', 'Tipe media tidak diizinkan.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'collection' => 'koleksi media',
            'filename' => 'nama berkas',
            'mime_type' => 'tipe media',
            'size' => 'ukuran media',
            'checksum' => 'kode pemeriksaan berkas',
        ];
    }
}
