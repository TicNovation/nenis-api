<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\RecodatorioPagoEmail;

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
        //TODO: Crear lógica para enviar recordatorio de pagos 1 día antes de que venza la suscripción
        //TODO: Crear lógica para enviar recordatorio de pagos 7 día antes de que venza la suscripción
        

        //Simulamos envio de recordatorio

        try {
            $data = [
            'nombre' => 'Fernando',
            'fecha_vencimiento' => '2026-02-21',
        ];

        Mail::to('luis.ramirez.b.itic@gmail.com')->send(new RecodatorioPagoEmail($data));

        $this->info('Recordatorio enviado');
        } catch (\Exception $e) {
            Log::error('Error al enviar recordatorio: ' . $e->getMessage());
            $this->error('Error al enviar recordatorio: ' . $e->getMessage());
        }
    }
}
