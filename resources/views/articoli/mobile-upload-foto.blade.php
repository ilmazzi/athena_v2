<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Carica foto articolo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <strong>Carica foto articolo</strong>
                    </div>
                    <div class="card-body">
                        @if(session('success'))
                            <div class="alert alert-success">{{ session('success') }}</div>
                        @endif

                        @if(session('error'))
                            <div class="alert alert-danger">{{ session('error') }}</div>
                        @endif

                        @if($errors->any())
                            <div class="alert alert-danger mb-3">
                                {{ $errors->first() }}
                            </div>
                        @endif

                        <p class="mb-1"><strong>Codice:</strong> {{ $articolo->codice }}</p>
                        <p class="mb-3"><strong>Descrizione:</strong> {{ $articolo->descrizione }}</p>

                        @php
                            $fotoUrl = null;
                            if (!empty($articolo->foto_principale)) {
                                if (str_starts_with($articolo->foto_principale, 'http://') || str_starts_with($articolo->foto_principale, 'https://')) {
                                    $fotoUrl = $articolo->foto_principale;
                                } elseif (str_starts_with($articolo->foto_principale, '/storage/') || str_starts_with($articolo->foto_principale, 'storage/')) {
                                    $fotoUrl = asset(ltrim($articolo->foto_principale, '/'));
                                } else {
                                    $fotoUrl = asset('storage/' . ltrim($articolo->foto_principale, '/'));
                                }
                            }
                        @endphp

                        <div class="mb-3 text-center">
                            @if($fotoUrl)
                                <img src="{{ $fotoUrl }}" alt="Foto articolo" class="img-fluid rounded border" style="max-height: 260px;">
                            @else
                                <div class="text-muted border rounded py-4">Nessuna foto attuale</div>
                            @endif
                        </div>

                        <form id="mobileFotoUploadForm" method="POST" action="{{ request()->fullUrl() }}" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <label for="foto" class="form-label">Seleziona immagine</label>
                                <input id="foto" name="foto" type="file" class="form-control" accept="image/*" required>
                                <div class="form-text">Ridimensionamento automatico attivo (max 1920px).</div>
                            </div>
                            <div id="mobileFotoUploadFeedback" class="alert d-none py-2 px-3" role="alert"></div>
                            <div id="mobileFotoUploadBusy" class="small text-muted d-none mb-2">
                                Ottimizzo immagine, attendi...
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Carica foto</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        (function () {
            const input = document.getElementById('foto');
            const form = document.getElementById('mobileFotoUploadForm');
            const feedbackEl = document.getElementById('mobileFotoUploadFeedback');
            const busyEl = document.getElementById('mobileFotoUploadBusy');

            if (!input || !form || !feedbackEl || !busyEl) {
                return;
            }

            const maxSide = 1920;
            const targetBytes = 2 * 1024 * 1024;

            const showFeedback = (message, type = 'danger') => {
                feedbackEl.className = `alert alert-${type} py-2 px-3`;
                feedbackEl.textContent = message;
            };

            const hideFeedback = () => {
                feedbackEl.className = 'alert d-none py-2 px-3';
                feedbackEl.textContent = '';
            };

            const toggleBusy = (isBusy) => {
                busyEl.classList.toggle('d-none', !isBusy);
            };

            const readImageFromFile = (file) => new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = () => {
                    const img = new Image();
                    img.onload = () => resolve(img);
                    img.onerror = reject;
                    img.src = reader.result;
                };
                reader.onerror = reject;
                reader.readAsDataURL(file);
            });

            const autoResizeFile = async (file) => {
                if (!file.type.startsWith('image/')) {
                    return file;
                }

                const image = await readImageFromFile(file);
                const ratio = Math.min(1, maxSide / Math.max(image.width, image.height));
                const width = Math.max(1, Math.round(image.width * ratio));
                const height = Math.max(1, Math.round(image.height * ratio));

                if (ratio === 1 && file.size <= targetBytes) {
                    return file;
                }

                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                if (!ctx) {
                    return file;
                }

                ctx.drawImage(image, 0, 0, width, height);
                const preferJpeg = file.type === 'image/jpeg' || file.type === 'image/jpg';
                const mime = preferJpeg ? 'image/jpeg' : 'image/webp';

                let quality = 0.86;
                let blob = await new Promise((resolve) => canvas.toBlob(resolve, mime, quality));
                while (blob && blob.size > targetBytes && quality > 0.55) {
                    quality -= 0.08;
                    blob = await new Promise((resolve) => canvas.toBlob(resolve, mime, quality));
                }

                if (!blob || blob.size >= file.size) {
                    return file;
                }

                const name = file.name.replace(/\.[^.]+$/, '') + (mime === 'image/jpeg' ? '.jpg' : '.webp');
                return new File([blob], name, { type: mime, lastModified: Date.now() });
            };

            const replaceInputFile = (newFile) => {
                const dt = new DataTransfer();
                dt.items.add(newFile);
                input.files = dt.files;
            };

            input.addEventListener('change', async () => {
                hideFeedback();
                const file = input.files?.[0];
                if (!file) {
                    return;
                }

                try {
                    toggleBusy(true);
                    const resized = await autoResizeFile(file);
                    replaceInputFile(resized);
                } catch (e) {
                    showFeedback('Errore durante l\'ottimizzazione dell\'immagine. Riprova.');
                } finally {
                    toggleBusy(false);
                }
            });

            form.addEventListener('submit', async (event) => {
                const file = input.files?.[0];
                if (!file) {
                    return;
                }

                event.preventDefault();
                hideFeedback();

                try {
                    toggleBusy(true);
                    const resized = await autoResizeFile(file);
                    replaceInputFile(resized);
                    form.submit();
                } catch (e) {
                    toggleBusy(false);
                    showFeedback('Upload non riuscito. Controlla il file e riprova.');
                }
            });
        })();
    </script>
</body>
</html>
