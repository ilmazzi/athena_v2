SELECT
    child.id AS child_id,
    child.codice AS child_code,
    child.codice_base AS child_codice_base,
    child.descrizione AS child_descrizione,
    child.numero_documento_carico AS child_doc,
    child.data_carico AS child_data,
    gc.quantita AS child_quantita,
    gc.quantita_residua AS child_residua,
    parent.id AS parent_id,
    parent.codice AS parent_code,
    parent.descrizione AS parent_descrizione,
    parent.numero_documento_carico AS parent_doc,
    parent.data_carico AS parent_data,
    gp.quantita AS parent_quantita,
    gp.quantita_residua AS parent_residua
FROM articoli child
LEFT JOIN giacenze gc ON gc.articolo_id = child.id AND gc.sede_id = child.sede_id
LEFT JOIN articoli parent ON parent.codice = child.codice_base
LEFT JOIN giacenze gp ON gp.articolo_id = parent.id AND gp.sede_id = parent.sede_id
WHERE child.codice = '20-24834';

-- Non eseguire update automatici qui.
-- Caso anomalo: codice 20 con codice_base del magazzino 5.
-- Da decidere dopo verifica manuale contro MSSQL e documento di carico.
