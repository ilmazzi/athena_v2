<?php
$path = 'c:/Users/dmazz/Herd/athena_v2/resources/views/livewire/inventario-monitor.blade.php';
$content = file_get_contents($path);
$content = str_replace("\r\n", "\n", $content);
$content = str_replace('Heatmap criticitÃ  per categoria', 'Heatmap criticitÃ  per magazzino', $content);
$content = str_replace("{{ \$articolo->categoriaMerceologica->nome ?? '-' }}", "{{ 'Magazzino ' . (\$articolo->magazzino_logico ?? '-') }}", $content);
$content = str_replace("{{ \$row->categoria ?? 'Senza categoria' }}", "{{ \$row->categoria ?? 'Senza magazzino' }}", $content);
file_put_contents($path, str_replace("\n", "\r\n", $content));
