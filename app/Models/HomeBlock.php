<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomeBlock extends Model
{
    protected $fillable = [
        'slot',
        'sort_order',
        'kicker',
        'title',
        'subtitle',
        'copy',
        'image',
        'cta_label',
        'cta_url',
        'cta2_label',
        'cta2_url',
        'layout',
        'items',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
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
            [
                'slot' => 'why',
                'sort_order' => 1,
                'kicker' => 'Why Elixir',
                'title' => 'We’re experts in ready.',
                'subtitle' => 'Here’s why.',
                'copy' => 'Putting your appearance in someone else’s hands is a big deal. We already have something in common: we want your self-care to feel easy, considered and enjoyable.',
                'image' => '/Assets/cute-girl.webp',
                'cta_label' => 'Book now',
                'cta_url' => '/contact',
                'cta2_label' => 'Buy now',
                'cta2_url' => '/all-treatment',
                'items' => [
                    ['title' => 'Expert staff', 'copy' => 'Therapists, nurses and doctors who design a plan around your features.'],
                    ['title' => 'Complimentary consult', 'copy' => 'Start with a conversation about your skin, not a hard sell.'],
                    ['title' => 'Advanced technology', 'copy' => 'Medical-grade devices chosen for results you can actually see.'],
                    ['title' => 'Convenient clinics', 'copy' => 'Find a branch, then book and buy there — treatments stay at that clinic.'],
                ],
            ],
            [
                'slot' => 'concerns',
                'sort_order' => 2,
                'kicker' => null,
                'title' => null,
                'items' => [
                    [
                        'id' => 'hair',
                        'label' => 'Unwanted hair',
                        'href' => '/laser-hair-remove',
                        'image' => '/Assets/unwatched-hair.webp',
                        'title' => 'Free yourself from fuzz',
                        'copy' => 'Let medical-grade laser do the work. Treatments are fast, comfortable, and suited to all skin and hair types. Most clients notice reduced growth in 8–12 sessions.',
                    ],
                    [
                        'id' => 'pigmentation',
                        'label' => 'Pigmentation',
                        'href' => '/pigmentation',
                        'image' => '/Assets/pigmentation.webp',
                        'title' => 'Seen-to-be-believed results',
                        'copy' => 'Hormones, sun and lifestyle can leave uneven tone. Our therapists use targeted technology to treat the cause — not just cover it.',
                    ],
                    [
                        'id' => 'aging',
                        'label' => 'Anti-aging',
                        'href' => '/all-treatment',
                        'image' => '/Assets/anti-aging.webp',
                        'title' => 'Treatments that work with you',
                        'copy' => 'Fine lines, dullness and loss of elasticity arrive on their own timeline. You can’t rewind, but we can help you press pause.',
                    ],
                    [
                        'id' => 'acne',
                        'label' => 'Acne & breakouts',
                        'href' => '/all-treatment',
                        'image' => '/Assets/acne or breakout.webp',
                        'title' => 'Medically backed acne solutions',
                        'copy' => 'Breakouts, redness and scarring need more than a guess. Clinically proven technology, guided by experienced therapists.',
                    ],
                ],
            ],
            [
                'slot' => 'finder',
                'sort_order' => 3,
                'kicker' => 'Elixir Clinic',
                'title' => 'Ready for treatments on your terms?',
                'copy' => 'Find your local clinic.',
                'image' => '/Assets/elexir-clinic1.png',
            ],
            [
                'slot' => 'journey',
                'sort_order' => 4,
                'kicker' => 'Discover',
                'title' => 'Your Journey to Readiness',
                'copy' => 'Everything we do is with care, precision, and expertise.',
                'items' => [
                    [
                        'title' => 'Medical Team',
                        'copy' => 'Our Therapists, Cosmetic Registered Nurses, and Doctors create tailored treatment plans for all our clients. Our treatments and technologies are approved by medical experts in our Medical Advisory Committee, to ensure they meet industry safety standards.',
                        'image' => '/Assets/medical-team.jpg',
                        'href' => '/doctors',
                    ],
                    [
                        'title' => 'Our Story',
                        'copy' => 'Since day one, we’ve been helping people feel ready for life, with a team of Therapists, Cosmetic Registered Nurses, and Doctors to make it happen.',
                        'image' => '/Assets/our-story.jpg',
                        'href' => '/about-us',
                    ],
                    [
                        'title' => 'Education Hub',
                        'copy' => 'Learn about our treatments, read expert advice, and find answers to our most asked questions.',
                        'image' => '/Assets/skin-laser.avif',
                        'href' => '/all-treatment',
                    ],
                ],
            ],
            [
                'slot' => 'promo',
                'sort_order' => 5,
                'title' => 'Laser Hair Removal',
                'copy' => 'Unlike other hair removal options, our medical-grade Laser Hair Removal means fast, safe, cost-effective, reliable, and permanent hair reduction. Our market-leading laser technology caters to all skin types, all skin tones, and genders. It\'s Laser Hair Removal tailored to you.',
                'image' => '/Assets/Removal-hair.webp',
                'cta_label' => 'Book Now',
                'cta_url' => '/laser-hair-remove',
                'cta2_label' => 'Buy Now',
                'cta2_url' => '/all-treatment',
                'layout' => 'image-left',
            ],
            [
                'slot' => 'promo',
                'sort_order' => 6,
                'title' => 'Skin Treatments',
                'copy' => 'Whatever your skin concern, we have the experience, knowledge, and professional Skin Treatments to deliver the best results. Our highly-trained team of Therapists can help identify your concerns and tailor a treatment plan based on your skin goals.',
                'image' => '/Assets/skin.webp',
                'cta_label' => 'Book Now',
                'cta_url' => '/hydrafacial',
                'cta2_label' => 'Buy Now',
                'cta2_url' => '/all-treatment',
                'layout' => 'image-right',
            ],
            [
                'slot' => 'promo',
                'sort_order' => 7,
                'title' => 'Cosmetic Injectable',
                'copy' => 'Cosmetic Injectable are one of the most effective and result-driven anti-ageing treatments, which can help rejuvenate and enhance your best features. Whether you wish to add volume, reduce the look of fine lines or sculpt and refine your jawline, our experienced team of medically registered professionals are ready to create a tailored plan that will target your priorities. Book in for a consultation today to understand how we can help.',
                'image' => '/Assets/cosmatic.webp',
                'cta_label' => 'Book Now',
                'cta_url' => '/botox',
                'layout' => 'image-left',
            ],
        ];
    }

    public static function seedDefaults(): void
    {
        if (static::query()->exists()) {
            return;
        }

        foreach (static::defaults() as $block) {
            static::query()->create(array_merge([
                'is_active' => true,
            ], $block));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'slot' => $this->slot,
            'sortOrder' => $this->sort_order,
            'kicker' => $this->kicker,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'copy' => $this->copy,
            'image' => $this->image,
            'ctaLabel' => $this->cta_label,
            'ctaUrl' => $this->cta_url,
            'cta2Label' => $this->cta2_label,
            'cta2Url' => $this->cta2_url,
            'layout' => $this->layout,
            'items' => array_values($this->items ?? []),
            'isActive' => $this->is_active,
        ];
    }
}
