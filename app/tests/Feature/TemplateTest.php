<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class TemplateTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $name): UploadedFile
    {
        return new UploadedFile(
            base_path("tests/Fixtures/{$name}"),
            $name,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            null,
            true
        );
    }

    public function test_methodologist_can_upload_template(): void
    {
        $methodologist = User::factory()->create(['role' => 'methodologist']);

        $response = $this->actingAs($methodologist, 'sanctum')->postJson('/api/templates', [
            'name' => 'Договор аренды',
            'format' => 'docx',
            'file' => $this->fixture('template.docx'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'draft')
            ->assertJsonPath('variables_count', 0);

        $this->assertDatabaseHas('templates', ['name' => 'Договор аренды']);
        $this->assertDatabaseCount('template_versions', 1);
    }

    public function test_plain_user_cannot_upload_template(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/templates', [
            'name' => 'Договор аренды',
            'format' => 'docx',
            'file' => $this->fixture('template.docx'),
        ]);

        $response->assertForbidden();
    }

    public function test_extracting_variables_registers_placeholders_from_the_docx(): void
    {
        $methodologist = User::factory()->create(['role' => 'methodologist']);
        $template = $this->uploadTemplate($methodologist, 'template.docx');

        $response = $this->actingAs($methodologist, 'sanctum')
            ->postJson("/api/templates/{$template->id}/variables/extract");

        $response->assertOk()
            ->assertJsonPath('variables_count', 1)
            ->assertJsonPath('variables.0.key', 'client_name');
    }

    public function test_extracting_variables_rejects_broken_markup(): void
    {
        $methodologist = User::factory()->create(['role' => 'methodologist']);
        $template = $this->uploadTemplate($methodologist, 'broken_template.docx');

        $response = $this->actingAs($methodologist, 'sanctum')
            ->postJson("/api/templates/{$template->id}/variables/extract");

        $response->assertStatus(422)->assertJsonStructure(['errors']);
    }

    public function test_publish_fails_when_markup_is_broken(): void
    {
        $methodologist = User::factory()->create(['role' => 'methodologist']);
        $template = $this->uploadTemplate($methodologist, 'broken_template.docx');

        $response = $this->actingAs($methodologist, 'sanctum')
            ->postJson("/api/templates/{$template->id}/publish");

        $response->assertStatus(422);
        $this->assertDatabaseHas('templates', ['id' => $template->id, 'status' => 'draft']);
    }

    public function test_published_template_is_visible_to_plain_users(): void
    {
        $methodologist = User::factory()->create(['role' => 'methodologist']);
        $template = $this->uploadTemplate($methodologist, 'template.docx');
        $this->actingAs($methodologist, 'sanctum')->postJson("/api/templates/{$template->id}/publish")->assertOk();

        $draftTemplate = $this->uploadTemplate($methodologist, 'template.docx');

        $user = User::factory()->create(['role' => 'user']);
        $response = $this->actingAs($user, 'sanctum')->getJson('/api/templates');

        $response->assertOk();
        $ids = collect($response->json())->pluck('id');
        $this->assertTrue($ids->contains($template->id));
        $this->assertFalse($ids->contains($draftTemplate->id));
    }

    public function test_extracting_variables_registers_acroform_fields_from_a_pdf(): void
    {
        $methodologist = User::factory()->create(['role' => 'methodologist']);
        $template = $this->uploadTemplate($methodologist, 'form_template.pdf', 'pdf');

        $response = $this->actingAs($methodologist, 'sanctum')
            ->postJson("/api/templates/{$template->id}/variables/extract");

        $response->assertOk()->assertJsonPath('variables_count', 2);

        $variables = collect($response->json('variables'))->keyBy('key');
        $this->assertSame('text', $variables['client_name']['type']);
        $this->assertSame('boolean', $variables['agree']['type']);
    }

    private function uploadTemplate(User $methodologist, string $fixture, string $format = 'docx'): Template
    {
        $response = $this->actingAs($methodologist, 'sanctum')->postJson('/api/templates', [
            'name' => 'Договор '.$fixture,
            'format' => $format,
            'file' => $this->fixture($fixture),
        ]);

        return Template::find($response->json('id'));
    }
}
