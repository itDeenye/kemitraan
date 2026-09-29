<?php

namespace App\Http\Resources\Api\V1\Member;

use App\Http\Resources\ApiResource;
use App\Support\MediaUrl;
use Illuminate\Http\Request;

class MemberGenealogyResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $directDownlineCount = (int) $this->resource->direct_downline_count;

        return [
            'id' => (int) $this->resource->id,
            'code' => $this->resource->code,
            'name' => $this->resource->name,
            'image' => MediaUrl::publicUrl($this->resource->image_url),
            'image_url' => MediaUrl::publicUrl($this->resource->image_url),
            'level' => [
                'id' => (int) $this->resource->level_id,
                'code' => $this->resource->level_code,
                'name' => $this->resource->level_name,
            ],
            'joined_at' => $this->resource->joined_at,
            'total_direct_downlines' => $directDownlineCount,
            'has_downlines' => $directDownlineCount > 0,
        ];
    }
}
