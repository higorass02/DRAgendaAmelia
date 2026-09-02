<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Patient',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Maria Silva'),
        new OA\Property(property: 'cpf', type: 'string', example: '11144477735'),
        new OA\Property(property: 'phone', type: 'string', example: '11999998888'),
        new OA\Property(property: 'email', type: 'string', nullable: true, example: 'maria@example.com'),
        new OA\Property(property: 'birth_date', type: 'string', format: 'date', example: '1990-05-10'),
    ]
)]
class PatientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'cpf' => $this->cpf,
            'phone' => $this->phone,
            'email' => $this->email,
            'birth_date' => $this->birth_date->format('Y-m-d'),
        ];
    }
}
