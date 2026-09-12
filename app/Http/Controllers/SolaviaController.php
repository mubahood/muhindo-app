<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Support\Spam\Captcha;
use App\Support\Spam\FormShield;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * The company section.
 *
 * These pages exist for a specific reader: somebody at a payment provider or a
 * bank, opening the site to confirm that a registered Ugandan company stands
 * behind the payments, that it sells something real, at a stated price, with a
 * refund route. Everything here is arranged for that reading, which is also
 * what an ordinary customer wants and rarely gets.
 */
class SolaviaController extends Controller
{
    public function home(): View
    {
        return view('solavia.home');
    }

    public function products(): View
    {
        return view('solavia.products', ['groups' => config('products')]);
    }

    public function terms(): View
    {
        return view('solavia.terms');
    }

    public function privacy(): View
    {
        return view('solavia.privacy');
    }

    public function refundPolicy(): View
    {
        return view('solavia.refund-policy');
    }

    public function contact(): View
    {
        return view('solavia.contact');
    }

    /**
     * The company contact form.
     *
     * Open to a stranger, because the whole point is that a reviewer or a
     * customer who has just been charged can reach a human without an account.
     * That is also why it carries the same spam shield as every other public
     * form here, on top of the throttle the route applies.
     */
    public function contactSubmit(Request $request): RedirectResponse
    {
        // Answered as success. A bot told it failed simply retries.
        if (FormShield::looksAutomated($request->all(), 'solavia-contact')) {
            return $this->thanks();
        }

        FormShield::assertHumanTiming($request->all());

        $data = $request->validate([
            'name' => 'required|string|min:2|max:120',
            'email' => 'required|email:rfc|max:150',
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^[0-9+()\s.-]*$/'],
            'message' => 'required|string|min:10|max:4000',
        ] + Captcha::rules(), Captcha::messages() + [
            'phone.regex' => 'That does not look like a phone number. Digits, spaces and + only.',
            'message.min' => 'Please say a little more so the reply can be useful.',
        ]);

        $subject = 'Company enquiry via '.config('app.name');

        $stored = ContactMessage::create([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'subject' => $subject,
            'message' => trim($data['message'])
                .(filled($data['phone'] ?? null) ? "\n\nPhone: ".$data['phone'] : ''),
        ]);

        // The database row is the record that matters; the email is a
        // convenience. A mail server that is down must not lose the message or
        // show the sender an error for something that did work.
        try {
            Mail::raw(
                $stored->message."\n\n---\nFrom: {$stored->name} <{$stored->email}>",
                function ($mail) use ($stored, $subject) {
                    $mail->to(config('company.email'))
                        ->replyTo($stored->email, $stored->name)
                        ->subject($subject);
                }
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->thanks();
    }

    /**
     * Machine-readable company details.
     *
     * Providers and banks ask for these in a form they can ingest, and a URL
     * that answers is faster than an email thread. Served from the same config
     * as the pages, so the two cannot disagree.
     */
    public function companyJson(): JsonResponse
    {
        $address = config('company.address');

        $products = [];
        foreach (config('products') as $group) {
            foreach ($group['items'] as $item) {
                $products[] = array_filter([
                    'name' => $item['name'],
                    'category' => $group['heading'],
                    'description' => $item['description'],
                    'price' => $item['price'] ?? null,
                    'platforms' => $item['platforms'],
                    'url' => $item['url'] ? url($item['url']) : null,
                ], fn ($v) => $v !== null);
            }
        }

        return response()->json([
            'name' => config('company.name'),
            'legalName' => config('company.name'),
            'registrationNumber' => config('company.registration_number'),
            'registrar' => config('company.registrar'),
            'incorporatedOn' => '2026-07-22',
            'business' => config('company.business'),
            'isic' => config('company.isic'),
            'url' => route('home'),
            'companyPage' => route('solavia.home'),
            'address' => [
                'street' => $address['street'],
                'tradingCentre' => $address['trading_centre'],
                'division' => $address['division'],
                'locality' => $address['locality'],
                'region' => $address['region'],
                'country' => $address['country'],
                'countryCode' => 'UG',
                'poBox' => $address['po_box'],
            ],
            'phone' => config('company.phone_e164'),
            'email' => config('company.email'),
            'leadership' => array_map(
                fn ($p) => ['name' => $p['name'], 'title' => $p['title']],
                config('company.leadership'),
            ),
            'policies' => [
                'terms' => route('solavia.terms'),
                'privacy' => route('solavia.privacy'),
                'refunds' => route('solavia.refund-policy'),
            ],
            'products' => $products,
        ], 200, [], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    private function thanks(): RedirectResponse
    {
        return redirect()->route('solavia.contact')
            ->with('success', 'Thank you. Your message has reached us and we reply within 2 business days.')
            ->withFragment('contact-form');
    }
}
