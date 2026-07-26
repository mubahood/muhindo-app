<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Carbon;

/** Read-only revenue analytics. Money is summed in bcmath — never float. */
class ReportService
{
    /**
     * @return array{total:string,by_method:array<string,string>,by_day:array<string,string>,count:int}
     */
    public function revenue(Carbon $from, Carbon $to): array
    {
        $payments = Payment::whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get(['method', 'amount', 'created_at']);

        $total = '0.00';
        $byMethod = [];
        $byDay = [];
        foreach ($payments as $p) {
            $total = bcadd($total, (string) $p->amount, 2);
            $m = $p->method->value;
            $byMethod[$m] = bcadd($byMethod[$m] ?? '0.00', (string) $p->amount, 2);
            $d = $p->created_at->toDateString();
            $byDay[$d] = bcadd($byDay[$d] ?? '0.00', (string) $p->amount, 2);
        }
        ksort($byDay);

        return ['total' => $total, 'by_method' => $byMethod, 'by_day' => $byDay, 'count' => $payments->count()];
    }
}
