<?php

namespace App\Services;

use App\Models\Articolo;
use App\Models\Stampante;
use App\Models\User;

class EtichettaService
{
    /**
     * Genera il codice ZPL per un'etichetta
     */
    public function generaEtichettaZPL(Articolo $articolo, $stampanteId = null): string
    {
        $stampante = $stampanteId ? 
            Stampante::find($stampanteId) : 
            $this->getStampanteDefault($articolo);
            
        if (!$stampante) {
            throw new \Exception('Nessuna stampante disponibile');
        }

        $template = $this->getTemplateZPL($stampante->modello);
        
        return $this->popolaTemplate($template, $articolo);
    }
    
    /**
     * Genera ZPL con prezzo personalizzato
     */
    public function generaEtichettaZPLConPrezzo(Articolo $articolo, string $prezzo, string $formatoPrezzo, $stampanteId = null): string
    {
        $stampante = $stampanteId ? 
            Stampante::find($stampanteId) : 
            $this->getStampanteDefault($articolo);
            
        if (!$stampante) {
            throw new \Exception('Nessuna stampante disponibile');
        }

        $template = $this->getTemplateZPL($stampante->modello);
        
        return $this->popolaTemplateConPrezzo($template, $articolo, $prezzo, $formatoPrezzo);
    }

    /**
     * Ottieni la stampante predefinita per un articolo
     */
    public function getStampanteDefault(Articolo $articolo): ?Stampante
    {
        // Prima prova con la stampante dell'utente corrente
        $user = auth()->user();
        if ($user && $user->stampante_default_id) {
            $stampante = Stampante::find($user->stampante_default_id);
            if ($stampante && $stampante->canPrintArticolo($articolo)) {
                return $stampante;
            }
        }

        // Poi cerca una stampante compatibile
        return Stampante::where('attiva', true)
            ->get()
            ->first(function ($stampante) use ($articolo) {
                return $stampante->canPrintArticolo($articolo);
            });
    }

    /**
     * Ottieni il template ZPL per il modello di stampante
     */
    private function getTemplateZPL(string $modello): string
    {
        $templates = [
            'ZT230' => $this->getTemplateZT230(),
            'ZT420' => $this->getTemplateZT420(),
            'ZT620' => $this->getTemplateZT620(),
        ];
        
        return $templates[$modello] ?? $templates['ZT230'];
    }

    /**
     * Template per ZT230 (Cavour/Lecco)
     */
    private function getTemplateZT230(): string
    {
        return '^XA
^MD30               ; Massima densità
^PR3                ; Velocità di stampa bassa = più scuro
^PW552^LL80
^FO10,10^BQ,2,2^FDQA,{CARICO}^FS
^FO60,10^A@N,14,14,E:TT0003M_.FNT^FD{CARICO}^FS
^FO60,25^A@N,13,13,E:TT0003M_.FNT^FD{PREZZO}^FS
^FO60,40^A@N,13,13,E:TT0003M_.FNT^FB100,2,3,L^FD{CARATI}^FS
^XZ';
    }

    /**
     * Template per ZT420 (Bellagio/Monastero)
     */
    private function getTemplateZT420(): string
    {
        return '^XA
^MD30
^CI28
^LH300,10                    ; Offset orizzontale fisso a 260
^PW552^LL80                ; Dimensioni dell\'etichetta
^FO05,10^BQ,2,3^FDQA,{CARICO}^FS   ; QR Code con dimensione leggibile
^FO80,10^A0G,19,19^FD{CARICO}^FS      ; Prezzo accanto al QR Code con font G
^FO80,35^A0A,19,19^FD{PREZZO}^FS      ; Carico accanto al prezzo con font G
^FO80,60^A0N,19,19^FB100,2,3,L^FD{CARATI}^FS ; Testo multilinea con font G
^XZ';
    }

