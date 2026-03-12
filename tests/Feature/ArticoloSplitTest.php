<?php

namespace Tests\Feature;

use App\Models\Articolo;
use App\Models\CategoriaMerceologica;
use App\Models\ContoDeposito;
use App\Models\Giacenza;
use App\Models\Sede;
use App\Services\ArticoloSplitService;
use App\Services\ContoDepositoService;
use App\Services\MovimentazioneService;
use App\Domain\Magazzino\DTOs\MovimentazioneDTO;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticoloSplitTest extends TestCase
{
    use RefreshDatabase;

    private function creaCategoria(int $sedeId, string $codice, string $nome): CategoriaMerceologica
    {
        return CategoriaMerceologica::create([
            'sede_id' => $sedeId,
            'codice' => $codice,
            'nome' => $nome,
            'citta' => 'LECCO',
            'attivo' => true,
        ]);
    }

    private function creaArticoloConGiacenza(CategoriaMerceologica $categoria, int $sedeId, string $codice, int $quantita): Articolo
    {
        $articolo = Articolo::create([
            'codice' => $codice,
            'descrizione' => 'Articolo test',
            'categoria_merceologica_id' => $categoria->id,
            'sede_id' => $sedeId,
            'stato' => 'disponibile',
        ]);

        Giacenza::create([
            'articolo_id' => $articolo->id,
            'categoria_merceologica_id' => $categoria->id,
            'sede_id' => $sedeId,
            'quantita' => $quantita,
            'quantita_iniziale' => $quantita,
            'quantita_residua' => $quantita,
            'costo_unitario' => 100,
        ]);

        return $articolo->fresh(['giacenza']);
    }

    public function test_split_service_creates_child_and_updates_giacenza(): void
    {
        $sede = Sede::firstOrFail();
        $categoria = $this->creaCategoria($sede->id, 'CAT-1', 'Orologi');
        $articolo = $this->creaArticoloConGiacenza($categoria, $sede->id, '1-100', 6);

        $split = app(ArticoloSplitService::class)->splitArticolo($articolo, 5);

        $this->assertNotNull($split->id);
        $this->assertSame($articolo->id, $split->articolo_padre_id);
        $this->assertSame(1, $split->split_index);
        $this->assertSame('1-100', $split->codice_base);
        $this->assertSame('1-100-1', $split->codice);
        $this->assertSame(5, $split->giacenza->quantita_residua);

        $articolo->refresh();
        $this->assertSame(1, $articolo->giacenza->quantita_residua);
    }

    public function test_split_then_movimentazione_updates_child(): void
    {
        $sede = Sede::firstOrFail();
        $destSede = Sede::query()->where('id', '!=', $sede->id)->firstOrFail();
        $categoriaOrigine = $this->creaCategoria($sede->id, 'CAT-2', 'Gioielli');
        $categoriaDest = $this->creaCategoria($destSede->id, 'CAT-3', 'Gioielli');
        $articolo = $this->creaArticoloConGiacenza($categoriaOrigine, $sede->id, '2-200', 6);

        $split = app(ArticoloSplitService::class)->splitArticolo($articolo, 5);

        $dto = new MovimentazioneDTO(
            articoloId: $split->id,
            quantita: 5,
            magazzinoOrigineId: $categoriaOrigine->id,
            magazzinoDestinazioneId: $categoriaDest->id,
            dataMovimentazione: now()->toDateString(),
            note: 'Test split movimentazione'
        );

        $movimentazione = app(MovimentazioneService::class)->eseguiMovimentazione($dto);
        $this->assertNotNull($movimentazione->id);

        $split->refresh();
        $this->assertSame($categoriaDest->id, $split->categoria_merceologica_id);
    }

    public function test_conto_deposito_partial_quantity_uses_split(): void
    {
        $sede = Sede::firstOrFail();
        $destSede = Sede::query()->where('id', '!=', $sede->id)->firstOrFail();
        $categoria = $this->creaCategoria($sede->id, 'CAT-4', 'Oreficeria');
        $articolo = $this->creaArticoloConGiacenza($categoria, $sede->id, '3-300', 6);

        $contoDeposito = ContoDeposito::create([
            'codice' => ContoDeposito::generaCodice(),
            'sede_mittente_id' => $sede->id,
            'sede_destinataria_id' => $destSede->id,
            'data_invio' => now()->toDateString(),
            'data_scadenza' => now()->addYear()->toDateString(),
            'stato' => 'attivo',
        ]);

        $movimento = app(ContoDepositoService::class)->inviaArticoloInDeposito(
            $contoDeposito,
            $articolo->id,
            5,
            100
        );

        $this->assertNotNull($movimento->id);
        $this->assertNotSame($articolo->id, $movimento->articolo_id);

        $articolo->refresh();
        $this->assertSame(1, $articolo->giacenza->quantita_residua);
    }
}
