<?php

namespace App\Models;

use App\Enums\FaqAudience;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'question',
        'answer',
        'audience',
        'is_published',
        'position',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'audience' => FaqAudience::class,
            'is_published' => 'boolean',
        ];
    }
}
