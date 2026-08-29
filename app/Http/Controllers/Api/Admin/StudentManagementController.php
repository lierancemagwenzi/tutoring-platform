<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\StudentManagementResource;
use App\Models\User;
use App\Services\Admin\AdminStudentManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentManagementController extends Controller
{
    public function __construct(private readonly AdminStudentManagementService $students) {}

    public function index(Request $request): JsonResponse
    {
        $students = $this->students->list($request->only(['search']), (int) $request->integer('per_page', 15));

        return response()->json([
            'students' => StudentManagementResource::collection($students->items()),
            'meta' => [
                'current_page' => $students->currentPage(),
                'last_page' => $students->lastPage(),
                'per_page' => $students->perPage(),
                'total' => $students->total(),
            ],
        ]);
    }

    public function show(User $student): JsonResponse
    {
        abort_unless($student->role === UserRole::Student, 404);

        return response()->json($this->students->detail($student));
    }
}
