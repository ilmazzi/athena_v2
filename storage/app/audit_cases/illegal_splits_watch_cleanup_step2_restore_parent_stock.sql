SELECT
    child.codice AS child_code,
    parent.codice AS parent_code,
    gc.quantita AS child_quantita,
    gc.quantita_residua AS child_residua,
    gp.quantita AS parent_quantita,
    gp.quantita_residua AS parent_residua
FROM articoli child
JOIN articoli parent ON parent.codice = SUBSTRING_INDEX(child.codice, '-', 2)
LEFT JOIN giacenze gc ON gc.articolo_id = child.id AND gc.sede_id = child.sede_id
LEFT JOIN giacenze gp ON gp.articolo_id = parent.id AND gp.sede_id = parent.sede_id
WHERE child.codice IN (
    '2-64402-2','2-64404-2','2-64407-2','2-64408-2','2-64409-2',
    '2-64411-2','2-64412-2','2-64413-2','2-64414-2'
)
ORDER BY child.codice;

START TRANSACTION;

UPDATE giacenze gp
JOIN articoli parent ON parent.id = gp.articolo_id
JOIN articoli child ON child.codice = CONCAT(parent.codice, '-2')
SET
    gp.quantita = GREATEST(COALESCE(gp.quantita, 0), 1),
    gp.quantita_iniziale = GREATEST(COALESCE(gp.quantita_iniziale, 0), 1),
    gp.quantita_residua = 1,
    gp.updated_at = NOW()
WHERE child.codice IN (
    '2-64402-2','2-64404-2','2-64407-2','2-64408-2','2-64409-2',
    '2-64411-2','2-64412-2','2-64413-2','2-64414-2'
);

UPDATE giacenze gc
JOIN articoli child ON child.id = gc.articolo_id
SET
    gc.quantita_residua = 0,
    gc.updated_at = NOW()
WHERE child.codice IN (
    '2-64402-2','2-64404-2','2-64407-2','2-64408-2','2-64409-2',
    '2-64411-2','2-64412-2','2-64413-2','2-64414-2'
);

UPDATE articoli
SET deleted_at = NOW(), updated_at = NOW()
WHERE codice IN (
    '2-64402-2','2-64404-2','2-64407-2','2-64408-2','2-64409-2',
    '2-64411-2','2-64412-2','2-64413-2','2-64414-2'
)
AND deleted_at IS NULL;

COMMIT;
