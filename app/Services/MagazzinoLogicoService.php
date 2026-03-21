<?php

namespace App\Services;

use App\Models\CategoriaMerceologica;

class MagazzinoLogicoService
{
    public function resolveFromCategoriaId(?int $categoriaId): ?int
    {
        if (!$categoriaId) {
            return null;
        }

        $categoria = CategoriaMerceologica::query()
            ->withoutGlobalScopes()
            ->withTrashed()
            ->find($categoriaId);

        return $this->resolveFromCategoria($categoria);
    }

    public function resolveFromCategoria(?CategoriaMerceologica $categoria): ?int
    {
        if (!$categoria) {
            return null;
        }

        return $this->extractMagazzinoLogico(
            (string) ($categoria->codice ?? ''),
            (string) ($categoria->nome ?? '')
        );
    }

    public function findCategoriaIdForSede(int $sedeId, int $magazzinoLogico): ?int
    {
        $categoria = CategoriaMerceologica::query()
            ->withoutGlobalScopes()
            ->withTrashed()
            ->where('sede_id', $sedeId)
            ->get()
            ->first(function (CategoriaMerceologica $candidate) use ($magazzinoLogico) {
                return $this->resolveFromCategoria($candidate) === $magazzinoLogico;
            });

        return $categoria?->id;
    }

    public function extractMagazzinoLogico(string $codice, string $nome = ''): ?int
    {
        $codice = trim($codice);
        $nome = trim($nome);

        if ($codice !== '' && ctype_digit($codice)) {
            return (int) $codice;
        }

        if ($codice !== '' && preg_match('/(?:MAG|MAGAZZINO)\s*([0-9]+)/i', $codice, $matches)) {
            return (int) $matches[1];
        }

        if ($nome !== '' && preg_match('/MAGAZZINO\s*([0-9]+)/i', $nome, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
