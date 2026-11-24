$ErrorActionPreference = 'Stop'

$identity = [Security.Principal.WindowsIdentity]::GetCurrent()
$principal = [Security.Principal.WindowsPrincipal]::new($identity)
$isAdministrator = $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdministrator) {
    $arguments = "-NoLogo -NoProfile -ExecutionPolicy Bypass -File `"$PSCommandPath`""
    Start-Process powershell.exe -Verb RunAs -ArgumentList $arguments
    exit
}

$tailscale = 'C:\Program Files\Tailscale\tailscale.exe'
if (-not (Test-Path -LiteralPath $tailscale)) {
    Write-Host 'Tailscale nao encontrado.' -ForegroundColor Red
    Read-Host 'Pressione Enter para fechar'
    exit 1
}

& $tailscale funnel --https=443 off
if ($LASTEXITCODE -eq 0) {
    Write-Host ''
    Write-Host 'O acesso do SmartControl pela internet foi desligado.' -ForegroundColor Green
    Write-Host 'O banco e o sistema local continuam no notebook.' -ForegroundColor White
}
else {
    Write-Host ''
    Write-Host 'Nao foi possivel desligar o acesso. Abra o Tailscale e tente novamente.' -ForegroundColor Red
}
Write-Host ''
Read-Host 'Pressione Enter para fechar esta janela'
