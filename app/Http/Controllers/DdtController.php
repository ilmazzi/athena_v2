<?php

namespace App\Http\Controllers;

use App\Models\Ddt;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DdtController extends Controller
{
    /**
     * Stampa DDT
     */
    public function stampa($id)
    {
        $ddt = Ddt::with([
            'dettagli.articolo.categoriaMerceologica',
            'dettagli.prodottoFinito.categoriaMerceologica',
            'sede',
            'fornitore',
            'creatoDa'
        ])->findOrFail($id);

        return view('ddt.stampa', [
            'ddt' => $ddt,
        ]);
    }

    public function showPdf(Ddt $ddt)
    {
        $path = $this->resolvePdfPath($ddt->allegato_path);
        if (!$path) {
            abort(404);
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return redirect()->to($path);
        }

        return response()->file($path);
    }

    private function resolvePdfPath(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $candidateStoragePaths = function (string $normalizedPath): array {
            $paths = [$normalizedPath];
            if (Str::contains($normalizedPath, 'storage/ddt_carico/')) {
                $paths[] = str_replace('storage/ddt_carico/', 'storage/DDT_Magazzino/', $normalizedPath);
            }

            return array_values(array_unique($paths));
        };

        if (Str::startsWith($path, ['http://', 'https://'])) {
            $parsedUrl = parse_url($path) ?: [];
            $normalizedUrlPath = ltrim($parsedUrl['path'] ?? '', '/');
            $urlHost = $parsedUrl['host'] ?? '';

            if (Str::contains($normalizedUrlPath, 'storage/')) {
                foreach ($candidateStoragePaths($normalizedUrlPath) as $candidatePath) {
                    $publicStoragePath = public_path($candidatePath);
                    if (file_exists($publicStoragePath)) {
                        return $publicStoragePath;
                    }

                    $relativeStoragePath = ltrim(Str::after($candidatePath, 'storage/'), '/');
                    $storagePublicPath = storage_path('app/public/' . $relativeStoragePath);
                    if (file_exists($storagePublicPath)) {
                        return $storagePublicPath;
                    }

                    $legacyRoot = env('LEGACY_STORAGE_ROOT');
                    if ($legacyRoot) {
                        $legacyPath = rtrim($legacyRoot, '\\/') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeStoragePath);
                        if (file_exists($legacyPath)) {
                            return $legacyPath;
                        }
                    }
                }
            }

            if (Str::contains($urlHost, 'athena.prod')) {
                return null;
            }

            return $path;
        }

        $normalizedPath = ltrim($path, '/');
        if (Str::startsWith($normalizedPath, 'storage/')) {
            foreach ($candidateStoragePaths($normalizedPath) as $candidatePath) {
                $publicStoragePath = public_path($candidatePath);
                if (file_exists($publicStoragePath)) {
                    return $publicStoragePath;
                }
            }
        }

        if (file_exists($path)) {
            return $path;
        }

        foreach ($candidateStoragePaths($normalizedPath) as $candidatePath) {
            $storagePath = storage_path('app/' . $candidatePath);
            if (file_exists($storagePath)) {
                return $storagePath;
            }

            $publicPath = storage_path('app/public/' . $candidatePath);
            if (file_exists($publicPath)) {
                return $publicPath;
            }

            if (Str::startsWith($candidatePath, 'storage/')) {
                $relativeStoragePath = ltrim(Str::after($candidatePath, 'storage/'), '/');
                $legacyRoot = env('LEGACY_STORAGE_ROOT');
                if ($legacyRoot) {
                    $legacyPath = rtrim($legacyRoot, '\\/') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeStoragePath);
                    if (file_exists($legacyPath)) {
                        return $legacyPath;
                    }
                }
            }
        }

        return null;
    }
}