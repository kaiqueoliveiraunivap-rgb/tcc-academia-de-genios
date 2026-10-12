param([switch]$Todos, [string]$Id = '', [string]$Xampp = 'C:\xampp')
$ErrorActionPreference = 'Stop'
$raiz = $PSScriptRoot
$php = Join-Path $Xampp 'php\php.exe'
if (!(Test-Path -LiteralPath $php)) { throw "PHP nao encontrado em $php. Instale o XAMPP ou informe -Xampp." }

$python = $env:PDF_PYTHON_BIN
if (!$python) {
    $comando = Get-Command python -ErrorAction SilentlyContinue
    if (!$comando) { $comando = Get-Command python3 -ErrorAction SilentlyContinue }
    if (!$comando) { throw 'Python 3 nao encontrado. Instale Python e adicione-o ao PATH.' }
    $python = $comando.Source
}
& $python -c 'import pypdf' 2>$null
if ($LASTEXITCODE -ne 0) { throw 'Instale pypdf com: python -m pip install "pypdf>=5,<7"' }
$env:PDF_PYTHON_BIN = $python

$extrator = Get-Command pdftotext -ErrorAction SilentlyContinue
if (!$extrator) {
    $gitPdftotext = Join-Path $env:ProgramFiles 'Git\mingw64\bin\pdftotext.exe'
    if (Test-Path -LiteralPath $gitPdftotext) {
        $env:PATH = (Split-Path $gitPdftotext -Parent) + ';' + $env:PATH
        $extrator = Get-Command pdftotext -ErrorAction SilentlyContinue
    }
}
if (!$extrator) { throw 'pdftotext nao encontrado. Instale Poppler e adicione a pasta bin ao PATH.' }

& (Join-Path $raiz 'iniciar-local.ps1') -Xampp $Xampp -Rapido
$catalogo = Get-Content (Join-Path $raiz 'biblioteca\catalogo-drive.json') -Raw | ConvertFrom-Json
if ($Todos) {
    & $php (Join-Path $raiz 'php_appest\tools\importar_drive_automatico.php') --todos
} else {
    if (!$Id) {
        $livro = $catalogo | Where-Object { $_.titulo -eq 'Matemática básica e vetores' } | Select-Object -First 1
        if (!$livro) { throw 'Livro de demonstracao nao encontrado no catalogo.' }
        $Id = $livro.id
    }
    & $php (Join-Path $raiz 'php_appest\tools\importar_drive_automatico.php') --id $Id
}
if ($LASTEXITCODE -ne 0) { throw 'A importacao nao terminou. Veja a mensagem do livro acima.' }
Write-Output 'Livro(s) preparado(s). Abra o app pelo Android Studio e confira materia, frente e capitulo.'
