# fifa-auto.ps1 - Auto-extract FIFA official match reports + sync to production.
# ASCII-only (safe for Task Scheduler). Runs: discover reports -> download PDFs ->
# pdftotext -table -> build assets/fifa/*.json -> sync to OSS repo (git push).
# Requires the PC to be on. Hostinger has no pdftotext, so extraction must run here.
$ErrorActionPreference = 'Continue'
$php  = 'C:/xampp/php/php.exe'
$pt   = (Get-Command pdftotext.exe -ErrorAction SilentlyContinue).Source
if (-not $pt) { $pt = 'C:/Program Files/Git/mingw64/bin/pdftotext.exe' }
$proj = 'C:/xampp/htdocs/worldcup2026'
$sync = 'C:/xampp/htdocs/sync-worldcup-to-oss.ps1'
if (-not (Test-Path $pt)) { Write-Output 'pdftotext missing'; exit 1 }

$txtDir = Join-Path $proj 'tools/_fifatxt'
New-Item -ItemType Directory -Force -Path $txtDir | Out-Null

$map = & $php "$proj/tools/fifa-build.php" map | ConvertFrom-Json
foreach ($n in $map.PSObject.Properties.Name) {
    $url = $map.$n
    $pdf = Join-Path $txtDir "$n.pdf"
    $txt = Join-Path $txtDir "$n.txt"
    try {
        Invoke-WebRequest -Uri $url -OutFile $pdf -UserAgent 'Mozilla/5.0' -TimeoutSec 60
        & $pt -table $pdf $txt
        Remove-Item $pdf -Force
    } catch { Write-Output ("skip M" + $n) }
}
& $php "$proj/tools/fifa-build.php" build $txtDir
Remove-Item $txtDir -Recurse -Force

# Sync to production (git commit/push is a no-op when nothing changed).
& powershell -ExecutionPolicy Bypass -File $sync -Message 'data: auto-update FIFA official match stats'
Write-Output 'fifa-auto done'
