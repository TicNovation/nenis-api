<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\Usuario;
use App\Models\Membresia;
use App\Mail\RecodatorioPagoEmail;

class ProcesarRecordatorioPagoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $usuario;
    protected $membresia;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Usuario $usuario, Membresia $membresia)
    {
        $this->usuario = $usuario;
        $this->membresia = $membresia;
        $this->onQueue('emails');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $data = [
                'nombre' => $this->usuario->nombre ?? explode('@', $this->usuario->correo)[0],
                // Convert timestamp to proper readable date format for email
                'fecha_vencimiento' => $this->membresia->fin_en->format('d/m/Y'),
            ];

            Mail::to($this->usuario->correo)->send(new RecodatorioPagoEmail($data));
            
            Log::info("Recordatorio enviado a usuario_id: {$this->usuario->id} por expiración el: {$data['fecha_vencimiento']}");

        } catch (\Exception $e) {
            Log::error('Error al enviar recordatorio a usuario_id ' . $this->usuario->id . ': ' . $e->getMessage());
        }
    }
}
