# Генерация public/123.rar размером 15 МБ (нули). Запуск из корня репозитория:
#   powershell -ExecutionPolicy Bypass -File scripts/generate-public-123-rar.ps1

$ErrorActionPreference = 'Stop'
# scripts/.. = корень проекта
$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$public = Join-Path $root 'public'
$target = Join-Path $public '123.rar'
$size = 15 * 1024 * 1024

New-Item -ItemType Directory -Force -Path $public | Out-Null
$fsutil = "fsutil"
& $fsutil file createnew $target $size
if ($LASTEXITCODE -ne 0) {
    $bytes = New-Object byte[] $size
    [IO.File]::WriteAllBytes($target, $bytes)
}
Write-Host "OK: $target ($size bytes)"
