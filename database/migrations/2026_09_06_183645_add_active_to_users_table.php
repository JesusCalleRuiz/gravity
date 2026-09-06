<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Por defecto TRUE: los usuarios que ya existen en la base de datos
     * (cuentas de prueba en uso) siguen entrando sin que esto los bloquee de
     * golpe. AuthController::register() es quien pone `active=false`
     * explícitamente para las cuentas NUEVAS a partir de ahora, que quedan
     * pendientes de que un administrador las active a mano en la tabla.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('active')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('active');
        });
    }
};
