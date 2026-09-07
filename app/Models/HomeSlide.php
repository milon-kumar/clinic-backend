<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomeSlide extends Model
{
    protected $fillable = [
        'image',
        'title',
        'subtitle',
        'link_url',
        'interval_ms',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'interval_ms' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function defaults(): array
    {
        return [
            ['image' => '/Assets/05.webp', 'interval_ms' => 10000, 'sort_order' => 1],
            ['image' => '/Assets/03.webp', 'interval_ms' => 2000, 'sort_order' => 2],
            ['image' => '/Assets/06.webp', 'interval_ms' => 5000, 'sort_order' => 3],
        ];
    }

    public static function seedDefaults(): void
    {
        if (static::query()->exists()) {
            return;
        }

        foreach (static::defaults() as $slide) {
            static::query()->create(array_merge([
                'title' => null,
                'subtitle' => null,
                'link_url' => null,
                'is_active' => true,
            ], $slide));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'image' => $this->image,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'linkUrl' => $this->link_url,
            'intervalMs' => $this->interval_ms,
            'sortOrder' => $this->sort_order,
            'isActive' => $this->is_active,
            'updatedAt' => $this->updated_at?->timestamp,
        ];
    }
}
