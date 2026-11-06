<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Crea un usuario del CRM o le cambia la contraseña si ya existe.
 *
 *   php artisan crm:usuario santiago@baseocho.com "Santiago"
 */
class CrearUsuario extends Command
{
    protected $signature = 'crm:usuario {email : Email para entrar} {nombre? : Nombre visible} {--password= : Contraseña (si no, se pide)}';

    protected $description = 'Crea un usuario del CRM o cambia su contraseña';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));
        $existente = User::where('email', $email)->first();
        $nombre = $this->argument('nombre') ?: ($existente?->name ?? $this->ask('Nombre'));
        $password = $this->option('password') ?: $this->secret('Contraseña (mínimo 8 caracteres)');

        $validador = Validator::make(compact('email', 'nombre', 'password'), [
            'email' => ['required', 'email'],
            'nombre' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:8'],
        ]);
        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::updateOrCreate(['email' => $email], ['name' => $nombre, 'password' => $password]);
        $this->info($existente ? "Contraseña actualizada para {$email}." : "Usuario {$email} creado.");

        return self::SUCCESS;
    }
}
