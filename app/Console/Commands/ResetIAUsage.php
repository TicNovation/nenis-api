<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Usuario;
use Illuminate\Support\Facades\Log;

class ResetIAUsage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ia:reset-usage';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reinicia el contador mensual de consultas de IA para todos los usuarios.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando el reinicio del contador de IA...');
        
        try {
            // Actualizar todos los usuarios a 0 consultas consumidas
            $total = Usuario::where('ia_consultas_mes_actual', '>', 0)->update(['ia_consultas_mes_actual' => 0]);
            
            $this->info("¡Listo! Se reinició el contador para $total usuarios.");
            Log::info("IA Usage Reset: se reinició el contador para $total usuarios.");
            
        } catch (\Exception $e) {
            $this->error('Ocurrió un error al reiniciar los contadores: ' . $e->getMessage());
            Log::error('IA Usage Reset Error: ' . $e->getMessage());
        }
    }
}
