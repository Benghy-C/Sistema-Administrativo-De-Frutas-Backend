<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venta_confirmaciones', function (Blueprint $table) {
            $table->id();
            $table->integer('venta_id')->unique();
            $table->string('codigo', 50)->unique();
            $table->bigInteger('usuario_id');
            $table->string('solicitud_hash', 64);
            $table->json('solicitud');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->foreign('venta_id')->references('pk_venta')->on('venta');
        });

        Schema::create('venta_lotes', function (Blueprint $table) {
            $table->id();
            $table->integer('detalle_id');
            $table->bigInteger('camara_id');
            $table->string('calidad', 1);
            $table->unsignedInteger('cantidad');
            $table->unsignedInteger('devuelta')->default(0);
            $table->unsignedInteger('revision')->default(1);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['detalle_id', 'camara_id', 'calidad', 'revision']);
            $table->foreign('detalle_id')->references('pk_ventadeta')->on('venta_detalle');
            $table->foreign('camara_id')->references('id_camara')->on('camara_refigeracion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_lotes');
        Schema::dropIfExists('venta_confirmaciones');
    }
};
