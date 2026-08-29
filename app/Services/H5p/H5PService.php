<?php

namespace App\Services\H5p;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

/**
 * Thin HTTP client for the dedicated H5P server (see h5p-server/). Laravel
 * never parses or stores H5P internals; every method here is a direct
 * pass-through to that service's REST API. The shared API key is only ever
 * used server-to-server — the browser talks to the H5P server directly for
 * the editor/player runtime assets (CORS-protected), but the content CRUD
 * lifecycle always flows through Laravel so ownership can be checked first.
 */
class H5PService
{
    protected function client(): PendingRequest
    {
        return Http::baseUrl(config('services.h5p.url'))
            ->withHeaders(['X-H5P-Api-Key' => config('services.h5p.key')])
            ->acceptJson();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listContent(): array
    {
        return $this->client()->get('/api/content')->throw()->json();
    }

    /**
     * Lightweight display metadata for a single content id — for showing a
     * lesson block's H5P summary without paying for the full render model
     * that editorModel()/playerModel() carry.
     *
     * @return array<string, mixed>|null
     */
    public function get(string $contentId): ?array
    {
        $response = $this->client()->get("/api/content/{$contentId}");

        return $response->successful() ? $response->json() : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function newEditorModel(): array
    {
        return $this->client()->get('/api/content/editor-model')->throw()->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function editorModel(string $contentId): array
    {
        return $this->client()->get("/api/content/{$contentId}/editor-model")->throw()->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function playerModel(string $contentId): array
    {
        return $this->client()->get("/api/content/{$contentId}/player-model")->throw()->json();
    }

    /**
     * Create (when $contentId is null) or update a piece of H5P content.
     *
     * @param  array<string, mixed>  $payload  Must contain parameters, metadata and mainLibraryUbername.
     * @return array<string, mixed>
     */
    public function save(?string $contentId, array $payload): array
    {
        $request = $this->client();

        return $contentId
            ? $request->patch("/api/content/{$contentId}", $payload)->throw()->json()
            : $request->post('/api/content', $payload)->throw()->json();
    }

    public function delete(string $contentId): void
    {
        $this->client()->delete("/api/content/{$contentId}")->throw();
    }

    public function export(string $contentId): Response
    {
        return $this->client()->get("/api/content/{$contentId}/export")->throw();
    }

    /**
     * @return array<string, mixed>
     */
    public function importPackage(UploadedFile $file): array
    {
        return Http::baseUrl(config('services.h5p.url'))
            ->withHeaders(['X-H5P-Api-Key' => config('services.h5p.key')])
            ->acceptJson()
            ->attach('file', $file->get(), $file->getClientOriginalName())
            ->post('/api/content/import')
            ->throw()
            ->json();
    }
}
