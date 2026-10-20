<?php

namespace App\Http\Resources\Rates;

use App\Models\Rooms\SeasonalDiscount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SeasonalDiscount */
class SeasonalDiscountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on->toDateString(),
            'percent' => $this->percent,
            'room_type_id' => $this->room_type_id,
            'is_active' => $this->is_active,
            'room_type' => $this->roomType ? ['id' => $this->roomType->id, 'name' => $this->roomType->name] : null,
        ];
    }
}
