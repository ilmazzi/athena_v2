<?php
$path = 'c:/Users/dmazz/Herd/athena_v2/resources/views/livewire/articoli-table.blade.php';
$content = file_get_contents($path);
$content = str_replace("\r\n", "\n", $content);

$old = <<<'CODE'
                                @foreach($magazzini as $magazzino)
                                    <div class="form-check py-1">
                                        <input type="checkbox" 
                                               class="form-check-input" 
                                               id="magazzino_{{ $magazzino->id }}"
                                               wire:change="toggleMagazzino({{ $magazzino->id }})"
                                               @if(in_array($magazzino->id, $magazziniSelezionati)) checked @endif>
                                        <label class="form-check-label w-100" for="magazzino_{{ $magazzino->id }}">
                                            {{ $magazzino->nome }}
                                        </label>
                                    </div>
                                @endforeach
CODE;

$new = <<<'CODE'
                                @foreach($magazziniGruppati as $sedeNome => $magazziniSede)
                                    <div class="small fw-semibold text-uppercase text-muted mt-2 mb-1">{{ $sedeNome }}</div>
                                    @foreach($magazziniSede as $magazzino)
                                        <div class="form-check py-1 ps-2">
                                            <input type="checkbox" 
                                                   class="form-check-input" 
                                                   id="magazzino_{{ $magazzino->sede_id }}_{{ $magazzino->id }}"
                                                   wire:change="toggleMagazzino({{ $magazzino->id }})"
                                                   @if(in_array($magazzino->id, $magazziniSelezionati)) checked @endif>
                                            <label class="form-check-label w-100" for="magazzino_{{ $magazzino->sede_id }}_{{ $magazzino->id }}">
                                                {{ $magazzino->nome }}
                                                <span class="text-muted small">({{ $magazzino->codice_locale }})</span>
                                            </label>
                                        </div>
                                    @endforeach
                                @endforeach
CODE;

$content = str_replace($old, $new, $content);

file_put_contents($path, str_replace("\n", "\r\n", $content));
