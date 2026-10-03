<?php

namespace App\Http\Controllers;

use App\Events\QuoteReadyForConfirmation;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderLocationChange;
use App\Models\Quote;
use App\Models\QuoteResponse;
use App\Models\Setting;
use App\Services\OrderPreviewService;
use App\Services\OrderStatusService;
use App\Services\WhatsAppNotifierInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function login()
    {
        return view('admin.login');
    }

    public function authenticate(Request $r)
    {
        $d = $r->validate(['email' => 'required|email', 'password' => 'required']);
        if (Auth::attempt($d, $r->boolean('remember')) && Auth::user()->is_admin) {
            $r->session()->regenerate();

            return redirect()->route('admin.dashboard');
        }Auth::logout();

        return back()->withErrors(['email' => 'Credenciales incorrectas.']);
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/');
    }

    public function dashboard(Request $request)
    {
        $filter = $request->query('filter', 'all');
        $query = Order::with('service')->orderByRaw('reviewed_at IS NULL DESC')->latest();
        match ($filter) { 'new' => $query->whereNull('reviewed_at'), 'quote' => $query->where('requires_quote', true), 'urgent' => $query->where('urgent', true), 'preparing' => $query->where('status', 'preparing'), default => null };
        return view('admin.dashboard', ['counts' => Order::selectRaw('status,count(*) total')->groupBy('status')->pluck('total', 'status'), 'orders' => $query->limit(20)->get(), 'today' => Order::whereDate('created_at', today())->count(), 'filter' => $filter]);
    }

    public function orders()
    {
        return view('admin.orders.index', ['orders' => Order::with('service')->latest()->paginate(25)]);
    }

    public function order(Order $order)
    {
        return view('admin.order', ['order' => $order->load(['service', 'files', 'imageComposition', 'histories', 'quotes.notification', 'payments', 'quoteResponses', 'locationChanges'])]);
    }

    public function status(Request $r, Order $order, OrderStatusService $statuses)
    {
        $d = $r->validate(['status' => 'required|string', 'note' => 'nullable|string|max:500', 'reason' => 'nullable|string|max:1000']);
        $updated = $statuses->transition($order, $d['status'], Auth::user(), $d['note'] ?? null, 'panel', $d['reason'] ?? null);

        return back()->with('ok', 'El pedido pasó a “'.$updated->status_label.'”.');
    }

    public function location(Request $r, Order $order, OrderPreviewService $previewService)
    {
        abort_unless(Auth::user()?->is_admin, 403);
        $d = $r->validate(['latitude' => 'required|numeric|between:-90,90', 'longitude' => 'required|numeric|between:-180,180', 'department' => 'required|string|max:120', 'municipality' => 'required|string|max:120', 'neighborhood' => 'nullable|string|max:120', 'street' => 'nullable|string|max:180', 'house_number' => 'nullable|string|max:40', 'address_reference' => 'nullable|string|max:500', 'reason' => 'required|string|min:5|max:500']);
        $preview = $previewService->calculate(['service_id' => $order->service_id, 'pages' => $order->pages, 'copies' => $order->copies, 'paper_size' => $order->paper_size, 'double_sided' => $order->double_sided, 'orientation' => $order->orientation, 'latitude' => $d['latitude'], 'longitude' => $d['longitude'], 'urgent' => $order->urgent]);
        $old = $order->only(['latitude', 'longitude', 'department', 'municipality', 'neighborhood', 'street', 'house_number', 'address_reference', 'distance_km', 'duration_minutes', 'delivery_fee', 'total', 'status']);
        $accepted = in_array($order->status, ['received', 'preparing', 'ready', 'on_the_way'], true);
        $delivery = $preview['delivery'];
        $locationValues = array_merge($d, ['distance_km' => $preview['distance_km'], 'duration_minutes' => $preview['duration_minutes'], 'route_provider' => $preview['route_provider'], 'route_calculated_at' => $preview['distance_km'] !== null ? now() : null]);
        unset($locationValues['reason']);
        $proposedTotal = $preview['total'];
        DB::transaction(function () use ($r, $order, $old, $locationValues, $delivery, $proposedTotal, $accepted, $d) {
            $new = $locationValues + ['proposed_delivery_fee' => $delivery['total'] ?? null, 'proposed_total' => $proposedTotal];
            if (! $accepted) {
                $locationValues = array_merge($locationValues, ['delivery_fee' => $delivery['total'] ?? null, 'total' => $proposedTotal, 'base_fee_snapshot' => $delivery['base'] ?? null, 'price_per_km_snapshot' => $delivery['per_km'] ?? null, 'minimum_fee_snapshot' => $delivery['minimum'] ?? null, 'pickup_factor_snapshot' => $delivery['factor'] ?? null, 'urgent_surcharge_snapshot' => $delivery['urgent'] ?? null]);
            }
            $order->update($locationValues);
            AuditLog::create(['user_id' => Auth::id(), 'action' => 'order_location_recalculated', 'auditable_type' => Order::class, 'auditable_id' => $order->id, 'old_values' => $old, 'new_values' => $new, 'reason' => $d['reason'], 'ip_address' => $r->ip()]);
        });

        return back()->with('ok', $accepted ? 'Ubicación actualizada. El precio anterior se conservó y el cliente debe confirmar el nuevo.' : 'Ubicación y domicilio recalculados.');
    }

    public function quote(Request $r, Order $order, WhatsAppNotifierInterface $notifier)
    {
        abort_if($order->delivery_fee === null, 422, 'El domicilio requiere revisión.');
        abort_unless($order->requires_quote && $order->status === 'pending_quote', 404);
        $d = $r->validate(['work_price' => 'required|numeric|min:0', 'deposit_required' => 'nullable|numeric|min:0', 'notes' => 'nullable|max:1000', 'expires_at' => 'nullable|date|after:now']);
        $d['addons_total'] = (float) $order->items()->with('options')->get()->flatMap->options->where('type', 'addon')->sum('price');
        $d['deposit_required'] = $order->requires_deposit ? (float) ($d['deposit_required'] ?? 0) : 0;
        $d['expires_at'] = $d['expires_at'] ?? now()->addHours(48);
        $quote = DB::transaction(function () use ($r, $order, $d) {
            $order->quotes()->whereIn('status', ['draft', 'sent'])->update(['status' => 'expired']);
            $version = ((int) $order->quotes()->max('version')) + 1;
            $delivery = (float) $order->delivery_fee;
            $addons = (float) $d['addons_total'];
            $total = round((float) $d['work_price'] + $delivery + $addons, 2);
            abort_if($d['deposit_required'] > $total, 422, 'El anticipo no puede superar el total.');
            $q = $order->quotes()->create($d + ['version' => $version, 'delivery_price' => $delivery, 'surcharges' => 0, 'total' => $total, 'status' => 'sent', 'token' => hash('sha256', Str::random(64)), 'created_by' => Auth::id()]);
            $order->update(['work_subtotal' => $d['work_price'], 'total' => $total]);
            AuditLog::create(['user_id' => Auth::id(), 'action' => 'quote_version_created', 'auditable_type' => Order::class, 'auditable_id' => $order->id, 'new_values' => $q->toArray(), 'ip_address' => $r->ip()]);

            return $q;
        });
        $notifier->prepareQuoteReady($order->load('service'), $quote);
        QuoteReadyForConfirmation::dispatch($quote);

        return back()->with('ok', 'Cotización creada. El mensaje de WhatsApp está pendiente de envío manual.');
    }

    public function markQuoteSent(Request $r, Order $order, Quote $quote)
    {
        abort_unless($quote->order_id === $order->id && $quote->status === 'sent' && ! $quote->expires_at->isPast(), 422);
        $quote->notification()->where('status', 'pending')->update(['status' => 'sent', 'sent_by' => Auth::id(), 'sent_at' => now()]);

        return back()->with('ok', 'Mensaje marcado como enviado manualmente.');
    }

    public function quoteResponse(Request $r, Order $order, Quote $quote, OrderStatusService $statuses)
    {
        abort_unless($quote->order_id === $order->id && $quote->status === 'sent' && ! $quote->expires_at->isPast(), 422);
        $d = $r->validate(['decision' => 'required|in:accepted,rejected', 'note' => 'nullable|string|max:1000']);
        $old = $order->status;
        $new = $d['decision'] === 'rejected' ? 'cancelled' : 'received';
        DB::transaction(function () use ($order, $quote, $d, $old, $new, $statuses) {
            $quote->update(['status' => $d['decision'], 'responded_at' => now()]);
            $statuses->transition($order, $new, Auth::user(), $d['note'] ?? null, 'quotation', $d['decision'] === 'rejected' ? ($d['note'] ?? 'Cotización rechazada por el cliente.') : null);
            QuoteResponse::create(['order_id' => $order->id, 'quote_id' => $quote->id, 'decision' => $d['decision'], 'channel' => 'whatsapp', 'recorded_by' => Auth::id(), 'note' => $d['note'] ?? null, 'from_status' => $old, 'to_status' => $new, 'responded_at' => now()]);
        });

        return back()->with('ok', 'Respuesta del cliente registrada.');
    }

    public function deliveryReview(Request $r, Order $order)
    {
        $d = $r->validate(['delivery_fee' => 'required|numeric|min:0', 'reason' => 'required|string|min:5|max:500']);
        $old = $order->delivery_fee;
        $order->update(['delivery_fee' => $d['delivery_fee']]);
        AuditLog::create(['user_id' => Auth::id(), 'action' => 'delivery_fee_manually_reviewed', 'auditable_type' => Order::class, 'auditable_id' => $order->id, 'old_values' => ['delivery_fee' => $old], 'new_values' => ['delivery_fee' => $d['delivery_fee']], 'reason' => $d['reason'], 'ip_address' => $r->ip()]);

        return back()->with('ok', 'Costo de domicilio revisado.');
    }

    public function locationChangeReviewed(Order $order, OrderLocationChange $change)
    {
        abort_unless($change->order_id === $order->id, 404);
        $change->update(['admin_seen_at' => now()]);

        return back()->with('ok', 'Cambio marcado como revisado.');
    }

    public function locationChangeApprove(Order $order, OrderLocationChange $change)
    {
        abort_unless($change->order_id === $order->id && $change->status === 'pending', 422);
        $order->update($change->new_location + ['distance_km' => $change->new_distance_km, 'duration_minutes' => $change->new_duration_minutes, 'delivery_fee' => $change->new_delivery_fee, 'route_calculated_at' => now(), 'total' => $order->work_subtotal !== null ? round((float) $order->work_subtotal + (float) $change->new_delivery_fee, 2) : null]);
        $change->update(['status' => 'applied', 'reviewed_by' => Auth::id(), 'reviewed_at' => now(), 'admin_seen_at' => now()]);

        return back()->with('ok', 'Cambio de ubicación aprobado.');
    }

    public function locationChangeReject(Request $r, Order $order, OrderLocationChange $change)
    {
        abort_unless($change->order_id === $order->id && $change->status === 'pending', 422);
        $d = $r->validate(['reason' => 'required|string|min:5|max:500']);
        $change->update(['status' => 'rejected', 'review_reason' => $d['reason'], 'reviewed_by' => Auth::id(), 'reviewed_at' => now(), 'admin_seen_at' => now()]);

        return back()->with('ok', 'Cambio rechazado.');
    }

    public function payment(Request $r, Order $order)
    {
        $d = $r->validate(['amount' => 'required|numeric|min:.01', 'method' => 'required|in:cash,transfer', 'status' => 'required|in:pending_verification,verified,rejected', 'notes' => 'nullable|max:500']);
        DB::transaction(function () use ($order, $d) {
            $order->payments()->create($d + ($d['status'] === 'verified' ? ['verified_by' => Auth::id(), 'verified_at' => now()] : []));
            $paid = $order->payments()->where('status', 'verified')->sum('amount');
            $order->update(['verified_paid' => min((float) $order->total, $paid), 'payment_status' => $paid <= 0 ? 'pending' : ($paid >= (float) $order->total ? 'paid' : 'partial')]);
        });

        return back()->with('ok', 'Pago registrado.');
    }

    public function download(Order $order, int $file)
    {
        $f = $order->files()->findOrFail($file);
        abort_unless(Storage::disk($f->disk)->exists($f->path), 404);

        return Storage::disk($f->disk)->download($f->path, $f->original_name);
    }

    public function settings(Request $r)
    {
        if ($r->isMethod('get')) {
            return view('admin.settings', ['settings' => Setting::orderBy('key')->get(), 'telegramConnection' => \App\Models\TelegramConnection::where('user_id', Auth::id())->first()]);
        }$submitted = $r->validate(['settings' => 'required|array', 'settings.*' => 'nullable|string|max:500'])['settings'];
        $telegramSettingsSubmitted = array_key_exists('telegram_reminder_minutes', $submitted)
            || collect(['telegram_admin_enabled','telegram_reminders_enabled','admin_sound_enabled'])->contains(fn ($key) => array_key_exists($key, $submitted));
        foreach (['telegram_admin_enabled','telegram_reminders_enabled','admin_sound_enabled'] as $booleanKey) {
            if ($telegramSettingsSubmitted) $submitted[$booleanKey] = isset($submitted[$booleanKey]) ? '1' : '0';
        }
        foreach ($submitted as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }cache()->flush();

        return back()->with('ok', 'Configuración guardada.');
    }
}
