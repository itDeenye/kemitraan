<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DataTableResource extends ApiResource
{
    /**
     * @param  array{results: mixed, pagination?: array<string, mixed>}  $resource
     * @param  class-string<JsonResource>  $itemResource
     */
    public function __construct($resource, private readonly string $itemResource)
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $itemResource = $this->itemResource;
        $data = [
            'results' => $itemResource::collection($this->resource['results']),
        ];

        if (isset($this->resource['pagination'])) {
            $data['pagination'] = $this->resource['pagination'];
        }

        return $data;
    }
}
