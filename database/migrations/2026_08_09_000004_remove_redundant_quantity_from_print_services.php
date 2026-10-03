<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('services')
            ->whereIn('slug', ['impresion-blanco-negro', 'impresion-color'])
            ->update(['requires_quantity' => false, 'requires_copies' => true]);
    }

    public function down(): void
    {
        // No se restaura el multiplicador redundante: las copias por archivo son la fuente canónica.
    }
};
