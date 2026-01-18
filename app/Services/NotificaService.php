<?php

namespace App\Services;

use App\Models\Notifica;
use App\Models\Societa;
use App\Models\ContoDeposito;
use App\Models\MovimentoDeposito;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * NotificaService - Gestione notifiche conti deposito
 * 
 * Gestisce:
 * - Creazione notifiche in database
 * - Invio email notifiche
 * - Notifiche per resi, vendite, scadenze
 */
class NotificaService
{
    /**
     * Crea e invia notifica per reso manuale/automatico
     */
    public function notificaNuovoDeposito(ContoDeposito $contoDeposito): ?Notifica
    {
        $societaDestinataria = $contoDeposito->getSocietaDestinataria();
        $societaMittente = $contoDeposito->getSocietaMittente();

        if (!$societaDestinataria) {
            return null;
        }

        $titolo = "Nuovo conto deposito {$contoDeposito->codice}";
        $mittente = $societaMittente?->ragione_sociale ?? $contoDeposito->sedeMittente->nome ?? 'Sede mittente';
        $messaggio = "Hai ricevuto un nuovo conto deposito {$contoDeposito->codice} da {$mittente}. " .
                     "Articoli inviati: {$contoDeposito->articoli_inviati}. Valore: €" .
                     number_format($contoDeposito->valore_totale_invio, 2, ',', '.') . ".";

        $dati = [
            'sede_mittente' => $contoDeposito->sedeMittente->nome ?? null,
            'sede_destinataria' => $contoDeposito->sedeDestinataria->nome ?? null,
            'data_invio' => $contoDeposito->data_invio,
            'articoli_inviati' => $contoDeposito->articoli_inviati,
            'valore_totale_invio' => $contoDeposito->valore_totale_invio,
        ];

        return $this->creaNotifica(
            tipo: 'nuovo_deposito',
            societaId: $societaDestinataria->id,
            contoDeposito: $contoDeposito,
            movimento: null,
            titolo: $titolo,
            messaggio: $messaggio,
            datiAggiuntivi: $dati
        );
    }

    /**
     * Crea e invia notifica per reso manuale/automatico
     */
    public function notificaReso(
        ContoDeposito $contoDeposito,
        MovimentoDeposito $movimento,
        array $datiAggiuntivi = []
    ): Notifica {
        $societaMittente = $contoDeposito->getSocietaMittente();
        
        if (!$societaMittente) {
            throw new \Exception("Impossibile creare notifica: società mittente non trovata");
        }

        $articolo = $movimento->articolo ?? $movimento->prodottoFinito;
        $codiceItem = $articolo ? $articolo->codice : 'N/A';
        $descrizioneItem = $articolo ? $articolo->descrizione : 'N/A';

        $titolo = "Reso da Conto Deposito {$contoDeposito->codice}";
        $messaggio = "È stato effettuato il reso dell'articolo {$codiceItem} ({$descrizioneItem}) " .
                    "dal conto deposito {$contoDeposito->codice}. " .
                    "Quantità restituita: {$movimento->quantita}. " .
                    "Valore: €" . number_format($movimento->costo_totale, 2, ',', '.');

        $datiAggiuntivi['articolo_codice'] = $codiceItem;
        $datiAggiuntivi['articolo_descrizione'] = $descrizioneItem;
        $datiAggiuntivi['quantita'] = $movimento->quantita;
        $datiAggiuntivi['valore_totale'] = $movimento->costo_totale;

        return $this->creaNotifica(
            tipo: 'reso',
            societaId: $societaMittente->id,
            contoDeposito: $contoDeposito,
            movimento: $movimento,
            titolo: $titolo,
            messaggio: $messaggio,
            datiAggiuntivi: $datiAggiuntivi
        );
    }

    /**
     * Crea e invia notifica per vendita da conto deposito
     */
    public function notificaVendita(
        ContoDeposito $contoDeposito,
        MovimentoDeposito $movimento,
        array $datiAggiuntivi = []
    ): Notifica {
        $societaMittente = $contoDeposito->getSocietaMittente();
        
        if (!$societaMittente) {
            throw new \Exception("Impossibile creare notifica: società mittente non trovata");
        }

        $articolo = $movimento->articolo ?? $movimento->prodottoFinito;
        $codiceItem = $articolo ? $articolo->codice : 'N/A';
        $descrizioneItem = $articolo ? $articolo->descrizione : 'N/A';

        $fatturaInfo = '';
        if ($movimento->fattura) {
            $fatturaInfo = " Fattura: {$movimento->fattura->numero} del " . 
                          $movimento->fattura->data_documento->format('d/m/Y');
        }

        $titolo = "Vendita da Conto Deposito {$contoDeposito->codice}";
        $messaggio = "È stata registrata una vendita dell'articolo {$codiceItem} ({$descrizioneItem}) " .
                    "dal conto deposito {$contoDeposito->codice}.{$fatturaInfo} " .
                    "Quantità venduta: {$movimento->quantita}. " .
                    "Valore: €" . number_format($movimento->costo_totale, 2, ',', '.');

        $datiAggiuntivi['articolo_codice'] = $codiceItem;
        $datiAggiuntivi['articolo_descrizione'] = $descrizioneItem;
        $datiAggiuntivi['quantita'] = $movimento->quantita;
        $datiAggiuntivi['valore_totale'] = $movimento->costo_totale;
        $datiAggiuntivi['fattura_id'] = $movimento->fattura_id;
        $datiAggiuntivi['fattura_numero'] = $movimento->fattura ? $movimento->fattura->numero : null;

        return $this->creaNotifica(
            tipo: 'vendita',
            societaId: $societaMittente->id,
            contoDeposito: $contoDeposito,
            movimento: $movimento,
            titolo: $titolo,
            messaggio: $messaggio,
            datiAggiuntivi: $datiAggiuntivi
        );
    }

