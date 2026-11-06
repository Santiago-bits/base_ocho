<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas del CRM. Los estados y canales se guardan como texto corto
 * (ver App\Enums) para que la migración funcione igual en MySQL y SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rubros', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rubro_id')->constrained('rubros')->restrictOnDelete();
            $table->string('nombre', 200);
            $table->string('ciudad', 120)->nullable()->index();
            $table->string('direccion', 255)->nullable();
            $table->string('telefono', 60)->nullable();
            $table->string('whatsapp', 60)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('web_redes', 255)->nullable();
            $table->string('fuente', 120)->nullable();
            $table->text('notas')->nullable();

            // Versiones normalizadas, usadas para detectar duplicados y armar links.
            $table->string('telefono_norm', 30)->nullable()->index();
            $table->string('whatsapp_norm', 30)->nullable()->index();
            $table->string('email_norm', 190)->nullable()->index();

            $table->string('estado', 30)->default('sin_contactar')->index();
            $table->string('proxima_accion', 255)->nullable();
            $table->date('proxima_accion_fecha')->nullable()->index();
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('nombre');
        });

        Schema::create('plantillas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rubro_id')->nullable()->constrained('rubros')->nullOnDelete();
            $table->string('nombre', 120);
            $table->string('canal', 20)->default('whatsapp');
            $table->string('asunto', 200)->nullable();
            $table->text('cuerpo');
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::create('contactos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('fecha_hora')->index();
            $table->string('canal', 20)->index();
            $table->foreignId('plantilla_id')->nullable()->constrained('plantillas')->nullOnDelete();
            $table->text('mensaje')->nullable();
            $table->boolean('pdf_enviado')->default(false);
            $table->string('respondio', 10)->nullable(); // 'si', 'no' o null = pendiente
            $table->text('resultado')->nullable();
            $table->timestamps();
        });

        Schema::create('cambios_estado', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('estado_anterior', 30)->nullable();
            $table->string('estado_nuevo', 30);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->unique()->constrained('empresas')->cascadeOnDelete();
            $table->string('servicio', 200);
            $table->date('fecha_alta');
            $table->decimal('monto_inicial', 12, 2)->default(0);
            $table->decimal('abono_mensual', 12, 2)->default(0);
            $table->boolean('activo')->default(true);
            $table->text('notas')->nullable();
            $table->timestamps();
        });

        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('fecha_pago');
            $table->decimal('monto', 12, 2);
            $table->string('periodo', 10); // 'inicial' o 'AAAA-MM'
            $table->string('nota', 255)->nullable();
            $table->timestamps();

            $table->index(['cliente_id', 'periodo']);
        });

        Schema::create('importaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rubro_id')->constrained('rubros')->restrictOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('archivo', 255);
            $table->unsignedInteger('filas')->default(0);
            $table->unsignedInteger('nuevas')->default(0);
            $table->unsignedInteger('actualizadas')->default(0);
            $table->unsignedInteger('salteadas')->default(0);
            $table->timestamps();
        });

        Schema::create('ajustes', function (Blueprint $table) {
            $table->string('clave', 60)->primary();
            $table->string('valor', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes');
        Schema::dropIfExists('importaciones');
        Schema::dropIfExists('pagos');
        Schema::dropIfExists('clientes');
        Schema::dropIfExists('cambios_estado');
        Schema::dropIfExists('contactos');
        Schema::dropIfExists('plantillas');
        Schema::dropIfExists('empresas');
        Schema::dropIfExists('rubros');
    }
};
