<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0">
                <iconify-icon icon="solar:printer-bold-duotone" class="me-2"></iconify-icon>
                Stampa NC
            </h4>
            <small class="text-muted">Cartellini non collegati ad articoli, con prezzo oppure prezzo + carati.</small>
        </div>
    </div>

    <div class="card-body">
        @if (session()->has('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if (session()->has('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Tipo Cartellino</label>
                <select class="form-select" wire:model.live="tipoNc">
                    <option value="prezzo_carati">Prezzo + Carati</option>
                    <option value="solo_prezzo">Solo Prezzo</option>
                </select>
                @error('tipoNc') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-4">
                <label class="form-label fw-semibold">Formato Prezzo</label>
                <select class="form-select" wire:model.defer="formatoPrezzo">
                    <option value="codificato">Codificato</option>
                    <option value="euro">Euro</option>
                </select>
                @error('formatoPrezzo') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-4">
                <label class="form-label fw-semibold">Quantità Copie</label>
                <input type="number" min="1" max="200" class="form-control" wire:model.defer="quantita">
                @error('quantita') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
                <label class="form-label fw-semibold">Prezzo</label>
                <input type="text"
                       class="form-control"
                       wire:model.live="prezzo"
                       autocapitalize="characters"
                       placeholder="Es. 345X3P3 oppure 1.250,00">
                <small class="text-muted">Accetta sia prezzo codificato sia prezzo in euro.</small>
                @error('prezzo') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
                <label class="form-label fw-semibold">Carati</label>
                <input type="text"
                       class="form-control"
                       wire:model.defer="carati"
                       placeholder="Es. 750"
                       @if($tipoNc === 'solo_prezzo') disabled @endif>
                <small class="text-muted">Usato solo per il cartellino prezzo + carati.</small>
                @error('carati') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-12">
                <label class="form-label fw-semibold">Stampante</label>
                <select class="form-select" wire:model.defer="stampanteId">
                    <option value="">Seleziona stampante...</option>
                    @foreach($stampantiDisponibili as $stampante)
                        <option value="{{ $stampante['id'] }}">
                            {{ $stampante['nome'] }} ({{ $stampante['modello'] }}) - {{ $stampante['ip_address'] }}
                        </option>
                    @endforeach
                </select>
                @error('stampanteId') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="mt-4 d-flex justify-content-end">
            <button type="button" class="btn btn-primary" wire:click="stampa">
                <iconify-icon icon="solar:printer-bold" class="me-1"></iconify-icon>
                Stampa NC
            </button>
        </div>
    </div>
</div>
