param([string]$Version = (Get-Date -Format 'yyyyMMdd-HHmmss'))
$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$artifacts = Join-Path $root 'artifacts'
$stage = Join-Path $artifacts "gym-web-$Version"
$zip = "$stage.zip"
if (Test-Path -LiteralPath $stage) { Remove-Item -LiteralPath $stage -Recurse -Force }
if (Test-Path -LiteralPath $zip) { Remove-Item -LiteralPath $zip -Force }
New-Item -ItemType Directory -Path $stage -Force | Out-Null

$directories = @('app','bootstrap','config','database','docs','lang','public','resources','routes','scripts','vendor')
foreach ($directory in $directories) {
    $source = Join-Path $root $directory
    if (Test-Path -LiteralPath $source) { Copy-Item -LiteralPath $source -Destination (Join-Path $stage $directory) -Recurse -Force }
}
$files = @('artisan','composer.json','composer.lock','package.json','package-lock.json','README.md','AGENTS.md','.env.example','.env.production.example','.editorconfig','.gitattributes')
foreach ($file in $files) { Copy-Item -LiteralPath (Join-Path $root $file) -Destination (Join-Path $stage $file) -Force }
$localDatabase = Join-Path $stage 'database/database.sqlite'
if (Test-Path -LiteralPath $localDatabase) { Remove-Item -LiteralPath $localDatabase -Force }

foreach ($path in @('storage/app/private','storage/app/public','storage/framework/cache/data','storage/framework/sessions','storage/framework/views','storage/logs','bootstrap/cache')) {
    New-Item -ItemType Directory -Path (Join-Path $stage $path) -Force | Out-Null
}
Set-Content -LiteralPath (Join-Path $stage 'storage/app/private/.gitignore') -Value "*`n!.gitignore`n" -Encoding ascii
Set-Content -LiteralPath (Join-Path $stage 'storage/app/public/.gitignore') -Value "*`n!.gitignore`n" -Encoding ascii
Set-Content -LiteralPath (Join-Path $stage 'storage/logs/.gitignore') -Value "*`n!.gitignore`n" -Encoding ascii

$forbidden = Get-ChildItem -LiteralPath $stage -Recurse -Force | Where-Object { $_.Name -in @('.env','database.sqlite','.phpunit.result.cache','node_modules','.git') }
if ($forbidden) { throw "Forbidden development or secret files entered the release package." }
if (-not (Test-Path -LiteralPath (Join-Path $stage 'public/build/manifest.json'))) { throw 'Production assets are missing. Run npm run build first.' }
$tar = Get-Command tar.exe -ErrorAction Stop
& $tar.Source -a -cf $zip -C $artifacts (Split-Path $stage -Leaf)
if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $zip)) { throw 'Release archive creation failed.' }
$hash = (Get-FileHash -LiteralPath $zip -Algorithm SHA256).Hash.ToLowerInvariant()
Set-Content -LiteralPath "$zip.sha256" -Value "$hash  $(Split-Path $zip -Leaf)" -Encoding ascii
Remove-Item -LiteralPath $stage -Recurse -Force
Write-Output $zip
Write-Output "SHA256: $hash"
