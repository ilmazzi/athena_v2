# 📊 Piano Migrazione Database - Gennaio 2026

**Data:** 15 Gennaio 2026  
**Database Source:** athena_dev (MSSQL Server)  
**Database Target:** athena_v2 (MySQL)

---

## 🔍 ANALISI STATO ATTUALE

### Database MSSQL (athena_dev):
```
✅ 15,484 articoli (elenco_articoli_magazzino)
✅ 25,559 DDT testate
✅   749 fornitori
✅    22 categorie/magazzini
✅   279 prodotti finiti
```

### Database MySQL (athena_v2 - STATO CORRENTE):
```
✅ 14,555 articoli (già importati)
✅  3,685 DDT
✅    449 fornitori
✅     24 categorie
✅    272 prodotti finiti
✅    552 componenti
✅      9,148 articoli con giacenza > 0
✅      5,405 articoli con giacenza = 0
```

---

## 📈 DIFFERENZE E GAP DA COLMARE

### 1. **Articoli**
- **Gap:** ~929 articoli (15,484 - 14,555)
- **Azione:** Identificare articoli nuovi e aggiungerli
- **Verifica:** Controllare se sono articoli realmente nuovi o duplicati

### 2. **DDT**
- **Gap:** ~21,874 DDT (25,559 - 3,685)
- **Nota:** Gap molto grande, probabilmente ci sono duplicati in MSSQL
- **Azione:** 
  - Prima pulire duplicati in MSSQL
  - Poi importare solo DDT validi
  - Verificare integrità con articoli

### 3. **Fornitori**
- **Gap:** 300 fornitori (749 - 449)
- **Azione:** Importare fornitori mancanti
- **Verifica:** Controllare se sono fornitori attivi o obsoleti

### 4. **Prodotti Finiti**
- **Gap:** 7 PF (279 - 272)
- **Azione:** Importare prodotti finiti mancanti + componenti

### 5. **Categorie**
- **Gap:** -2 categorie (MySQL ha 24, MSSQL ha 22)
- **Nota:** Probabilmente aggiunte manualmente in MySQL
- **Azione:** Verificare mapping categorie

---

## 🎯 STRATEGIA DI MIGRAZIONE

### Opzione A: MIGRAZIONE INCREMENTALE (CONSIGLIATA) ⭐
**Pro:**
- ✅ Mantiene dati esistenti
- ✅ Aggiunge solo ciò che manca
- ✅ Più sicuro
- ✅ Rollback facile

**Contro:**
- ⚠️ Richiede identificazione precisa dei gap
- ⚠️ Più lenta

**Steps:**
1. Identifica articoli nuovi (non in MySQL)
2. Importa articoli nuovi + giacenze
3. Importa DDT nuovi
4. Importa fornitori mancanti
5. Importa prodotti finiti mancanti
6. Verifica integrità relazioni

### Opzione B: MIGRAZIONE COMPLETA (RESET TOTALE)
**Pro:**
- ✅ Database pulito
- ✅ Dati consistenti
- ✅ Più semplice

**Contro:**
- ❌ Perde dati esistenti in MySQL
- ❌ Reset completo sistema
- ❌ Richiede backup completo

**Steps:**
1. Backup completo MySQL
2. Truncate tutte le tabelle
3. Migrazione completa da MSSQL
4. Pulizia duplicati
5. Verifica finale

---

## 🛠️ PROBLEMI NOTI DA RISOLVERE

### 1. **Duplicati DDT in MSSQL**
```sql
-- Query per identificare duplicati
SELECT numero_documento, COUNT(*) as count
FROM mag_ddt_articoli_testate
GROUP BY numero_documento
HAVING COUNT(*) > 1
ORDER BY count DESC
```

**Soluzione:** Comando `documenti:pulisci-duplicati` già esistente

### 2. **Articoli con ID non sequenziali**
- MSSQL ha ID sparse (8659, 8660, 8661...)
- MySQL potrebbe avere conflitti di ID

**Soluzione:** 
- Usare `IGNORE` su insert
- O generare nuovi ID in MySQL

### 3. **Fornitori obsoleti**
- Molti fornitori in MSSQL potrebbero essere inattivi

