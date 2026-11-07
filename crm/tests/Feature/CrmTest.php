<?php

namespace Tests\Feature;

use App\Enums\Canal;
use App\Enums\EstadoEmpresa;
use App\Models\Ajuste;
use App\Models\Cliente;
use App\Models\Contacto;
use App\Models\Empresa;
use App\Models\Plantilla;
use App\Models\Rubro;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Datos sintéticos: nada de esto representa empresas reales. */
class CrmTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private Rubro $rubro;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usuario = User::create(['name' => 'Prueba', 'email' => 'prueba@example.com', 'password' => 'secreto123']);
        $this->rubro = Rubro::create(['nombre' => 'Rubro de prueba']);
    }

    private function empresa(array $datos = []): Empresa
    {
        return Empresa::create($datos + [
            'rubro_id' => $this->rubro->id,
            'nombre' => 'Empresa Prueba',
            'ciudad' => 'Ciudad X',
            'whatsapp' => '0345 15 400-0001',
            'estado' => EstadoEmpresa::SinContactar,
        ]);
    }

    public function test_sin_sesion_redirige_al_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Entrar');
    }

    public function test_login_correcto_e_incorrecto(): void
    {
        $this->post('/login', ['email' => 'prueba@example.com', 'password' => 'mal'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => 'prueba@example.com', 'password' => 'secreto123'])
            ->assertRedirect(route('panel'));
        $this->assertAuthenticatedAs($this->usuario);
    }

    public function test_todas_las_pantallas_cargan(): void
    {
        $empresa = $this->empresa();
        $plantilla = Plantilla::create(['nombre' => 'Primer contacto', 'canal' => 'whatsapp', 'cuerpo' => 'Hola {nombre} de {ciudad}', 'activa' => true]);
        $empresa->contactos()->create(['usuario_id' => $this->usuario->id, 'fecha_hora' => now(), 'canal' => 'whatsapp', 'plantilla_id' => $plantilla->id, 'mensaje' => 'Hola']);
        $cliente = $empresa->cliente()->create(['servicio' => 'Catálogo', 'fecha_alta' => today()->subMonths(2), 'monto_inicial' => 1000, 'abono_mensual' => 100]);
        $pago = $cliente->pagos()->create(['fecha_pago' => today(), 'monto' => 100, 'periodo' => 'inicial']);

        $this->actingAs($this->usuario);
        foreach ([
            '/', '/empresas', '/empresas/crear', "/empresas/{$empresa->id}", "/empresas/{$empresa->id}/editar",
            '/importar', '/clientes', "/clientes/{$cliente->id}", "/clientes/{$cliente->id}/editar",
            '/plantillas', '/plantillas/crear', "/plantillas/{$plantilla->id}/editar", '/estadisticas', '/ajustes',
            '/empresas?sin_wa=1', '/empresas?q=4000001', '/empresas?canal=whatsapp&orden=proxima',
        ] as $url) {
            $this->get($url)->assertOk();
        }

        // La plantilla llega a la ficha con las variables completadas.
        $this->get("/empresas/{$empresa->id}")->assertSee('Hola Empresa Prueba de Ciudad X');
        $this->getJson('/empresas/exportar')->assertOk()->assertJsonPath('filas.0.Nombre', 'Empresa Prueba');
        $this->assertNotNull($pago->id);
    }

    public function test_buscador_encuentra_por_telefono_en_otro_formato(): void
    {
        $this->empresa();
        $this->actingAs($this->usuario)->get('/empresas?q='.urlencode('345 4000001'))->assertSee('Empresa Prueba');
        $this->actingAs($this->usuario)->get('/empresas?q=nada-que-ver')->assertDontSee('Empresa Prueba');
    }

    public function test_importacion_detecta_duplicados_y_registra_historial(): void
    {
        $existente = $this->empresa(['nombre' => 'Ya Existe', 'whatsapp' => '+54 9 345 400-0001']);
        $this->actingAs($this->usuario);

        $filas = [
            ['_fila' => 2, 'nombre' => 'Nueva Uno', 'telefono' => '0343 15 400-0002', 'estado_envio' => 'Enviado', 'notas' => 'Llamar a la tarde', 'estado' => 'contactado'],
            ['_fila' => 3, 'nombre' => 'Ya Existe SRL', 'whatsapp' => '345 4000001', 'ciudad' => 'Ciudad Nueva', 'estado' => 'sin_contactar'],
            ['_fila' => 4, 'nombre' => 'Repetida', 'whatsapp' => '+54 9 343 400 0002', 'estado' => 'sin_contactar'],
            ['_fila' => 5, 'nombre' => '', 'telefono' => '1234567', 'estado' => 'sin_contactar'],
        ];

        $this->postJson('/importar/revisar', ['filas' => $filas])
            ->assertOk()
            ->assertJsonPath('analisis.0.tipo', 'nueva')
            ->assertJsonPath('analisis.1.tipo', 'duplicada')
            ->assertJsonPath('analisis.1.coincidencia.id', $existente->id)
            ->assertJsonPath('analisis.2.tipo', 'repetida')
            ->assertJsonPath('analisis.3.tipo', 'invalida')
            ->assertJsonPath('resumen.nueva', 1);

        $this->postJson('/importar/confirmar', [
            'rubro_id' => $this->rubro->id,
            'archivo' => 'prueba.xlsx',
            'filas' => $filas,
            'decisiones' => ['1' => 'actualizar'],
        ])->assertOk()->assertJsonPath('resumen.nuevas', 1)->assertJsonPath('resumen.actualizadas', 1)->assertJsonPath('resumen.salteadas', 2);

        $nueva = Empresa::where('nombre', 'Nueva Uno')->firstOrFail();
        $this->assertSame(EstadoEmpresa::Contactado, $nueva->estado);
        $this->assertSame('5493434000002', $nueva->telefono_norm);
        $historial = $nueva->contactos()->first();
        $this->assertSame(Canal::Importado, $historial->canal);
        $this->assertStringContainsString('Estado envío: Enviado', $historial->mensaje);
        $this->assertStringContainsString('Notas: Llamar a la tarde', $historial->mensaje);

        // La actualización completa datos sin pisar el nombre con vacío y deja registro.
        $existente->refresh();
        $this->assertSame('Ciudad Nueva', $existente->ciudad);
        $this->assertSame(1, $existente->contactos()->count());
        $this->assertSame(2, Empresa::count());
    }

    public function test_importacion_puede_crear_rubro_nuevo(): void
    {
        $this->actingAs($this->usuario)->postJson('/importar/confirmar', [
            'rubro_nuevo' => 'Rubro Recién Creado',
            'archivo' => 'x.csv',
            'filas' => [['_fila' => 2, 'nombre' => 'Alguna', 'estado' => 'sin_contactar']],
        ])->assertOk();

        $this->assertSame('Rubro Recién Creado', Empresa::first()->rubro->nombre);
    }

    public function test_registrar_contacto_avanza_estado_y_avisa_limite_de_whatsapp(): void
    {
        $empresa = $this->empresa();
        Ajuste::guardar('limite_whatsapp_diario', '2');
        $this->actingAs($this->usuario);

        $this->post("/empresas/{$empresa->id}/contactos", ['canal' => 'whatsapp', 'mensaje' => 'Hola'])
            ->assertRedirect("/empresas/{$empresa->id}")
            ->assertSessionMissing('aviso');
        $this->assertSame(EstadoEmpresa::Contactado, $empresa->fresh()->estado);

        $this->post("/empresas/{$empresa->id}/contactos", ['canal' => 'whatsapp', 'mensaje' => 'Hola otra vez'])
            ->assertSessionHas('aviso');

        // Marcar la respuesta pasa a "Respondió".
        $contacto = $empresa->contactos()->first();
        $this->patch("/contactos/{$contacto->id}", ['respondio' => 'si'])->assertRedirect();
        $this->assertSame(EstadoEmpresa::Respondio, $empresa->fresh()->estado);
        $this->assertSame(2, $empresa->cambiosEstado()->count());
    }

    public function test_pasar_a_cliente_pide_datos_y_registra_pago_inicial(): void
    {
        $empresa = $this->empresa();
        $this->actingAs($this->usuario);

        $this->patch("/empresas/{$empresa->id}/estado", ['estado' => 'cliente'])
            ->assertRedirect(route('clientes.create', $empresa));

        $this->post(route('clientes.store', $empresa), [
            'servicio' => 'Catálogo online',
            'fecha_alta' => '2026-08-15',
            'monto_inicial' => '350.000',
            'abono_mensual' => '40.000',
            'registrar_pago_inicial' => '1',
        ])->assertRedirect();

        $cliente = Cliente::firstOrFail();
        $this->assertSame('350000.00', $cliente->monto_inicial);
        $this->assertSame('inicial', $cliente->pagos()->first()->periodo);
        $this->assertSame(EstadoEmpresa::Cliente, $empresa->fresh()->estado);
    }

    public function test_abonos_vencidos_y_por_vencer(): void
    {
        $cliente = $this->empresa()->cliente()->create([
            'servicio' => 'Catálogo', 'fecha_alta' => '2026-08-15', 'monto_inicial' => 1000, 'abono_mensual' => 100,
        ]);
        $hoy = CarbonImmutable::parse('2026-11-12');

        $cuotas = $cliente->cuotas($hoy, 5);
        $this->assertSame(['2026-09', '2026-10', '2026-11'], array_column($cuotas, 'periodo'));
        $this->assertSame(['vencido', 'vencido', 'proximo'], array_column($cuotas, 'estado'));

        $cliente->pagos()->create(['fecha_pago' => '2026-09-15', 'monto' => 100, 'periodo' => '2026-09']);
        $cliente->unsetRelation('pagos');
        $this->assertSame(['pagado', 'vencido', 'proximo'], array_column($cliente->cuotas($hoy, 5), 'estado'));

        // Alta un 31: el abono de un mes corto vence el último día.
        $otro = $this->empresa(['nombre' => 'Otra', 'whatsapp' => null])->cliente()->create([
            'servicio' => 'Web', 'fecha_alta' => '2026-01-31', 'monto_inicial' => 0, 'abono_mensual' => 100,
        ]);
        $this->assertSame('2026-02-28', $otro->cuotas(CarbonImmutable::parse('2026-02-01'))[0]['vence']->format('Y-m-d'));
    }

    public function test_estadisticas_por_plantilla_y_franja(): void
    {
        $empresa = $this->empresa();
        $plantilla = Plantilla::create(['nombre' => 'P1', 'canal' => 'whatsapp', 'cuerpo' => 'x', 'activa' => true]);
        foreach ([['09:00', 'si'], ['10:00', null], ['21:00', 'no']] as [$hora, $resp]) {
            Contacto::create([
                'empresa_id' => $empresa->id, 'fecha_hora' => today()->format('Y-m-d')." $hora",
                'canal' => 'whatsapp', 'plantilla_id' => $plantilla->id, 'respondio' => $resp,
            ]);
        }
        Contacto::create(['empresa_id' => $empresa->id, 'fecha_hora' => now(), 'canal' => 'importado']);

        $tasas = app(\App\Services\Estadisticas::class)->tasas(CarbonImmutable::today()->subDay(), CarbonImmutable::today());
        $this->assertSame(3, $tasas['total']['enviados']);
        $this->assertSame(33.3, $tasas['plantilla']['P1']['tasa']);
        $this->assertSame(['Mañana', 'Noche'], array_keys($tasas['franja']));
        $this->assertSame(100.0, $tasas['franja']['Mañana']['tasa'] * 2);
    }

    public function test_comando_crea_usuario(): void
    {
        $this->artisan('crm:usuario', ['email' => 'nuevo@example.com', 'nombre' => 'Nuevo', '--password' => 'clave-segura'])
            ->assertSuccessful();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('clave-segura', User::where('email', 'nuevo@example.com')->first()->password));
    }
}
