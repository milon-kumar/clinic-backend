<?php

namespace App\Http\Resources;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Service */
class ServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'slug' => $this->slug,
            'name' => $this->name,
            'category' => $this->category,
            'treatmentType' => $this->treatment_type,
            'description' => $this->description,
            'durationMinutes' => $this->duration_minutes,
            'basePricePence' => $this->base_price_pence,
            'appointmentAmountPence' => $this->appointmentAmountPence(),
            'appointmentAmount' => $this->appointmentAmountPence() / 100,
            'supportsBuy' => $this->supports_buy,
            'supportsBook' => $this->supports_book,
            'coverImage' => $this->cover_image,
            'images' => $this->images ?? [],
            'metadata' => $this->metadata_json ?? [],
            'isActive' => $this->is_active,
            'isFeatured' => $this->is_featured,
            'packages' => $this->whenLoaded('packages', fn () => $this->packages->map(fn ($pkg) => [
                'id' => $pkg->id,
                'title' => $pkg->title,
                'sessions' => $pkg->sessions,
                'pricePence' => $pkg->price_pence,
            ])),
            'benefits' => $this->whenLoaded('benefits', fn () => $this->benefits->map(fn ($benefit) => [
                'id' => $benefit->id,
                'title' => $benefit->title,
                'description' => $benefit->description,
            ])),
            'faqs' => $this->whenLoaded('faqs', fn () => $this->faqs->map(fn ($faq) => [
                'id' => $faq->id,
                'question' => $faq->question,
                'answer' => $faq->answer,
            ])),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
