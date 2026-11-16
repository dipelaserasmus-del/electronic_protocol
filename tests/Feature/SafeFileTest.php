<?php

namespace Tests\Feature;

use App\Livewire\ProtocolForm;
use App\Models\Attachment;
use App\Models\Protocol;
use App\Services\Protocols\ProtocolWriter;
use App\Support\SafeFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/** R4a (S06): attachments are given back safely and only safe files are taken in. */
class SafeFileTest extends FeatureTestCase
{
    private function attachment(string $name, string $content, ?string $mime): Attachment
    {
        $p = Protocol::factory()->number(1)->create();
        Storage::put("arxeio/Φ.1/1-x_$name", $content);
        return Attachment::factory()->create(['protocol_id' => $p->id, 'name' => $name, 'mimeType' => $mime, 'savedPath' => "arxeio/Φ.1/1-x_$name"]);
    }

    public function test_an_uploaded_html_page_is_downloaded_never_shown_as_a_page_of_the_application(): void
    {
        $a = $this->attachment('σελίδα.html', '<script>fetch("/users")</script>', 'text/html');

        $r = $this->actingAs($this->writer())->get("/download/{$a->id}")->assertOk();

        $this->assertSame('application/octet-stream', $r->headers->get('Content-Type'), 'before R4a: text/html, run in the app');
        $this->assertStringStartsWith('attachment;', $r->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $r->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('sandbox', $r->headers->get('Content-Security-Policy'));
        $this->assertSame('<script>fetch("/users")</script>', $r->streamedContent());
    }

    public function test_pdf_opens_in_the_browser_with_its_greek_name_encoded(): void
    {
        $a = $this->attachment('Απόφαση "τελική"; 2026.pdf', '%PDF-1.4 x', 'text/html');   // stored type is ignored

        $r = $this->actingAs($this->writer())->get("/download/{$a->id}")->assertOk();

        $this->assertSame('application/pdf', $r->headers->get('Content-Type'));
        $this->assertSame("inline; filename=\"Apofasi _teliki_; 2026.pdf\"; filename*=utf-8''" . rawurlencode('Απόφαση "τελική"; 2026.pdf'),
            preg_replace('/filename="[^"]*"/', 'filename="Apofasi _teliki_; 2026.pdf"', $r->headers->get('Content-Disposition')));
        $this->assertStringNotContainsString("\n", $r->headers->get('Content-Disposition'));
        $this->assertSame('%PDF-1.4 x', $r->streamedContent());
    }

    public function test_office_files_download_with_their_own_type(): void
    {
        $docx = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        $a = $this->attachment('Έγγραφο.docx', 'PK', $docx);
        $r = $this->actingAs($this->writer())->get("/download/{$a->id}")->assertOk();
        $this->assertSame($docx, $r->headers->get('Content-Type'));
        $this->assertStringStartsWith('attachment;', $r->headers->get('Content-Disposition'));
    }

    public function test_download_still_follows_the_access_rules_and_reports_a_missing_file(): void
    {
        $this->setting('limitProtocolAccessList', 1);
        $a = $this->attachment('μυστικό.pdf', '%PDF', 'application/pdf');
        $this->actingAs($this->writer())->get("/download/{$a->id}")->assertNotFound();

        Storage::delete($a->savedPath);
        $this->actingAs($this->admin())->from('/home/list')->get("/download/{$a->id}")->assertRedirect('/home/list');
        $this->assertStringContainsString('δεν υπάρχει στον δίσκο', session('notification')['message']);
    }

    public function test_files_that_run_code_are_refused_whatever_their_name(): void
    {
        $exe = "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xFF\xFF\x00\x00" . str_repeat("\0", 48) . 'This program cannot be run in DOS mode';
        $cases = [
            UploadedFile::fake()->createWithContent('πρόγραμμα.exe', 'x'),
            UploadedFile::fake()->createWithContent('τιμολόγιο.pdf.exe', 'x'),
            UploadedFile::fake()->createWithContent('script.JS', 'alert(1)'),
            UploadedFile::fake()->createWithContent('σελίδα.html', '<html><body>x</body></html>'),
            UploadedFile::fake()->createWithContent('εικόνα.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'),
            UploadedFile::fake()->createWithContent('έγγραφο.pdf', $exe),   // renamed program: the content says so
        ];
        foreach ($cases as $file) {
            $this->assertStringContainsString('δεν επιτρέπεται', (string) SafeFile::refuse($file), $file->getClientOriginalName());
        }
        foreach (['έγγραφο.pdf' => '%PDF-1.4', 'υπογεγραμμένο.pdf.p7m' => 'x', 'πίνακας.xlsx' => 'PK', 'φωτογραφία.jpg' => "\xFF\xD8\xFF", 'αρχείο.zip' => 'PK', 'μήνυμα.eml' => 'From: a'] as $name => $content) {
            $this->assertNull(SafeFile::refuse(UploadedFile::fake()->createWithContent($name, $content)), $name);
        }
    }

    public function test_size_limit_comes_from_the_settings_file(): void
    {
        config(['attachments.max_kb' => 1]);
        $this->assertStringContainsString('μεγαλύτερο από 1 KB', (string) SafeFile::refuse(UploadedFile::fake()->createWithContent('μεγάλο.pdf', str_repeat('x', 2048))));
        $this->assertNull(SafeFile::refuse(UploadedFile::fake()->createWithContent('μικρό.pdf', 'x')));
    }

    public function test_the_service_refuses_them_and_saves_nothing(): void
    {
        try {
            app(ProtocolWriter::class)->create($this->writer(), $this->protocolForm(['fakelos' => 'Φ.1']),
                [['file' => UploadedFile::fake()->createWithContent('ok.pdf', '%PDF'), 'ada' => null],
                 ['file' => UploadedFile::fake()->createWithContent('κακό.bat', 'del *'), 'ada' => null]]);
            $this->fail('accepted');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('κακό.bat', $e->errors()['uploads'][0]);
        }
        $this->assertSame(0, Protocol::count());
        $this->assertSame(0, Attachment::count());
    }

    public function test_the_form_says_so_at_once_and_keeps_the_good_files(): void
    {
        Livewire::actingAs($this->writer())->test(ProtocolForm::class)
            ->set('picked', [UploadedFile::fake()->createWithContent('καλό.pdf', '%PDF'), UploadedFile::fake()->createWithContent('κακό.vbs', 'x')])
            ->assertCount('uploads', 1)->assertHasErrors('picked')
            ->assertSee('Το «κακό.vbs» δεν επιτρέπεται');
    }
}
