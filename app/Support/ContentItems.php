<?php

namespace App\Support;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The ordered item list behind both the Tutor-Led "content" lesson block and
 * the Self-Paced "content" activity: any number and mix of rich text, maths,
 * Mermaid diagrams and files. A file item's media_item_id is the id of a file
 * row owned by the same container — a MediaItem for a lesson block, a
 * SelfPacedActivityAttachment for a self-paced activity.
 */
class ContentItems
{
    public const TYPES = ['rich_text', 'math', 'mermaid', 'media'];

    /**
     * Validation rules for an item list found at $path (e.g. 'items' or
     * 'content.items').
     *
     * @return array<string, mixed>
     */
    public static function rules(string $path, bool $required): array
    {
        return [
            $path => [$required ? 'present' : 'nullable', 'array', 'max:200'],
            "{$path}.*.id" => ['nullable', 'string', 'max:64'],
            "{$path}.*.type" => ['required', Rule::in(self::TYPES)],
            "{$path}.*.html" => ["required_if:{$path}.*.type,rich_text", 'nullable', 'string'],
            "{$path}.*.json" => ['nullable', 'array'],
            "{$path}.*.latex" => ["required_if:{$path}.*.type,math", 'nullable', 'string', 'max:10000'],
            "{$path}.*.display_mode" => ['nullable', 'boolean'],
            "{$path}.*.diagram" => ["required_if:{$path}.*.type,mermaid", 'nullable', 'string', 'max:20000'],
            "{$path}.*.media_item_id" => ["required_if:{$path}.*.type,media", 'nullable', 'integer'],
        ];
    }

    /**
     * Keep only the fields that belong to each item's type, and give every
     * item a stable id.
     *
     * @param  iterable<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public static function normalise(iterable $items): array
    {
        $normalised = [];

        foreach ($items as $item) {
            $base = ['id' => $item['id'] ?? (string) Str::uuid(), 'type' => $item['type']];

            $normalised[] = match ($item['type']) {
                'rich_text' => [...$base, 'html' => $item['html'] ?? '', 'json' => $item['json'] ?? null],
                'math' => [...$base, 'latex' => $item['latex'] ?? '', 'display_mode' => filter_var($item['display_mode'] ?? false, FILTER_VALIDATE_BOOLEAN)],
                'mermaid' => [...$base, 'diagram' => $item['diagram'] ?? ''],
                'media' => [...$base, 'media_item_id' => (int) $item['media_item_id']],
            };
        }

        return $normalised;
    }

    /**
     * The file-row ids an item list refers to.
     *
     * @param  iterable<array<string, mixed>>  $items
     * @return list<int>
     */
    public static function mediaIds(iterable $items): array
    {
        $ids = [];

        foreach ($items as $item) {
            if (($item['type'] ?? null) === 'media') {
                $ids[] = (int) $item['media_item_id'];
            }
        }

        return $ids;
    }
}
