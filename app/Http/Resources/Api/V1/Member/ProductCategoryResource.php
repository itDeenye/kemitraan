<?php

namespace App\Http\Resources\Api\V1\Member;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

class ProductCategoryResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->product_category_id,
            'name' => $this->product_category_name,
            'description' => $this->product_category_description,
        ];
    }
}
