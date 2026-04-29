<?php

namespace App\Services;

use App\Models\Articolo;
use App\Models\Stampante;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Socket;

class EtichettaService
{
    private const PRINTER_CONNECT_TIMEOUT_MS = 2000;
    private const PRINTER_IO_TIMEOUT_MS = 2000;
    private const EURO_SYMBOL = "\u{20AC}";

    /**
     * Genera il codice ZPL per un'etichetta
     */
    public function generaEtichettaZPL(Articolo $articolo, $stampanteId = null, string $layout = 'standard'): string
    {
        $stampante = $stampanteId
            ? Stampante::find($stampanteId)
            : $this->getStampanteDefault($articolo);

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
        $stampante = $stampanteId
            ? Stampante::find($stampanteId)
            : $this->getStampanteDefault($articolo);

        if (!$stampante) {
            throw new \Exception('Nessuna stampante disponibile');
        }

        $template = $this->getTemplateZPL($stampante->modello, $layout);

        return $this->popolaTemplateConPrezzo($template, $articolo, $prezzo, $formatoPrezzo, $layout);
    }

    /**
     * Genera ZPL per cartellino NC non legato ad articolo.
     */
    public function generaEtichettaNcZpl(string $prezzo, string $formatoPrezzo = 'codificato', ?string $carati = null, $stampanteId = null): string
    {
        $stampante = $stampanteId
            ? Stampante::find($stampanteId)
            : $this->getStampanteDefaultNc();

        if (!$stampante) {
            throw new \Exception('Nessuna stampante disponibile');
        }

        $prezzoFormattato = $this->formattaPrezzoCompat($prezzo, $formatoPrezzo);

        if ($stampante->modello === 'ZT421') {
            return $this->buildNcZplZT421Validated($prezzoFormattato, $carati);
        }

        $layout = filled($carati) ? 'nc_prezzo_carati' : 'nc_prezzo';
        $template = $this->getTemplateZPL($stampante->modello, $layout);

        return $this->popolaTemplateNc(
            $template,
            $prezzoFormattato,
            $carati,
            $stampante->modello
        );
    }

    /**
     * Stampa cartellino NC non legato ad articolo.
     */
    public function stampaEtichettaNc(string $prezzo, string $formatoPrezzo = 'codificato', ?string $carati = null, $stampanteId = null, int $quantita = 1): bool
    {
        $stampante = $stampanteId
            ? Stampante::find($stampanteId)
            : $this->getStampanteDefaultNc();

        if (!$stampante) {
            throw new \Exception('Nessuna stampante disponibile');
        }

        $quantita = max(1, $quantita);
        $zpl = $this->generaEtichettaNcZpl($prezzo, $formatoPrezzo, $carati, $stampante->id);

        $payload = str_repeat($zpl, $quantita);

        return $this->inviaAllaStampante($stampante->ip_address, $stampante->port, $payload);
    }

    /**
     * Ottieni la stampante predefinita per un articolo
     */
    public function getStampanteDefault(Articolo $articolo): ?Stampante
    {
        $user = Auth::user();
        if ($user && $user->stampante_default_id) {
            $stampante = Stampante::find($user->stampante_default_id);
            if ($stampante && $stampante->attiva) {
                return $stampante;
            }
        }

        return Stampante::where('attiva', true)->first();
    }

    /**
     * Stampante predefinita per cartellini NC: usa default utente o prima attiva.
     */
    public function getStampanteDefaultNc(): ?Stampante
    {
        $user = Auth::user();

        if ($user && $user->stampante_default_id) {
            $stampante = Stampante::find($user->stampante_default_id);
            if ($stampante?->attiva) {
                return $stampante;
            }
        }

        return Stampante::where('attiva', true)->orderBy('nome')->first();
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
            'ZT421' => $this->getTemplateZT421(),
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
^MD30
^PR3
^PW552^LL80
^FO10,10^BQ,2,2^FDQA,{CARICO}^FS
^FO60,10^A@N,14,14,E:TT0003M_.FNT^FD{CARICO}^FS
^FO60,25^A@N,13,13,E:TT0003M_.FNT^FD{PREZZO}^FS
^FO60,40^A@N,13,13,E:TT0003M_.FNT^FB100,2,3,L^FD{CARATI}^FS
^XZ';
    }

    /**
     * Template per ZT421 (Lecco) con offset orizzontale validato.
     */
    private function getTemplateZT421(): string
    {
        return '^XA
^MD30
^PR3
^LH180,0
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
        return $this->getTemplateZT230();
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
        if ($modello === 'ZT421') {
            return $this->getTemplateNcZT421();
        }

        if ($modello === 'ZT420') {
            return $this->getTemplateNcZT420();
        }

        $path = $modello === 'ZT620'
            ? resource_path('zpl/etichetta_template_nc_roma.zpl')
            : resource_path('zpl/etichetta_nc_template.zpl');

        if (!file_exists($path)) {
            Log::warning('Template NC mancante', ['modello' => $modello, 'path' => $path]);
            return $this->getTemplateZT230();
        }

        return (string) file_get_contents($path);
    }

