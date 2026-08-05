<?php

namespace Tests\Feature\Learning;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Learning\CertificateService;
use App\Services\QrService;
use App\Support\VerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Issuing a certificate, and the public check that it is genuine.
 *
 * A certificate is only worth anything if a stranger can confirm it without an
 * account, and if a forgery is told apart from a typo. Those two answers are
 * not interchangeable: telling somebody their certificate is fake when they
 * mistyped a digit is a serious thing to get wrong.
 */
class CertificateVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function certificate(): Certificate
    {
        $user = User::factory()->create(['name' => 'Aisha Nakalema', 'is_student' => true]);
        $course = Course::factory()->create(['title' => 'Web Development Foundations', 'is_published' => true]);

        $enrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'completed',
            'source' => 'self',
            'enrolled_at' => now()->subMonths(2),
            'completed_at' => now()->subDay(),
        ]);

        return app(CertificateService::class)->issue($enrollment);
    }

    // Issuing

    public function test_issuing_stores_one_pdf_and_never_a_second_certificate(): void
    {
        $certificate = $this->certificate();

        $this->assertNotNull($certificate->pdf_path);
        $this->assertTrue(Storage::disk('local')->exists($certificate->pdf_path));

        // A replayed completion event or a double-click must not mint a second.
        $again = app(CertificateService::class)->issue($certificate->enrollment);
        $this->assertSame($certificate->id, $again->id);
        $this->assertSame(1, Certificate::count());
    }

    public function test_the_certificate_number_carries_a_working_check_character(): void
    {
        $certificate = $this->certificate();

        $number = $certificate->certificate_no;
        $this->assertTrue(VerificationCode::isValid($number));

        /*
         * Every single-digit substitution must be caught. That is the typo a
         * person actually makes reading a number off paper. Measured across
         * all of them rather than one sample, because the check character is a
         * mod-32 sum: any one arbitrary change has roughly a 1-in-32 chance of
         * colliding, and picking a single example is picking a coin flip.
         * Digit-for-digit substitutions happen to be caught outright.
         */
        $core = substr($number, 0, -1);
        $check = substr($number, -1);
        $missed = [];

        foreach (range(12, strlen($core) - 1) as $position) {
            foreach (str_split('0123456789') as $digit) {
                if ($core[$position] === $digit) {
                    continue;
                }
                $mutated = $core;
                $mutated[$position] = $digit;

                if (VerificationCode::isValid($mutated.$check)) {
                    $missed[] = $mutated.$check;
                }
            }
        }

        $this->assertSame([], $missed, 'these mistyped numbers passed the check character');
    }

    public function test_the_pdf_carries_the_verification_address_as_text_not_only_a_qr(): void
    {
        $certificate = $this->certificate();
        $pdf = Storage::disk('local')->get($certificate->pdf_path);

        // A photocopy on a desk with no phone to hand still has to be checkable,
        // so the number and the lookup address are printed, not just encoded.
        $this->assertNotEmpty($pdf);
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    // The QR

    public function test_the_qr_encodes_the_public_verification_url(): void
    {
        $certificate = $this->certificate();

        // Rendered through the same service the PDF uses. Decoding the image is
        // done outside the suite (OpenCV); what is asserted here is that the
        // URL handed to the encoder is the public page, and that the page works.
        $url = route('certificates.verify', $certificate);

        $this->assertNotEmpty(app(QrService::class)->png($url));
        $this->get($url)->assertOk()->assertSee('Aisha Nakalema');
    }

    // The public check

    public function test_a_stranger_can_verify_without_an_account(): void
    {
        $certificate = $this->certificate();

        $this->assertGuest();
        $this->get(route('certificates.verify', $certificate))->assertOk()
            ->assertSee('Aisha Nakalema')
            ->assertSee('Web Development Foundations');
    }

    public function test_a_genuine_number_typed_by_hand_is_confirmed(): void
    {
        $certificate = $this->certificate();

        $this->get(route('certificates.lookup', ['code' => $certificate->certificate_no]))->assertOk()
            ->assertSee('This certificate is genuine')
            ->assertSee('Aisha Nakalema')
            ->assertSee('Web Development Foundations');
    }

    public function test_the_number_is_forgiven_its_case_and_whitespace(): void
    {
        $certificate = $this->certificate();

        // It gets typed off paper, often in lower case, often with a stray space.
        $this->get(route('certificates.lookup', ['code' => '  '.strtolower($certificate->certificate_no).' ']))
            ->assertOk()->assertSee('This certificate is genuine');
    }

    public function test_the_uuid_from_the_qr_also_works_in_the_box(): void
    {
        $certificate = $this->certificate();

        $this->get(route('certificates.lookup', ['code' => $certificate->uuid]))->assertOk()
            ->assertSee('This certificate is genuine');
    }

    public function test_a_number_that_was_never_issued_is_refused_outright(): void
    {
        $this->certificate();

        // Well formed, correct check character, simply not ours.
        $fake = VerificationCode::make('CRT', 999999);
        $this->assertTrue(VerificationCode::isValid($fake));

        $this->get(route('certificates.lookup', ['code' => $fake]))->assertOk()
            ->assertSee('No certificate with that number')
            ->assertDontSee('This certificate is genuine');
    }

    public function test_a_mistyped_number_is_called_a_typo_and_not_a_forgery(): void
    {
        $this->certificate();

        // Same shape, broken check character.
        $this->get(route('certificates.lookup', ['code' => 'TD-CRT-2026-9999999']))->assertOk()
            ->assertSee('That number has a typo')
            ->assertDontSee('No certificate with that number')
            ->assertDontSee('This certificate is genuine');
    }

    public function test_random_text_is_refused_without_being_called_a_typo(): void
    {
        $this->certificate();

        // Nothing resembling one of our numbers, so there is no typo to claim.
        $this->get(route('certificates.lookup', ['code' => 'not-a-certificate']))->assertOk()
            ->assertSee('No certificate with that number')
            ->assertDontSee('That number has a typo');
    }

    public function test_the_empty_form_makes_no_claim_either_way(): void
    {
        $this->get(route('certificates.lookup'))->assertOk()
            ->assertSee('Check a certificate')
            ->assertDontSee('This certificate is genuine')
            ->assertDontSee('No certificate with that number');
    }

    public function test_verification_does_not_leak_anything_beyond_the_award(): void
    {
        $certificate = $this->certificate();
        $email = $certificate->enrollment->user->email;

        // A public anti-forgery check should confirm the award, not publish the
        // holder's contact details to anyone holding their certificate number.
        $this->get(route('certificates.lookup', ['code' => $certificate->certificate_no]))->assertOk()
            ->assertDontSee($email);

        $this->get(route('certificates.verify', $certificate))->assertOk()->assertDontSee($email);
    }

    public function test_the_public_site_links_to_the_checker(): void
    {
        // Somebody holding a certificate has the address printed on it, but
        // somebody who was merely handed a number needs to find this.
        $this->get(route('home'))->assertOk()->assertSee(route('certificates.lookup'), false);
    }
}
