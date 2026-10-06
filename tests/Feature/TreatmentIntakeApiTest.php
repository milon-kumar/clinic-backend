<?php

namespace Tests\Feature;

use App\Mail\PurchaseConfirmedMail;
use App\Models\CategoryLanding;
use Illuminate\Support\Facades\Mail;
use Tests\PlatformTestCase;

class TreatmentIntakeApiTest extends PlatformTestCase
{
    public function test_purchase_emails_question_link_and_admin_can_read_answers(): void
    {
        Mail::fake();

        $admin = $this->createUser(['role' => 'superadmin']);
        $customer = $this->createUser(['email' => 'intake-buyer@example.com', 'name' => 'Amina Khan']);
        $clinic = $this->createClinic(['slug' => 'intake-clinic', 'code' => 'INTAKE']);
        $service = $this->createService(['name' => 'Hydrafacial', 'base_price_pence' => 12000]);
        $this->attachServiceToClinic($clinic, $service);

        CategoryLanding::create([
            'slug' => 'skin-faq',
            'category' => 'skin',
            'title' => 'Skin',
            'faqs' => [
                ['question' => 'Are you pregnant?', 'options' => ['yes', 'no']],
                ['question' => 'List any allergies', 'answer' => ''],
            ],
            'is_active' => true,
        ]);

        $customer->update(['selected_clinic_id' => $clinic->id]);
        $this->actingAsUser($customer);

        $this->postJson('/api/v1/cart/lines', [
            'serviceId' => $service->id,
            'quantity' => 1,
            'clinicId' => $clinic->id,
        ])->assertOk();

        $confirm = $this->postJson('/api/v1/buy/checkout/confirm')->assertOk();
        $packageId = $confirm->json('data.packages.0.id');

        $link = null;
        Mail::assertSent(PurchaseConfirmedMail::class, function (PurchaseConfirmedMail $mail) use ($customer, &$link) {
            $links = $mail->payload['questionLinks'] ?? [];
            $link = $links[0]['url'] ?? null;

            return $mail->hasTo($customer->email)
                && count($links) === 1
                && str_contains((string) $link, '/intake/');
        });

        $token = basename((string) $link);

        $this->getJson('/api/v1/customers/me/packages')
            ->assertOk()
            ->assertJsonPath('data.0.intake.status', 'pending')
            ->assertJsonPath('data.0.intake.token', $token);

        $this->getJson('/api/v1/intake/'.$token)
            ->assertOk()
            ->assertJsonPath('data.treatmentName', 'Hydrafacial')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonCount(2, 'data.questions');

        $questions = $this->getJson('/api/v1/intake/'.$token)->json('data.questions');

        $this->postJson('/api/v1/intake/'.$token, [
            'answers' => [
                ['id' => $questions[0]['id'], 'answer' => 'no'],
                ['id' => $questions[1]['id'], 'answer' => 'None'],
            ],
        ])->assertOk()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.questions.0.answer', 'no')
            ->assertJsonPath('data.questions.1.answer', 'None');

        $this->postJson('/api/v1/intake/'.$token, [
            'answers' => [
                ['id' => $questions[0]['id'], 'answer' => 'yes'],
                ['id' => $questions[1]['id'], 'answer' => 'Changed'],
            ],
        ])->assertStatus(422);

        $this->actingAsUser($admin);
        $this->getJson('/api/v1/admin/treatment-journeys/'.$packageId)
            ->assertOk()
            ->assertJsonPath('data.intake.status', 'submitted')
            ->assertJsonPath('data.intake.questions.0.prompt', 'Are you pregnant?')
            ->assertJsonPath('data.intake.questions.0.answer', 'no')
            ->assertJsonPath('data.intake.questions.1.answer', 'None');
    }

    public function test_superadmin_saves_pre_questions_on_a_treatment(): void
    {
        $this->actingAsUser($this->createUser(['role' => 'superadmin']));

        $created = $this->postJson('/api/v1/admin/services', [
            'name' => 'PRP Face',
            'category' => 'skin',
            'basePricePence' => 15000,
            'preQuestions' => [
                ['prompt' => 'Any recent surgery?', 'answerType' => 'yes_no', 'required' => true],
                ['prompt' => '', 'answerType' => 'text'],
                ['prompt' => 'Current medication', 'answerType' => 'text', 'required' => false],
            ],
        ])->assertCreated()
            ->assertJsonCount(2, 'data.preQuestions')
            ->assertJsonPath('data.preQuestions.0.answerType', 'yes_no')
            ->assertJsonPath('data.preQuestions.1.prompt', 'Current medication')
            ->assertJsonPath('data.preQuestions.1.required', false);

        $id = $created->json('data.id');

        $this->putJson('/api/v1/admin/services/'.$id, [
            'name' => 'PRP Face',
            'preQuestions' => [],
        ])->assertOk()->assertJsonCount(0, 'data.preQuestions');
    }
}
