$ErrorActionPreference = 'Stop'

$projectRoot = $PSScriptRoot
$xamppRoot = 'C:\xampp'
$apacheStart = Join-Path $xamppRoot 'apache_start.bat'
$mysqlStart = Join-Path $xamppRoot 'mysql_start.bat'
$php = Join-Path $xamppRoot 'php\php.exe'
$apiRoot = Join-Path $xamppRoot 'htdocs\smartcontrol\api\mobile'
$tailscale = 'C:\Program Files\Tailscale\tailscale.exe'
$tailscaleHelper = Join-Path $projectRoot 'LIGAR_TAILSCALE_MOBILE.ps1'
$logDirectory = Join-Path $projectRoot 'tunnel-logs'
$publicAddress = 'https://smartcontrol-tcc.tail495b46.ts.net'
$addressFile = Join-Path $projectRoot 'mobile\ENDERECO_DO_SERVIDOR.txt'

function Test-TcpPort {
    param([int]$Port)

    $client = [System.Net.Sockets.TcpClient]::new()
    try {
        $connection = $client.ConnectAsync('127.0.0.1', $Port)
        return $connection.Wait(800) -and $client.Connected
    }
    catch {
        return $false
    }
    finally {
        $client.Dispose()
    }
}

function Wait-TcpPort {
    param([int]$Port, [int]$Seconds)

    $deadline = (Get-Date).AddSeconds($Seconds)
    do {
        if (Test-TcpPort -Port $Port) { return $true }
        Start-Sleep -Milliseconds 500
    } while ((Get-Date) -lt $deadline)
    return $false
}

function Stop-WithMessage {
    param([string]$Message)

    Write-Host ''
    Write-Host $Message -ForegroundColor Red
    Write-Host ''
    Read-Host 'Pressione Enter para fechar'
    exit 1
}

function Test-PublicDns {
    $phpCode = '$o=["http"=>["header"=>"accept: application/dns-json\r\n"]];' +
        '$j=json_decode(@file_get_contents("https://cloudflare-dns.com/dns-query?name=smartcontrol-tcc.tail495b46.ts.net&type=A",false,stream_context_create($o)),true);' +
        'echo (($j["Status"]??-1)===0 && !empty($j["Answer"])) ? "READY" : "WAIT";'
    try {
        $result = & $php -d display_errors=0 -d display_startup_errors=0 -d error_reporting=0 -r $phpCode 2>$null
        return "$result".Trim() -eq 'READY'
    }
    catch {
        return $false
    }
}

foreach ($requiredPath in @($apacheStart, $mysqlStart, $php, $apiRoot, $tailscale, $tailscaleHelper)) {
    if (-not (Test-Path -LiteralPath $requiredPath)) {
        Stop-WithMessage "Arquivo necessario nao encontrado: $requiredPath"
    }
}

New-Item -ItemType Directory -Path $logDirectory -Force | Out-Null

Write-Host ''
Write-Host 'SmartControl Mobile - acesso pela internet' -ForegroundColor Cyan
Write-Host '------------------------------------------' -ForegroundColor DarkCyan

if (-not (Test-TcpPort -Port 3306)) {
    Write-Host 'Iniciando o MySQL do XAMPP...'
    Start-Process -FilePath 'cmd.exe' -ArgumentList @('/c', $mysqlStart) -WorkingDirectory $xamppRoot -WindowStyle Hidden
    if (-not (Wait-TcpPort -Port 3306 -Seconds 35)) {
        Stop-WithMessage 'O MySQL nao iniciou. Abra o painel do XAMPP, inicie o MySQL e tente novamente.'
    }
}
else {
    Write-Host 'MySQL do XAMPP ja estava ligado.' -ForegroundColor DarkGray
}
Write-Host 'Banco de dados: pronto.' -ForegroundColor Green

if (-not (Test-TcpPort -Port 80)) {
    Write-Host 'Iniciando o Apache do XAMPP...'
    Start-Process -FilePath 'cmd.exe' -ArgumentList @('/c', $apacheStart) -WorkingDirectory $xamppRoot -WindowStyle Hidden
    if (-not (Wait-TcpPort -Port 80 -Seconds 35)) {
        Stop-WithMessage 'O Apache nao iniciou. Abra o painel do XAMPP, inicie o Apache e tente novamente.'
    }
}
else {
    Write-Host 'Apache do XAMPP ja estava ligado.' -ForegroundColor DarkGray
}

$localHealthAddress = 'http://127.0.0.1/smartcontrol/api/mobile/index.php?resource=health'
$apiReady = $false
try {
    $health = Invoke-RestMethod -Uri $localHealthAddress -TimeoutSec 5
    $apiReady = $health.data.status -eq 'ok' -and [int]$health.data.version -ge 2
}
catch {
    $apiReady = $false
}
if (-not $apiReady) {
    Stop-WithMessage 'A API v2 nao respondeu pelo XAMPP. Confira Apache, banco e a pasta C:\xampp\htdocs\smartcontrol.'
}
Write-Host 'API v2 do aplicativo no XAMPP: pronta.' -ForegroundColor Green

Write-Host 'Ligando e anunciando o endereco HTTPS...'
$tailscaleStatusFile = Join-Path $logDirectory 'tailscale-start-status.txt'
Remove-Item -LiteralPath $tailscaleStatusFile -Force -ErrorAction SilentlyContinue
$helperArguments = "-NoLogo -NoProfile -ExecutionPolicy Bypass -File `"$tailscaleHelper`" -StatusFile `"$tailscaleStatusFile`""
try {
    $helperProcess = Start-Process powershell.exe `
        -Verb RunAs `
        -ArgumentList $helperArguments `
        -WindowStyle Hidden `
        -Wait `
        -PassThru
}
catch {
    Stop-WithMessage 'A permissao de administrador foi cancelada. Execute novamente e aceite a confirmacao do Windows.'
}

$tailscaleResult = if (Test-Path -LiteralPath $tailscaleStatusFile) {
    (Get-Content -LiteralPath $tailscaleStatusFile -Raw).Trim()
}
else {
    'ERROR|O auxiliar do Tailscale nao retornou o resultado.'
}
if ($helperProcess.ExitCode -ne 0 -or -not $tailscaleResult.StartsWith('READY')) {
    $details = ($tailscaleResult -replace '^ERROR\|', '')
    Stop-WithMessage "Nao foi possivel ligar o acesso HTTPS do Tailscale. $details"
}

if (Test-PublicDns) {
    Write-Host 'DNS publico: confirmado.' -ForegroundColor Green
}
else {
    Write-Host 'DNS publico: ainda propagando; o aplicativo pode levar alguns minutos para conectar.' -ForegroundColor Yellow
}

Set-Content -LiteralPath $addressFile -Value $publicAddress -Encoding utf8
Write-Host ''
Write-Host '============================================================' -ForegroundColor Green
Write-Host ' SMARTCONTROL DISPONIVEL PARA O APLICATIVO' -ForegroundColor Green
Write-Host " $publicAddress" -ForegroundColor White
Write-Host ' O celular e o notebook podem usar redes diferentes.' -ForegroundColor Green
Write-Host ' Mantenha o notebook ligado durante a apresentacao.' -ForegroundColor Yellow
Write-Host '============================================================' -ForegroundColor Green
Write-Host ''
Read-Host 'Pressione Enter para fechar esta janela'
