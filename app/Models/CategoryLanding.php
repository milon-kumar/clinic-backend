<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CategoryLanding extends Model
{
    protected $fillable = [
        'slug',
        'category',
        'mother_category_id',
        'title',
        'description',
        'seo_title',
        'seo_description',
        'hero_title',
        'hero_description',
        'hero_image',
        'list_title',
        'list_copy',
        'benefits',
        'faqs',
        'aliases',
        'cta_title',
        'cta_copy',
        'cta_url',
        'is_active',
        'show_in_menu',
    ];

    protected function casts(): array
    {
        return [
            'benefits' => 'array',
            'faqs' => 'array',
            'aliases' => 'array',
            'is_active' => 'boolean',
            'show_in_menu' => 'boolean',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function defaults(): array
    {
        $path = database_path('data/category_landings.json');
        if (! File::exists($path)) {
            return [];
        }

        $rows = json_decode(File::get($path), true);

        return is_array($rows) ? $rows : [];
    }

    public static function seedDefaults(): void
    {
        foreach (static::defaults() as $row) {
            static::query()->firstOrCreate(
                ['slug' => $row['slug']],
                $row
            );
        }
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return list<array{question: string, answer: ?string, options: list<string>}>
     */
    public static function cleanFaqs(array $rows): array
    {
        $clean = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $question = trim((string) ($row['question'] ?? ''));
            if ($question === '') {
                continue;
            }
            $answer = trim((string) ($row['answer'] ?? ''));
            $options = [];
            foreach ($row['options'] ?? [] as $option) {
                $label = trim((string) (is_array($option) ? ($option['label'] ?? '') : $option));
                if ($label !== '') {
                    $options[] = $label;
                }
            }
            $clean[] = [
                'question' => $question,
                'answer' => $answer !== '' ? $answer : null,
                'options' => array_values($options),
            ];
        }

        return $clean;
    }

    /**
     * Questions saved on a category page, keyed by category, slug, and aliases.
     *
     * @return array<string, list<array{question: string, answer: ?string, fromCategory: true}>>
     */
    public static function faqIndex(): array
    {
        if (app()->bound('category_landing.faq_index')) {
            return app('category_landing.faq_index');
        }

        $index = [];
        foreach (static::query()->get(['category', 'slug', 'aliases', 'faqs']) as $landing) {
            $faqs = array_map(
                fn (array $faq) => $faq + ['fromCategory' => true],
                static::cleanFaqs($landing->faqs ?? []),
            );
            if ($faqs === []) {
                continue;
            }

            $keys = array_merge(
                [$landing->category, $landing->slug],
                is_array($landing->aliases) ? $landing->aliases : [],
            );
            foreach ($keys as $key) {
                $normalized = mb_strtolower(trim((string) $key));
                if ($normalized === '') {
                    continue;
                }
                foreach ($faqs as $faq) {
                    $index[$normalized][] = $faq;
                }
            }
        }

        app()->instance('category_landing.faq_index', $index);

        return $index;
    }

    /**
     * Category questions that belong on this treatment.
     *
     * @return list<array{question: string, answer: ?string, fromCategory: true}>
     */
    public static function faqsForTreatment(?string $category, ?string $slug = null): array
    {
        $index = static::faqIndex();
        $merged = [];
        $seen = [];

        foreach ([$category, $slug] as $key) {
            $normalized = mb_strtolower(trim((string) $key));
            if ($normalized === '' || ! isset($index[$normalized])) {
                continue;
            }
            foreach ($index[$normalized] as $faq) {
                $dedupe = mb_strtolower($faq['question']);
                if (isset($seen[$dedupe])) {
                    continue;
                }
                $seen[$dedupe] = true;
                $merged[] = $faq;
            }
        }

        return $merged;
    }

    public function motherCategory(): BelongsTo
    {
        return $this->belongsTo(MotherCategory::class);
    }

    /**
     * First-path segments used by the Next.js app that must not become landing URLs.
     *
     * @return list<string>
     */
    public static function reservedSlugs(): array
    {
        return [
            'about-us',
            'account',
            'admin',
            'all-treatment',
            'api',
            'booking',
            'cart',
            'chat',
            'checkout',
            'contact',
            'dashboard',
            'doctors',
            'forgot-password',
            'go-to-clinic',
            'home',
            'login',
            'payment',
            'signup',
            'verify-email',
            'wishlist',
            'zaina',
        ];
    }

    public static function normalizeSlug(?string $slug, ?string $fallback = null): string
    {
        return Str::slug((string) ($slug ?: $fallback));
    }

    /**
     * @return array<string, mixed>
     */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'category' => $this->category,
            'motherCategoryId' => $this->mother_category_id,
            'title' => $this->title,
            'description' => $this->description,
            'seoTitle' => $this->seo_title,
            'seoDescription' => $this->seo_description,
            'heroTitle' => $this->hero_title,
            'heroDescription' => $this->hero_description,
            'heroImage' => $this->hero_image,
            'listTitle' => $this->list_title,
            'listCopy' => $this->list_copy,
            'benefits' => array_values($this->benefits ?? []),
            'faqs' => static::cleanFaqs($this->faqs ?? []),
            'aliases' => array_values($this->aliases ?? []),
            'ctaTitle' => $this->cta_title,
            'ctaCopy' => $this->cta_copy,
            'ctaUrl' => $this->cta_url,
            'isActive' => $this->is_active,
            'showInMenu' => (bool) $this->show_in_menu,
        ];
    }
}
