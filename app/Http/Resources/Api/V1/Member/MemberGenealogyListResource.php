<?php

namespace App\Http\Resources\Api\V1\Member;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

class MemberGenealogyListResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'context' => $this->resource['context'],
            'results' => MemberGenealogyResource::collection($this->resource['results']),
            'pagination' => $this->resource['pagination'],
        ];
    }
}
