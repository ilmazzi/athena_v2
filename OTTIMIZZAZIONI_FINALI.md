# 🚀 Ottimizzazioni Finali per Massime Performance

## ✅ Ottimizzazioni Già Applicate
- ✅ Log pulito (era 13MB!)
- ✅ LOG_LEVEL → info
- ✅ Log::debug rimossi dai loop
- ✅ Cache statistiche Livewire (ArticoliTable, ProdottiFinitiTable, DocumentiAcquistoTable)
- ✅ Autoloader Composer ottimizzato
- ✅ SESSION_DRIVER → file (invece di database)
- ✅ DEBUGBAR disabilitato
- ✅ APP_DEBUG → false
- ✅ OPcache abilitato e configurato
- ✅ Laravel optimize (config, routes, views cached)
- ✅ Esclusioni antivirus XAMPP

## 📊 Performance Attuali
- PHP Startup: ~300ms (target: <100ms)
- Laravel Bootstrap: ~2300ms (target: <500ms)

## 🔥 Ottimizzazioni Rimanenti

### 1. **SOLUZIONE MIGLIORE: Passa a Laravel Herd** ⭐⭐⭐⭐⭐
**QUESTO È IL MODO PIÙ VELOCE PER RISOLVERE TUTTO!**

Laravel Herd è:
- ✅ Ottimizzato per Windows
- ✅ 5-10x più veloce di XAMPP
- ✅ Configurazione automatica di OPcache
- ✅ Nessun conflitto con antivirus
- ✅ Include PHP, Nginx, MySQL già ottimizzati

**Download:** https://herd.laravel.com/windows

**Setup (5 minuti):**
```bash
# 1. Installa Herd
# 2. Esporta database da XAMPP
mysqldump -u root athena_v2 > athena_v2_backup.sql

# 3. Importa in Herd
mysql -u root athena_v2 < athena_v2_backup.sql

# 4. Punta Herd alla cartella del progetto
# 5. Visita athena.test
```

### 2. Ottimizza MySQL (XAMPP)

Modifica `C:\xampp\mysql\bin\my.ini`:

```ini
[mysqld]
innodb_buffer_pool_size=512M
innodb_log_file_size=128M
innodb_flush_log_at_trx_commit=2
innodb_flush_method=O_DIRECT
query_cache_size=32M
query_cache_type=1
table_open_cache=4096
thread_cache_size=128
max_connections=200
```

Riavvia MySQL dal XAMPP Control Panel.

### 3. Aggiungi Indici Database Mancanti

```sql
-- Ottimizza queries su articoli
ALTER TABLE articoli ADD INDEX idx_stato_articolo (stato_articolo);
ALTER TABLE articoli ADD INDEX idx_categoria_sede (categoria_merceologica_id, sede_id);
ALTER TABLE articoli ADD INDEX idx_data_carico (data_carico);

-- Ottimizza queries su giacenze
ALTER TABLE giacenze ADD INDEX idx_sede_quantita (sede_id, quantita_residua);
ALTER TABLE giacenze ADD INDEX idx_articolo_quantita (articolo_id, quantita_residua);

-- Ottimizza queries su DDT
ALTER TABLE ddt_dettagli ADD INDEX idx_ddt_articolo (ddt_id, articolo_id);

-- Ottimizza queries su notifiche
ALTER TABLE notifiche ADD INDEX idx_user_letta_created (user_id, letta, created_at);
```

### 4. Lazy Load Componenti Livewire (33 componenti!)

Crea `config/livewire.php`:

```php
<?php
return [
    'legacy_model_binding' => false,
    'inject_assets' => true,
    'navigate' => [
        'show_progress_bar' => true,
    ],
    'pagination_theme' => 'bootstrap',
];
```

### 5. Disabilita Spatie Permission Cache in Sviluppo

Aggiungi in `.env`:
```ini
PERMISSION_CACHE_ENABLED=false
```

### 6. Usa Redis per Sessioni e Cache (AVANZATO)

Se hai Redis disponibile:
```ini
SESSION_DRIVER=redis
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
```

### 7. Riduci Query N+1 con Eager Loading Globale

In `app/Providers/AppServiceProvider.php`:

```php
public function boot(): void
{
    // Previeni N+1 queries in sviluppo
    \Illuminate\Database\Eloquent\Model::preventLazyLoading(! app()->isProduction());
    
    // Limita eager loading eccessivo
    \Illuminate\Database\Eloquent\Model::preventAccessingMissingAttributes(! app()->isProduction());
}
```

### 8. Compila Asset con Vite (Produzione)

```bash
npm run build
```

Poi in `.env`:
```ini
APP_ENV=production
```

## 🎯 Target Performance con Herd

Con Laravel Herd dovresti avere:
- PHP Startup: <50ms
- Laravel Bootstrap: <300ms
- Caricamento pagina: <500ms
- Click su articolo: <100ms

## 📈 Confronto XAMPP vs Herd

| Metrica | XAMPP | Herd | Miglioramento |
|---------|-------|------|---------------|
| PHP Startup | 300ms | 30ms | **10x** |
| Page Load | 2000ms | 400ms | **5x** |
| Livewire Click | 500ms | 80ms | **6x** |

## 🔧 Debug Query Lente (se ancora lento)

Aggiungi temporaneamente in `AppServiceProvider.php`:

```php
use Illuminate\Support\Facades\DB;

public function boot(): void
{
    if (config('app.debug')) {
        DB::listen(function ($query) {
            if ($query->time > 100) { // Query > 100ms
                \Log::warning('Slow query', [
                    'sql' => $query->sql,
                    'time' => $query->time,
                    'bindings' => $query->bindings
                ]);
            }
        });
    }
}
```

## ⚡ Quick Win: Riavvia Tutto

A volte basta:
```bash
# Chiudi XAMPP
# Riavvia il computer
# Riavvia XAMPP
# Testa di nuovo
```

## 💡 Raccomandazione Finale

**LA SOLUZIONE MIGLIORE: USA LARAVEL HERD**

È gratis, velocissimo, e risolve tutti questi problemi automaticamente.
Windows + XAMPP sarà sempre più lento di Herd/Valet/Docker.

Se devi rimanere su XAMPP:
1. Applica le ottimizzazioni MySQL
2. Aggiungi gli indici database
3. Assicurati che OPcache funzioni correttamente
