# daylog helper for Windows: start, update, stop.
# Called by start.bat / update.bat / stop.bat. Kept ASCII-only for Windows PowerShell 5.1.
param([ValidateSet('start', 'update', 'stop')] [string]$Command = 'start')

$ErrorActionPreference = 'Continue'
Set-Location -LiteralPath $PSScriptRoot
$EnvFile = Join-Path $PSScriptRoot '.env'

function Fail([string]$Message) {
    Write-Host ''
    Write-Host $Message -ForegroundColor Red
    Read-Host 'Press Enter to close'
    exit 1
}

function Assert-Docker {
    docker info 2>&1 | Out-Null
    if ($LASTEXITCODE -ne 0) {
        Fail 'Docker Desktop is not running. Start it, wait until it says "Engine running", then try again.'
    }
}

function Read-EnvFile {
    $values = @{}
    if (Test-Path $EnvFile) {
        foreach ($line in Get-Content $EnvFile) {
            if ($line -match '^\s*([A-Z_]+)=(.*)$') { $values[$Matches[1]] = $Matches[2] }
        }
    }
    return $values
}

function Write-EnvValue([string]$Name, [string]$Value) {
    $lines = @()
    $found = $false
    if (Test-Path $EnvFile) {
        foreach ($line in Get-Content $EnvFile) {
            if ($line -match "^$Name=") { $lines += "$Name=$Value"; $found = $true } else { $lines += $line }
        }
    }
    if (-not $found) { $lines += "$Name=$Value" }
    [IO.File]::WriteAllLines($EnvFile, [string[]]$lines)   # UTF-8 without BOM
}

function New-Secret {
    $bytes = New-Object byte[] 24
    [Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
    return ([Convert]::ToBase64String($bytes) -replace '[^A-Za-z0-9]', '').Substring(0, 24)
}

function Test-PortFree([int]$Port) {
    # check the wildcard and the loopback address, a program may hold only one of them
    foreach ($address in @([Net.IPAddress]::Any, [Net.IPAddress]::Loopback)) {
        $listener = New-Object Net.Sockets.TcpListener($address, $Port)
        try { $listener.Start(); $listener.Stop() } catch { return $false }
    }
    return $true
}

function Find-FreePort([int]$From) {
    for ($p = $From; $p -lt $From + 100; $p++) { if (Test-PortFree $p) { return $p } }
    Fail "No free port found between $From and $($From + 99)."
}

function Test-AppRunning {
    $running = docker compose ps --status running --services 2>$null
    return ($running -contains 'app')
}

function Test-DataExists {
    $volumes = docker volume ls -q --filter 'name=^daylog_db_data$' 2>$null
    return [bool]$volumes
}

# First start: create .env with a free port and random database passwords.
function Initialize-EnvFile {
    if (Test-Path $EnvFile) { return }
    if (Test-DataExists) {
        Fail ('The settings file .env is missing, but daylog data exists. Restore .env from the backups folder ' +
              '(env-backup.txt) into this folder and run start.bat again.')
    }
    $port = Find-FreePort 47080
    [IO.File]::WriteAllLines($EnvFile, [string[]]@(
        "APP_PORT=$port",
        'TZ=Europe/Berlin',
        "DB_PASSWORD=$(New-Secret)",
        "DB_ROOT_PASSWORD=$(New-Secret)"
    ))
    New-Item -ItemType Directory -Force -Path 'backups' | Out-Null
    Copy-Item $EnvFile (Join-Path 'backups' 'env-backup.txt') -Force
}

# Another program took our port since last time: move to the next free one.
function Resolve-Port {
    $port = [int](Read-EnvFile)['APP_PORT']
    if (-not (Test-AppRunning) -and -not (Test-PortFree $port)) {
        $new = Find-FreePort ($port + 1)
        Write-Host "Port $port is in use by another program, switching to $new." -ForegroundColor Yellow
        Write-EnvValue 'APP_PORT' $new
        Copy-Item $EnvFile (Join-Path 'backups' 'env-backup.txt') -Force
    }
}

function Wait-App {
    $port = (Read-EnvFile)['APP_PORT']
    Write-Host -NoNewline 'Waiting for daylog'
    for ($i = 0; $i -lt 90; $i++) {
        try {
            Invoke-WebRequest -UseBasicParsing -TimeoutSec 3 "http://localhost:$port/api/profile" | Out-Null
            Write-Host ''
            return "http://localhost:$port"
        } catch {
            Write-Host -NoNewline '.'
            Start-Sleep -Seconds 2
        }
    }
    Fail 'daylog did not respond. Show details with: docker compose logs app db'
}

function Start-Daylog {
    Assert-Docker
    Initialize-EnvFile
    Resolve-Port
    Write-Host 'Starting daylog. The first start downloads the app and can take a few minutes...'
    docker compose up -d --remove-orphans
    if ($LASTEXITCODE -ne 0) { Fail 'daylog could not be started. See the messages above.' }
    $url = Wait-App
    Start-Process $url
    Write-Host "daylog is running at $url" -ForegroundColor Green
    Start-Sleep -Seconds 3
}

function Save-Backup {
    Write-Host 'Saving a backup of your data...'
    docker compose up -d db | Out-Null
    for ($i = 0; $i -lt 60; $i++) {
        docker compose exec -T db mysqladmin ping -h localhost --silent 2>&1 | Out-Null
        if ($LASTEXITCODE -eq 0) { break }
        Start-Sleep -Seconds 2
    }
    docker compose exec -T db sh -c 'MYSQL_PWD=$MYSQL_ROOT_PASSWORD mysqldump -uroot --single-transaction --routines --triggers $MYSQL_DATABASE > /tmp/daylog-backup.sql'
    if ($LASTEXITCODE -ne 0) { Fail 'The backup failed, so the update was cancelled. Your data is unchanged.' }

    New-Item -ItemType Directory -Force -Path 'backups' | Out-Null
    $file = Join-Path 'backups' ("daylog-{0}.sql" -f (Get-Date -Format 'yyyy-MM-dd_HHmm'))
    docker compose cp db:/tmp/daylog-backup.sql $file | Out-Null
    if ($LASTEXITCODE -ne 0) { Fail 'The backup could not be saved, so the update was cancelled.' }

    # keep the 10 newest backups
    Get-ChildItem 'backups' -Filter 'daylog-*.sql' | Sort-Object Name -Descending | Select-Object -Skip 10 | Remove-Item
    Write-Host "Backup saved: $file"
}

function Update-Daylog {
    Assert-Docker
    Initialize-EnvFile
    if (Test-DataExists) { Save-Backup }
    Write-Host 'Downloading the latest version...'
    docker compose pull app
    if ($LASTEXITCODE -ne 0) { Fail 'The download failed. Check your internet connection and try again.' }
    Resolve-Port
    docker compose up -d --remove-orphans
    if ($LASTEXITCODE -ne 0) { Fail 'daylog could not be restarted. See the messages above.' }
    $url = Wait-App
    Start-Process $url
    Write-Host "daylog is up to date and running at $url" -ForegroundColor Green
    Start-Sleep -Seconds 3
}

function Stop-Daylog {
    docker compose down
    Write-Host 'daylog stopped. Your data is kept.' -ForegroundColor Green
    Start-Sleep -Seconds 2
}

switch ($Command) {
    'start'  { Start-Daylog }
    'update' { Update-Daylog }
    'stop'   { Stop-Daylog }
}
