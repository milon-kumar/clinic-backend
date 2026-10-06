<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Order;
use App\Models\PrepaidPackage;
use App\Models\TreatmentIntake;
use App\Models\TreatmentIntakeAnswer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TreatmentIntakeService
{
    public function findByToken(string $token): TreatmentIntake
    {
        return TreatmentIntake::query()
            ->with(['answers', 'package.service', 'package.clinic'])
            ->where('token', $token)
            ->firstOrFail();
    }

    /**
     * Copy the treatment's current questions onto this package once.
     */
    public function openForPackage(PrepaidPackage $package): ?TreatmentIntake
    {
        $existing = TreatmentIntake::query()
            ->with('answers')
            ->where('prepaid_package_id', $package->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $package->loadMissing('service');
        $questions = $package->service?->bookingQuestionRows() ?? [];
        if ($questions === []) {
            return null;
        }

        try {
            return DB::transaction(function () use ($package, $questions) {
                $intake = TreatmentIntake::create([
                    'prepaid_package_id' => $package->id,
                    'order_id' => $package->order_id,
                    'token' => Str::random(48),
                ]);

                $this->appendQuestions($intake, $questions);

                return $intake->load('answers');
            });
        } catch (QueryException) {
            return TreatmentIntake::query()
                ->with('answers')
                ->where('prepaid_package_id', $package->id)
                ->first();
        }
    }

    /**
     * A booking email links here so the client can submit the treatment's options.
     */
    public function openForAppointment(Appointment $appointment): ?TreatmentIntake
    {
        $existing = TreatmentIntake::query()
            ->with('answers')
            ->where('appointment_id', $appointment->id)
            ->first();

        $appointment->loadMissing('service');
        $questions = $appointment->service?->bookingQuestionRows() ?? [];
        if ($questions === []) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($appointment, $questions, $existing) {
                $intake = $existing ?: TreatmentIntake::create([
                    'appointment_id' => $appointment->id,
                    'token' => Str::random(48),
                ]);

                $before = $intake->answers()->count();
                $this->appendQuestions($intake, $questions);
                $intake->unsetRelation('answers');
                $intake->load('answers');
                if ($intake->answers->count() > $before && $intake->submitted_at) {
                    $intake->update(['submitted_at' => null]);
                }

                if (! $intake->submitted_at) {
                    $this->applySavedAnswers($intake, $appointment->question_answers ?? []);
                }

                $intake->load('answers');
                $hasAnswer = $intake->answers->contains(fn (TreatmentIntakeAnswer $item) => filled($item->answer));
                if ($intake->submitted_at && ! $hasAnswer) {
                    $intake->update(['submitted_at' => null]);
                }

                return $intake->fresh('answers');
            });
        } catch (QueryException) {
            return TreatmentIntake::query()
                ->with('answers')
                ->where('appointment_id', $appointment->id)
                ->first();
        }
    }

    /**
     * @param  list<array{prompt: string, answer_type: string, options: ?list<string>, is_required: bool}>  $questions
     */
    private function appendQuestions(TreatmentIntake $intake, array $questions): void
    {
        $intake->loadMissing('answers');
        $known = $intake->answers
            ->map(fn (TreatmentIntakeAnswer $item) => mb_strtolower(trim($item->prompt)))
            ->all();
        $sort = (int) $intake->answers->max('sort_order');

        foreach ($questions as $question) {
            $prompt = trim((string) ($question['prompt'] ?? ''));
            $key = mb_strtolower($prompt);
            if ($prompt === '' || in_array($key, $known, true)) {
                continue;
            }
            $known[] = $key;
            $sort++;
            TreatmentIntakeAnswer::create([
                'treatment_intake_id' => $intake->id,
                'prompt' => $prompt,
                'answer_type' => $question['answer_type'] ?? 'text',
                'options' => $question['options'] ?? null,
                'is_required' => (bool) ($question['is_required'] ?? false),
                'sort_order' => $sort,
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $saved
     */
    private function applySavedAnswers(TreatmentIntake $intake, array $saved): void
    {
        if ($saved === []) {
            return;
        }

        $byPrompt = collect($saved)->keyBy(
            fn ($row) => mb_strtolower(trim((string) ($row['prompt'] ?? '')))
        );
        $complete = $intake->answers->isNotEmpty();

        foreach ($intake->answers as $item) {
            $row = $byPrompt->get(mb_strtolower(trim($item->prompt)));
            $value = trim((string) ($row['answer'] ?? ''));
            if ($value === '' || ! $this->answerMatches($item, $value)) {
                $complete = false;
                continue;
            }
            if ($item->answer !== $value) {
                $item->update(['answer' => $value]);
            }
        }

        if ($complete) {
            $intake->update(['submitted_at' => now()]);
        }
    }

    private function answerMatches(TreatmentIntakeAnswer $item, string $value): bool
    {
        if ($item->answer_type === 'yes_no') {
            return in_array(strtolower($value), ['yes', 'no'], true);
        }

        if ($item->answer_type === 'choice') {
            return in_array($value, array_values($item->options ?? []), true);
        }

        return true;
    }

    /**
     * @return array<int, array{name: string, url: string, packageId: int}>
     */
    public function linksForOrder(Order $order): array
    {
        $order->loadMissing(['packages.service.preQuestions', 'packages.intake']);

        $links = [];
        foreach ($order->packages as $package) {
            $intake = $this->openForPackage($package);
            if (! $intake) {
                continue;
            }

            $intake->loadMissing('answers');
            $links[] = [
                'name' => $package->service?->name ?: 'Treatment',
                'url' => $intake->publicUrl(),
                'packageId' => (int) $package->id,
                'questions' => $intake->answers->map(fn (TreatmentIntakeAnswer $answer) => [
                    'prompt' => $answer->prompt,
                    'options' => $answer->toApi()['options'],
                ])->values()->all(),
            ];
        }

        return $links;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function submit(string $token, array $rows): TreatmentIntake
    {
        $intake = $this->findByToken($token);

        if ($intake->submitted_at) {
            throw ValidationException::withMessages([
                'intake' => ['These questions have already been answered.'],
            ]);
        }

        $byId = collect($rows)->keyBy(fn ($row) => (int) ($row['id'] ?? 0));

        DB::transaction(function () use ($intake, $byId) {
            foreach ($intake->answers as $item) {
                $raw = $byId->get($item->id)['answer'] ?? '';
                $value = trim((string) $raw);

                if ($item->answer_type === 'yes_no') {
                    $value = strtolower($value);
                    if (! in_array($value, ['yes', 'no'], true)) {
                        if ($item->is_required) {
                            throw ValidationException::withMessages([
                                'answers' => ['Choose yes or no for: '.$item->prompt],
                            ]);
                        }
                        $value = '';
                    }
                } elseif ($item->answer_type === 'choice') {
                    $allowed = array_values($item->options ?? []);
                    if (! in_array($value, $allowed, true)) {
                        if ($item->is_required) {
                            throw ValidationException::withMessages([
                                'answers' => ['Choose an option for: '.$item->prompt],
                            ]);
                        }
                        $value = '';
                    }
                } elseif ($item->is_required && $value === '') {
                    throw ValidationException::withMessages([
                        'answers' => ['Answer is required: '.$item->prompt],
                    ]);
                }

                if (mb_strlen($value) > 4000) {
                    throw ValidationException::withMessages([
                        'answers' => ['Answer is too long: '.$item->prompt],
                    ]);
                }

                $item->update(['answer' => $value !== '' ? $value : null]);
            }

            $intake->update(['submitted_at' => now()]);
        });

        return $intake->fresh(['answers', 'package.service', 'package.clinic']);
    }
}
