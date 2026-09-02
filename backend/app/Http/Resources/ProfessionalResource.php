<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Professional',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Dr. João'),
        new OA\Property(property: 'specialty', type: 'string', example: 'Cardiologia'),
        new OA\Property(
            property: 'availabilities',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/ProfessionalAvailability')
        ),
    ]
)]
class ProfessionalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'specialty' => $this->specialty,
            'availabilities' => ProfessionalAvailabilityResource::collection($this->whenLoaded('availabilities')),
        ];
    }
}