    /**
     * Crea notifica per scadenza in arrivo
     */
    public function notificaScadenza(
        ContoDeposito $contoDeposito,
        int $giorniRimanenti
    ): Notifica {
        $societaMittente = $contoDeposito->getSocietaMittente();
        
        if (!$societaMittente) {
            throw new \Exception("Impossibile creare notifica: società mittente non trovata");
        }

        $titolo = "Scadenza Conto Deposito {$contoDeposito->codice}";
        $messaggio = "Il conto deposito {$contoDeposito->codice} scadrà tra {$giorniRimanenti} giorni " .
                    "({$contoDeposito->data_scadenza->format('d/m/Y')}). " .
                    "Prepara il reso degli articoli rimanenti.";

        $datiAggiuntivi = [
            'giorni_rimanenti' => $giorniRimanenti,
            'data_scadenza' => $contoDeposito->data_scadenza->format('Y-m-d'),
            'articoli_rimanenti' => $contoDeposito->getArticoliRimanenti(),
        ];

        return $this->creaNotifica(
            tipo: 'scadenza',
            societaId: $societaMittente->id,
            contoDeposito: $contoDeposito,
            movimento: null,
            titolo: $titolo,
            messaggio: $messaggio,
            datiAggiuntivi: $datiAggiuntivi
        );
    }

    /**
     * Crea notifica per deposito scaduto
     */
    public function notificaDepositoScaduto(ContoDeposito $contoDeposito): Notifica
    {
        $societaMittente = $contoDeposito->getSocietaMittente();
        
        if (!$societaMittente) {
            throw new \Exception("Impossibile creare notifica: società mittente non trovata");
        }

        $titolo = "Deposito Scaduto: {$contoDeposito->codice}";
        $messaggio = "Il conto deposito {$contoDeposito->codice} è scaduto il {$contoDeposito->data_scadenza->format('d/m/Y')}. " .
                    "È necessario procedere al reso degli articoli rimanenti.";

        $datiAggiuntivi = [
            'giorni_scaduto' => now()->diffInDays($contoDeposito->data_scadenza),
            'data_scadenza' => $contoDeposito->data_scadenza->format('Y-m-d'),
            'articoli_rimanenti' => $contoDeposito->getArticoliRimanenti(),
        ];

        return $this->creaNotifica(
            tipo: 'deposito_scaduto',
            societaId: $societaMittente->id,
            contoDeposito: $contoDeposito,
            movimento: null,
            titolo: $titolo,
            messaggio: $messaggio,
            datiAggiuntivi: $datiAggiuntivi
        );
    }

    /**
     * Crea notifica e invia email
     */
    protected function creaNotifica(
        string $tipo,
        int $societaId,
        ?ContoDeposito $contoDeposito = null,
        ?MovimentoDeposito $movimento = null,
        string $titolo,
        string $messaggio,
        array $datiAggiuntivi = []
    ): Notifica {
        // Crea notifica in database
        $notifica = Notifica::create([
            'tipo' => $tipo,
            'societa_id' => $societaId,
            'conto_deposito_id' => $contoDeposito?->id,
            'movimento_deposito_id' => $movimento?->id,
            'titolo' => $titolo,
            'messaggio' => $messaggio,
            'dati_aggiuntivi' => $datiAggiuntivi,
            'letta' => false,
            'email_inviata' => false,
        ]);

        // Invia email
        try {
            $this->inviaEmail($notifica);
        } catch (\Exception $e) {
            Log::error("Errore invio email notifica ID {$notifica->id}: " . $e->getMessage());
            $notifica->marcaErroreEmail($e->getMessage());
        }

        return $notifica;
    }

    /**
     * Invia email notifica
     */
    protected function inviaEmail(Notifica $notifica): void
    {
        $societa = $notifica->societa;
        
        if (!$societa) {
            throw new \Exception("Società non trovata per notifica ID {$notifica->id}");
        }

        $destinatari = $societa->getEmailNotifiche();
        
        if (empty($destinatari)) {
            Log::warning("Nessun destinatario email per società {$societa->codice}");
            return;
        }

        // Crea email semplice (puoi usare Mail::raw o una vista personalizzata)
        foreach ($destinatari as $email) {
            try {
                Mail::raw($notifica->messaggio, function ($message) use ($email, $notifica, $societa) {
                    $message->to($email)
                            ->subject("[Athena] {$notifica->titolo}");
                });
            } catch (\Exception $e) {
                Log::error("Errore invio email a {$email} per notifica ID {$notifica->id}: " . $e->getMessage());
                throw $e;
            }
        }

        $notifica->marcaEmailInviata();
    }

    /**
     * Conta notifiche non lette per una società
     */
    public function contaNonLette(int $societaId): int
    {
        return Notifica::perSocieta($societaId)
            ->nonLette()
            ->count();
    }

    /**
     * Ottieni notifiche recenti per società
     */
    public function getNotificheRecenti(int $societaId, int $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        return Notifica::perSocieta($societaId)
            ->recenti($limit)
            ->with(['contoDeposito', 'movimentoDeposito'])
            ->get();
    }
}
