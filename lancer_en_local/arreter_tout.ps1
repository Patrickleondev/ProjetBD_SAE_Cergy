# Arrête ce que lancer_tout.ps1 a démarré : serveur des bornes, site web, base locale.
$ici = Split-Path -Parent $MyInvocation.MyCommand.Path

Get-CimInstance Win32_Process -Filter "name='java.exe'" |
    Where-Object { $_.CommandLine -like '*serveur-bornes.jar*' } |
    ForEach-Object { Stop-Process -Id $_.ProcessId -Force; "serveur des bornes arrêté" }

Get-CimInstance Win32_Process -Filter "name='php.exe'" |
    Where-Object { $_.CommandLine -like '*localhost:8000*' } |
    ForEach-Object { Stop-Process -Id $_.ProcessId -Force; "site web arrêté" }

& "C:\Program Files\PostgreSQL\18\bin\pg_ctl.exe" -D (Join-Path $ici "pgdata") stop -m fast
