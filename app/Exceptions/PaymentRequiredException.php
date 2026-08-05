<?php

namespace App\Exceptions;

use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown when someone reaches content they have started buying but not paid
 * for. Renders as a redirect to the payment screen rather than a 403.
 *
 * The distinction matters. "Forbidden" is the right answer for a course
 * somebody has nothing to do with; it is the wrong answer for a course they
 * enrolled in ten seconds ago and owe money on, where the only thing standing
 * between them and the content is a payment they are trying to make. Sending
 * them to the payment screen turns a dead end into the next step.
 *
 * It lives in an exception so every route into a course, the overview, a
 * lesson, quizzes, assignments, discussions, the heartbeat endpoints, behaves
 * the same way without each one remembering to check.
 */
class PaymentRequiredException extends RuntimeException
{
    public function __construct(private readonly Invoice $invoice, private readonly string $reason)
    {
        parent::__construct($reason);
    }

    public function render(Request $request): RedirectResponse
    {
        return redirect()
            ->route('payments.show', $this->invoice)
            ->with('error', $this->reason);
    }
}
