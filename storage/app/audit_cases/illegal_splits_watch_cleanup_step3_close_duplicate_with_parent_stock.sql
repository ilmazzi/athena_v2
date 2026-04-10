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
    '2-64388-2','2-64403-2','2-64405-2','2-64406-2',
    '2-64415-2','2-64416-2','20-24791-2','20-24806-2'
)
ORDER BY child.codice;

START TRANSACTION;

UPDATE giacenze gc
JOIN articoli child ON child.id = gc.articolo_id
SET
    gc.quantita_residua = 0,
    gc.updated_at = NOW()
WHERE child.codice IN (
    '2-64388-2','2-64403-2','2-64405-2','2-64406-2',
    '2-64415-2','2-64416-2','20-24791-2','20-24806-2'
);

UPDATE articoli
SET deleted_at = NOW(), updated_at = NOW()
WHERE codice IN (
    '2-64388-2','2-64403-2','2-64405-2','2-64406-2',
    '2-64415-2','2-64416-2','20-24791-2','20-24806-2'
)
AND deleted_at IS NULL;

COMMIT;
