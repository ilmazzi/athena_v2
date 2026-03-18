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

                        <form method="POST" action="{{ request()->fullUrl() }}" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <label for="foto" class="form-label">Seleziona immagine</label>
                                <input id="foto" name="foto" type="file" class="form-control" accept="image/*" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Carica foto</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
