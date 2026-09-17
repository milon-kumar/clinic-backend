<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MotherCategory extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'sort_order',
        'is_active',
        'show_in_menu',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'show_in_menu' => 'boolean',
        ];
    }

    public function landings(): HasMany
    {
        return $this->hasMany(CategoryLanding::class);
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
            'title' => $this->title,
            'slug' => $this->slug,
            'sortOrder' => (int) $this->sort_order,
            'isActive' => $this->is_active,
            'showInMenu' => (bool) $this->show_in_menu,
        ];
    }
}
