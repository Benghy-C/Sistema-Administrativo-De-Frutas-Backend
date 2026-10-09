<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venta_operaciones', function (Blueprint $table) {
            $table->id();
            $table->uuid('clave')->unique();
            $table->integer('venta_id');
            $table->bigInteger('usuario_id');
            $table->string('tipo', 20);
            $table->string('solicitud_hash', 64);
            $table->json('resultado');
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('venta_id')->references('pk_venta')->on('venta');
        });

        Schema::create('registro_actividad', function (Blueprint $table) {
            $table->id();
            $table->string('entidad', 40);
            $table->bigInteger('registro_id');
            $table->bigInteger('usuario_id');
            $table->string('accion', 40);
            $table->string('motivo', 500)->nullable();
            $table->json('antes')->nullable();
            $table->json('despues')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['entidad', 'registro_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registro_actividad');
        Schema::dropIfExists('venta_operaciones');
    }
};
