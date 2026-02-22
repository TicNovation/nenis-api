<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Models\Membresia;
use App\Jobs\ProcesarRecordatorioPagoJob;
use Carbon\Carbon;

class EnviarRecodatorioPago extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:enviar-recordatorio-pago';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envía un recordatorio de pago a los usuarios que tienen una suscripción por vencer';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando envio de recordatorios de pago...');

        try {
            $enSieteDias = Carbon::now()->addDays(7)->toDateString();
            $enUnDia = Carbon::now()->addDays(1)->toDateString();

            $membresias_por_vencer = Membresia::with('usuario')
                ->where('estatus', 'activo')
                ->where('id_plan', '>', 1)
                ->where(function ($q) use ($enSieteDias, $enUnDia) {
                    $q->whereDate('fin_en', $enSieteDias)
                      ->orWhereDate('fin_en', $enUnDia);
                })
                ->get();

            if ($membresias_por_vencer->isEmpty()) {
                $this->info('Hoy no hay membresías premium por vencer dentro de 7 o 1 día.');
                return;
            }

            foreach ($membresias_por_vencer as $membresia) {
                // Despachamos el Job para evitar saturar el servidor y manejar reintentos
                if ($membresia->usuario) {
                    ProcesarRecordatorioPagoJob::dispatch($membresia->usuario, $membresia);
                    $this->info("Recordatorio enviado a cola para: {$membresia->usuario->correo}");
                }
            }

            $this->info("Se han puesto en cola {$membresias_por_vencer->count()} recordatorios de pago.");
        } catch (\Exception $e) {
            Log::error('Error general al ejecutar envío de recordatorios de cobro: ' . $e->getMessage());
            $this->error('Ocurrió un error general procesando los correos. Revisa los logs.');
        }
    }
}
