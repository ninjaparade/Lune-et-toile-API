<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GameResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uid' => $this->uid,
            'status' => $this->status,
            'created_by' => $this->created_by,
            'white' => $this->white ? new PlayerResource($this->white) : null,
            'black' => $this->black ? new PlayerResource($this->black) : null,
            'invited' => $this->invited ? new PlayerResource($this->invited) : null,
            'moves' => $this->moves,
            'result' => $this->result,
            'termination' => $this->termination,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
