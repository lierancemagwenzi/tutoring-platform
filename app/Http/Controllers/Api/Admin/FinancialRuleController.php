<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFinancialRuleRequest;
use App\Http\Requests\Admin\UpdateFinancialRuleRequest;
use App\Http\Resources\Admin\FinancialRuleResource;
use App\Models\FinancialRule;
use App\Services\Admin\FinancialRuleManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialRuleController extends Controller
{
    public function __construct(private readonly FinancialRuleManagementService $rules) {}

    public function index(Request $request): JsonResponse
    {
        $overrides = $this->rules->listOverrides($request->only(['scope']), (int) $request->integer('per_page', 15));

        return response()->json([
            'rules' => FinancialRuleResource::collection($overrides->items()),
            'meta' => [
                'current_page' => $overrides->currentPage(),
                'last_page' => $overrides->lastPage(),
                'per_page' => $overrides->perPage(),
                'total' => $overrides->total(),
            ],
        ]);
    }

    public function showGlobal(): JsonResponse
    {
        return response()->json(['rule' => new FinancialRuleResource($this->rules->globalRule())]);
    }

    public function updateGlobal(UpdateFinancialRuleRequest $request): JsonResponse
    {
        $rule = $this->rules->updateGlobal($request->validated(), $request->user());

        return response()->json(['rule' => new FinancialRuleResource($rule)]);
    }

    public function store(StoreFinancialRuleRequest $request): JsonResponse
    {
        $rule = $this->rules->createOverride($request->validated(), $request->user());

        return response()->json(['rule' => new FinancialRuleResource($rule)], 201);
    }

    public function update(UpdateFinancialRuleRequest $request, FinancialRule $financialRule): JsonResponse
    {
        $rule = $this->rules->updateOverride($financialRule, $request->validated(), $request->user());

        return response()->json(['rule' => new FinancialRuleResource($rule)]);
    }

    public function deactivate(Request $request, FinancialRule $financialRule): JsonResponse
    {
        $rule = $this->rules->deactivateOverride($financialRule, $request->user());

        return response()->json(['rule' => new FinancialRuleResource($rule)]);
    }

    public function activate(Request $request, FinancialRule $financialRule): JsonResponse
    {
        $rule = $this->rules->activateOverride($financialRule, $request->user());

        return response()->json(['rule' => new FinancialRuleResource($rule)]);
    }
}
