$ErrorActionPreference = 'Stop'
$arquivoPid = Join-Path $PSScriptRoot '.runtime\api.pid'
if (Test-Path -LiteralPath $arquivoPid) {
    $apiId = [int](Get-Content -LiteralPath $arquivoPid)
    $processo = Get-CimInstance Win32_Process -Filter "ProcessId=$apiId"
    $router = Join-Path $PSScriptRoot 'php_appest\tools\router-local.php'
    if ($processo -and $processo.Name -eq 'php.exe' -and $processo.CommandLine.Contains($router)) {
        Stop-Process -Id $apiId
        Write-Output 'API local encerrada. MySQL mantido em execução.'
    }
}
