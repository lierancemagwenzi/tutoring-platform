<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps a single item from the H5P server's content list response
 * (id, title, mainLibrary, language) — see H5PService::listContent().
 */
class H5pContentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource['id'],
            'title' => $this->resource['title'] ?? null,
            'main_library' => $this->resource['mainLibrary'] ?? null,
            'language' => $this->resource['language'] ?? null,
        ];
    }
}
