<?php

namespace App\Services;

use App\Models\Articolo;
use App\Models\Stampante;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class EtichettaService
{
    /**
     * Genera il codice ZPL per un'etichetta
     */
    public function generaEtichettaZPL(Articolo $articolo, $stampanteId = null, string $layout = 'standard'): string
    {
        $stampante = $stampanteId ? 
            Stampante::find($stampanteId) : 
            $this->getStampanteDefault($articolo);
            
        if (!$stampante) {
            throw new \Exception('Nessuna stampante disponibile');
        }

        $template = $this->getTemplateZPL($stampante->modello, $layout);
        
        return $this->popolaTemplate($template, $articolo, $layout);
    }
    
    /**
     * Genera ZPL con prezzo personalizzato
     */
    public function generaEtichettaZPLConPrezzo(Articolo $articolo, string $prezzo, string $formatoPrezzo, $stampanteId = null, string $layout = 'standard'): string
    {
        $stampante = $stampanteId ? 
            Stampante::find($stampanteId) : 
            $this->getStampanteDefault($articolo);
            
        if (!$stampante) {
            throw new \Exception('Nessuna stampante disponibile');
        }

        $template = $this->getTemplateZPL($stampante->modello, $layout);
        
        return $this->popolaTemplateConPrezzo($template, $articolo, $prezzo, $formatoPrezzo, $layout);
    }

    /**
     * Ottieni la stampante predefinita per un articolo
     */
    public function getStampanteDefault(Articolo $articolo): ?Stampante
    {
        // Prima prova con la stampante dell'utente corrente
        $user = Auth::user();
        if ($user && $user->stampante_default_id) {
            $stampante = Stampante::find($user->stampante_default_id);
            if (!$stampante) {
                // noop
            } elseif ($user instanceof User && $user->isAdmin()) {
                return $stampante;
            } elseif ($stampante->canPrintArticolo($articolo)) {
                return $stampante;
            }
        }

        // Poi cerca una stampante compatibile
        return Stampante::where('attiva', true)
            ->get()
            ->first(function ($stampante) use ($articolo, $user) {
                if ($user instanceof User && $user->isAdmin()) {
                    return true;
                }

                return $stampante->canPrintArticolo($articolo);
            });
    }

    /**
     * Ottieni il template ZPL per il modello di stampante
     */
    private function getTemplateZPL(string $modello, string $layout = 'standard'): string
    {
        if ($layout !== 'standard') {
            return $this->getTemplateNc($modello);
        }

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
^LH220,8                     ; Taratura Bellagio: sposta il contenuto più a sinistra
^PW552^LL80
^FO05,10^BQ,2,3^FDQA,{CARICO}^FS
^FO80,10^A0N,19,19^FD{CARICO}^FS
^FO80,35^A0N,19,19^FD{PREZZO}^FS
^FO80,60^A0N,19,19^FB120,2,3,L^FD{CARATI}^FS
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

    private function getTemplateNc(string $modello): string
    {
        $path = $modello === 'ZT620'
            ? resource_path('zpl/etichetta_template_nc_roma.zpl')
            : resource_path('zpl/etichetta_nc_template.zpl');

        if (!file_exists($path)) {
            Log::warning('Template NC mancante', ['modello' => $modello, 'path' => $path]);
            return $this->getTemplateZT230();
        }

        return file_get_contents($path);
    }

    /**
     * Popola il template con i dati dell'articolo
     */
    private function popolaTemplate(string $template, Articolo $articolo, string $layout = 'standard'): string
    {
        $carico = $layout === 'standard' ? $this->getEtichettaCarico($articolo) : '';
        $carati = $this->getEtichettaCarati($articolo);
        $oro = $this->getEtichettaOro($articolo);
        $brill = $this->getEtichettaBrill($articolo);
        $pietre = $this->getEtichettaPietre($articolo);
        $caricoQr = $layout === 'standard' ? ($articolo->getCodicePerEtichetta() ?: $carico) : '';
        if ($layout === 'nc_prezzo') {
            $carati = '';
            $oro = '';
            $brill = '';
            $pietre = '';
        } elseif ($layout === 'nc_prezzo_carati') {
            $oro = '';
            $brill = '';
            $pietre = '';
        }

        return str_replace([
            '{CARICO}',
            '{CARICOQR}',
            '{PREZZO}',
            '{CARATI}',
            '{ORO}',
            '{BRILL}',
            '{PIETRE}'
        ], [
            $carico,
            $caricoQr, // Per QR code
            '€' . number_format($articolo->prezzo_fornitore ?? $articolo->prezzo_acquisto ?? 0, 2),
            $carati,
            $oro,
            $brill,
            $pietre
        ], $template);
    }
    
    /**
     * Popola il template ZPL con prezzo personalizzato
     */
    private function popolaTemplateConPrezzo(string $template, Articolo $articolo, string $prezzo, string $formatoPrezzo, string $layout = 'standard'): string
    {
        // Formatta il prezzo in base al formato
        $prezzoFormattato = $this->formattaPrezzo($prezzo, $formatoPrezzo);
        $carico = $layout === 'standard' ? $this->getEtichettaCarico($articolo) : '';
        $carati = $this->getEtichettaCarati($articolo);
        $oro = $this->getEtichettaOro($articolo);
        $brill = $this->getEtichettaBrill($articolo);
        $pietre = $this->getEtichettaPietre($articolo);
        $caricoQr = $layout === 'standard' ? ($articolo->getCodicePerEtichetta() ?: $carico) : '';
        if ($layout === 'nc_prezzo') {
            $carati = '';
            $oro = '';
            $brill = '';
            $pietre = '';
        } elseif ($layout === 'nc_prezzo_carati') {
            $oro = '';
            $brill = '';
            $pietre = '';
        }
        
        return str_replace([
            '{CARICO}',
            '{CARICOQR}',
            '{PREZZO}',
            '{CARATI}',
            '{ORO}',
            '{BRILL}',
            '{PIETRE}'
        ], [
            $carico,
            $caricoQr, // Per QR code
            $prezzoFormattato,
            $carati,
            $oro,
            $brill,
            $pietre
        ], $template);
    }

    private function getEtichettaCarico(Articolo $articolo): string
    {
        return $articolo->getCodicePerEtichetta()
            ?: $articolo->codice
            
            ?: 'N/A';
    }

    private function getEtichettaCarati(Articolo $articolo): string
    {
        $carati = $articolo->caratura
            ?? $articolo->carati
            ?? ($articolo->caratteristiche['caratura'] ?? null);

        return $carati ?: 'N/A';
    }

    private function getEtichettaOro(Articolo $articolo): string
    {
        $oro = $articolo->materiale
            ?? ($articolo->caratteristiche['materiale'] ?? null)
            ?? $articolo->colore;

        return $oro ?: 'N/A';
    }

    private function getEtichettaBrill(Articolo $articolo): string
    {
        $brill = $articolo->brill
            ?? ($articolo->caratteristiche['brill'] ?? null);

        return $brill ?: 'N/A';
    }

    private function getEtichettaPietre(Articolo $articolo): string
    {
        $pietre = $articolo->pietre
            ?? ($articolo->caratteristiche['pietre'] ?? null);

        return $pietre ?: 'N/A';
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
            Log::error('Errore stampa etichetta: ' . $e->getMessage());
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
