<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Plan;
use App\Models\Membresia;
use App\Models\Usuario;
use App\Models\Negocio;
use Carbon\Carbon;


class TerminarMembresia extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:terminar-membresia';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expira membresías vencidas y degrada a los usuarios al plan básico si no tienen periodos vigentes.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $ahora = Carbon::now();

        // 1. Obtener membresías que acaban de vencer
        $vencidas = Membresia::where('estatus', 'activo')
            ->where('fin_en', '<=', $ahora)
            ->get();

        if ($vencidas->isEmpty()) {
            $this->info('No hay membresías vencidas por procesar.');
            return;
        }

        foreach ($vencidas as $membresia) {
            // Marcar como expirada
            $membresia->update(['estatus' => 'expirado']);

            $usuario = Usuario::find($membresia->id_usuario);
            if (!$usuario) continue;

            // 2. Verificar si tiene otra membresía activa (por ejemplo, una renovación encolada)
            $tieneMas = Membresia::where('id_usuario', $usuario->id)
                ->where('estatus', 'activo')
                ->where('fin_en', '>', $ahora)
                ->exists();

            if (!$tieneMas) {
                // 3. Si no tiene más periodos activos, degradar al Plan 1 (Básico)
                $planBasico = Plan::find(1);
                
                if ($planBasico) {
                    $usuario->id_plan_activo = 1;
                    $usuario->max_alcance_visibilidad = $planBasico->max_alcance_visibilidad;
                    $usuario->prioridad_cache = $planBasico->prioridad_busqueda;
                    $usuario->destacado_cache = $planBasico->destacado;
                    $usuario->destacado_titulo_cache = $planBasico->nombre;
                    $usuario->save();

                    // 4. Sincronizar beneficios con sus negocios
                    Negocio::where('id_usuario', $usuario->id)->update([
                        'destacado_cache' => $usuario->destacado_cache,
                        'destacado_titulo_cache' => $usuario->destacado_titulo_cache,
                        'alcance_visibilidad' => $usuario->max_alcance_visibilidad,
                        'prioridad_cache' => $usuario->prioridad_cache,
                    ]);

                    $this->warn("Usuario {$usuario->correo} degradado al Plan Básico por falta de pago/renovación.");
                }
            } else {
                $this->info("Membresía anterior de {$usuario->correo} expiró, pero cuenta con periodos vigentes adicionales.");
            }
        }

        $this->info('Proceso de terminación de membresías completado.');
    }
}
