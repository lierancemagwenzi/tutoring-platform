<?php

namespace App\Console\Commands;

use App\Models\AvailabilityDate;
use App\Models\Course;
use App\Models\Curriculum;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\SelfPacedSurveyContent;
use App\Models\ServiceCategory;
use App\Models\SessionFormat;
use App\Models\Subject;
use App\Models\TutorProfile;
use App\Models\TutorSubject;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One-off: wipe a tutor's authored content and reseed a fresh, complete
 * demo set (approved subjects, published services, 5 complete self-paced
 * courses, 2 weeks of availability, and Tutor-Led courses with real lesson
 * content). Throwaway — remove after running.
 */
class ResetTutorDemoData extends Command
{
    protected $signature = 'demo:reset-tutor {email}';

    protected $description = 'Clear a tutor\'s authored content and reseed fresh demo data.';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user || ! $user->tutorProfile) {
            $this->error('No tutor found for that email.');

            return self::FAILURE;
        }

        $tutor = $user->tutorProfile;

        DB::transaction(function () use ($tutor) {
            $this->wipe($tutor);
            $this->seed($tutor);
        });

        $this->info('Done.');

        return self::SUCCESS;
    }

    private function wipe(TutorProfile $tutor): void
    {
        // Soft-deletable models need forceDelete() to actually trigger the
        // real DB-level ON DELETE CASCADE on their dependents (bookings,
        // teaching sessions, modules/activities/assessments, enrollments
        // and all learner-progress rows under them).
        $tutor->services()->get()->each->forceDelete();
        $tutor->selfPacedCourses()->get()->each->forceDelete();

        $tutor->selfPacedSurveyContents()->delete();
        $tutor->tutorSubjects()->delete();
        $tutor->availabilityDates()->delete();
        Course::where('tutor_profile_id', $tutor->id)->delete();

        $this->line('Wiped existing subjects, services, self-paced courses, availability, and tutor-led courses.');
    }

    private function seed(TutorProfile $tutor): void
    {
        $subjects = Subject::whereIn('name', ['Mathematics', 'Physical Sciences', 'English', 'Information Technology'])
            ->get()->keyBy('name');
        $grades = Grade::whereIn('level', [10, 11, 12])->get()->keyBy('level');
        $curriculum = Curriculum::where('name', 'CAPS')->firstOrFail();
        $category = ServiceCategory::where('name', 'Private Lesson')->firstOrFail();
        $onlineFormat = SessionFormat::where('name', 'Online')->firstOrFail();

        // --- Approved subjects, each linked to the grades it's taught at ---
        $subjectGrades = [
            'Mathematics' => [10, 12],
            'Physical Sciences' => [11],
            'English' => [12],
            'Information Technology' => [10],
        ];

        $tutorSubjects = [];
        foreach ($subjectGrades as $subjectName => $levels) {
            $tutorSubject = TutorSubject::create([
                'tutor_profile_id' => $tutor->id,
                'subject_id' => $subjects[$subjectName]->id,
                'status' => 'approved',
            ]);
            foreach ($levels as $level) {
                $tutorSubject->tutorSubjectGrades()->create(['grade_id' => $grades[$level]->id]);
            }
            $tutorSubjects[$subjectName] = $tutorSubject;
        }
        $this->line('Created '.count($tutorSubjects).' approved subjects.');

        // --- Published services, one per subject ---
        $serviceDefs = [
            ['subject' => 'Mathematics', 'grade' => 10, 'title' => 'Grade 10 Mathematics Tutoring', 'price' => 350, 'sessions' => 8],
            ['subject' => 'Physical Sciences', 'grade' => 11, 'title' => 'Grade 11 Physical Sciences Tutoring', 'price' => 400, 'sessions' => 6],
            ['subject' => 'English', 'grade' => 12, 'title' => 'Grade 12 English Essay Coaching', 'price' => 300, 'sessions' => 4],
            ['subject' => 'Information Technology', 'grade' => 10, 'title' => 'Grade 10 IT Programming Tutoring', 'price' => 380, 'sessions' => 6],
        ];

        foreach ($serviceDefs as $def) {
            $tutor->services()->create([
                'subject_id' => $subjects[$def['subject']]->id,
                'grade_id' => $grades[$def['grade']]->id,
                'service_category_id' => $category->id,
                'session_format_id' => $onlineFormat->id,
                'title' => $def['title'],
                'description' => "One-on-one {$def['subject']} tutoring for Grade {$def['grade']} students, focused on building real exam-ready confidence.",
                'price' => $def['price'],
                'currency' => 'ZAR',
                'session_duration_minutes' => 60,
                'sessions_included' => $def['sessions'],
                'validity_period_days' => 60,
                'max_students_per_session' => 1,
                'visibility' => 'published',
            ]);
        }
        $this->line('Created '.count($serviceDefs).' published services.');

        // --- 2 weeks of availability ---
        $start = Carbon::today();
        for ($date = $start->copy(); $date->lt($start->copy()->addDays(14)); $date->addDay()) {
            $availabilityDate = AvailabilityDate::create(['tutor_profile_id' => $tutor->id, 'date' => $date->toDateString()]);
            $availabilityDate->slots()->create(['start_time' => '09:00', 'end_time' => '12:00']);
            $availabilityDate->slots()->create(['start_time' => '14:00', 'end_time' => '17:00']);
        }
        $this->line('Created 14 days of availability.');

        // --- 5 complete, published self-paced courses ---
        $this->seedSelfPacedCourses($tutor, $subjects, $grades, $curriculum);

        // --- Tutor-Led courses with real lesson content ---
        $this->seedTutorLedCourses($tutor, $subjects, $grades, $curriculum);
    }

    /**
     * @param  Collection<string, Subject>  $subjects
     * @param  Collection<int, Grade>  $grades
     */
    private function seedSelfPacedCourses(TutorProfile $tutor, $subjects, $grades, Curriculum $curriculum): void
    {
        $courses = [
            [
                'subject' => 'Mathematics', 'grade' => 10, 'difficulty' => 'beginner',
                'title' => 'Algebra Foundations: Grade 10 Mathematics',
                'subtitle' => 'Build a rock-solid foundation in algebraic thinking.',
                'description' => 'A step-by-step introduction to algebra for Grade 10 students — variables, expressions, and solving linear equations, with worked examples and a graded check at the end.',
                'objectives' => ['Understand what a variable represents', 'Simplify algebraic expressions', 'Solve linear equations confidently'],
                'module1' => 'Understanding Variables',
                'module1Activities' => [
                    ['type' => 'rich_text', 'title' => 'What Is a Variable?', 'html' => '<p>A <strong>variable</strong> is a letter or symbol that stands in for a number we don\'t know yet, or that can change. In the expression <code>3x + 5</code>, <code>x</code> is the variable.</p><p>Variables let us describe patterns and relationships without knowing every exact value up front — which is the whole point of algebra.</p>'],
                    ['type' => 'katex', 'title' => 'Simplifying Expressions', 'latex' => '3x + 2x - 4 = 5x - 4'],
                    ['type' => 'reading', 'title' => 'Practice Set', 'instructions' => "Simplify the following on paper before moving on:\n1) 4x + 3x - 2\n2) 7y - 2y + 5\n3) 2(x + 3) - x"],
                ],
                'module2' => 'Solving Linear Equations',
                'quizTitle' => 'Grade 10 Algebra Check',
                'quizQuestion' => ['title' => 'Solve for x: 2x + 4 = 10', 'choices' => ['x = 2', 'x = 3', 'x = 7'], 'correct' => 'x = 3'],
                'passingScore' => 70,
            ],
            [
                'subject' => 'Physical Sciences', 'grade' => 11, 'difficulty' => 'intermediate',
                'title' => 'Physical Sciences: Forces and Motion',
                'subtitle' => 'Newton\'s laws, explained clearly and applied practically.',
                'description' => 'Covers Grade 11 Mechanics — forces, Newton\'s three laws of motion, and how to apply them to real-world problems — finishing with a knowledge check.',
                'objectives' => ['State and apply Newton\'s three laws of motion', 'Calculate net force on an object', 'Explain everyday motion using physics concepts'],
                'module1' => 'Newton\'s Laws of Motion',
                'module1Activities' => [
                    ['type' => 'rich_text', 'title' => 'The Three Laws', 'html' => '<p><strong>Newton\'s First Law:</strong> An object at rest stays at rest, and an object in motion stays in motion, unless acted on by a net external force.</p><p><strong>Second Law:</strong> Force equals mass times acceleration (F = ma).</p><p><strong>Third Law:</strong> For every action, there is an equal and opposite reaction.</p>'],
                    ['type' => 'external_resource', 'title' => 'Further Reading: Khan Academy', 'url' => 'https://www.khanacademy.org/science/physics/forces-newtons-laws'],
                ],
                'module2' => 'Applying Force Calculations',
                'quizTitle' => 'Forces and Motion Knowledge Check',
                'quizQuestion' => ['title' => 'A 10 kg object accelerates at 2 m/s². What is the net force?', 'choices' => ['5 N', '20 N', '12 N'], 'correct' => '20 N'],
                'passingScore' => 70,
            ],
            [
                'subject' => 'English', 'grade' => 12, 'difficulty' => 'intermediate',
                'title' => 'Grade 12 English Essay Writing Mastery',
                'subtitle' => 'Structure, argue, and write essays examiners love to mark.',
                'description' => 'A practical course on writing strong argumentative essays for Grade 12 English — planning, structuring paragraphs, and building a persuasive conclusion.',
                'objectives' => ['Plan an essay before writing', 'Structure clear, well-supported paragraphs', 'Write a persuasive conclusion'],
                'module1' => 'Planning and Structure',
                'module1Activities' => [
                    ['type' => 'rich_text', 'title' => 'The Essay Skeleton', 'html' => '<p>Every strong essay follows a clear skeleton: an <strong>introduction</strong> that states your argument, <strong>body paragraphs</strong> that each make one point with evidence, and a <strong>conclusion</strong> that ties it together without repeating yourself.</p>'],
                    ['type' => 'homework', 'title' => 'Draft an Outline', 'instructions' => 'Choose one of this term\'s essay topics and write a one-page outline: introduction thesis, 3 body paragraph topics with one piece of evidence each, and a conclusion angle.'],
                ],
                'module2' => 'Persuasive Techniques',
                'quizTitle' => 'Essay Writing Check',
                'quizQuestion' => ['title' => 'Which belongs in an introduction?', 'choices' => ['A restated thesis', 'Your main argument (thesis statement)', 'A new, unrelated point'], 'correct' => 'Your main argument (thesis statement)'],
                'passingScore' => 70,
            ],
            [
                'subject' => 'Information Technology', 'grade' => 10, 'difficulty' => 'beginner',
                'title' => 'Introduction to Information Technology',
                'subtitle' => 'Programming logic and computer fundamentals for beginners.',
                'description' => 'An entry-level IT course covering how computers process information and the basics of programming logic, with a hands-on flowchart exercise.',
                'objectives' => ['Explain the input-process-output model', 'Read a simple flowchart', 'Understand basic programming logic'],
                'module1' => 'How Computers Think',
                'module1Activities' => [
                    ['type' => 'rich_text', 'title' => 'Input, Process, Output', 'html' => '<p>Every computer program follows the same basic pattern: it takes <strong>input</strong>, <strong>processes</strong> it according to a set of instructions, and produces <strong>output</strong>. Understanding this pattern is the first step to thinking like a programmer.</p>'],
                    ['type' => 'mermaid', 'title' => 'A Simple Decision Flowchart', 'syntax' => "flowchart TD\n    A[Start] --> B{Is it raining?}\n    B -- Yes --> C[Take an umbrella]\n    B -- No --> D[Leave the umbrella]\n    C --> E[End]\n    D --> E[End]"],
                ],
                'module2' => 'Programming Logic Basics',
                'quizTitle' => 'IT Fundamentals Check',
                'quizQuestion' => ['title' => 'Which of these is NOT part of the input-process-output model?', 'choices' => ['Input', 'Process', 'Storage'], 'correct' => 'Storage'],
                'passingScore' => 70,
            ],
            [
                'subject' => 'Mathematics', 'grade' => 12, 'difficulty' => 'advanced',
                'title' => 'Exam Ready: Mathematics Grade 12',
                'subtitle' => 'Final-year exam preparation across key topics.',
                'description' => 'A focused revision course for Grade 12 Mathematics, consolidating algebra, functions, and calculus fundamentals ahead of final exams.',
                'objectives' => ['Revise core Grade 12 algebra and functions', 'Apply basic differentiation rules', 'Build exam technique under time pressure'],
                'module1' => 'Functions and Calculus Revision',
                'module1Activities' => [
                    ['type' => 'rich_text', 'title' => 'Differentiation Refresher', 'html' => '<p>The derivative of a function tells you its <strong>rate of change</strong>. For a simple power function, the power rule states: bring the exponent down and subtract one from it.</p>'],
                    ['type' => 'katex', 'title' => 'Power Rule Example', 'latex' => '\\frac{d}{dx}\\,x^n = nx^{n-1}'],
                    ['type' => 'assignment', 'title' => 'Timed Practice Paper', 'instructions' => 'Complete Section A of the most recent past paper under exam timing (45 minutes, no notes). Mark it against the memo and note any recurring mistakes.'],
                ],
                'module2' => 'Final Exam Simulation',
                'quizTitle' => 'Grade 12 Final Readiness Check',
                'quizQuestion' => ['title' => "Differentiate: f(x) = x³. What is f'(x)?", 'choices' => ['3x²', 'x²', '3x'], 'correct' => '3x²'],
                'passingScore' => 75,
            ],
        ];

        foreach ($courses as $def) {
            $course = $tutor->selfPacedCourses()->create([
                'subject_id' => $subjects[$def['subject']]->id,
                'grade_id' => $grades[$def['grade']]->id,
                'title' => $def['title'],
                'subtitle' => $def['subtitle'],
                'description' => $def['description'],
                'promo_description' => $def['subtitle'],
                'difficulty' => $def['difficulty'],
                'language' => 'English',
                'estimated_duration_minutes' => 180,
                'learning_objectives' => $def['objectives'],
                'prerequisites' => [],
                'target_audience' => ["Grade {$def['grade']} {$def['subject']} students"],
                'status' => 'published',
                'visibility' => 'public',
                'price' => 249,
                'currency' => 'ZAR',
            ]);

            $module1 = $course->modules()->create([
                'title' => $def['module1'], 'position' => 0,
                'activity_completion_required' => true, 'assessment_completion_required' => false,
            ]);
            foreach ($def['module1Activities'] as $position => $activity) {
                $content = match ($activity['type']) {
                    'rich_text' => ['html' => $activity['html']],
                    'katex' => ['latex' => $activity['latex']],
                    'mermaid' => ['syntax' => $activity['syntax']],
                    'external_resource' => ['url' => $activity['url']],
                    'homework', 'assignment', 'reading' => ['instructions' => $activity['instructions']],
                };
                $module1->activities()->create([
                    'type' => $activity['type'], 'title' => $activity['title'], 'position' => $position,
                    'required' => true, 'content' => $content,
                ]);
            }

            $survey = SelfPacedSurveyContent::create([
                'tutor_profile_id' => $tutor->id,
                'grade_id' => $grades[$def['grade']]->id,
                'subject_id' => $subjects[$def['subject']]->id,
                'curriculum_id' => $curriculum->id,
                'title' => $def['quizTitle'],
            ]);
            $survey->questions()->create([
                'position' => 0, 'type' => 'radiogroup',
                'definition' => ['title' => $def['quizQuestion']['title'], 'choices' => $def['quizQuestion']['choices'], 'correctAnswer' => $def['quizQuestion']['correct']],
                'points' => 10,
            ]);

            $module2 = $course->modules()->create([
                'title' => $def['module2'], 'position' => 1,
                'activity_completion_required' => false, 'assessment_completion_required' => true,
            ]);
            $module2->assessments()->create([
                'assessment_type' => 'chapter_test', 'title' => $def['quizTitle'], 'position' => 0, 'required' => true,
                'passing_score' => $def['passingScore'], 'attempts_mode' => 'unlimited',
                'provider' => 'surveyjs', 'provider_config' => ['survey_content_id' => $survey->id],
            ]);
        }
        $this->line('Created '.count($courses).' complete, published self-paced courses.');
    }

    /**
     * @param  Collection<string, Subject>  $subjects
     * @param  Collection<int, Grade>  $grades
     */
    private function seedTutorLedCourses(TutorProfile $tutor, $subjects, $grades, Curriculum $curriculum): void
    {
        $courses = [
            [
                'subject' => 'Mathematics', 'grade' => 10, 'difficulty' => 'beginner',
                'title' => 'Mathematics Grade 10 — Full Year Curriculum',
                'description' => 'The complete Grade 10 Mathematics syllabus, delivered chapter by chapter across live tutoring sessions.',
                'chapters' => [
                    ['title' => 'Algebra Basics', 'lessons' => [
                        ['title' => 'Variables and Expressions', 'blocks' => [
                            ['type' => 'rich_text', 'html' => '<p>In this lesson we introduce variables and how to build algebraic expressions from word problems.</p>'],
                            ['type' => 'media', 'youtube' => 'https://www.youtube.com/watch?v=NybHckSEQBI', 'title' => 'Intro to Algebra (video)'],
                        ]],
                        ['title' => 'Solving Equations', 'blocks' => [
                            ['type' => 'rich_text', 'html' => '<p>We walk through solving one-step and two-step linear equations, with worked examples on the board.</p>'],
                            ['type' => 'mermaid', 'diagram' => "flowchart TD\n    A[Equation] --> B[Isolate the variable]\n    B --> C[Check your answer]"],
                        ]],
                    ]],
                    ['title' => 'Geometry Fundamentals', 'lessons' => [
                        ['title' => 'Angles and Triangles', 'blocks' => [
                            ['type' => 'rich_text', 'html' => '<p>An overview of angle types and the triangle angle sum property, with practice problems.</p>'],
                        ]],
                        ['title' => 'Area and Perimeter', 'blocks' => [
                            ['type' => 'rich_text', 'html' => '<p>Formulas for area and perimeter of common shapes, applied to real measurement problems.</p>'],
                            ['type' => 'media', 'youtube' => 'https://www.youtube.com/watch?v=2xoXbUzsyAI', 'title' => 'Area & Perimeter (video)'],
                        ]],
                    ]],
                ],
            ],
            [
                'subject' => 'Information Technology', 'grade' => 10, 'difficulty' => 'beginner',
                'title' => 'Information Technology — Programming Basics',
                'description' => 'An introductory programming course covering logic, flowcharts, and the fundamentals of writing code.',
                'chapters' => [
                    ['title' => 'Programming Logic', 'lessons' => [
                        ['title' => 'Sequence, Selection, Loops', 'blocks' => [
                            ['type' => 'rich_text', 'html' => '<p>The three building blocks of every program: sequence (do things in order), selection (make decisions), and iteration (repeat things).</p>'],
                            ['type' => 'mermaid', 'diagram' => "flowchart TD\n    A[Start] --> B[Read input]\n    B --> C{Valid?}\n    C -- Yes --> D[Process]\n    C -- No --> B\n    D --> E[Output]\n    E --> F[End]"],
                        ]],
                        ['title' => 'Writing Your First Program', 'blocks' => [
                            ['type' => 'rich_text', 'html' => '<p>We write and trace through a simple program together, step by step, to see exactly how the computer executes each line.</p>'],
                            ['type' => 'media', 'youtube' => 'https://www.youtube.com/watch?v=zOjov-2OZ0E', 'title' => 'Programming Basics (video)'],
                        ]],
                    ]],
                ],
            ],
        ];

        foreach ($courses as $def) {
            $course = Course::create([
                'tutor_profile_id' => $tutor->id,
                'curriculum_id' => $curriculum->id,
                'grade_id' => $grades[$def['grade']]->id,
                'subject_id' => $subjects[$def['subject']]->id,
                'title' => $def['title'],
                'description' => $def['description'],
                'estimated_duration_minutes' => 600,
                'difficulty' => $def['difficulty'],
                'language' => 'English',
                'status' => 'published',
            ]);

            foreach ($def['chapters'] as $chapterPosition => $chapterDef) {
                $chapter = $course->chapters()->create([
                    'title' => $chapterDef['title'], 'position' => $chapterPosition, 'status' => 'published',
                ]);

                foreach ($chapterDef['lessons'] as $lessonPosition => $lessonDef) {
                    $lesson = $chapter->lessons()->create([
                        'title' => $lessonDef['title'], 'position' => $lessonPosition,
                        'estimated_duration_minutes' => 45, 'status' => 'published',
                    ]);

                    foreach ($lessonDef['blocks'] as $blockPosition => $blockDef) {
                        $block = $lesson->blocks()->create([
                            'block_type' => $blockDef['type'] === 'media' ? 'media' : $blockDef['type'],
                            'title' => $blockDef['title'] ?? null,
                            'position' => $blockPosition,
                            'status' => 'published',
                            'settings' => [],
                            'content' => match ($blockDef['type']) {
                                'rich_text' => ['html' => $blockDef['html'], 'json' => ['type' => 'doc', 'content' => []]],
                                'mermaid' => ['diagram' => $blockDef['diagram']],
                                'media' => [],
                                default => [],
                            },
                        ]);

                        if ($blockDef['type'] === 'media') {
                            $block->mediaItems()->create([
                                'media_type' => 'video_youtube',
                                'title' => $blockDef['title'],
                                'external_url' => $blockDef['youtube'],
                                'position' => 0,
                                'status' => 'published',
                            ]);
                        }
                    }
                }
            }
        }
        $this->line('Created '.count($courses).' Tutor-Led courses with chapters, lessons, and lesson blocks.');
    }
}
