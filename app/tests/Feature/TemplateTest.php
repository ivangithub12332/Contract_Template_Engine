<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\User;
use App\Models\Variable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

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

    public function test_extracting_variables_normalizes_placeholders_split_by_word_runs(): void
    {
        $methodologist = User::factory()->create(['role' => 'methodologist']);
        $template = $this->uploadTemplateFile($methodologist, $this->splitPlaceholderFixture());

        $response = $this->actingAs($methodologist, 'sanctum')
            ->postJson("/api/templates/{$template->id}/variables/extract");

        $response->assertOk()
            ->assertJsonPath('variables_count', 1)
            ->assertJsonPath('variables.0.key', 'client_name');
    }

    public function test_extracting_variables_removes_invalid_keys_saved_before_normalization(): void
    {
        $methodologist = User::factory()->create(['role' => 'methodologist']);
        $template = $this->uploadTemplateFile($methodologist, $this->splitPlaceholderFixture());

        Variable::create([
            'template_id' => $template->id,
            'key' => '</w:t><w:r><w:t>client_name',
            'label' => 'broken',
            'type' => 'text',
            'required' => false,
        ]);

        $response = $this->actingAs($methodologist, 'sanctum')
            ->postJson("/api/templates/{$template->id}/variables/extract");

        $response->assertOk();

        $keys = Variable::where('template_id', $template->id)->pluck('key')->all();
        $this->assertSame(['client_name'], $keys);
    }

    public function test_extracting_variables_detects_conditional_and_table_blocks(): void
    {
        $methodologist = User::factory()->create(['role' => 'methodologist']);
        $template = $this->uploadTemplateFile($methodologist, $this->blockFixture());

        $response = $this->actingAs($methodologist, 'sanctum')
            ->postJson("/api/templates/{$template->id}/variables/extract");

        $response->assertOk()->assertJsonPath('variables_count', 4);

        $variables = collect($response->json('variables'))->keyBy('key');
        $this->assertSame('text', $variables['contract_number']['type']);
        $this->assertSame('table', $variables['items']['type']);
        $this->assertSame(['columns' => ['item_name', 'quantity']], $variables['items']['options']);
        $this->assertSame('boolean', $variables['has_delivery']['type']);
        $this->assertSame('text', $variables['delivery_address']['type']);
        $this->assertFalse($variables->has('item_name'));
        $this->assertFalse($variables->has('quantity'));
    }

    public function test_extracting_variables_removes_table_columns_saved_as_standalone_fields(): void
    {
        $methodologist = User::factory()->create(['role' => 'methodologist']);
        $template = $this->uploadTemplateFile($methodologist, $this->blockFixture());

        Variable::create([
            'template_id' => $template->id,
            'key' => 'item_name',
            'label' => 'item_name',
            'type' => 'text',
            'required' => false,
        ]);

        $response = $this->actingAs($methodologist, 'sanctum')
            ->postJson("/api/templates/{$template->id}/variables/extract");

        $response->assertOk();

        $keys = Variable::where('template_id', $template->id)->pluck('key')->all();
        $this->assertNotContains('item_name', $keys);
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
        return $this->uploadTemplateFile($methodologist, $this->fixture($fixture), $format, $fixture);
    }

    private function uploadTemplateFile(
        User $methodologist,
        UploadedFile $file,
        string $format = 'docx',
        string $name = 'template.docx'
    ): Template {
        $response = $this->actingAs($methodologist, 'sanctum')->postJson('/api/templates', [
            'name' => 'Договор '.$name,
            'format' => $format,
            'file' => $file,
        ]);

        return Template::find($response->json('id'));
    }

    private function splitPlaceholderFixture(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'split-placeholder').'.docx';
        copy(base_path('tests/Fixtures/template.docx'), $path);

        $zip = new ZipArchive();
        $zip->open($path);
        $xml = $zip->getFromName('word/document.xml');
        $xml = str_replace(
            '{{client_name}}',
            '{{cli</w:t></w:r><w:r><w:t>ent_name}}',
            $xml
        );
        $zip->deleteName('word/document.xml');
        $zip->addFromString('word/document.xml', $xml);
        $zip->close();

        return new UploadedFile(
            $path,
            'split_placeholder.docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            null,
            true
        );
    }

    private function blockFixture(): UploadedFile
    {
        return $this->modifiedTemplateFixture(
            '{{contract_number}} {{items}}{{item_name}} {{quantity}}{{/items}} {{has_delivery}}{{delivery_address}}{{/has_delivery}}',
            'block_template.docx'
        );
    }

    private function modifiedTemplateFixture(string $replacement, string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'template').'.docx';
        copy(base_path('tests/Fixtures/template.docx'), $path);

        $zip = new ZipArchive();
        $zip->open($path);
        $xml = $zip->getFromName('word/document.xml');
        $xml = str_replace('{{client_name}}', $replacement, $xml);
        $zip->deleteName('word/document.xml');
        $zip->addFromString('word/document.xml', $xml);
        $zip->close();

        return new UploadedFile(
            $path,
            $name,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            null,
            true
        );
    }
}
