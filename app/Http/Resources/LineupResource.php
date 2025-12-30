<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LineupResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'created_at' => $this->created_at->format('M j, Y'),
            'updated_at' => $this->updated_at->diffForHumans(),
            'total_artists' => $this->artists_count ?? $this->artists()->count(),
            'artists' => $this->whenLoaded('artists', function () {
                return $this->artists->map(function ($artist) {
                    return [
                        'id' => $artist->id,
                        'name' => $artist->name,
                        'image_url' => $artist->image_url,
                        'tier' => $artist->pivot->tier ?? 'undercard',
                    ];
                });
            }),
        ];
    }
}
