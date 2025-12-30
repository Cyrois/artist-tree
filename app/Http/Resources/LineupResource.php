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
            'createdAt' => $this->created_at->format('M j, Y'),
            'updatedAt' => $this->updated_at->diffForHumans(),
            'stats' => [
                'artistCount' => $this->artists->count(),
                'avgScore' => 0,
                'confirmedCount' => 0,
                'pendingCount' => 0,
                'totalBudget' => 0,
            ],
            'previewArtists' => $this->artists->take(3)->map(fn ($artist) => [
                'id' => $artist->id,
                'name' => $artist->name,
                'image' => $artist->image_url,
            ]),
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
