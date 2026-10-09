<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class Service extends Model
{
    protected $fillable = [
        'sku',
        'slug',
        'name',
        'category',
        'treatment_type',
        'description',
        'seo_title',
        'seo_description',
        'hero_title',
        'hero_description',
        'duration_minutes',
        'base_price_pence',
        'appointment_amount_pence',
        'stripe_buy_price_id',
        'stripe_appointment_price_id',
        'images',
        'supports_buy',
        'supports_book',
        'is_active',
        'is_featured',
        'show_in_menu',
        'sort_order',
        'allow_local',
        'requires_signature',
    ];

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'supports_buy' => 'boolean',
            'supports_book' => 'boolean',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'show_in_menu' => 'boolean',
            'sort_order' => 'integer',
            'allow_local' => 'boolean',
            'requires_signature' => 'boolean',
        ];
    }

    public function scopeOrderedForDisplay(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function packages(): HasMany
    {
        return $this->hasMany(ServicePackage::class);
    }

    public function benefits(): HasMany
    {
        return $this->hasMany(ServiceBenefit::class);
    }

    public function faqs(): HasMany
    {
        return $this->hasMany(ServiceFaq::class);
    }

    public function preQuestions(): HasMany
    {
        return $this->hasMany(ServicePreQuestion::class)->orderBy('sort_order')->orderBy('id');
    }

    public function prerequisites(): HasMany
    {
        return $this->hasMany(ServicePrerequisite::class);
    }

    public function clinicServices(): HasMany
    {
        return $this->hasMany(ClinicService::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function scopeWithReviewStats(Builder $query): Builder
    {
        return $query
            ->withAvg(['reviews as rating_avg' => fn ($reviews) => $reviews->where('status', Review::STATUS_PUBLISHED)], 'rating')
            ->withCount(['reviews as rating_count' => fn ($reviews) => $reviews->where('status', Review::STATUS_PUBLISHED)]);
    }

    public static function resolveId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || (is_string($value) && preg_match('/^\d+$/', $value) === 1)) {
            return (int) $value;
        }

        $id = static::query()->where('slug', (string) $value)->value('id');

        return $id ? (int) $id : null;
    }

    public function appointmentAmountPence(): int
    {
        return max(0, (int) $this->appointment_amount_pence);
    }

    public function isFreeAppointment(): bool
    {
        return $this->appointmentAmountPence() === 0;
    }

    /**
     * Nearby treatments for the "You might also like" row.
     *
     * @return Collection<int, Service>
     */
    public function recommendedServices(int $limit = 4): Collection
    {
        $limit = max(1, min(8, $limit));
        $category = mb_strtolower((string) $this->category);
        $type = mb_strtolower((string) $this->treatment_type);

        $same = collect();
        if ($category !== '' || $type !== '') {
            $same = static::query()
                ->withReviewStats()
                ->where('is_active', true)
                ->where('id', '!=', $this->id)
                ->where(function ($query) use ($category, $type) {
                    if ($category !== '') {
                        $query->orWhereRaw('LOWER(category) = ?', [$category]);
                    }
                    if ($type !== '') {
                        $query->orWhereRaw('LOWER(treatment_type) = ?', [$type]);
                    }
                })
                ->with(['packages'])
                ->orderedForDisplay()
                ->limit($limit)
                ->get();
        }

        if ($same->count() >= $limit) {
            return $same->values();
        }

        $exclude = $same->pluck('id')->push($this->id);
        $fill = static::query()
            ->withReviewStats()
            ->where('is_active', true)
            ->whereNotIn('id', $exclude)
            ->with(['packages'])
            ->orderedForDisplay()
            ->limit($limit - $same->count())
            ->get();

        return $same->concat($fill)->values();
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'slug' => $this->slug,
            'name' => $this->name,
            'title' => $this->name,
            'category' => $this->category,
            'treatmentType' => $this->treatment_type,
            'description' => $this->description,
            'seoTitle' => $this->seo_title,
            'seoDescription' => $this->seo_description,
            'heroTitle' => $this->hero_title,
            'heroDescription' => $this->hero_description,
            'durationMinutes' => $this->duration_minutes,
            'basePricePence' => $this->base_price_pence,
            'price' => $this->base_price_pence / 100,
            'appointmentAmountPence' => $this->appointmentAmountPence(),
            'appointmentAmount' => $this->appointmentAmountPence() / 100,
            'images' => array_values(array_filter($this->images ?? [])),
            'supportsBuy' => $this->supports_buy,
            'supportsBook' => $this->supports_book,
            'isActive' => $this->is_active,
            'isFeatured' => $this->is_featured,
            'showInMenu' => $this->show_in_menu,
            'sortOrder' => (int) $this->sort_order,
            'allowLocal' => $this->allow_local,
            'requiresSignature' => (bool) $this->requires_signature,
            'ratingAvg' => round((float) ($this->getAttribute('rating_avg') ?? 0), 1),
            'ratingCount' => (int) ($this->getAttribute('rating_count') ?? 0),
            'packages' => $this->relationLoaded('packages')
                ? $this->packages->map(fn (ServicePackage $p) => $p->toApi())->all()
                : [],
            'benefits' => $this->relationLoaded('benefits')
                ? $this->benefits->map(fn (ServiceBenefit $b) => $b->toApi())->all()
                : [],
            'faqs' => $this->inheritedFaqs(),
            'preQuestions' => $this->relationLoaded('preQuestions')
                ? $this->preQuestions->map(fn (ServicePreQuestion $q) => $q->toApi())->values()->all()
                : [],
            'clinicIds' => $this->relationLoaded('clinicServices')
                ? $this->clinicServices->pluck('clinic_id')->map(fn ($id) => (int) $id)->values()->all()
                : [],
            'clinics' => $this->relationLoaded('clinicServices')
                ? $this->clinicServices->map(fn (ClinicService $row) => [
                    'id' => $row->clinic_id,
                    'name' => $row->clinic?->name,
                    'buyEnabled' => (bool) $row->buy_enabled,
                    'bookEnabled' => (bool) $row->book_enabled,
                    'onlineBuyEnabled' => (bool) $row->online_buy_enabled,
                ])->values()->all()
                : [],
        ];
    }

    /**
     * @param  array<int, mixed>  $submitted
     * @return list<array<string, mixed>>
     */
    public function bookingAnswers(array $submitted): array
    {
        $this->loadMissing('preQuestions');
        $given = [];
        foreach ($submitted as $row) {
            if (! is_array($row)) {
                continue;
            }
            $given[(string) ($row['id'] ?? '')] = trim((string) ($row['answer'] ?? ''));
        }

        $saved = [];
        $missing = [];
        foreach ($this->preQuestions as $question) {
            $answer = $given[(string) $question->id] ?? '';
            if ($question->answer_type === 'yes_no' && ! in_array($answer, ['yes', 'no'], true)) {
                $answer = '';
            }
            if ($question->answer_type === 'choice' && ! in_array($answer, $question->choiceOptions(), true)) {
                $answer = '';
            }
            if ($answer === '') {
                if ($question->is_required) {
                    $missing[] = $question->prompt;
                }
                continue;
            }
            $saved[] = [
                'id' => $question->id,
                'prompt' => $question->prompt,
                'answerType' => $question->answer_type,
                'options' => $question->choiceOptions(),
                'required' => (bool) $question->is_required,
                'answer' => $answer === '' ? null : $answer,
            ];
        }

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'answers' => ['Please answer: '.implode(', ', $missing)],
            ]);
        }

        return $saved;
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return list<array<string, mixed>>
     */
    public static function faqAnswerRows(array $rows): array
    {
        $saved = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $prompt = trim((string) ($row['prompt'] ?? ''));
            $answer = trim((string) ($row['answer'] ?? ''));
            if ($prompt === '' || $answer === '') {
                continue;
            }
            $options = ServicePreQuestion::cleanOptions($row['options'] ?? []);
            $saved[] = [
                'id' => null,
                'prompt' => $prompt,
                'answerType' => $options === [] ? 'text' : 'choice',
                'options' => $options,
                'required' => false,
                'answer' => $answer,
            ];
        }

        return $saved;
    }

    /**
     * Questions the client can answer when booking, or later from the email link.
     *
     * @return list<array{prompt: string, answer_type: string, options: ?list<string>, is_required: bool}>
     */
    public function bookingQuestionRows(): array
    {
        $rows = [];
        $seen = [];

        foreach ($this->inheritedFaqs() as $faq) {
            $prompt = trim((string) ($faq['question'] ?? ''));
            $key = mb_strtolower($prompt);
            if ($prompt === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $options = ServicePreQuestion::cleanOptions($faq['options'] ?? []);
            $rows[] = [
                'prompt' => $prompt,
                'answer_type' => $options === [] ? 'text' : 'choice',
                'options' => $options === [] ? null : $options,
                'is_required' => false,
            ];
        }

        return $rows;
    }

    /**
     * Category questions are inherited by every treatment in that category.
     *
     * @return list<array<string, mixed>>
     */
    public function inheritedFaqs(): array
    {
        return CategoryLanding::faqsForTreatment($this->category, $this->slug);
    }
}
