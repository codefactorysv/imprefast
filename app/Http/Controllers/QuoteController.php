<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Services\OrderStatusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuoteController extends Controller
{
    public function show(string $token)
    {
        $quote = Quote::with('order.service')->where('token', $token)->firstOrFail();

        return view('quotes.show', compact('quote'));
    }

    public function respond(Request $r, string $token, OrderStatusService $statuses)
    {
        $q = Quote::where('token', $token)->firstOrFail();
        abort_if($q->status !== 'sent' || $q->expires_at->isPast(), 410);
        $d = $r->validate(['decision' => 'required|in:accepted,rejected']);
        DB::transaction(function () use ($q, $d, $statuses) {
            $q->update(['status' => $d['decision'], 'responded_at' => now()]);
            $statuses->transition($q->order, $d['decision'] === 'accepted' ? 'received' : 'cancelled', null, null, 'quotation', $d['decision'] === 'rejected' ? 'Cotización rechazada por el cliente.' : null);
        });

        return back()->with('ok', 'Respuesta registrada.');
    }
}
