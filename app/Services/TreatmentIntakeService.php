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

        $package->loadMissing('service.preQuestions');
        $questions = $package->service?->preQuestions;
        if ($questions === null || $questions->isEmpty()) {
            return null;
        }

        try {
            return DB::transaction(function () use ($package, $questions) {
                $intake = TreatmentIntake::create([
                    'prepaid_package_id' => $package->id,
                    'order_id' => $package->order_id,
                    'token' => Str::random(48),
                ]);

                foreach ($questions->values() as $index => $question) {
                    TreatmentIntakeAnswer::create([
                        'treatment_intake_id' => $intake->id,
                        'prompt' => $question->prompt,
                        'answer_type' => $question->answer_type,
                        'options' => $question->choiceOptions() === [] ? null : $question->choiceOptions(),
                        'is_required' => (bool) $question->is_required,
                        'sort_order' => $index,
                    ]);
                }

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

        if ($existing) {
            return $existing;
        }

        $appointment->loadMissing('service.preQuestions');
        $questions = $appointment->service?->preQuestions;
        if ($questions === null || $questions->isEmpty()) {
            return null;
        }

        try {
            return DB::transaction(function () use ($appointment, $questions) {
                $intake = TreatmentIntake::create([
                    'appointment_id' => $appointment->id,
                    'token' => Str::random(48),
                ]);

                foreach ($questions->values() as $index => $question) {
                    TreatmentIntakeAnswer::create([
                        'treatment_intake_id' => $intake->id,
                        'prompt' => $question->prompt,
                        'answer_type' => $question->answer_type,
                        'options' => $question->choiceOptions() === [] ? null : $question->choiceOptions(),
                        'is_required' => (bool) $question->is_required,
                        'sort_order' => $index,
                    ]);
                }

                $intake->load('answers');
                $this->applySavedAnswers($intake, $appointment->question_answers ?? []);

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
        $complete = true;

        foreach ($intake->answers as $item) {
            $row = $byPrompt->get(mb_strtolower(trim($item->prompt)));
            $value = trim((string) ($row['answer'] ?? ''));
            if ($item->is_required && $value === '') {
                $complete = false;
            }
            if ($value !== '') {
                $item->update(['answer' => $value]);
            }
        }

        if ($complete) {
            $intake->update(['submitted_at' => now()]);
        }
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
