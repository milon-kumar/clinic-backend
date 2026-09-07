<?php

namespace Tests\Feature;

use App\Models\ClinicStaff;
use Tests\PlatformTestCase;

class AdminDoctorApiTest extends PlatformTestCase
{
    public function test_public_doctors_include_every_assigned_clinic(): void
    {
        $reading = $this->createClinic(['name' => 'Reading', 'code' => 'RDG-D', 'slug' => 'reading-doc']);
        $london = $this->createClinic(['name' => 'London', 'code' => 'LON-D', 'slug' => 'london-doc']);
        $doctor = $this->createDoctor(['name' => 'Dr Sarah'], [$reading->id, $london->id]);

        $this->getJson('/api/v1/doctors')
            ->assertOk()
            ->assertJsonPath('data.0.id', $doctor->id)
            ->assertJsonCount(2, 'data.0.clinics')
            ->assertJsonPath('data.0.clinics.0.city', 'Reading')
            ->assertJsonPath('data.0.clinics.0.hours.0.openTime', '09:00:00');

        $this->getJson('/api/v1/doctors?clinicId='.$reading->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Dr Sarah')
            ->assertJsonCount(2, 'data.0.clinics');

        $this->getJson('/api/v1/doctors/'.$doctor->id)
            ->assertOk()
            ->assertJsonPath('data.clinics.0.name', 'London')
            ->assertJsonPath('data.clinics.1.name', 'Reading');
    }

    public function test_superadmin_creates_and_assigns_a_doctor_to_multiple_clinics(): void
    {
        $reading = $this->createClinic(['name' => 'Reading', 'code' => 'RDG-A', 'slug' => 'reading-assign']);
        $london = $this->createClinic(['name' => 'London', 'code' => 'LON-A', 'slug' => 'london-assign']);
        $this->actingAsUser($this->createUser(['role' => 'superadmin']));

        $created = $this->postJson('/api/v1/admin/doctors', [
            'name' => 'Dr James',
            'specialty' => 'Injectables',
            'clinicIds' => [$reading->id],
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Dr James')
            ->assertJsonCount(1, 'data.clinics');

        $doctorId = $created->json('data.id');

        $this->postJson("/api/v1/admin/clinics/{$london->id}/doctors", [
            'doctorId' => $doctorId,
        ])->assertOk()
            ->assertJsonCount(2, 'data.clinics');

        $this->getJson('/api/v1/admin/doctors?clinicId='.$london->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(2, 'data.0.clinics');

        $this->getJson("/api/v1/admin/doctors/{$doctorId}")
            ->assertOk()
            ->assertJsonCount(2, 'data.clinics')
            ->assertJsonFragment(['id' => $reading->id, 'name' => 'Reading'])
            ->assertJsonFragment(['id' => $london->id, 'name' => 'London']);
    }

    public function test_branch_staff_only_see_doctors_at_their_clinic(): void
    {
        $reading = $this->createClinic(['name' => 'Reading', 'code' => 'RDG-S', 'slug' => 'reading-scope-doc']);
        $london = $this->createClinic(['name' => 'London', 'code' => 'LON-S', 'slug' => 'london-scope-doc']);
        $this->createDoctor(['name' => 'Reading Doctor'], [$reading->id]);
        $this->createDoctor(['name' => 'London Doctor'], [$london->id]);

        $receptionist = $this->createUser([
            'role' => 'receptionist',
            'clinic_id' => $reading->id,
        ]);
        ClinicStaff::create([
            'clinic_id' => $reading->id,
            'user_id' => $receptionist->id,
            'is_active' => true,
        ]);

        $this->actingAsUser($receptionist);

        $this->getJson('/api/v1/admin/doctors')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Reading Doctor');

        $this->postJson('/api/v1/admin/doctors', [
            'name' => 'Dr New',
            'clinicIds' => [$reading->id],
        ])->assertForbidden();
    }

    public function test_unassigning_a_doctor_keeps_remaining_clinics(): void
    {
        $reading = $this->createClinic(['name' => 'Reading', 'code' => 'RDG-U', 'slug' => 'reading-unassign']);
        $london = $this->createClinic(['name' => 'London', 'code' => 'LON-U', 'slug' => 'london-unassign']);
        $doctor = $this->createDoctor(['name' => 'Dr Emma'], [$reading->id, $london->id]);
        $this->actingAsUser($this->createUser(['role' => 'superadmin']));

        $this->deleteJson("/api/v1/admin/clinics/{$reading->id}/doctors/{$doctor->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.clinics')
            ->assertJsonPath('data.clinics.0.id', $london->id);
    }

    public function test_superadmin_can_attach_a_cv_to_a_doctor(): void
    {
        $clinic = $this->createClinic(['name' => 'Reading', 'code' => 'RDG-CV', 'slug' => 'reading-cv']);
        $this->actingAsUser($this->createUser(['role' => 'superadmin']));

        $created = $this->postJson('/api/v1/admin/doctors', [
            'name' => 'Dr Claire',
            'specialty' => 'Laser',
            'bio' => 'Public profile copy.',
            'cv' => '/storage/doctors/cv/claire.pdf',
            'clinicIds' => [$clinic->id],
        ])->assertCreated()
            ->assertJsonPath('data.cv', '/storage/doctors/cv/claire.pdf');

        $this->getJson('/api/v1/doctors/'.$created->json('data.id'))
            ->assertOk()
            ->assertJsonPath('data.cv', '/storage/doctors/cv/claire.pdf')
            ->assertJsonPath('data.bio', 'Public profile copy.');
    }

    public function test_superadmin_can_save_full_public_profile_fields(): void
    {
        $clinic = $this->createClinic(['name' => 'Reading', 'code' => 'RDG-P', 'slug' => 'reading-profile']);
        $this->actingAsUser($this->createUser(['role' => 'superadmin']));

        $created = $this->postJson('/api/v1/admin/doctors', [
            'name' => 'Dr Zaina',
            'nameAccent' => 'EQ',
            'kicker' => 'Elixir Aesthetic Medicine Clinic',
            'tagline' => 'Expert care. Natural beauty. A confident you.',
            'specialty' => 'Aesthetics & Laser',
            'professionalTitle' => 'Medical Doctor · Aesthetics & Laser',
            'bio' => 'Medical-first aesthetic care.',
            'credentials' => [
                ['label' => 'Medicine', 'detail' => 'MD, Universidade de Lisboa'],
                ['label' => '', 'detail' => ''],
            ],
            'expertiseKicker' => 'Practice',
            'expertiseHeading' => 'Areas of expertise',
            'expertiseTags' => 'Medicine · Aesthetics · Laser · Wellness',
            'expertise' => [
                ['icon' => 'bi-stars', 'title' => 'Medical Aesthetics', 'description' => 'Botox and fillers.'],
                ['icon' => 'bi-heart', 'title' => '', 'description' => 'skip me'],
            ],
            'education' => [
                [
                    'years' => '2011 – 2017',
                    'title' => 'Bachelor of Medicine',
                    'school' => 'Universidade de Lisboa',
                    'place' => 'Lisbon',
                    'note' => 'Excellent GPA',
                ],
            ],
            'promises' => [
                ['title' => 'Safe practice', 'copy' => 'Patient safety first.'],
            ],
            'ctaTitle' => 'Ready to feel like yourself again?',
            'ctaCopy' => 'Book a private consultation.',
            'clinicIds' => [$clinic->id],
        ])->assertCreated()
            ->assertJsonPath('data.nameAccent', 'EQ')
            ->assertJsonPath('data.professionalTitle', 'Medical Doctor · Aesthetics & Laser')
            ->assertJsonCount(1, 'data.credentials')
            ->assertJsonPath('data.credentials.0.label', 'Medicine')
            ->assertJsonCount(1, 'data.expertise')
            ->assertJsonPath('data.expertise.0.title', 'Medical Aesthetics')
            ->assertJsonPath('data.ctaTitle', 'Ready to feel like yourself again?');

        $this->getJson('/api/v1/doctors/'.$created->json('data.id'))
            ->assertOk()
            ->assertJsonPath('data.tagline', 'Expert care. Natural beauty. A confident you.')
            ->assertJsonPath('data.education.0.school', 'Universidade de Lisboa')
            ->assertJsonPath('data.promises.0.title', 'Safe practice');
    }
}
