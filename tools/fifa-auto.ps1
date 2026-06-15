# fifa-auto.ps1 - Hourly auto-update of ALL FIFA data + sync to production.
# ASCII-only (safe for Task Scheduler). PC must be on. Syncs (git push) ONLY when
# something actually changed. Two independent steps:
#   1) NEW official match-report PDFs -> physical-stats block (needs pdftotext, local only)
#   2) Player feed (photos + technical metrics) from the public FIFA feed, so UPCOMING
#      matches, NEW players and SUBSTITUTES appear automatically as the tournament goes.
$ErrorActionPreference = 'Continue'
$php  = 'C:/xampp/php/php.exe'
$pt   = (Get-Command pdftotext.exe -ErrorAction SilentlyContinue).Source
if (-not $pt) { $pt = 'C:/Program Files/Git/mingw64/bin/pdftotext.exe' }
$proj = 'C:/xampp/htdocs/worldcup2026'
$sync = 'C:/xampp/htdocs/sync-worldcup-to-oss.ps1'
$needSync = $false

# ===== 1) NEW match-report PDFs (physical-stats block) =====
if (Test-Path $pt) {
    # Force a fresh hub scrape so newly-published reports are seen THIS hour
    # (the 6h reports() cache would otherwise blind us for up to 6 hours).
    Remove-Item "$proj/cache/fifa-reports.json","$proj/cache/fifa-reports.json.fail" -Force -ErrorAction SilentlyContinue
    $pending = & $php "$proj/tools/fifa-build.php" pending | ConvertFrom-Json
    $names = @($pending.PSObject.Properties | Where-Object { $_.MemberType -eq 'NoteProperty' } | Select-Object -ExpandProperty Name)
    if ($names.Count -gt 0) {
        $txtDir = Join-Path $proj 'tools/_fifatxt'
        New-Item -ItemType Directory -Force -Path $txtDir | Out-Null
        foreach ($n in $names) {
            $pdf = Join-Path $txtDir "$n.pdf"; $txt = Join-Path $txtDir "$n.txt"
            try {
                Invoke-WebRequest -Uri $pending.$n -OutFile $pdf -UserAgent 'Mozilla/5.0' -TimeoutSec 60
                & $pt -table $pdf $txt; Remove-Item $pdf -Force
            } catch { Write-Output ("skip M" + $n) }
        }
        $built = & $php "$proj/tools/fifa-build.php" build $txtDir
        Remove-Item $txtDir -Recurse -Force
        Write-Output $built
        if ($built -match 'built\s+([1-9]\d*)') { $needSync = $true }
    } else { Write-Output 'no new reports' }
} else { Write-Output 'pdftotext missing (PDF step skipped)' }

# ===== 2) Player feed: photos + technical metrics (new players / subs) =====
$feedDir = Join-Path $proj 'tools/_feed'
New-Item -ItemType Directory -Force -Path $feedDir | Out-Null
$metricsFile = Join-Path $proj 'assets/fifa-metrics.json'
$beforeLen = if (Test-Path $metricsFile) { (Get-Item $metricsFile).Length } else { -1 }
$feedOk = $true
foreach ($f in 'data.js','ratings.js','posreal.js') {
    try { Invoke-WebRequest -Uri "https://fifaphy.vercel.app/$f" -OutFile (Join-Path $feedDir $f) -UserAgent 'Mozilla/5.0' -TimeoutSec 90 }
    catch { $feedOk = $false; Write-Output ("feed download failed: " + $f) }
}
if ($feedOk) {
    & $php "$proj/tools/fifa-metrics-build.php" $feedDir
    $afterLen = if (Test-Path $metricsFile) { (Get-Item $metricsFile).Length } else { -1 }
    # length changes only on real data change (date-only edits keep the same byte length)
    if ($afterLen -ne $beforeLen) { $needSync = $true; Write-Output 'feed changed' }
    else { Write-Output 'feed unchanged' }
}
Remove-Item $feedDir -Recurse -Force -ErrorAction SilentlyContinue

# ===== sync once if anything changed =====
if ($needSync) {
    & powershell -ExecutionPolicy Bypass -File $sync -Message 'data: auto-update FIFA reports + player feed (photos/metrics)'
    Write-Output 'synced'
} else {
    Write-Output 'nothing changed'
}
