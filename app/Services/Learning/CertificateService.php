<?php

namespace App\Services\Learning;

use App\Models\Certificate;
use App\Models\Enrollment;
use App\Services\QrService;
use App\Support\VerificationCode;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Issues and serves course-completion certificates. The PDF is rendered once
 * at issuance and stored on the private `local` disk (mirrors DocumentService);
 * every later view streams the stored file instead of re-rendering. Issuance
 * is idempotent on `enrollment_id` — a replayed completion event or a
 * double-click on "complete" must never produce a second certificate.
 */
class CertificateService
{
    private const DISK = 'local';

    public function __construct(
        private readonly QrService $qr,
        private readonly GradebookService $gradebook,
    ) {}

    /**
     * §4.6 — issues only when every lesson is complete *and*, if the course has any
     * counts_toward_certificate quiz, the gradebook's quiz-requirement check passes. Safe to
     * call from multiple trigger points (a lesson finishing, a quiz being graded) — issues at
     * most once (issue()'s own idempotency) and returns null while any requirement is unmet.
     */
    public function issueIfEligible(Enrollment $enrollment): ?Certificate
    {
        if ($enrollment->progressPercent() < 100) {
            return null;
        }

        if (! $this->gradebook->meetsCertificateQuizRequirement($enrollment)) {
            return null;
        }

        return $this->issue($enrollment);
    }

    public function issue(Enrollment $enrollment): Certificate
    {
        try {
            $certificate = Certificate::firstOrCreate(
                ['enrollment_id' => $enrollment->id],
                [
                    'uuid' => (string) Str::uuid(),
                    'certificate_no' => VerificationCode::make('CRT', $enrollment->id),
                    'issued_at' => now(),
                ],
            );
        } catch (UniqueConstraintViolationException) {
            $certificate = Certificate::where('enrollment_id', $enrollment->id)->firstOrFail();
        }

        $this->ensurePdfStored($certificate);

        return $certificate;
    }

    public function stream(Certificate $certificate): StreamedResponse
    {
        $this->ensurePdfStored($certificate);

        return $this->disk()->response($certificate->pdf_path, "certificate-{$certificate->certificate_no}.pdf");
    }

    private function ensurePdfStored(Certificate $certificate): void
    {
        if ($certificate->pdf_path && $this->disk()->exists($certificate->pdf_path)) {
            return;
        }

        $certificate->loadMissing('enrollment.user', 'enrollment.course');

        // The QR encodes the public verification page, and the same address is
        // printed beneath it so a paper copy is checkable without a scanner.
        $verifyUrl = route('certificates.verify', $certificate);

        $pdf = Pdf::loadView('pdf.certificate', [
            'certificate' => $certificate,
            'qrDataUri' => $this->qr->pngDataUri($verifyUrl),
            'verifyUrl' => $verifyUrl,
            'lookupUrl' => route('certificates.lookup'),
        ])->setPaper('a4', 'landscape');
        $path = "certificates/{$certificate->uuid}.pdf";
        $this->disk()->put($path, $pdf->output());

        $certificate->update(['pdf_path' => $path]);
    }

    private function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }
}
