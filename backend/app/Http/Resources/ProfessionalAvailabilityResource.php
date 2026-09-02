<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ProfessionalAvailability',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'weekday', type: 'integer', description: '0=domingo ... 6=sábado', example: 1),
        new OA\Property(property: 'start_time', type: 'string', example: '08:00'),
        new OA\Property(property: 'end_time', type: 'string', example: '18:00'),
    ]
)]
class ProfessionalAvailabilityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'weekday' => $this->weekday,
            'start_time' => substr((string) $this->start_time, 0, 5),
            'end_time' => substr((string) $this->end_time, 0, 5),
        ];
    }
}