    /**
     * Template per ZT620 (Roma)
     */
    private function getTemplateZT620(): string
    {
        return '^XA
^MD30
^CI28
^LH180,0
^PW552^LL80

^FO-10,10^BQ,2,2^FDQA,{CARICOQR}^FS

^FO60,15^A0N,19,17^FD{CARICO}^FS
^FO57,33
^A0N,15,15
^FB100,2,2,L,0
^FD{ORO} {BRILL} {PIETRE}^FS
^FO57,70^A0N,19,17^FD{PREZZO}^FS

^XZ';
    }

    /**
     * Popola il template con i dati dell'articolo
     */
    private function popolaTemplate(string $template, Articolo $articolo): string
    {
        return str_replace([
            '{CARICO}',
            '{CARICOQR}',
            '{PREZZO}',
            '{CARATI}',
            '{ORO}',
            '{BRILL}',
            '{PIETRE}'
        ], [
            $articolo->numero_carico ?? 'N/A',
            $articolo->numero_carico ?? 'N/A', // Per QR code
            '€' . number_format($articolo->prezzo_fornitore ?? $articolo->prezzo_acquisto ?? 0, 2),
            $articolo->carati ?? 'N/A',
            $articolo->materiale ?? 'N/A',
            $articolo->brill ?? 'N/A',
            $articolo->pietre ?? 'N/A'
        ], $template);
    }
    
    /**
     * Popola il template ZPL con prezzo personalizzato
     */
    private function popolaTemplateConPrezzo(string $template, Articolo $articolo, string $prezzo, string $formatoPrezzo): string
    {
        // Formatta il prezzo in base al formato
        $prezzoFormattato = $this->formattaPrezzo($prezzo, $formatoPrezzo);
        
        return str_replace([
            '{CARICO}',
            '{CARICOQR}',
            '{PREZZO}',
            '{CARATI}',
            '{ORO}',
            '{BRILL}',
            '{PIETRE}'
        ], [
            $articolo->numero_carico ?? 'N/A',
            $articolo->numero_carico ?? 'N/A', // Per QR code
            $prezzoFormattato,
            $articolo->carati ?? 'N/A',
            $articolo->materiale ?? 'N/A',
            $articolo->brill ?? 'N/A',
            $articolo->pietre ?? 'N/A'
        ], $template);
    }
    
    /**
     * Formatta il prezzo in base al formato specificato
     */
    private function formattaPrezzo(string $prezzo, string $formatoPrezzo): string
    {
        if ($formatoPrezzo === 'euro') {
            // Rimuovi caratteri non numerici eccetto virgola e punto
            $prezzoNumerico = preg_replace('/[^\d,.]/', '', $prezzo);
            $prezzoNumerico = str_replace(',', '.', $prezzoNumerico);
            
            if (is_numeric($prezzoNumerico)) {
                return '€' . number_format((float)$prezzoNumerico, 2, ',', '.');
            } else {
                return '€' . $prezzo; // Se non è numerico, usa così com'è
            }
        } else {
            // Formato codificato (es. 345X3P3) - usa così com'è
            return $prezzo;
        }
    }

    /**
     * Invia il codice ZPL alla stampante
     */
    public function inviaAllaStampante(string $ip, int $port, string $zpl): bool
    {
        try {
            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            if (!$socket) {
                throw new \Exception('Impossibile creare il socket');
            }

            $connected = socket_connect($socket, $ip, $port);
            if (!$connected) {
                throw new \Exception('Impossibile connettersi alla stampante');
            }

            $sent = socket_write($socket, $zpl, strlen($zpl));
            socket_close($socket);

            return $sent !== false;
        } catch (\Exception $e) {
            \Log::error('Errore stampa etichetta: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Stampa etichetta per un articolo
     */
    public function stampaEtichetta(Articolo $articolo, $stampanteId = null): bool
    {
        $zpl = $this->generaEtichettaZPL($articolo, $stampanteId);
        
        $stampante = $stampanteId ? 
            Stampante::find($stampanteId) : 
            $this->getStampanteDefault($articolo);

        if (!$stampante) {
            throw new \Exception('Nessuna stampante disponibile');
        }

        return $this->inviaAllaStampante(
            $stampante->ip_address, 
            $stampante->port, 
            $zpl
        );
    }
}