**Soluzione:** Filtrare `attivo = 1` durante import

### 4. **Giacenze inconsistenti**
- Alcune giacenze hanno quantità residua < 0
- Alcune giacenze duplicate per stesso articolo

**Soluzione:** Validazione post-import

---

## 📋 CHECKLIST PRE-MIGRAZIONE

### Database Source (MSSQL):
- [ ] Connessione attiva e funzionante
- [ ] Vista `elenco_articoli_magazzino` disponibile
- [ ] Verifica integrità dati (no NULL critici)
- [ ] Backup database MSSQL

### Database Target (MySQL):
- [ ] Backup completo database attuale
- [ ] Spazio disco sufficiente
- [ ] Indici database ottimizzati
- [ ] Log abilitati per debug

### Comandi Esistenti:
- [x] `MigrateFromProduction` - Migrazione completa
- [x] `documenti:pulisci-duplicati` - Pulizia DDT
- [x] `documenti:ricalcola-conteggi` - Ricalcolo conteggi
- [x] `pf:migra-v2` - Migrazione prodotti finiti
- [ ] Comando incrementale articoli (DA CREARE)
- [ ] Comando sync fornitori (DA CREARE)

---

## 🚀 RACCOMANDAZIONE

**PROCEDURA CONSIGLIATA:**

### FASE 1: ANALISI DETTAGLIATA (1-2 ore)
```bash
# 1. Identifica articoli nuovi
php artisan analizza:gap-articoli

# 2. Identifica fornitori mancanti
php artisan analizza:gap-fornitori

# 3. Analizza stato DDT
php artisan analizza:ddt-duplicati

# 4. Verifica prodotti finiti
php artisan analizza:gap-prodotti-finiti
```

### FASE 2: BACKUP (15 minuti)
```bash
# Backup MySQL completo
mysqldump -u root athena_v2 > backup_athena_v2_$(date +%Y%m%d).sql

# Backup MSSQL (tramite SQL Server Management Studio)
```

### FASE 3: MIGRAZIONE INCREMENTALE (2-3 ore)
```bash
# 1. Importa articoli nuovi
php artisan importa:articoli-nuovi --dry-run
php artisan importa:articoli-nuovi

# 2. Importa fornitori mancanti
php artisan importa:fornitori-mancanti

# 3. Importa DDT nuovi (dopo pulizia duplicati)
php artisan importa:ddt-nuovi

# 4. Importa prodotti finiti mancanti
php artisan importa:pf-mancanti

# 5. Ricalcola tutto
php artisan documenti:ricalcola-conteggi
```

### FASE 4: VERIFICA (30 minuti)
```bash
# Verifica integrità
php artisan verifica:integrità-database

# Test relazioni
php artisan test:relazioni

# Verifica giacenze
php artisan verifica:giacenze-consistenti
```

---

## ⚠️ COSA DEVO FARE ORA?

**PROSSIMI PASSI:**

1. **Tu decidi:**
   - [ ] Migrazione INCREMENTALE (solo nuovi dati)
   - [ ] Migrazione COMPLETA (reset totale)

2. **Se INCREMENTALE:**
   - Creo comandi di analisi gap
   - Creo comandi import incrementale
   - Test su subset dati
   - Import completo

3. **Se COMPLETA:**
   - Backup tutto
   - Truncate MySQL
   - Esegui `MigrateFromProduction` esistente
   - Pulizia duplicati
   - Verifica finale

---

## 📞 DOMANDE PER TE:

1. **Hai bisogno di mantenere i dati attuali in MySQL?**
   - Se SI → Migrazione incrementale
   - Se NO → Migrazione completa

2. **I DDT attuali in MySQL (3,685) sono corretti?**
   - Se SI → Importa solo nuovi DDT
   - Se NO → Reimporta tutti i DDT

3. **I prodotti finiti attuali (272) sono corretti?**
   - Se SI → Importa solo i 7 mancanti
   - Se NO → Reimporta tutti

4. **Vuoi pulire i duplicati in MSSQL prima di importare?**
   - Consigliato: SI

**DIMMI QUALE APPROCCIO PREFERISCI E PROCEDIAMO!** 🚀
