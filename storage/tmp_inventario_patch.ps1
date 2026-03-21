$path = '"'"'c:\Users\dmazz\Herd\athena_v2\app\Http\Livewire\InventarioMonitor.php'"'"'
$content = Get-Content -Raw $path
$content = $content -replace '"'"'(?s)    public function mount\(\$sessione = null\)\r?\n    \{.*?\r?\n    \}'"'"', @"
    public function mount($sessione = null)
    {
        $this->sessioneId = $sessione;
        $this->sedi = Sede::all();
        $this->categorie = collect($this->buildMagazzinoOptions());
        
        if ($this->sessioneId) {
            $this->caricaSessione();
        }
    }
"@
Set-Content -Path $path -Value $content -Encoding UTF8
