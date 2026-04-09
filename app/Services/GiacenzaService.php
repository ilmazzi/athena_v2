<?php

namespace App\Services;

use App\Models\Articolo;
use App\Models\Giacenza;
use Illuminate\Support\Facades\DB;

/**
 * Service per gestione giacenze.
 *
 * Regola di dominio:
 * - `quantita` = quantita caricata storica
 * - `quantita_residua` = disponibilita operativa
 */
class GiacenzaService
{
    public function __construct(
        private readonly MagazzinoLogicoService $magazzinoLogicoService,
    ) {
    }

    public function creaGiacenza(
        int $articoloId,
        int $magazzinoId,
        int $quantita = 1,
        ?string $scaffale = null
    ): Giacenza {
        return DB::transaction(function () use ($articoloId, $magazzinoId, $quantita, $scaffale) {
            $esistente = Giacenza::where('articolo_id', $articoloId)
                ->where('categoria_merceologica_id', $magazzinoId)
                ->first();

            if ($esistente) {
                throw new \LogicException("Giacenza gia esistente per articolo ID {$articoloId}");
            }

            return Giacenza::create([
                'articolo_id' => $articoloId,
                'categoria_merceologica_id' => $magazzinoId,
                'magazzino_logico' => $this->magazzinoLogicoService->resolveFromCategoriaId($magazzinoId),
                'quantita' => $quantita,
                'quantita_iniziale' => $quantita,
                'quantita_residua' => $quantita,
                'scaffale' => $scaffale,
                'ultimo_movimento_at' => now(),
            ]);
        });
    }

    public function incrementa(int $articoloId, int $quantita = 1): Giacenza
    {
        return DB::transaction(function () use ($articoloId, $quantita) {
            $giacenza = Giacenza::where('articolo_id', $articoloId)
                ->lockForUpdate()
                ->firstOrFail();

            $giacenza->incrementa($quantita);

            return $giacenza->fresh();
        });
    }

    /**
     * @throws GiacenzaInsufficienteException
     */
    public function decrementa(int $articoloId, int $quantita = 1): Giacenza
    {
        return DB::transaction(function () use ($articoloId, $quantita) {
            $giacenza = Giacenza::where('articolo_id', $articoloId)
                ->lockForUpdate()
                ->firstOrFail();

            $giacenza->decrementa($quantita);

            return $giacenza->fresh();
        });
    }

    public function verificaDisponibilita(int $articoloId, int $quantita = 1): bool
    {
        $giacenza = Giacenza::where('articolo_id', $articoloId)->first();

        if (!$giacenza) {
            return false;
        }

        return $giacenza->hasDisponibilita($quantita);
    }

    public function getQuantitaDisponibile(int $articoloId): int
    {
        $giacenza = Giacenza::where('articolo_id', $articoloId)->first();

        if (!$giacenza) {
            return 0;
        }

        return (int) ($giacenza->quantita_residua ?? ($giacenza->quantita ?? 0));
    }

    public function trasferisci(
        int $articoloId,
        int $magazzinoDestinazioneId,
        int $quantita = 1
    ): Giacenza {
        $articolo = Articolo::findOrFail($articoloId);

        return $this->trasferisciDaA(
            $articoloId,
            $articolo->categoria_merceologica_id,
            $magazzinoDestinazioneId,
            $quantita
        );
    }

    /**
     * Trasferisce l'articolo tra magazzini aggiornando la collocazione,
     * senza alterare la quantita storica caricata.
     */
    public function trasferisciDaA(
        int $articoloId,
        int $magazzinoOrigineId,
        int $magazzinoDestinazioneId,
        int $quantita = 1
    ): Giacenza {
        return DB::transaction(function () use ($articoloId, $magazzinoOrigineId, $magazzinoDestinazioneId, $quantita) {
            $giacenza = Giacenza::where('articolo_id', $articoloId)
                ->where('categoria_merceologica_id', $magazzinoOrigineId)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$giacenza->hasDisponibilita($quantita)) {
                $disponibile = (int) ($giacenza->quantita_residua ?? ($giacenza->quantita ?? 0));

                throw new GiacenzaInsufficienteException(
                    "Giacenza insufficiente per articolo {$articoloId}: richiesti {$quantita}, disponibili {$disponibile}"
                );
            }

            $magazzinoLogicoDestinazione = $this->magazzinoLogicoService->resolveFromCategoriaId($magazzinoDestinazioneId);

            $giacenza->update([
                'categoria_merceologica_id' => $magazzinoDestinazioneId,
                'magazzino_logico' => $magazzinoLogicoDestinazione,
                'ultimo_movimento_at' => now(),
            ]);

            Articolo::findOrFail($articoloId)->update([
                'categoria_merceologica_id' => $magazzinoDestinazioneId,
                'magazzino_logico' => $magazzinoLogicoDestinazione,
            ]);

            return $giacenza->fresh();
        });
    }

    public function reportGiacenzeMagazzino(int $magazzinoId): array
    {
        $giacenze = Giacenza::where('categoria_merceologica_id', $magazzinoId)
            ->with('articolo')
            ->disponibili()
            ->get();

        return [
            'totale_articoli' => $giacenze->count(),
            'valore_totale' => $giacenze->sum(fn ($g) => ($g->articolo->prezzo_acquisto ?? 0) * ($g->quantita_residua ?? 0)),
            'quantita_totale' => $giacenze->sum(fn ($g) => (int) ($g->quantita_residua ?? ($g->quantita ?? 0))),
            'giacenze' => $giacenze,
        ];
    }
}
