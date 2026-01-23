from pathlib import Path
import re
path = Path('resources/views/livewire/prodotti-finiti-table.blade.php')
text = path.read_text(encoding='utf-8')
# remove root closing right before modal
text, n = re.subn(r'\n</div>\s*\n\s*@if\(\$showSmontaModal', '\n@if($showSmontaModal', text, count=1)
if n == 0:
    raise SystemExit('root closing before modal not found')
# ensure there's a closing root after modal
# insert </div> after the modal @endif
text, n2 = re.subn(r'@endif\s*', '@endif\n</div>\n', text, count=1)
if n2 == 0:
    raise SystemExit('modal endif not found')
path.write_text(text, encoding='utf-8')
