<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SeriesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'kind' => $this->kind,
            'topic' => $this->relationLoaded('topic') && $this->topic
                ? ['slug' => $this->topic->slug, 'name' => $this->topic->name, 'icon' => $this->topic->icon]
                : null,
            'count' => $this->items_count ?? $this->itemsCount(),
        ];
    }
}