    private function getTemplateNcZT421(): string
    {
        return '^XA
^MD30
^PR3
^CI28
^LH180,0
^PW552^LL80
^FO60,25^A@N,13,13,E:TT0003M_.FNT^FD{PREZZO}^FS
^FO60,40^A@N,13,13,E:TT0003M_.FNT^FB100,2,3,L^FD{CARATI}^FS
^XZ';
    }

    private function getTemplateNcZT420(): string
    {
        return $this->getTemplateNcZT421();
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
            '{PIETRE}',
        ], [
            $carico,
            $caricoQr,
            $this->formatEuroLabel($articolo->prezzo_fornitore ?? $articolo->prezzo_acquisto ?? 0),
            $carati,
            $oro,
            $brill,
            $pietre,
        ], $template);
    }

    /**
     * Popola il template ZPL con prezzo personalizzato
     */
    private function popolaTemplateConPrezzo(string $template, Articolo $articolo, string $prezzo, string $formatoPrezzo, string $layout = 'standard'): string
    {
        $prezzoFormattato = $this->formattaPrezzoCompat($prezzo, $formatoPrezzo);
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
            '{PIETRE}',
        ], [
            $carico,
            $caricoQr,
            $prezzoFormattato,
            $carati,
            $oro,
            $brill,
            $pietre,
        ], $template);
    }

    private function popolaTemplateNc(string $template, string $prezzoFormattato, ?string $carati, string $modello): string
    {
        $carati = trim((string) $carati);
        $isRoma = $modello === 'ZT620';
        $isLecco = $modello === 'ZT421';

        if ($isLecco) {
            $prezzoFormattato = trim((string) $prezzoFormattato);
            if ($prezzoFormattato !== '' && !str_starts_with($prezzoFormattato, self::EURO_SYMBOL . ' ')) {
                $prezzoFormattato = preg_replace('/^[^\d]*/u', '', $prezzoFormattato);
                $prezzoFormattato = self::EURO_SYMBOL . ' ' . ltrim((string) $prezzoFormattato);
            }

            if ($carati !== '') {
                $carati = preg_match('/^ct\b/i', $carati)
                    ? 'CT ' . trim(substr($carati, 2))
                    : 'CT ' . $carati;
            }
        }

        return str_replace([
            '{CARICO}',
            '{CARICOQR}',
            '{PREZZO}',
            '{CARATI}',
            '{ORO}',
            '{BRILL}',
            '{PIETRE}',
        ], [
            '',
            '',
            $prezzoFormattato,
            $carati,
            $isRoma ? $carati : '',
            '',
            '',
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
            $prezzoNumerico = preg_replace('/[^\d,.]/', '', $prezzo);
            $prezzoNumerico = str_replace(',', '.', $prezzoNumerico);

            if (is_numeric($prezzoNumerico)) {
                return $this->formatEuroLabel((float) $prezzoNumerico);
            }

            return self::EURO_SYMBOL . ' ' . trim((string) $prezzo);
        }

        return mb_strtoupper(trim($prezzo), 'UTF-8');
    }

    private function formattaPrezzoCompat(string $prezzo, string $formatoPrezzo): string
    {
        if ($formatoPrezzo !== 'euro') {
            return mb_strtoupper(trim($prezzo), 'UTF-8');
        }

        $prezzoNumerico = $this->normalizeEuroPriceCompat($prezzo);
        if ($prezzoNumerico === null) {
            $raw = trim((string) $prezzo);
            $raw = preg_replace('/^[^\d]*/u', '', $raw);

            return self::EURO_SYMBOL . ' ' . $raw;
        }

        return $this->formatEuroLabel($prezzoNumerico);
    }

    private function normalizeEuroPriceCompat($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $raw = preg_replace('/[^\d,.\-]/', '', trim((string) $value));
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^\d{1,3}(?:,\d{3})*(?:\.\d+)?$/', $raw) || preg_match('/^\d+(?:\.\d+)?$/', $raw)) {
            $normalized = str_replace(',', '', $raw);
            return is_numeric($normalized) ? (float) $normalized : null;
        }

        if (preg_match('/^\d{1,3}(?:\.\d{3})*(?:,\d+)?$/', $raw) || preg_match('/^\d+(?:,\d+)?$/', $raw)) {
            $normalized = str_replace('.', '', $raw);
            $normalized = str_replace(',', '.', $normalized);
            return is_numeric($normalized) ? (float) $normalized : null;
        }

        return null;
    }

    /**
     * Invia il codice ZPL alla stampante
     */
    public function inviaAllaStampante(string $ip, int $port, string $zpl): bool
    {
        $startedAt = microtime(true);

        try {
            $zpl = $this->normalizePrinterEncoding($zpl);
            Log::info('ZPL completo inviato alla stampante', [
                'ip' => $ip,
                'port' => $port,
                'payload_length' => strlen($zpl),
                'zpl' => $zpl,
            ]);
            $socket = $this->createPrinterSocket($ip, $port);

            $sent = socket_write($socket, $zpl, strlen($zpl));
            socket_close($socket);

            $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);
            if ($elapsedMs > 500) {
                Log::warning('Invio etichetta lento', [
                    'ip' => $ip,
                    'port' => $port,
                    'elapsed_ms' => $elapsedMs,
                    'payload_length' => strlen($zpl),
                ]);
            }

            return $sent !== false;
        } catch (\Exception $e) {
            $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);
            Log::error('Errore stampa etichetta: ' . $e->getMessage(), [
                'ip' => $ip,
                'port' => $port,
                'elapsed_ms' => $elapsedMs,
            ]);

            return false;
        }
    }

    private function createPrinterSocket(string $ip, int $port): Socket
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$socket) {
            throw new \Exception('Impossibile creare il socket');
        }

        $this->applySocketTimeouts($socket);

        $connected = @socket_connect($socket, $ip, $port);
        if (!$connected) {
            $message = socket_strerror(socket_last_error($socket));
            socket_close($socket);

            throw new \Exception("Impossibile connettersi alla stampante ({$message})");
        }

        return $socket;
    }

    private function applySocketTimeouts(Socket $socket): void
    {
        $timeout = [
            'sec' => intdiv(self::PRINTER_IO_TIMEOUT_MS, 1000),
            'usec' => (self::PRINTER_IO_TIMEOUT_MS % 1000) * 1000,
        ];

        @socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, $timeout);
        @socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, $timeout);

        if (defined('TCP_SYNCNT')) {
            @socket_set_option($socket, SOL_TCP, TCP_SYNCNT, 1);
        }
    }

    private function normalizePrinterEncoding(string $zpl): string
    {
        return str_replace(
            ['ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬', 'ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬', 'ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬', 'â‚¬'],
            self::EURO_SYMBOL,
            $zpl
        );
    }

    private function buildNcZplZT421(string $prezzoFormattato, ?string $carati): string
    {
        $prezzoFormattato = trim($prezzoFormattato);
        $prezzoFormattato = preg_replace('/^[^\d]*/u', '', $prezzoFormattato);
        $prezzoFormattato = self::EURO_SYMBOL . ' ' . ltrim($prezzoFormattato);

        $carati = trim((string) $carati);
        if ($carati !== '') {
            $carati = preg_replace('/^ct\s*/i', '', $carati);
            $carati = 'CT ' . ltrim($carati);
        }

        return '^XA
    ^MD30
    ^PR3
    ^LH180,0
    ^PW552^LL80
    ^FO60,25^A@N,13,13,E:TT0003M_.FNT^FD' . $prezzoFormattato . '^FS
    ^FO60,40^A@N,13,13,E:TT0003M_.FNT^FD' . $carati . '^FS
    ^XZ';
    }

    private function buildNcZplZT421Validated(string $prezzoFormattato, ?string $carati): string
    {
        $prezzoFormattato = trim((string) $prezzoFormattato);
        $prezzoFormattato = preg_replace('/^[^\d]*/u', '', $prezzoFormattato);
        $prezzoFormattato = self::EURO_SYMBOL . ' ' . ltrim($prezzoFormattato);

        $carati = trim((string) $carati);
        if ($carati !== '') {
            $carati = preg_replace('/^ct\s*/i', '', $carati);
            $carati = 'CT ' . ltrim($carati);
        }

        return '^XA
^MD30
^PR3
^CI28
^LH180,0
^PW552^LL80
^FO60,25^A@N,13,13,E:TT0003M_.FNT^FD' . $prezzoFormattato . '^FS
^FO60,40^A@N,13,13,E:TT0003M_.FNT^FB100,2,3,L^FD' . $carati . '^FS
^XZ';
    }

    /**
     * Stampa etichetta per un articolo
     */
    public function stampaEtichetta(Articolo $articolo, $stampanteId = null): bool
    {
        $zpl = $this->generaEtichettaZPL($articolo, $stampanteId);

        $stampante = $stampanteId
            ? Stampante::find($stampanteId)
            : $this->getStampanteDefault($articolo);

        if (!$stampante) {
            throw new \Exception('Nessuna stampante disponibile');
        }

        return $this->inviaAllaStampante(
            $stampante->ip_address,
            $stampante->port,
            $zpl
        );
    }

    private function formatEuroLabel(float|int|string $value): string
    {
        $numeric = is_numeric($value) ? (float) $value : ($this->normalizeEuroPriceCompat($value) ?? 0.0);

        return self::EURO_SYMBOL . number_format($numeric, 2, ',', '.');
    }
}
