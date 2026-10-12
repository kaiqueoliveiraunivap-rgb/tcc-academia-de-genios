param([string]$Xampp = 'C:\xampp', [switch]$Rapido)
$ErrorActionPreference = 'Stop'
$projeto = $PSScriptRoot
$php = Join-Path $Xampp 'php\php.exe'
$mysql = Join-Path $Xampp 'mysql\bin\mysqld.exe'
$api = Join-Path $projeto 'php_appest'
$runtime = Join-Path $projeto '.runtime'
if (!(Test-Path -LiteralPath $php)) { throw "PHP não encontrado em $php. Instale XAMPP ou informe -Xampp." }
New-Item -ItemType Directory -Force -Path $runtime | Out-Null
function PortaAberta([int]$porta) {
    $cliente = New-Object Net.Sockets.TcpClient
    try { $t = $cliente.ConnectAsync('127.0.0.1', $porta); return ($t.Wait(500) -and $cliente.Connected) }
    catch { return $false }
    finally { $cliente.Dispose() }
}
function ApiDesteProjeto {
    $arquivoPid=Join-Path $runtime 'api.pid'
    if (!(Test-Path -LiteralPath $arquivoPid)) { return $false }
    try {
        $idApi=[int](Get-Content -LiteralPath $arquivoPid -Raw).Trim()
        $pApi=Get-CimInstance Win32_Process -Filter "ProcessId=$idApi"
        return ($pApi.ExecutablePath -eq $php -and $pApi.CommandLine.Contains((Join-Path $api 'tools\router-local.php')))
    } catch { return $false }
}
$url = 'http://127.0.0.1:8088/php_appest/saude.php'
# Atualiza apenas o processo da API iniciado por este projeto quando os limites
# antigos do PHP impediriam os PDFs. Não encerra um serviço desconhecido na porta.
if (PortaAberta 8088) {
    try {
        $configApi = Invoke-RestMethod -Uri $url -TimeoutSec 3
        if ($configApi.servico -eq 'academia-genios-api' -and
            ([long]$configApi.upload_max_bytes -lt 134217728 -or $configApi.erros_publicos)) {
            $pidArquivo = Join-Path $runtime 'api.pid'
            if (Test-Path -LiteralPath $pidArquivo) {
                $pidApi = [int](Get-Content -LiteralPath $pidArquivo -Raw).Trim()
                $processoApi = Get-CimInstance Win32_Process -Filter "ProcessId=$pidApi"
                $routerEsperado = Join-Path $api 'tools\router-local.php'
                if ($processoApi.ExecutablePath -eq $php -and $processoApi.CommandLine.Contains($routerEsperado)) {
                    Stop-Process -Id $pidApi -Force
                    for ($i=0; $i -lt 20 -and (PortaAberta 8088); $i++) { Start-Sleep -Milliseconds 100 }
                }
            }
        }
    } catch { }
}
if ($Rapido -and (PortaAberta 8088)) {
    try {
        $saude = Invoke-RestMethod -Uri $url -TimeoutSec 3
        if ($saude.servico -eq 'academia-genios-api' -and $saude.versao -eq 2) {
            # O instalador usa a assinatura dos scripts: aplica alterações novas
            # mesmo quando a API já está aberta e reaproveita o banco nos demais runs.
            & $php (Join-Path $api 'tools\instalar.php')
            if ($LASTEXITCODE -ne 0) { throw 'A atualização do banco falhou.' }
            Write-Output 'API local já está pronta para o emulador.'
            exit 0
        }
    } catch {
        # O servidor PHP de desenvolvimento atende uma requisição por vez.
        # Uma geração de IA em andamento não significa conflito de porta.
        if (ApiDesteProjeto) {
            & $php (Join-Path $api 'tools\instalar.php')
            if ($LASTEXITCODE -ne 0) { throw 'A atualização do banco falhou.' }
            Write-Output 'API local em execução; uma requisição está sendo atendida.'
            exit 0
        }
    }
}
# Reutiliza o MySQL existente; não substitui dados.
# ShellExecute destaca o helper do processo de build: nenhum pipe do Gradle
# fica aberto nos serviços. O helper mantém os logs e o PID na pasta .runtime.
function IniciarServico([string]$servico) {
    $helper = Join-Path $api 'tools\iniciar-servico-local.ps1'
    $info = New-Object System.Diagnostics.ProcessStartInfo
    $info.FileName = (Get-Command powershell.exe -ErrorAction Stop).Source
    $info.Arguments = "-NoProfile -NonInteractive -ExecutionPolicy Bypass -File `"$helper`" -Servico $servico -Xampp `"$Xampp`""
    $info.WorkingDirectory = $projeto
    $info.UseShellExecute = $true
    $info.WindowStyle = [System.Diagnostics.ProcessWindowStyle]::Hidden
    [System.Diagnostics.Process]::Start($info) | Out-Null
}
if (!(PortaAberta 3306)) {
    if (!(Test-Path -LiteralPath $mysql)) { throw 'MySQL do XAMPP não encontrado.' }
    IniciarServico 'mysql'
    for ($i=0; $i -lt 40 -and !(PortaAberta 3306); $i++) { Start-Sleep -Milliseconds 250 }
    if (!(PortaAberta 3306)) { throw 'MySQL não iniciou. Confira o painel do XAMPP.' }
}
& $php (Join-Path $api 'tools\instalar.php')
if ($LASTEXITCODE -ne 0) { throw 'A preparação do banco falhou.' }
if (PortaAberta 8088) {
    try { $saude = Invoke-RestMethod -Uri $url -TimeoutSec 3 }
    catch {
        if (ApiDesteProjeto) { $saude=@{servico='academia-genios-api';versao=2} }
        else { throw 'A porta 8088 está ocupada por outro serviço.' }
    }
    if ($saude.servico -ne 'academia-genios-api' -or $saude.versao -ne 2) { throw 'A porta 8088 está ocupada por outra API.' }
} else {
    IniciarServico 'api'
    $pronto = $false
    for ($i=0; $i -lt 20; $i++) {
        try { $saude = Invoke-RestMethod -Uri $url -TimeoutSec 2; if ($saude.servico -eq 'academia-genios-api') { $pronto=$true; break } }
        catch { Start-Sleep -Milliseconds 250 }
    }
    if (!$pronto) { throw 'API não iniciou. Consulte .runtime/api-error.log.' }
}
Write-Output 'API pronta para o emulador: http://10.0.2.2:8088/php_appest/'
