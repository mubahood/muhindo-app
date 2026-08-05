<?php

namespace Tests\Feature\Documents;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Muhindo's signature on the documents he issues.
 *
 * Three things worth holding: it appears where he is genuinely the signatory,
 * it does NOT appear on a receipt that names somebody else as having taken the
 * money, and it is not sitting at a public URL for anyone to lift.
 */
class SignatureTest extends TestCase
{
    use RefreshDatabase;

    private function render(string $view, array $data = []): string
    {
        return (string) view($view, $data)->render();
    }

    public function test_the_signature_is_not_served_over_the_web(): void
    {
        /*
         * DomPDF reads it off the filesystem, so nothing needs it to be
         * web-reachable. A signature at a guessable URL is one anyone can
         * download and paste onto a document of their own.
         */
        $this->assertFileExists(resource_path('brand/signature.png'));
        $this->assertFileDoesNotExist(public_path('brand/signature.png'));

        $this->get('/brand/signature.png')->assertNotFound();

        // And it must actually ship. It was briefly put under storage/app,
        // which is gitignored in its entirety. A fresh deployment would have
        // issued unsigned documents and nothing would have said so.
        // git check-ignore exits 0 when a path IS ignored, 1 when it is not.
        exec(
            'cd '.escapeshellarg(base_path()).' && git check-ignore -q resources/brand/signature.png',
            $ignored,
            $status
        );

        $this->assertSame(1, $status, 'the signature is gitignored and would not ship with the code');
    }

    public function test_the_block_renders_the_ink_over_a_ruled_line(): void
    {
        $html = $this->render('pdf.partials.signature');

        $this->assertStringContainsString(resource_path('brand/signature.png'), $html);
        $this->assertStringContainsString('sig-rule', $html);
        $this->assertStringContainsString('Muhindo Mubaraka', $html);
    }

    public function test_the_role_line_can_be_set_per_document(): void
    {
        // An invoice signs for the business; a certificate signs as instructor.
        $this->assertStringContainsString(
            'For and on behalf of the business',
            $this->render('pdf.partials.signature', ['role' => 'For and on behalf of the business'])
        );

        $this->assertStringContainsString(
            'Software engineer &amp; instructor',
            $this->render('pdf.partials.signature')
        );
    }

    public function test_a_missing_signature_file_still_produces_a_signable_line(): void
    {
        $real = resource_path('brand/signature.png');
        $parked = $real.'.parked';

        rename($real, $parked);

        try {
            $html = $this->render('pdf.partials.signature');

            // A deployment without the asset should print an unsigned line to
            // sign by hand, not a broken image and not a fatal.
            $this->assertStringNotContainsString('<img', $html);
            $this->assertStringContainsString('sig-rule', $html);
            $this->assertStringContainsString('Muhindo Mubaraka', $html);
        } finally {
            rename($parked, $real);
        }
    }

    public function test_the_certificate_and_invoice_carry_it_but_the_receipt_does_not(): void
    {
        foreach (['pdf.certificate', 'pdf.invoice'] as $document) {
            $this->assertStringContainsString(
                "@include('pdf.partials.signature'",
                (string) file_get_contents(resource_path('views/'.str_replace('.', '/', $document).'.blade.php')),
                "{$document} should be signed"
            );
        }

        /*
         * Deliberately unsigned. A receipt records that a named person took
         * the money ("Received by" may be any staff member) so stamping
         * Muhindo's signature on it would misstate who did that.
         */
        $this->assertStringNotContainsString(
            'pdf.partials.signature',
            (string) file_get_contents(resource_path('views/pdf/receipt.blade.php')),
            'a receipt names its own receiver and must not be signed by someone else'
        );
    }
}
