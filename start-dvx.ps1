$ErrorActionPreference = 'Stop'
$projectDir = $PSScriptRoot
$publicDir = Join-Path $projectDir 'public'
$logDir = Join-Path $projectDir 'storage\logs'
$tempDir = Join-Path $projectDir 'storage\app\private\upload-tmp'
$phpIni = Join-Path $projectDir 'php.ini'
$router = Join-Path $projectDir 'vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php'
$phpExe = Get-ChildItem "$env:LOCALAPPDATA\Microsoft\WinGet\Packages\PHP.PHP.8.4_*\php.exe" -ErrorAction SilentlyContinue | Select-Object -First 1 -ExpandProperty FullName
if (-not $phpExe) { throw 'PHP 8.4 was not found. Install PHP.PHP.8.4 with winget.' }
$nodeDir = 'C:\Program Files\nodejs'
if (-not (Test-Path (Join-Path $nodeDir 'npm.cmd'))) { throw 'Node.js/npm was not found.' }
New-Item -ItemType Directory -Force -Path $logDir, $tempDir | Out-Null
if (-not (Get-Process mysqld -ErrorAction SilentlyContinue)) {
    Start-Process -FilePath 'C:\xampp\mysql\bin\mysqld.exe' -ArgumentList @('--defaults-file=C:\xampp\mysql\bin\my.ini', '--standalone') -WorkingDirectory 'C:\xampp\mysql\bin' -WindowStyle Hidden
}
foreach ($port in 8000, 5173) {
    Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue | ForEach-Object { Stop-Process -Id $_.OwningProcess -Force -ErrorAction SilentlyContinue }
}
$quotedIni = '"' + $phpIni + '"'
$quotedTemp = '"' + $tempDir + '"'
$quotedRouter = '"' + $router + '"'
$serverArgs = @('-c', $quotedIni, '-d', ('sys_temp_dir=' + $quotedTemp), '-d', ('upload_tmp_dir=' + $quotedTemp), '-S', '127.0.0.1:8000', '-t', '.', $quotedRouter)
Start-Process -FilePath $phpExe -ArgumentList $serverArgs -WorkingDirectory $publicDir -WindowStyle Hidden -RedirectStandardOutput (Join-Path $logDir 'php-server.out.log') -RedirectStandardError (Join-Path $logDir 'php-server.err.log')
Start-Process -FilePath $phpExe -ArgumentList @('-c', $quotedIni, '-d', ('sys_temp_dir=' + $quotedTemp), 'artisan', 'queue:listen', '--tries=1') -WorkingDirectory $projectDir -WindowStyle Hidden -RedirectStandardOutput (Join-Path $logDir 'queue.out.log') -RedirectStandardError (Join-Path $logDir 'queue.err.log')
$viteCommand = 'set "PATH=' + $nodeDir + ';%PATH%" && npm.cmd run dev -- --host 127.0.0.1'
Start-Process -FilePath 'cmd.exe' -ArgumentList @('/d', '/c', $viteCommand) -WorkingDirectory $projectDir -WindowStyle Hidden -RedirectStandardOutput (Join-Path $logDir 'vite.out.log') -RedirectStandardError (Join-Path $logDir 'vite.err.log')
Start-Sleep -Seconds 5
$response = Invoke-WebRequest -Uri 'http://127.0.0.1:8000/up' -UseBasicParsing -TimeoutSec 20
if ($response.StatusCode -ne 200) { throw "DVX health check failed with HTTP $($response.StatusCode)." }
Start-Process 'http://127.0.0.1:8000'
Write-Host 'DVX is running at http://127.0.0.1:8000'
