<?php
$path = 'c:/Users/dmazz/Herd/athena_v2/app/Http/Livewire/ArticoliTable.php';
$content = file_get_contents($path);
$content = str_replace("\r\n", "\n", $content);

$old = <<<'CODE'
    private function getMagazziniFilterOptions()
    {
        $userSedeId = auth()->user()?->sede_id;
        $service = app(MagazzinoLogicoService::class);

        $categorie = CategoriaMerceologica::where('attivo', true)
            ->when($userSedeId, fn ($q) => $q->where('sede_id', $userSedeId))
            ->orderBy('nome')
            ->get();

        return $categorie
            ->map(function (CategoriaMerceologica $categoria) use ($service) {
                $magazzinoLogico = $service->resolveFromCategoria($categoria);
                if (!$magazzinoLogico) {
                    return null;
                }

                return (object) [
                    'id' => $magazzinoLogico,
                    'nome' => 'Magazzino ' . $magazzinoLogico,
                ];
            })
            ->filter()
            ->unique('id')
            ->values();
    }
CODE;

$new = <<<'CODE'
    private function getMagazziniFilterOptions()
    {
        $userSedeId = auth()->user()?->sede_id;
        $service = app(MagazzinoLogicoService::class);

        $categorie = CategoriaMerceologica::query()
            ->with('sede')
            ->where('attivo', true)
            ->when($userSedeId, fn ($q) => $q->where('sede_id', $userSedeId))
            ->orderBy('sede_id')
            ->orderBy('nome')
            ->get();

        return $categorie
            ->map(function (CategoriaMerceologica $categoria) use ($service) {
                $magazzinoLogico = $service->resolveFromCategoria($categoria);
                if (!$magazzinoLogico) {
                    return null;
                }

                return (object) [
                    'id' => (int) $magazzinoLogico,
                    'nome' => 'Magazzino ' . $magazzinoLogico,
                ];
            })
            ->filter()
            ->unique('id')
            ->sortBy('id')
            ->values();
    }

    private function getMagazziniGroupedBySede()
    {
        $userSedeId = auth()->user()?->sede_id;
        $service = app(MagazzinoLogicoService::class);

        $categorie = CategoriaMerceologica::query()
            ->with('sede')
            ->where('attivo', true)
            ->when($userSedeId, fn ($q) => $q->where('sede_id', $userSedeId))
            ->get()
            ->map(function (CategoriaMerceologica $categoria) use ($service) {
                $magazzinoLogico = $service->resolveFromCategoria($categoria);
                if (!$magazzinoLogico) {
                    return null;
                }

                return (object) [
                    'id' => (int) $magazzinoLogico,
                    'nome' => 'Magazzino ' . $magazzinoLogico,
                    'sede_id' => $categoria->sede_id,
                    'sede_nome' => $categoria->sede->nome ?? ('Sede ' . $categoria->sede_id),
                    'codice_locale' => $categoria->codice,
                ];
            })
            ->filter();

        return $categorie
            ->groupBy('sede_nome')
            ->map(fn ($items) => $items->sortBy('id')->values())
            ->sortKeys();
    }
CODE;

$content = str_replace($old, $new, $content);
$content = str_replace('        $magazzini = $this->getMagazziniFilterOptions();' . "\n", '        $magazzini = $this->getMagazziniFilterOptions();' . "\n" . '        $magazziniGruppati = $this->getMagazziniGroupedBySede();' . "\n", $content);
$content = str_replace("compact('articoli', 'stats', 'magazzini', 'fornitori', 'marche', 'sedi')", "compact('articoli', 'stats', 'magazzini', 'magazziniGruppati', 'fornitori', 'marche', 'sedi')", $content);

file_put_contents($path, str_replace("\n", "\r\n", $content));
