<?php

namespace App\Services;

use App\Models\CategoriaMerceologica;
use App\Models\Sede;

class MagazzinoLogicoService
{
    private const SEDE_CODE_CAVOUR = 'CAV';
    private const SEDE_CODE_JOLLY = 'JOL';
    private const SEDE_CODE_MAZZINI = 'MAZ';
    private const SEDE_CODE_MONASTERO = 'MON';

    public function getSedePrincipaleId(): ?int
    {
        return Sede::query()
            ->where('attivo', true)
            ->orderBy('id')
            ->value('id');
    }

    public function getSedePrincipaleIdBySocieta(int $societaId): ?int
    {
        return Sede::query()
            ->where('attivo', true)
            ->where('societa_id', $societaId)
            ->orderBy('id')
            ->value('id');
    }

    public function resolveSedeMagazziniIdForCarico(int $sedeId): ?int
    {
        $sedeCarico = Sede::query()
            ->where('attivo', true)
            ->find($sedeId);

        if (!$sedeCarico) {
            return null;
        }

        $codice = strtoupper(trim((string) $sedeCarico->codice));

        // Jolly usa sempre i propri magazzini dedicati.
        if ($codice === self::SEDE_CODE_JOLLY) {
            return (int) $sedeCarico->id;
        }

        // Mazzini e Monastero condividono i magazzini della sede Cavour/Lecco.
        if (in_array($codice, [self::SEDE_CODE_MAZZINI, self::SEDE_CODE_MONASTERO], true)) {
            $cavourId = Sede::query()
                ->where('attivo', true)
                ->where('societa_id', $sedeCarico->societa_id)
                ->where('codice', self::SEDE_CODE_CAVOUR)
                ->value('id');

            if ($cavourId) {
                return (int) $cavourId;
            }
        }

        // Fallback: sede principale della società (es. Roma -> Roma).
        return $this->getSedePrincipaleIdBySocieta((int) $sedeCarico->societa_id);
    }

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

    public function findCategoriaIdForSedePrincipale(int $magazzinoLogico): ?int
    {
        $sedePrincipaleId = $this->getSedePrincipaleId();
        if (!$sedePrincipaleId) {
            return null;
        }

        return $this->findCategoriaIdForSede($sedePrincipaleId, $magazzinoLogico);
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

    public function getLabelForCategoria(?CategoriaMerceologica $categoria): ?string
    {
        $magazzinoLogico = $this->resolveFromCategoria($categoria);
        if (!$magazzinoLogico) {
            return null;
        }

        $nome = trim((string) ($categoria->nome ?? ''));
        if ($nome !== '' && !preg_match('/^MAGAZZINO\s*[0-9]+$/i', $nome)) {
            return $nome . ' (Magazzino ' . $magazzinoLogico . ')';
        }

        return 'Magazzino ' . $magazzinoLogico;
    }

    public function getLabelForMagazzinoLogico(int $magazzinoLogico, ?int $sedeId = null): string
    {
        $categoria = CategoriaMerceologica::query()
            ->withoutGlobalScopes()
            ->withTrashed()
            ->when($sedeId, fn ($query) => $query->where('sede_id', $sedeId))
            ->get()
            ->first(fn (CategoriaMerceologica $candidate) => $this->resolveFromCategoria($candidate) === $magazzinoLogico);

        return $this->getLabelForCategoria($categoria) ?? ('Magazzino ' . $magazzinoLogico);
    }
}
