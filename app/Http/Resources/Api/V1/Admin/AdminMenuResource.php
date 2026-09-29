<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

class AdminMenuResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'title' => $this->resource['title'],
            'description' => $this->resource['description'],
            'route' => $this->resource['route'],
            'icon' => $this->resource['icon'],
            'css_class' => $this->resource['css_class'],
            'sort_order' => $this->resource['sort_order'],
            'children' => self::collection($this->resource['children'])->resolve($request),
        ];
    }
}
