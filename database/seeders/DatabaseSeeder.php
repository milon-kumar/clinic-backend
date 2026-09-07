<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\ClinicSchedule;
use App\Models\ClinicService;
use App\Models\Doctor;
use App\Models\Promotion;
use App\Models\Service;
use App\Models\HomeBlock;
use App\Models\CategoryLanding;
use App\Models\HomeSlide;
use App\Models\SiteSetting;
use App\Models\ServiceBenefit;
use App\Models\ServiceFaq;
use App\Models\ServicePackage;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        SiteSetting::query()->firstOrCreate([], SiteSetting::defaults());
        HomeSlide::seedDefaults();
        HomeBlock::seedDefaults();
        CategoryLanding::seedDefaults();

        $clinics = [
            [
                'code' => 'LCUK_READING',
                'name' => 'Reading',
                'slug' => 'reading',
                'region' => 'england',
                'address_json' => ['line1' => '10 Broad Street', 'city' => 'Reading', 'postcode' => 'RG1 2BH'],
                'latitude' => 51.4543,
                'longitude' => -0.9781,
                'phone' => '+44 118 123 4567',
            ],
            [
                'code' => 'LCUK_LONDON',
                'name' => 'London Oxford Street',
                'slug' => 'london',
                'region' => 'england',
                'address_json' => ['line1' => '100 Oxford Street', 'city' => 'London', 'postcode' => 'W1D 1LL'],
                'latitude' => 51.5154,
                'longitude' => -0.1419,
                'phone' => '+44 20 1234 5678',
            ],
            [
                'code' => 'LCUK_MANCHESTER',
                'name' => 'Manchester',
                'slug' => 'manchester',
                'region' => 'england',
                'address_json' => ['line1' => '50 Deansgate', 'city' => 'Manchester', 'postcode' => 'M3 2EG'],
                'latitude' => 53.4808,
                'longitude' => -2.2426,
                'phone' => '+44 161 123 4567',
            ],
        ];

        $clinicModels = [];
        foreach ($clinics as $clinicData) {
            $clinic = Clinic::create(array_merge($clinicData, [
                'timezone' => 'Europe/London',
                'is_active' => true,
            ]));
            $clinicModels[$clinic->slug] = $clinic;

            foreach ([1, 2, 3, 4, 5, 6] as $day) {
                ClinicSchedule::create([
                    'clinic_id' => $clinic->id,
                    'day_of_week' => $day,
                    'open_time' => '09:00:00',
                    'close_time' => '18:00:00',
                    'slot_interval_minutes' => 60,
                ]);
            }
        }

        $servicesData = [
            [
                'sku' => 'LHR_FULL_BODY',
                'slug' => 'laser-hair-removal-full-body',
                'name' => 'Laser Hair Removal - Full Body',
                'category' => 'lhr',
                'treatment_type' => 'laser',
                'description' => 'Complete full body laser hair removal package.',
                'duration_minutes' => 60,
                'base_price_pence' => 29900,
                'packages' => [
                    ['title' => 'Single Session', 'sessions' => 1, 'price_pence' => 29900],
                    ['title' => '3 Sessions', 'sessions' => 3, 'price_pence' => 71700],
                    ['title' => '6 Sessions', 'sessions' => 6, 'price_pence' => 125580],
                ],
                'benefits' => [
                    ['title' => 'Long-lasting results', 'description' => 'Reduce hair growth permanently over a course of treatments.'],
                    ['title' => 'All skin types', 'description' => 'Suitable for a wide range of skin tones.'],
                ],
                'faqs' => [
                    ['question' => 'How many sessions do I need?', 'answer' => 'Most clients need 6-10 sessions for optimal results.'],
                ],
                'is_featured' => true,
            ],
            [
                'sku' => 'SKIN_HYDRAFACIAL',
                'slug' => 'hydrafacial-platinum',
                'name' => 'Hydrafacial Platinum',
                'category' => 'skin',
                'treatment_type' => 'facial',
                'description' => 'Deep cleansing, exfoliation, and hydration facial treatment.',
                'duration_minutes' => 45,
                'base_price_pence' => 15000,
                'packages' => [
                    ['title' => 'Single Session', 'sessions' => 1, 'price_pence' => 15000],
                    ['title' => '3 Sessions', 'sessions' => 3, 'price_pence' => 36000],
                ],
                'benefits' => [
                    ['title' => 'Instant glow', 'description' => 'Visible results after just one session.'],
                ],
                'faqs' => [
                    ['question' => 'Is there downtime?', 'answer' => 'No downtime. You can return to normal activities immediately.'],
                ],
                'is_featured' => true,
            ],
            [
                'sku' => 'INJ_BOTOX',
                'slug' => 'anti-wrinkle-injections',
                'name' => 'Anti-Wrinkle Injections',
                'category' => 'injectables',
                'treatment_type' => 'injectable',
                'description' => 'Professional anti-wrinkle treatment by qualified practitioners.',
                'duration_minutes' => 30,
                'base_price_pence' => 20000,
                'packages' => [
                    ['title' => 'Single Treatment', 'sessions' => 1, 'price_pence' => 20000],
                ],
                'benefits' => [
                    ['title' => 'Natural results', 'description' => 'Subtle smoothing of fine lines and wrinkles.'],
                ],
                'faqs' => [
                    ['question' => 'How long do results last?', 'answer' => 'Results typically last 3-4 months.'],
                ],
                'is_featured' => true,
            ],
            [
                'sku' => 'BODY_COOLSCULPT',
                'slug' => 'coolsculpting',
                'name' => 'CoolSculpting',
                'category' => 'body',
                'treatment_type' => 'body',
                'description' => 'Non-invasive fat reduction treatment.',
                'duration_minutes' => 60,
                'base_price_pence' => 35000,
                'packages' => [
                    ['title' => 'Single Area', 'sessions' => 1, 'price_pence' => 35000],
                ],
                'benefits' => [
                    ['title' => 'No surgery', 'description' => 'Non-invasive alternative to liposuction.'],
                ],
                'faqs' => [],
                'is_featured' => true,
            ],
            [
                'sku' => 'CONSULT_FREE',
                'slug' => 'consultation',
                'name' => 'Initial Consultation',
                'category' => 'consult',
                'treatment_type' => 'consult',
                'description' => 'Complimentary consultation for new clients.',
                'duration_minutes' => 20,
                'base_price_pence' => 0,
                'supports_buy' => false,
                'packages' => [],
                'benefits' => [],
                'faqs' => [],
            ],
        ];

        $serviceModels = [];
        foreach ($servicesData as $svc) {
            $packages = $svc['packages'];
            $benefits = $svc['benefits'];
            $faqs = $svc['faqs'];
            unset($svc['packages'], $svc['benefits'], $svc['faqs']);

            $service = Service::create(array_merge([
                'supports_buy' => true,
                'supports_book' => true,
                'is_active' => true,
            ], $svc));

            foreach ($packages as $pkg) {
                ServicePackage::create(array_merge($pkg, ['service_id' => $service->id]));
            }
            foreach ($benefits as $benefit) {
                ServiceBenefit::create(array_merge($benefit, ['service_id' => $service->id]));
            }
            foreach ($faqs as $faq) {
                ServiceFaq::create(array_merge($faq, ['service_id' => $service->id]));
            }

            $serviceModels[$service->slug] = $service;
        }

        $matrix = [
            'reading' => [
                'laser-hair-removal-full-body' => ['buy_enabled' => true, 'book_enabled' => true, 'online_buy_enabled' => true],
                'hydrafacial-platinum' => ['buy_enabled' => true, 'book_enabled' => true, 'online_buy_enabled' => true],
                'anti-wrinkle-injections' => ['buy_enabled' => true, 'book_enabled' => true, 'online_buy_enabled' => true],
                'coolsculpting' => ['buy_enabled' => true, 'book_enabled' => true, 'online_buy_enabled' => true],
                'consultation' => ['buy_enabled' => false, 'book_enabled' => true, 'online_buy_enabled' => false],
            ],
            'london' => [
                'laser-hair-removal-full-body' => ['buy_enabled' => true, 'book_enabled' => true, 'online_buy_enabled' => true],
                'hydrafacial-platinum' => ['buy_enabled' => true, 'book_enabled' => true, 'online_buy_enabled' => true],
                'anti-wrinkle-injections' => ['buy_enabled' => true, 'book_enabled' => true, 'online_buy_enabled' => true],
                'coolsculpting' => ['buy_enabled' => false, 'book_enabled' => true, 'online_buy_enabled' => false],
                'consultation' => ['buy_enabled' => false, 'book_enabled' => true, 'online_buy_enabled' => false],
            ],
            'manchester' => [
                'laser-hair-removal-full-body' => ['buy_enabled' => true, 'book_enabled' => true, 'online_buy_enabled' => true],
                'hydrafacial-platinum' => ['buy_enabled' => false, 'book_enabled' => true, 'online_buy_enabled' => false],
                'anti-wrinkle-injections' => ['buy_enabled' => true, 'book_enabled' => true, 'online_buy_enabled' => true],
                'coolsculpting' => ['buy_enabled' => true, 'book_enabled' => false, 'online_buy_enabled' => true],
                'consultation' => ['buy_enabled' => false, 'book_enabled' => true, 'online_buy_enabled' => false],
            ],
        ];

        foreach ($matrix as $clinicSlug => $services) {
            $clinic = $clinicModels[$clinicSlug];
            foreach ($services as $serviceSlug => $flags) {
                $service = $serviceModels[$serviceSlug];
                ClinicService::create(array_merge([
                    'clinic_id' => $clinic->id,
                    'service_id' => $service->id,
                    'price_pence' => null,
                ], $flags));
            }
        }

        User::create([
            'name' => 'Admin User',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'username' => 'admin',
            'email' => 'admin@elixir.com',
            'password' => Hash::make('password'),
            'role' => 'superadmin',
            'is_verified' => true,
            'email_verified_at' => now(),
            'selected_clinic_id' => $clinicModels['reading']->id,
        ]);

        $manager = User::create([
            'name' => 'Reading Manager',
            'first_name' => 'Riley',
            'last_name' => 'Manager',
            'username' => 'riley',
            'email' => 'manager@elixir.com',
            'password' => Hash::make('password'),
            'role' => 'manager',
            'clinic_id' => $clinicModels['reading']->id,
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);

        $receptionist = User::create([
            'name' => 'Reading Reception',
            'first_name' => 'Sam',
            'last_name' => 'Desk',
            'username' => 'sam',
            'email' => 'reception@elixir.com',
            'password' => Hash::make('password'),
            'role' => 'receptionist',
            'clinic_id' => $clinicModels['reading']->id,
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);

        foreach ([$manager, $receptionist] as $staff) {
            \App\Models\ClinicStaff::create([
                'clinic_id' => $clinicModels['reading']->id,
                'user_id' => $staff->id,
                'job_title' => $staff->role === 'manager' ? 'Branch manager' : 'Front desk',
                'is_active' => true,
            ]);
        }

        User::create([
            'name' => 'Jane Patient',
            'first_name' => 'Jane',
            'last_name' => 'Patient',
            'username' => 'jane',
            'email' => 'jane@elixir.com',
            'password' => Hash::make('password'),
            'role' => 'patient',
            'is_verified' => true,
            'email_verified_at' => now(),
            'selected_clinic_id' => $clinicModels['reading']->id,
            'phone' => '+44 7700 900123',
        ]);

        Promotion::create([
            'code' => 'SAVE10',
            'type' => 'percent',
            'rules_json' => ['percent' => 10],
            'requires_login' => false,
            'is_active' => true,
        ]);

        $doctors = [
            ['name' => 'Dr. Sarah Mitchell', 'specialty' => 'Laser & Skin', 'clinics' => ['reading', 'london']],
            ['name' => 'Dr. James Chen', 'specialty' => 'Injectables', 'clinics' => ['london', 'manchester']],
            ['name' => 'Dr. Emma Walsh', 'specialty' => 'Body Contouring', 'clinics' => ['manchester']],
            ['name' => 'Dr. Michael Torres', 'specialty' => 'Dermatology', 'clinics' => ['reading', 'manchester']],
        ];

        foreach ($doctors as $doctor) {
            $clinicIds = array_map(fn ($slug) => $clinicModels[$slug]->id, $doctor['clinics']);
            $row = Doctor::create([
                'name' => $doctor['name'],
                'specialty' => $doctor['specialty'],
                'bio' => 'Experienced practitioner with over 10 years in aesthetic medicine.',
                'clinic_id' => $clinicIds[0],
                'is_active' => true,
            ]);
            $row->syncClinics($clinicIds);
        }

        $zaina = Doctor::create([
            'name' => 'Dr Zaina',
            'kicker' => 'Elixir Aesthetic Medicine Clinic',
            'name_accent' => 'EQ',
            'tagline' => 'Expert care. Natural beauty. A confident you.',
            'specialty' => 'Aesthetics & Laser',
            'professional_title' => 'Medical Doctor · Aesthetics & Laser',
            'bio' => 'Dr Zaina EQ is a Medical Doctor and Aesthetics & Laser Specialist. She combines science, safety and artistry for natural-looking results across medical aesthetics, laser, body contouring and intimate wellness.',
            'image' => '/Assets/zaina.png',
            'cv' => '/Assets/Dr-Zaina-EQ-CV.pdf',
            'credentials' => [
                ['label' => 'Medicine', 'detail' => 'MD, Universidade de Lisboa'],
                ['label' => 'Aesthetics', 'detail' => 'Master’s, TECH Universidad'],
                ['label' => 'Laser', 'detail' => 'Master’s, EFAP Lisbon'],
                ['label' => 'Research', 'detail' => 'PhD, University of Chichester'],
            ],
            'expertise_kicker' => 'Practice',
            'expertise_heading' => 'Areas of expertise',
            'expertise_tags' => 'Medicine · Aesthetics · Laser · Wellness',
            'expertise' => [
                ['icon' => 'bi-stars', 'title' => 'Medical Aesthetics', 'description' => 'Botox, dermal fillers, skin rejuvenation and facial harmonisation with a medical-first approach.'],
                ['icon' => 'bi-lightning-charge', 'title' => 'Laser Treatments', 'description' => 'Hair removal, skin resurfacing, pigmentation and vascular lesions using medical-grade laser.'],
                ['icon' => 'bi-circle', 'title' => 'Body Contouring', 'description' => 'Non-surgical fat reduction, skin tightening and cellulite treatment tailored to your goals.'],
                ['icon' => 'bi-heart', 'title' => 'Intimate Aesthetics', 'description' => 'Vaginal rejuvenation, PRP, feminine wellness and enhancement in a discreet clinical setting.'],
            ],
            'education' => [
                ['years' => '2011 – 2017', 'title' => 'Bachelor of Medicine', 'school' => 'Universidade de Lisboa', 'place' => 'Lisbon, Portugal', 'note' => 'Excellent GPA'],
                ['years' => '2021', 'title' => 'Master’s in Medical Aesthetics', 'school' => 'TECH Universidad', 'place' => 'Mexico City', 'note' => 'Clinical aesthetics'],
                ['years' => '2021', 'title' => 'Master’s Degree in Laser', 'school' => 'EFAP', 'place' => 'Lisbon, Portugal', 'note' => 'Laser medicine'],
                ['years' => 'In progress', 'title' => 'Doctoral research', 'school' => 'University of Chichester', 'place' => 'Chichester, UK', 'note' => 'PhD'],
            ],
            'promises' => [
                ['title' => 'Safe practice', 'copy' => 'Patient safety is the first decision in every treatment plan.'],
                ['title' => 'Natural results', 'copy' => 'Enhancement with restraint — never overdone, always considered.'],
                ['title' => 'Patient focused', 'copy' => 'Personalised care, explained clearly, delivered with respect.'],
                ['title' => 'Quiet confidence', 'copy' => 'Look like yourself, only more at ease in your own skin.'],
            ],
            'cta_title' => 'Ready to feel like yourself again?',
            'cta_copy' => 'Book a private consultation and we will design a plan around your features, not a trend.',
            'clinic_id' => $clinicModels['reading']->id,
            'is_active' => true,
        ]);
        $zaina->syncClinics(array_map(fn ($clinic) => $clinic->id, $clinicModels));
    }
}
