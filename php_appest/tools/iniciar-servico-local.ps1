param(
    [ValidateSet('api','mysql')][string]$Servico,
    [string]$Xampp = 'C:\xampp'
)
$ErrorActionPreference = 'Stop'
$api = Split-Path $PSScriptRoot -Parent
$projeto = Split-Path $api -Parent
$runtime = Join-Path $projeto '.runtime'
$entrada = Join-Path $runtime 'servicos-stdin.txt'
if (!(Test-Path -LiteralPath $entrada)) { New-Item -ItemType File -Path $entrada | Out-Null }
if ($Servico -eq 'api') {
    $exe = Join-Path $Xampp 'php\php.exe'
    $router = Join-Path $PSScriptRoot 'router-local.php'
    $argumentos = @('-d','upload_max_filesize=128M','-d','post_max_size=132M','-d','display_errors=Off','-d',"`"upload_tmp_dir=$runtime`"",'-S','127.0.0.1:8088','-t',"`"$api`"", "`"$router`"")
} else {
    $exe = Join-Path $Xampp 'mysql\bin\mysqld.exe'
    $ini = Join-Path $Xampp 'mysql\bin\my.ini'
    $argumentos = @("--defaults-file=`"$ini`"",'--standalone')
}
$processo = Start-Process -FilePath $exe -ArgumentList $argumentos -WorkingDirectory $api -WindowStyle Hidden -PassThru -RedirectStandardInput $entrada -RedirectStandardOutput (Join-Path $runtime "$Servico-out.log") -RedirectStandardError (Join-Path $runtime "$Servico-error.log")
$processo.Id | Set-Content -LiteralPath (Join-Path $runtime "$Servico.pid")
# Este helper roda em um processo destacado, sem os pipes do Gradle.
# Mantém as saídas redirecionadas ativas durante a execução do serviço.
$processo.WaitForExit()
