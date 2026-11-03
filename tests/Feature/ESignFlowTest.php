<?php

namespace Tests\Feature;

use App\Mail\DocumentCompleted;
use App\Mail\DocumentDeclined;
use App\Mail\SigningRequest;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use Tests\TestCase;

class ESignFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
    }

    protected function samplePdf(int $pages = 2): UploadedFile
    {
        $pdf = new \FPDF;
        for ($i = 1; $i <= $pages; $i++) {
            $pdf->AddPage();
            $pdf->SetFont('Helvetica', '', 14);
            $pdf->Cell(0, 10, "Agreement page $i");
        }

        return UploadedFile::fake()->createWithContent('agreement.pdf', $pdf->Output('S'));
    }

    protected function pngDataUrl(): string
    {
        $img = imagecreatetruecolor(200, 60);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        imageline($img, 10, 50, 190, 10, imagecolorallocate($img, 0, 0, 0));
        ob_start();
        imagepng($img);

        return 'data:image/png;base64,'.base64_encode(ob_get_clean());
    }

    protected function uploadAndSetup(User $user, bool $sequential = false): Document
    {
        $this->actingAs($user)->post('/documents', ['title' => 'NDA', 'file' => $this->samplePdf()])
            ->assertRedirect();
        $document = Document::firstOrFail();
        $this->assertSame(2, $document->page_count);

        $setup = [
            'signers' => [
                ['key' => 'a', 'name' => 'Alice Doe', 'email' => 'alice@example.com', 'order' => 1],
                ['key' => 'b', 'name' => 'Bob Roe', 'email' => 'bob@example.com', 'order' => 2],
            ],
            'fields' => [
                ['signer' => 'a', 'type' => 'signature', 'page' => 1, 'x' => 0.1, 'y' => 0.8, 'width' => 0.25, 'height' => 0.06],
                ['signer' => 'a', 'type' => 'date', 'page' => 1, 'x' => 0.4, 'y' => 0.8, 'width' => 0.15, 'height' => 0.03],
                ['signer' => 'a', 'type' => 'text', 'page' => 2, 'x' => 0.1, 'y' => 0.2, 'width' => 0.3, 'height' => 0.03, 'required' => true],
                ['signer' => 'b', 'type' => 'signature', 'page' => 2, 'x' => 0.5, 'y' => 0.8, 'width' => 0.25, 'height' => 0.06],
                ['signer' => 'b', 'type' => 'name', 'page' => 2, 'x' => 0.5, 'y' => 0.88, 'width' => 0.25, 'height' => 0.03],
            ],
        ];

        $this->put("/documents/{$document->id}", [
            'title' => 'NDA', 'message' => 'Please sign', 'sequential' => $sequential ? '1' : '0',
            'action' => 'send', 'setup' => json_encode($setup),
        ])->assertRedirect("/documents/{$document->id}");

        return $document->fresh('signers');
    }

    public function test_full_signing_flow_produces_signed_pdf(): void
    {
        $user = User::factory()->create();
        $document = $this->uploadAndSetup($user);

        $this->assertSame('sent', $document->status);
        Mail::assertSent(SigningRequest::class, 2);

        [$alice, $bob] = $document->signers;

        $this->get("/sign/{$alice->token}")->assertOk()->assertSee('NDA');
        $this->get("/sign/{$alice->token}/file")->assertOk();
        $this->assertSame('viewed', $alice->fresh()->status);

        $textField = $alice->fields()->where('type', 'text')->first();
        $sigField = $alice->fields()->where('type', 'signature')->first();

        // Missing required text field is rejected.
        $this->post("/sign/{$alice->token}", ['consent' => '1', 'values' => [$sigField->id => $this->pngDataUrl()]])
            ->assertSessionHasErrors('values');

        // Missing consent is rejected.
        $this->post("/sign/{$alice->token}", ['values' => [$sigField->id => $this->pngDataUrl(), $textField->id => 'ACME']])
            ->assertSessionHasErrors('consent');

        $this->post("/sign/{$alice->token}", ['consent' => '1', 'values' => [$sigField->id => $this->pngDataUrl(), $textField->id => 'ACME Ltd']])
            ->assertRedirect("/sign/{$alice->token}");
        $this->assertSame('signed', $alice->fresh()->status);
        $this->assertSame('sent', $document->fresh()->status);

        // Cannot sign twice.
        $this->post("/sign/{$alice->token}", ['consent' => '1', 'values' => []])->assertForbidden();

        $bobSig = $bob->fields()->where('type', 'signature')->first();
        $this->post("/sign/{$bob->token}", ['consent' => '1', 'values' => [$bobSig->id => $this->pngDataUrl()]])
            ->assertRedirect();

        $document->refresh();
        $this->assertSame('completed', $document->status);
        $this->assertNotNull($document->signed_path);
        Storage::disk('local')->assertExists($document->signed_path);
        $this->assertSame(hash('sha256', Storage::disk('local')->get($document->signed_path)), $document->signed_hash);

        // 2 document pages + certificate page.
        $this->assertSame(3, (new Fpdi)->setSourceFile(Storage::disk('local')->path($document->signed_path)));

        Mail::assertSent(DocumentCompleted::class, 3);
        $this->get("/documents/{$document->id}/download")->assertOk();
        $this->get("/sign/{$bob->token}/download")->assertOk();
        $this->assertSame(['created', 'sent', 'emailed', 'emailed', 'viewed', 'signed', 'signed', 'completed'], $document->auditLogs()->pluck('action')->all());
    }

    public function test_sequential_signing_waits_for_previous_signer(): void
    {
        $user = User::factory()->create();
        $document = $this->uploadAndSetup($user, sequential: true);
        [$alice, $bob] = $document->signers;

        Mail::assertSent(SigningRequest::class, 1);
        Mail::assertSent(SigningRequest::class, fn ($m) => $m->hasTo('alice@example.com'));
        $this->get("/sign/{$bob->token}")->assertOk()->assertSee('Not your turn yet');
        $this->post("/sign/{$bob->token}", ['consent' => '1'])->assertForbidden();

        $values = [];
        foreach ($alice->fields as $f) {
            $values[$f->id] = $f->type === 'signature' ? $this->pngDataUrl() : 'x';
        }
        $this->post("/sign/{$alice->token}", ['consent' => '1', 'values' => $values])->assertRedirect();

        Mail::assertSent(SigningRequest::class, fn ($m) => $m->hasTo('bob@example.com'));
    }

    public function test_decline_cancels_document(): void
    {
        $user = User::factory()->create();
        $document = $this->uploadAndSetup($user);
        $alice = $document->signers[0];

        $this->post("/sign/{$alice->token}/decline", ['reason' => 'Wrong terms'])->assertRedirect();

        $this->assertSame('declined', $document->fresh()->status);
        Mail::assertSent(DocumentDeclined::class);
        $this->get("/sign/{$document->signers[1]->token}")->assertSee('Signing cancelled');
    }

    public function test_send_requires_signature_field_for_each_signer(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/documents', ['title' => 'NDA', 'file' => $this->samplePdf(1)]);
        $document = Document::firstOrFail();

        $setup = ['signers' => [['key' => 'a', 'name' => 'A', 'email' => 'a@example.com', 'order' => 1]], 'fields' => []];
        $this->put("/documents/{$document->id}", ['title' => 'NDA', 'action' => 'send', 'setup' => json_encode($setup)])
            ->assertSessionHasErrors('setup');
        $this->assertSame('draft', $document->fresh()->status);

        // Saving a draft without fields is fine.
        $this->put("/documents/{$document->id}", ['title' => 'NDA 2', 'action' => 'save', 'setup' => json_encode($setup)])
            ->assertSessionHasNoErrors();
        $this->assertSame('NDA 2', $document->fresh()->title);
        $this->get("/documents/{$document->id}/edit")->assertOk();
    }

    public function test_users_cannot_access_others_documents(): void
    {
        $document = Document::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($other)->get("/documents/{$document->id}")->assertForbidden();
        $this->get("/documents/{$document->id}/edit")->assertForbidden();
        $this->get("/documents/{$document->id}/file")->assertForbidden();
        $this->delete("/documents/{$document->id}")->assertForbidden();
    }

    public function test_rejects_non_pdf_upload(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/documents', ['title' => 'x', 'file' => UploadedFile::fake()->create('a.txt', 3, 'text/plain')])
            ->assertSessionHasErrors('file');
    }

    public function test_register_login_and_dashboard(): void
    {
        $this->post('/register', ['name' => 'Jane', 'email' => 'jane@example.com', 'password' => 'secret123', 'password_confirmation' => 'secret123'])
            ->assertRedirect('/documents');
        $this->get('/documents')->assertOk()->assertSee('Documents');
        $this->post('/logout');
        $this->get('/documents')->assertRedirect('/login');
        $this->post('/login', ['email' => 'jane@example.com', 'password' => 'secret123'])->assertRedirect('/documents');
    }
}
