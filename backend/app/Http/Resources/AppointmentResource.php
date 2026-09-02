<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Appointment',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'patient', properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'name', type: 'string'),
        ], type: 'object'),
        new OA\Property(property: 'professional', properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'specialty', type: 'string'),
        ], type: 'object'),
        new OA\Property(property: 'start_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'end_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'status', properties: [
            new OA\Property(property: 'value', type: 'string', example: 'scheduled'),
            new OA\Property(property: 'label', type: 'string', example: 'Agendada'),
        ], type: 'object'),
        new OA\Property(property: 'rescheduled_from_id', type: 'integer', nullable: true),
        new OA\Property(property: 'rescheduled_to_id', type: 'integer', nullable: true),
    ]
)]
class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient' => [
                'id' => $this->patient->id,
                'name' => $this->patient->name,
            ],
            'professional' => [
                'id' => $this->professional->id,
                'name' => $this->professional->name,
                'specialty' => $this->professional->specialty,
            ],
            'start_at' => $this->start_at->toIso8601String(),
            'end_at' => $this->end_at->toIso8601String(),
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'rescheduled_from_id' => $this->rescheduled_from_id,
            'rescheduled_to_id' => $this->rescheduled_to_id,
            'created_by' => [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ],
        ];
    }
}
