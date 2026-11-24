param(
    [Parameter(Mandatory = $true)]
    [string]$StatusFile
)

$ErrorActionPreference = 'Stop'
$tailscale = 'C:\Program Files\Tailscale\tailscale.exe'

try {
    if (-not (Test-Path -LiteralPath $tailscale)) {
        throw 'Tailscale nao encontrado.'
    }

    $status = (& $tailscale status --json | ConvertFrom-Json)
    if ($status.BackendState -ne 'Running') {
        throw 'O Tailscale esta desconectado. Abra o programa e entre na conta configurada.'
    }

    $funnelOutput = (& $tailscale funnel --bg --yes http://127.0.0.1:80/smartcontrol/api/mobile 2>&1 | Out-String).Trim()
    if ($LASTEXITCODE -ne 0) {
        throw ('O comando do Funnel falhou: ' + $funnelOutput)
    }

    Set-Content -LiteralPath $StatusFile -Value 'READY' -Encoding utf8
    exit 0
}
catch {
    Set-Content -LiteralPath $StatusFile -Value ("ERROR|" + $_.Exception.Message) -Encoding utf8
    exit 1
}
