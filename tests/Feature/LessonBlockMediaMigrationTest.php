<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LessonBlockMediaMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_pdf_and_video_blocks_are_converted_to_media_blocks(): void
    {
        $lessonId = $this->seedLesson();

        $pdfBlockId = DB::table('lesson_blocks')->insertGetId([
            'lesson_id' => $lessonId,
            'block_type' => 'pdf',
            'position' => 0,
            'title' => 'Notes',
            'content' => json_encode(['path' => 'lesson-pdfs/notes.pdf', 'original_name' => 'notes.pdf', 'size' => 1234]),
            'settings' => json_encode([]),
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $videoBlockId = DB::table('lesson_blocks')->insertGetId([
            'lesson_id' => $lessonId,
            'block_type' => 'video',
            'position' => 1,
            'title' => null,
            'content' => json_encode(['source' => 'external', 'path' => null, 'url' => 'https://youtube.com/watch?v=abc', 'provider' => 'youtube']),
            'settings' => json_encode([]),
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_07_17_071830_convert_pdf_and_video_blocks_to_media.php');
        $migration->up();

        $pdfBlock = DB::table('lesson_blocks')->find($pdfBlockId);
        $this->assertSame('media', $pdfBlock->block_type);
        $this->assertSame('{}', $pdfBlock->content);

        $pdfMediaItem = DB::table('media_items')->where('lesson_block_id', $pdfBlockId)->first();
        $this->assertNotNull($pdfMediaItem);
        $this->assertSame('pdf', $pdfMediaItem->media_type);
        $this->assertSame('lesson-pdfs/notes.pdf', $pdfMediaItem->file_path);
        $this->assertSame('notes.pdf', $pdfMediaItem->original_name);
        $this->assertSame('draft', $pdfMediaItem->status);

        $videoBlock = DB::table('lesson_blocks')->find($videoBlockId);
        $this->assertSame('media', $videoBlock->block_type);

        $videoMediaItem = DB::table('media_items')->where('lesson_block_id', $videoBlockId)->first();
        $this->assertNotNull($videoMediaItem);
        $this->assertSame('video_youtube', $videoMediaItem->media_type);
        $this->assertSame('https://youtube.com/watch?v=abc', $videoMediaItem->external_url);
        $this->assertSame('published', $videoMediaItem->status);
    }

    private function seedLesson(): int
    {
        $tutorUserId = DB::table('users')->insertGetId([
            'first_name' => 'Test', 'last_name' => 'Tutor', 'email' => 'migration-tutor@test.com',
            'password' => bcrypt('password'), 'role' => 'tutor', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $tutorProfileId = DB::table('tutor_profiles')->insertGetId([
            'user_id' => $tutorUserId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $subjectId = DB::table('subjects')->insertGetId(['name' => 'Mathematics', 'slug' => 'mathematics', 'status' => 'active', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $gradeId = DB::table('grades')->insertGetId(['name' => 'Grade 10', 'level' => 10, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $curriculumId = DB::table('curricula')->insertGetId(['name' => 'CAPS', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $courseId = DB::table('courses')->insertGetId([
            'tutor_profile_id' => $tutorProfileId, 'curriculum_id' => $curriculumId, 'grade_id' => $gradeId, 'subject_id' => $subjectId,
            'title' => 'Algebra', 'description' => 'Algebra course.', 'estimated_duration_minutes' => 60,
            'difficulty' => 'beginner', 'language' => 'English', 'status' => 'draft',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $chapterId = DB::table('chapters')->insertGetId([
            'course_id' => $courseId, 'title' => 'Introduction', 'position' => 0, 'status' => 'draft',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('lessons')->insertGetId([
            'chapter_id' => $chapterId, 'title' => 'Lesson 1', 'position' => 0, 'status' => 'draft',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
