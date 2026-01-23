<div>
    <!-- Success Message -->

@if($showSmontaModal && $prodottoDaSmontare)
    <div class="modal fade show" style="display: block;" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <iconify-icon icon="solar:undo-left-bold" class="text-danger me-2"></iconify-icon>
                        Smonta prodotto finito
                    </h5>
                    <button type="button" class="btn-close" wire:click="chiudiSmontaModal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <iconify-icon icon="solar:danger-triangle-bold" class="me-2"></iconify-icon>
                        Questa operazione annulla l'assemblaggio e ripristina le giacenze dei componenti.
                    </div>
                    <div class="mb-2">
                        <strong>Codice:</strong> {{ $prodottoDaSmontare->codice }}
                    </div>
                    <div class="mb-2">
                        <strong>Descrizione:</strong> {{ $prodottoDaSmontare->descrizione }}
                    </div>
                    <div class="mb-2">
                        <strong>Componenti:</strong> {{ $prodottoDaSmontare->componentiArticoli->count() }}
                    </div>
                    <div class="text-muted small">
                        Il prodotto finito verra marcato come annullato e non sara piu visibile in elenco.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" wire:click="chiudiSmontaModal">Annulla</button>
                    <button type="button" class="btn btn-danger" wire:click="confermaSmonta">
                        <iconify-icon icon="solar:undo-left-bold" class="me-1"></iconify-icon>
                        Smonta
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
@endif
</div>

