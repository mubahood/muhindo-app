<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use Illuminate\View\View;

/** Public, unauthenticated anti-forgery check for a certificate — closes L3. */
class CertificateVerificationController extends Controller
{
    public function show(Certificate $certificate): View
    {
        $certificate->load('enrollment.user', 'enrollment.course');

        return view('certificates.verify', ['certificate' => $certificate]);
    }
}
