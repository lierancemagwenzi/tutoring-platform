<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Services\Admin\PlatformSettingService;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    public function __construct(private readonly PlatformSettingService $settings) {}

    public function index(): JsonResponse
    {
        return response()->json(['settings' => $this->settings->all()]);
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        foreach ($request->validated('settings') as $key => $value) {
            $this->settings->set($key, $value, $request->user());
        }

        return response()->json(['settings' => $this->settings->all()]);
    }
}
