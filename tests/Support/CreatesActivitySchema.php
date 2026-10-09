<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait CreatesActivitySchema
{
    protected function createActivitySchema(): void
    {
        Schema::create('registro_actividad', function (Blueprint $table) {
            $table->id();
            $table->string('entidad');
            $table->integer('registro_id');
            $table->integer('usuario_id');
            $table->string('accion');
            $table->text('antes')->nullable();
            $table->text('despues')->nullable();
            $table->string('motivo')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }
}
