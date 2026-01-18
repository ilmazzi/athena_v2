# Guida per Risolvere i Rallentamenti

## Problema Identificato
PHP impiega 476ms solo per avviarsi, indicando che l'antivirus sta scansionando i file.

## Soluzioni (IN ORDINE DI PRIORITÀ)

### 1. ⚡ ESCLUSIONI ANTIVIRUS (CRITICO)
Aggiungi queste cartelle alle esclusioni di Windows Defender:

**Apri Windows Security → Protezione da virus e minacce → Gestisci impostazioni → Aggiungi esclusione**

Escludi:
- `C:\xampp\` (intera cartella XAMPP)
- `C:\xampp\htdocs\athena_v2\` (il progetto)
- `C:\xampp\php\` (PHP)
- `C:\xampp\mysql\` (MySQL)

### 2. ✅ ABILITARE OPCACHE
Modifica `C:\xampp\php\php.ini`:

```ini
[opcache]
zend_extension=php_opcache.dll
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.validate_timestamps=1
opcache.revalidate_freq=2
opcache.fast_shutdown=1
```

Riavvia Apache dopo le modifiche.

### 3. 🚀 OTTIMIZZAZIONI APPLICATE
✅ Log pulito (era 13MB!)
✅ LOG_LEVEL cambiato a `info`
✅ Rimossi Log::debug() dentro loop
✅ Cache statistiche nei componenti Livewire
✅ Autoloader Composer ottimizzato
✅ Configurazioni Laravel cachate
✅ SESSION_DRIVER cambiato da `database` a `file`
✅ DEBUGBAR disabilitato

### 4. 🔄 RIAVVIO SERVIZI
Dopo aver fatto le esclusioni e modificato php.ini:

```bash
# Riavvia Apache da XAMPP Control Panel
```

## Test Velocità
Dopo le modifiche, testa:

```bash
cd C:\xampp\htdocs\athena_v2
Measure-Command { php -r "echo 'test';" }
```

**Dovrebbe essere < 50ms** invece di 476ms!

## Alternative a XAMPP (se il problema persiste)
- **Laravel Herd** (consigliato per Windows): https://herd.laravel.com
- **Laragon**: Più veloce di XAMPP
- **Docker**: Più complesso ma performance migliori

## Verifica OPcache Funzionante
```bash
php -r "echo opcache_get_status()['opcache_enabled'] ? 'ENABLED' : 'DISABLED';"
```
