from pathlib import Path
text = Path('resources/views/livewire/prodotti-finiti-table.blade.php').read_text(encoding='utf-8')
needle = "\n    </div>\n</div>\n\n@if($showSmontaModal"
if needle not in text:
    raise SystemExit('needle not found')
text = text.replace(needle, "\n    </div>\n\n@if($showSmontaModal", 1)

# Ensure closing root div after modal
if '@if($showSmontaModal' in text:
    modal_end = text.find('@endif', text.find('@if($showSmontaModal'))
    if modal_end == -1:
        raise SystemExit('modal endif not found')
    modal_end_line = text.find('\n', modal_end)
    if modal_end_line == -1:
        modal_end_line = len(text)
    # If the next non-empty line is not </div>, insert it
    rest = text[modal_end_line:].lstrip('\r\n')
    if not rest.startswith('</div>'):
        text = text[:modal_end_line+1] + '</div>\n' + text[modal_end_line+1:]

Path('resources/views/livewire/prodotti-finiti-table.blade.php').write_text(text, encoding='utf-8')
