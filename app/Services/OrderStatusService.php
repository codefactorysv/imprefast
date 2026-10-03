<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderStatusService
{
    public const LABELS = [
        'received' => 'Recibido',
        'pending_quote' => 'Pendiente de cotización',
        'preparing' => 'En preparación',
        'ready' => 'Listo',
        'on_the_way' => 'En camino',
        'delivered' => 'Entregado',
        'cancelled' => 'Cancelado',
    ];

    public const TRANSITIONS = [
        'received' => ['preparing', 'cancelled'],
        'pending_quote' => ['received', 'cancelled'],
        'preparing' => ['ready', 'cancelled'],
        'ready' => ['on_the_way', 'cancelled'],
        'on_the_way' => ['delivered'],
        'delivered' => [],
        'cancelled' => [],
    ];

    public function transition(Order $order, string $newStatus, ?User $user, ?string $note = null, string $origin = 'panel', ?string $reason = null): Order
    {
        return DB::transaction(function () use ($order, $newStatus, $user, $note, $origin, $reason) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->status === $newStatus) return $locked;
            if (! isset(self::LABELS[$newStatus]) || ! in_array($newStatus, self::TRANSITIONS[$locked->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => $this->invalidMessage($locked->status, $newStatus)]);
            }
            if ($locked->status === 'pending_quote' && $newStatus === 'received' && ! $locked->quotes()->where('status', 'accepted')->exists()) {
                throw ValidationException::withMessages(['status' => 'No se pudo cambiar el estado porque la cotización todavía no ha sido aceptada.']);
            }
            if ($newStatus === 'cancelled' && blank($reason)) {
                throw ValidationException::withMessages(['reason' => 'Debes indicar el motivo de la cancelación.']);
            }
            $oldStatus = $locked->status;
            $locked->update(['status' => $newStatus]);
            $locked->histories()->create([
                'from_status' => $oldStatus, 'to_status' => $newStatus, 'user_id' => $user?->id,
                'note' => $note, 'origin' => $origin, 'reason' => $reason,
            ]);

            return $locked->fresh();
        });
    }

    private function invalidMessage(string $current, string $new): string
    {
        if (in_array($current, ['delivered', 'cancelled'], true)) return 'No se puede modificar un pedido '.strtolower(self::LABELS[$current]).'.';

        return 'No se permite pasar de “'.(self::LABELS[$current] ?? $current).'” a “'.(self::LABELS[$new] ?? $new).'”.';
    }
}
