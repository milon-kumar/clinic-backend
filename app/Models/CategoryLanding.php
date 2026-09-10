<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CategoryLanding extends Model
{
    protected $fillable = [
        'slug',
        'category',
        'title',
        'description',
        'hero_image',
        'list_title',
        'list_copy',
        'benefits',
        'aliases',
        'cta_title',
        'cta_copy',
        'cta_url',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'benefits' => 'array',
            'aliases' => 'array',
            'is_active' => 'boolean',
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
            'cosmatic-product',
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
            'title' => $this->title,
            'description' => $this->description,
            'heroImage' => $this->hero_image,
            'listTitle' => $this->list_title,
            'listCopy' => $this->list_copy,
            'benefits' => array_values($this->benefits ?? []),
            'aliases' => array_values($this->aliases ?? []),
            'ctaTitle' => $this->cta_title,
            'ctaCopy' => $this->cta_copy,
            'ctaUrl' => $this->cta_url,
            'isActive' => $this->is_active,
        ];
    }
}
