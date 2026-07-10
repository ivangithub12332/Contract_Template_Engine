<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\User;
use App\Models\Variable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    private function publishedTemplate(): Template
    {
        $methodologist = User::factory()->create(['role' => 'methodologist']);

        $file = new UploadedFile(
            base_path('tests/Fixtures/template.docx'),
            'template.docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            null,
            true
        );

        $response = $this->actingAs($methodologist, 'sanctum')->postJson('/api/templates', [
            'name' => 'Договор аренды',
            'format' => 'docx',
            'file' => $file,
        ]);
        $template = Template::find($response->json('id'));

        $this->actingAs($methodologist, 'sanctum')
            ->postJson("/api/templates/{$template->id}/variables/extract")
            ->assertOk();

        Variable::where('template_id', $template->id)->update(['required' => true]);

        $this->actingAs($methodologist, 'sanctum')
            ->postJson("/api/templates/{$template->id}/publish")
            ->assertOk();

        return $template->fresh();
    }

    public function test_user_can_generate_document_from_published_template(): void
    {
        $template = $this->publishedTemplate();
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/templates/{$template->id}/documents", [
            'values' => ['client_name' => 'Acme LLC'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('values.0.variable_key', 'client_name')
            ->assertJsonPath('values.0.value', 'Acme LLC');

        $this->assertDatabaseCount('documents', 1);
    }

    public function test_generation_fails_when_required_value_is_missing(): void
    {
        $template = $this->publishedTemplate();
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/templates/{$template->id}/documents", [
            'values' => [],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_generation_fails_for_unpublished_template(): void
    {
        $methodologist = User::factory()->create(['role' => 'methodologist']);
        $file = new UploadedFile(
            base_path('tests/Fixtures/template.docx'),
            'template.docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            null,
            true
        );
        $response = $this->actingAs($methodologist, 'sanctum')->postJson('/api/templates', [
            'name' => 'Черновик',
            'format' => 'docx',
            'file' => $file,
        ]);
        $template = Template::find($response->json('id'));

        $user = User::factory()->create(['role' => 'user']);
        $response = $this->actingAs($user, 'sanctum')->postJson("/api/templates/{$template->id}/documents", [
            'values' => ['client_name' => 'Acme LLC'],
        ]);

        $response->assertStatus(422);
    }

    public function test_user_cannot_access_another_users_document(): void
    {
        $template = $this->publishedTemplate();
        $owner = User::factory()->create(['role' => 'user']);
        $other = User::factory()->create(['role' => 'user']);

        $created = $this->actingAs($owner, 'sanctum')->postJson("/api/templates/{$template->id}/documents", [
            'values' => ['client_name' => 'Acme LLC'],
        ]);
        $documentId = $created->json('id');

        $this->actingAs($other, 'sanctum')->getJson("/api/documents/{$documentId}")
            ->assertForbidden();

        $this->actingAs($owner, 'sanctum')->getJson("/api/documents/{$documentId}")
            ->assertOk();
    }

    public function test_history_list_is_scoped_to_the_requesting_user(): void
    {
        $template = $this->publishedTemplate();
        $owner = User::factory()->create(['role' => 'user']);
        $other = User::factory()->create(['role' => 'user']);

        $this->actingAs($owner, 'sanctum')->postJson("/api/templates/{$template->id}/documents", [
            'values' => ['client_name' => 'Acme LLC'],
        ])->assertCreated();

        $this->actingAs($other, 'sanctum')->getJson('/api/documents')
            ->assertOk()
            ->assertJsonCount(0);

        $this->actingAs($owner, 'sanctum')->getJson('/api/documents')
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_table_and_boolean_defaults_are_used_and_saved_for_recreation(): void
    {
        $methodologist = User::factory()->create(['role' => 'methodologist']);
        $template = $this->uploadBlockTemplate($methodologist);
        $variables = $template->variables()->get()->keyBy('key');
        $tableDefault = [
            ['attachment_name' => 'Приложение 1', 'attachment_note' => 'Первый файл'],
            ['attachment_name' => 'Приложение 2', 'attachment_note' => 'Второй файл'],
        ];

        $variables['attachments']->update([
            'default_value' => json_encode($tableDefault, JSON_UNESCAPED_UNICODE),
        ]);
        $variables['include_extra_terms']->update(['default_value' => 'true']);

        $this->actingAs($methodologist, 'sanctum')
            ->postJson("/api/templates/{$template->id}/publish")
            ->assertOk();

        $user = User::factory()->create(['role' => 'user']);
        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/templates/{$template->id}/documents", ['values' => []]);

        $response->assertCreated();
        $values = collect($response->json('values'))->keyBy('variable_key');
        $this->assertSame($tableDefault, $values['attachments']['value']);
        $this->assertTrue($values['include_extra_terms']['value']);
    }

    public function test_user_can_generate_document_from_a_published_pdf_template(): void
    {
        $methodologist = User::factory()->create(['role' => 'methodologist']);
        $file = new UploadedFile(
            base_path('tests/Fixtures/form_template.pdf'),
            'form_template.pdf',
            'application/pdf',
            null,
            true
        );
        $template = Template::find(
            $this->actingAs($methodologist, 'sanctum')->postJson('/api/templates', [
                'name' => 'PDF-анкета',
                'format' => 'pdf',
                'file' => $file,
            ])->json('id')
        );
        $this->actingAs($methodologist, 'sanctum')
            ->postJson("/api/templates/{$template->id}/variables/extract")->assertOk();
        $this->actingAs($methodologist, 'sanctum')
            ->postJson("/api/templates/{$template->id}/publish")->assertOk();

        $user = User::factory()->create(['role' => 'user']);
        $response = $this->actingAs($user, 'sanctum')->postJson("/api/templates/{$template->id}/documents", [
            'values' => ['client_name' => 'Acme LLC', 'agree' => true],
        ]);

        $response->assertCreated();
        $documentId = $response->json('id');

        $download = $this->actingAs($user, 'sanctum')->get("/api/documents/{$documentId}/download");
        $download->assertOk();
        $this->assertStringStartsWith('%PDF', $download->streamedContent());
    }

    private function uploadBlockTemplate(User $methodologist): Template
    {
        $path = tempnam(sys_get_temp_dir(), 'block-defaults').'.docx';
        copy(base_path('tests/Fixtures/template.docx'), $path);

        $zip = new ZipArchive();
        $zip->open($path);
        $xml = $zip->getFromName('word/document.xml');
        $xml = str_replace(
            '{{client_name}}',
            '{{include_extra_terms}}Условия включены{{/include_extra_terms}} '
                .'{{attachments}}{{attachment_name}}: {{attachment_note}}{{/attachments}}',
            $xml
        );
        $zip->deleteName('word/document.xml');
        $zip->addFromString('word/document.xml', $xml);
        $zip->close();

        $file = new UploadedFile(
            $path,
            'block_defaults.docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            null,
            true
        );
        $template = Template::find(
            $this->actingAs($methodologist, 'sanctum')->postJson('/api/templates', [
                'name' => 'Шаблон со значениями по умолчанию',
                'format' => 'docx',
                'file' => $file,
            ])->json('id')
        );

        $this->actingAs($methodologist, 'sanctum')
            ->postJson("/api/templates/{$template->id}/variables/extract")
            ->assertOk();

        return $template->fresh('variables');
    }
}
