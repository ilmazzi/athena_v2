<?php

namespace App\Http\Controllers;

use App\Models\Articolo;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;

class ArticoloFotoMobileController extends Controller
{
    public function __invoke(Request $request, Articolo $articolo)
    {
        if ($request->isMethod('post')) {
            try {
                $validated = $request->validate([
                    'foto' => 'required|image|max:10240', // 10MB
                ]);

                $vecchioPath = $articolo->foto_principale;
                $nuovoPath = $validated['foto']->store("articoli/{$articolo->id}", 'public');
                $this->ottimizzaImmagineSalvata($nuovoPath);

                $articolo->update([
                    'foto_principale' => $nuovoPath,
                ]);

                if (!empty($vecchioPath) && !str_starts_with($vecchioPath, 'http://') && !str_starts_with($vecchioPath, 'https://')) {
                    $normalized = ltrim(str_replace('\\', '/', $vecchioPath), '/');
                    if (str_starts_with($normalized, 'storage/')) {
                        $normalized = substr($normalized, 8);
                    }
                    if (Storage::disk('public')->exists($normalized)) {
                        Storage::disk('public')->delete($normalized);
                    }
                }

                return back()->with('success', "Foto caricata con successo per {$articolo->codice}");
            } catch (ValidationException $e) {
                throw $e;
            } catch (\Throwable $e) {
                return back()->with('error', 'Caricamento immagine fallito. Verifica il file e riprova.');
            }
        }

        return view('articoli.mobile-upload-foto', [
            'articolo' => $articolo,
        ]);
    }

    private function ottimizzaImmagineSalvata(string $relativePath): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            return;
        }

        $disk = Storage::disk('public');
        if (!$disk->exists($relativePath)) {
            return;
        }

        $absolutePath = $disk->path($relativePath);
        $imageInfo = @getimagesize($absolutePath);
        if ($imageInfo === false) {
            return;
        }

        [$width, $height, $imageType] = $imageInfo;
        $maxSide = 1920;
        $fileSize = @filesize($absolutePath) ?: 0;

        $shouldResize = $width > $maxSide || $height > $maxSide;
        $shouldCompress = $fileSize > (2 * 1024 * 1024);
        if (!$shouldResize && !$shouldCompress) {
            return;
        }

        $createFn = match ($imageType) {
            IMAGETYPE_JPEG => 'imagecreatefromjpeg',
            IMAGETYPE_PNG => 'imagecreatefrompng',
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? 'imagecreatefromwebp' : null,
            IMAGETYPE_GIF => 'imagecreatefromgif',
            default => null,
        };

        if (!$createFn || !function_exists($createFn)) {
            return;
        }

        $source = @$createFn($absolutePath);
        if (!$source) {
            return;
        }

        $targetWidth = $width;
        $targetHeight = $height;
        if ($shouldResize) {
            $ratio = min($maxSide / $width, $maxSide / $height);
            $targetWidth = max(1, (int) round($width * $ratio));
            $targetHeight = max(1, (int) round($height * $ratio));
        }

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        if (in_array($imageType, [IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
            imagealphablending($target, false);
            imagesavealpha($target, true);
            $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
            imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $transparent);
        }

        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        match ($imageType) {
            IMAGETYPE_JPEG => imagejpeg($target, $absolutePath, 82),
            IMAGETYPE_PNG => imagepng($target, $absolutePath, 7),
            IMAGETYPE_WEBP => function_exists('imagewebp') ? imagewebp($target, $absolutePath, 82) : null,
            IMAGETYPE_GIF => imagegif($target, $absolutePath),
            default => null,
        };

        imagedestroy($source);
        imagedestroy($target);
    }
}
