Illegal split cleanup plan for watch warehouses (`2`, `3`, `20`)

Rules applied:
- split codes are illegal for prefixes `2`, `3`, `20`
- the parent must exist and remain the canonical article
- each watch must end with a single active article carrying the stock

Groups:

1. `figlio_non_giacente`
- child has `quantita_residua = 0`
- action: soft-delete child only

Codes:
- `2-64410-2`
- `2-64417-2`
- `3-14284-2`
- `3-14285-2`
- `3-14286-2`
- `3-14287-2`
- `3-14288-2`
- `3-14289-2`
- `20-24711-2`
- `20-24712-2`
- `20-24713-2`
- `20-24714-2`
- `20-24715-2`
- `20-24716-2`
- `20-24717-2`
- `20-24718-2`
- `20-24790-2`
- `20-24792-2`
- `20-24793-2`
- `20-24794-2`
- `20-24795-2`
- `20-24796-2`
- `20-24797-2`
- `20-24798-2`
- `20-24799-2`
- `20-24800-2`
- `20-24801-2`
- `20-24802-2`
- `20-24803-2`
- `20-24804-2`
- `20-24805-2`

2. `duplicato_con_padre_scarico`
- child has `quantita_residua = 1`
- parent has `quantita_residua = 0`
- action: restore stock on parent, zero child residual, soft-delete child

Codes:
- `2-64402-2`
- `2-64404-2`
- `2-64407-2`
- `2-64408-2`
- `2-64409-2`
- `2-64411-2`
- `2-64412-2`
- `2-64413-2`
- `2-64414-2`

3. `duplicato_con_padre_giacente`
- both child and parent have `quantita_residua = 1`
- action: keep parent, zero child residual, soft-delete child

Codes:
- `2-64388-2`
- `2-64403-2`
- `2-64405-2`
- `2-64406-2`
- `2-64415-2`
- `2-64416-2`
- `20-24791-2`
- `20-24806-2`

4. `mapping_anomalo`
- `20-24834`
- special case: `codice_base = 5-23199`
- action: manual analysis only, no bulk update
