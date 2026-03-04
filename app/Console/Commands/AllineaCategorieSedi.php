<?php

namespace App\Console\Commands;

use App\Models\CategoriaMerceologica;
use App\Models\Sede;
use Illuminate\Console\Command;

class AllineaCategorieSedi extends Command
{
    protected $signature = 'categorie:assegna-sedi
        {--apply : Applica le modifiche (default: solo report)}
        {--force : Sovrascrive sede_id anche se già valorizzato}
        {--cavour=CAVOUR : Nome sede Cavour/Lecco}
        {--jolly=JOLLY : Nome sede Jolly}
        {--roma=ROMA : Nome sede Roma}';

    protected $description = 'Allinea sede_id delle categorie merceologiche per range ID.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $force = (bool) $this->option('force');

        $sedeCavour = $this->findSedeId($this->option('cavour'));
        $sedeJolly = $this->findSedeId($this->option('jolly'));
        $sedeRoma = $this->findSedeId($this->option('roma'));

        if (!$sedeCavour || !$sedeJolly || !$sedeRoma) {
            $this->error('Una o più sedi non trovate. Verifica i nomi con --cavour/--jolly/--roma.');
            return self::FAILURE;
        }

        $ranges = [
            ['from' => 1, 'to' => 9, 'sede_id' => $sedeCavour, 'label' => 'Cavour/Lecco'],
            ['from' => 10, 'to' => 12, 'sede_id' => $sedeJolly, 'label' => 'Jolly'],
            ['from' => 13, 'to' => 22, 'sede_id' => $sedeRoma, 'label' => 'Roma'],
        ];

        $this->info($apply ? 'Applico aggiornamenti...' : 'Report (nessuna modifica applicata).');

        foreach ($ranges as $range) {
            $query = CategoriaMerceologica::whereBetween('id', [$range['from'], $range['to']]);
            if (!$force) {
                $query->whereNull('sede_id');
            }

            $count = (clone $query)->count();
            $this->line("Range {$range['from']}-{$range['to']} → {$range['label']} (sede_id={$range['sede_id']}): {$count} righe.");

            if ($apply && $count > 0) {
                $query->update(['sede_id' => $range['sede_id']]);
            }
        }

        $this->info('Operazione completata.');
        return self::SUCCESS;
    }

    private function findSedeId(string $name): ?int
    {
        return Sede::where('nome', 'like', '%' . $name . '%')->value('id');
    }
}
