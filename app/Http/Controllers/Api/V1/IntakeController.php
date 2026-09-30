<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\TreatmentIntakeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntakeController extends Controller
{
    public function __construct(private TreatmentIntakeService $intakes) {}

    public function show(string $token): JsonResponse
    {
        return response()->json([
            'data' => $this->intakes->findByToken($token)->toPublicApi(),
        ]);
    }

    public function store(Request $request, string $token): JsonResponse
    {
        $data = $request->validate([
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.id' => ['required', 'integer'],
            'answers.*.answer' => ['nullable', 'string', 'max:4000'],
        ]);

        $intake = $this->intakes->submit($token, $data['answers']);

        return response()->json([
            'data' => $intake->toPublicApi(),
        ]);
    }
}
