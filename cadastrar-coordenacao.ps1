param([string]$Xampp='C:\xampp')
$ErrorActionPreference='Stop'
& (Join-Path $PSScriptRoot 'iniciar-local.ps1') -Xampp $Xampp
if ($LASTEXITCODE -ne 0) { throw 'Falha ao preparar o banco.' }
$nome=Read-Host 'Nome da pessoa da coordenação'
$email=Read-Host 'E-mail de acesso'
$segredo=Read-Host 'Senha inicial (pelo menos 8 caracteres)' -AsSecureString
$ptr=[Runtime.InteropServices.Marshal]::SecureStringToBSTR($segredo)
try {
    $dados=@{nome=$nome;email=$email;senha=[Runtime.InteropServices.Marshal]::PtrToStringBSTR($ptr)} | ConvertTo-Json -Compress
    $info=New-Object Diagnostics.ProcessStartInfo
    $info.FileName=Join-Path $Xampp 'php\php.exe'
    $info.Arguments='"'+(Join-Path $PSScriptRoot 'php_appest\tools\criar_coordenacao.php')+'"'
    $info.UseShellExecute=$false
    $info.CreateNoWindow=$true
    $info.RedirectStandardInput=$true
    $info.StandardInputEncoding=New-Object Text.UTF8Encoding($false)
    $processo=[Diagnostics.Process]::Start($info)
    $processo.StandardInput.Write($dados)
    $processo.StandardInput.Close()
    $processo.WaitForExit()
    if ($processo.ExitCode -ne 0) { throw 'Cadastro não concluído.' }
} finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr)
    $dados=$null
    $segredo.Dispose()
}
