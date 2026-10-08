# GuideMate automatic database backup.
# Dumps the `guidemate` MySQL database to the backups/ folder with a timestamp
# and keeps only the most recent $KeepCount backups.
#
# Run manually:   powershell -ExecutionPolicy Bypass -File database\backup_db.ps1
# Scheduled:      registered as the "GuideMate DB Backup" task (daily).

param(
    [int]$KeepCount = 14
)

$ErrorActionPreference = 'Stop'

# --- Settings ---------------------------------------------------------------
$mysqldump = 'C:\xamppss\mysql\bin\mysqldump.exe'
$dbName    = 'guidemate'
$dbUser    = 'root'
$dbPass    = ''   # XAMPP default: empty
$backupDir = Join-Path $PSScriptRoot '..\backups'
# ---------------------------------------------------------------------------

if (-not (Test-Path $mysqldump)) {
    Write-Error "mysqldump not found at $mysqldump. Update the path in backup_db.ps1."
    exit 1
}

New-Item -ItemType Directory -Force -Path $backupDir | Out-Null
$backupDir = (Resolve-Path $backupDir).Path

$stamp = Get-Date -Format 'yyyyMMdd_HHmmss'
$outFile = Join-Path $backupDir "guidemate_$stamp.sql"

$dumpArgs = @('-u', $dbUser)
if ($dbPass -ne '') { $dumpArgs += "-p$dbPass" }
$dumpArgs += @('--databases', $dbName, '--result-file', $outFile)

& $mysqldump @dumpArgs

if ((Test-Path $outFile) -and ((Get-Item $outFile).Length -gt 0)) {
    Write-Output "Backup created: $outFile ($([math]::Round((Get-Item $outFile).Length/1KB,1)) KB)"
} else {
    Write-Error "Backup failed (empty or missing file)."
    exit 1
}

# Retention: keep only the newest $KeepCount dumps.
Get-ChildItem $backupDir -Filter 'guidemate_*.sql' |
    Sort-Object LastWriteTime -Descending |
    Select-Object -Skip $KeepCount |
    ForEach-Object { Remove-Item $_.FullName -Force; Write-Output "Pruned old backup: $($_.Name)" }
