<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias_gasto', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80);
            $table->string('nombre_normalizado', 80)->unique();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });
        Schema::table('cjchica', function (Blueprint $table) {
            $table->bigInteger('categoria_id')->nullable();
            $table->foreign('categoria_id')->references('id')->on('categorias_gasto');
        });
        Schema::create('gasto_confirmaciones', function (Blueprint $table) {
            $table->id();
            $table->uuid('operacion')->unique();
            $table->bigInteger('gasto_id')->unique();
            $table->bigInteger('usuario_id');
            $table->string('solicitud_hash', 64);
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('gasto_id')->references('id_caja')->on('cjchica');
        });
        Schema::create('perdidas', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('camara_id');
            $table->string('calidad', 1);
            $table->unsignedInteger('cantidad');
            $table->date('fecha');
            $table->string('motivo', 500);
            $table->bigInteger('usuario_id');
            $table->decimal('costo_unitario', 16, 2)->nullable();
            $table->uuid('operacion')->unique();
            $table->string('solicitud_hash', 64);
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('camara_id')->references('id_camara')->on('camara_refigeracion');
            $table->index(['fecha', 'camara_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perdidas');
        Schema::dropIfExists('gasto_confirmaciones');
        Schema::table('cjchica', function (Blueprint $table) {
            $table->dropForeign(['categoria_id']);
            $table->dropColumn('categoria_id');
        });
        Schema::dropIfExists('categorias_gasto');
    }
};
