<?php

namespace App\Services\Admin;

use App\Models\Faq;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Admin CRUD for the FAQ library. Unlike Subjects, nothing else in the
 * schema references a Faq, so deletion is a real hard delete rather than
 * an archive-only terminal state.
 */
class FaqManagementService
{
    /**
     * @param  array{search?: string, audience?: string, is_published?: string}  $filters
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Faq::query();

        if (! empty($filters['search'])) {
            $query->where('question', 'like', '%'.$filters['search'].'%');
        }

        if (! empty($filters['audience'])) {
            $query->where('audience', $filters['audience']);
        }

        if (isset($filters['is_published']) && $filters['is_published'] !== '') {
            $query->where('is_published', filter_var($filters['is_published'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->orderBy('position')->orderBy('id')->paginate($perPage);
    }

    /**
     * @param  array{question: string, answer: string, audience: string, is_published?: bool, position?: int}  $data
     */
    public function create(array $data): Faq
    {
        return Faq::create([
            'question' => $data['question'],
            'answer' => $data['answer'],
            'audience' => $data['audience'],
            'is_published' => $data['is_published'] ?? true,
            'position' => $data['position'] ?? 0,
        ]);
    }

    /**
     * @param  array{question?: string, answer?: string, audience?: string, is_published?: bool, position?: int}  $data
     */
    public function update(Faq $faq, array $data): Faq
    {
        $faq->update([
            'question' => $data['question'] ?? $faq->question,
            'answer' => $data['answer'] ?? $faq->answer,
            'audience' => $data['audience'] ?? $faq->audience,
            'is_published' => array_key_exists('is_published', $data) ? $data['is_published'] : $faq->is_published,
            'position' => $data['position'] ?? $faq->position,
        ]);

        return $faq;
    }

    public function delete(Faq $faq): void
    {
        $faq->delete();
    }
}
