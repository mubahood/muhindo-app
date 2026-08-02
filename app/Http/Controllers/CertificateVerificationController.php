<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public, unauthenticated anti-forgery check for a certificate — closes L3.
 *
 * Two ways in, because a certificate is used in two ways. Someone with the
 * document on a screen scans the QR and lands on show() directly. Someone
 * holding a printed or photocopied certificate — an employer, a registrar —
 * has a number on paper and no scanner, and needs somewhere to type it.
 *
 * What matters most is the answer when a code does NOT resolve. Route model
 * binding answered that with a 404, which reads as "this site is broken"
 * rather than "this certificate is not genuine" — the one answer the page
 * exists to give. A forged number now gets an explicit, unambiguous no.
 */
class CertificateVerificationController extends Controller
{
    public function show(Certificate $certificate): View
    {
        $certificate->load('enrollment.user', 'enrollment.course');

        return view('certificates.verify', ['certificate' => $certificate]);
    }

    /** The search page, and the answer to a submitted number. */
    public function lookup(Request $request): View
    {
        $code = trim((string) $request->query('code', ''));
        $certificate = $code === '' ? null : $this->find($code);

        /*
         * Certificate numbers carry a check character (VerificationCode), so a
         * mistyped one is detectable as mistyped. That distinction matters:
         * telling somebody their certificate is not genuine when they simply
         * fat-fingered a digit is a serious thing to get wrong.
         *
         * Only claimed when the string looks like one of our numbers at all —
         * a UUID from the QR has no checksum, and neither does a random word.
         */
        $looksMistyped = $certificate === null
            && $code !== ''
            && preg_match('/^TD-[A-Z]+-\d{4}-\w+$/i', $code) === 1
            && ! \App\Support\VerificationCode::isValid($code);

        return view('certificates.lookup', [
            'code' => $code,
            'certificate' => $certificate,
            'searched' => $code !== '',
            'looksMistyped' => $looksMistyped,
        ]);
    }

    /**
     * Accepts either identifier printed on the document: the certificate
     * number, or the UUID the QR encodes.
     *
     * Case and surrounding whitespace are forgiven, because this gets typed
     * off paper. Nothing else is — no partial matching, no fuzzy search. A
     * verification that guesses is not a verification.
     */
    private function find(string $code): ?Certificate
    {
        return Certificate::with('enrollment.user', 'enrollment.course')
            ->where(fn ($q) => $q
                ->whereRaw('UPPER(certificate_no) = ?', [mb_strtoupper($code)])
                ->orWhere('uuid', $code))
            ->first();
    }
}
