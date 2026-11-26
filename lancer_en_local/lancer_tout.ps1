# Lance Voltoise sur ce PC, comme en salle de TP : base PostgreSQL, serveur des bornes, site web.
#   clic droit > Exécuter avec PowerShell, ou :  powershell -ExecutionPolicy Bypass -File lancer_tout.ps1
#   ajouter -Reinitialiser pour repartir du jeu de données d'origine (avant de filmer, par exemple)
#
# La base tourne dans le dossier pgdata/ à côté de ce script, sur le port 54329, sans mot de passe
# (connexions acceptées seulement depuis ce PC). Elle ne touche pas au PostgreSQL du port 5433.
param([switch]$Reinitialiser)
$ErrorActionPreference = "Stop"

$ici    = Split-Path -Parent $MyInvocation.MyCommand.Path
$sae    = Split-Path -Parent $ici
$pg     = "C:\Program Files\PostgreSQL\18\bin"
$java   = "C:\Program Files\Android\Android Studio\jbr\bin\java.exe"
$phpExt = "$env:USERPROFILE\scoop\apps\php\current\ext"
$pgdata = Join-Path $ici "pgdata"
$port   = 54329
$props  = Join-Path $ici "serveur-local.properties"
$dist   = Join-Path $sae "reseau\serveur-java\dist"
$client = Join-Path $sae "reseau\client-python"

# 1. la base
if (-not (Test-Path $pgdata)) {
    "création de la base locale dans $pgdata"
    & "$pg\initdb.exe" -D $pgdata -U postgres -A trust -E UTF8 --locale=C | Out-Null
    $Reinitialiser = $true
}
& "$pg\pg_isready.exe" -h localhost -p $port | Out-Null
if ($LASTEXITCODE -ne 0) {
    # pas de -Wait : pg_ctl laisse tourner postgres, on attend qu'il réponde
    Start-Process "$pg\pg_ctl.exe" -ArgumentList "-D `"$pgdata`" -o `"-p $port`" -l `"$ici\postgres.log`" start" -WindowStyle Hidden
    for ($i = 0; $i -lt 30; $i++) {
        Start-Sleep -Milliseconds 500
        & "$pg\pg_isready.exe" -h localhost -p $port | Out-Null
        if ($LASTEXITCODE -eq 0) { break }
    }
}
"PostgreSQL : port $port"

if ($Reinitialiser) {
    $env:PGOPTIONS = "-c client_min_messages=warning"     # pas de NOTICE à l'écran
    & "$pg\psql.exe" -U postgres -h localhost -p $port -q -c "DROP DATABASE IF EXISTS voltoise" -c "CREATE DATABASE voltoise"
    & "$pg\psql.exe" -U postgres -h localhost -p $port -d voltoise -q -v ON_ERROR_STOP=1 -f "$sae\sql\01_creation.sql"
    "base voltoise rechargée avec sql\01_creation.sql"
}

# 2. le site lit app/config.local.php (jamais sur GitHub) : on le pointe sur cette base
$config = Join-Path $sae "web\app\config.local.php"
@"
<?php
// Configuration locale (lancer_en_local). Jamais versionnée.
defined('VOLTOISE') or exit;
return [
    'db' => ['host' => 'localhost', 'port' => '$port', 'dbname' => 'voltoise', 'user' => 'postgres', 'password' => ''],
    'debug' => true,
];
"@ | Set-Content -Encoding UTF8 $config

# 3. le serveur des bornes, dans sa propre fenêtre (on y lit le journal)
@"
port=5000
db.url=jdbc:postgresql://localhost:$port/voltoise
db.user=postgres
db.password=
"@ | Set-Content -Encoding ASCII $props
Start-Process powershell -ArgumentList "-NoExit", "-Command",
    "`$host.UI.RawUI.WindowTitle='SERVEUR Voltoise'; cd '$dist'; & '$java' -jar serveur-bornes.jar '$props'"

# 4. le site web, dans sa propre fenêtre
Start-Process powershell -ArgumentList "-NoExit", "-Command",
    "`$host.UI.RawUI.WindowTitle='SITE Voltoise'; php -d extension_dir='$phpExt' -d extension=pgsql -d extension=mbstring -S localhost:8000 -t '$sae\web'"

# 5. une fenêtre prête pour jouer la borne
Start-Process powershell -ArgumentList "-NoExit", "-Command",
    "`$host.UI.RawUI.WindowTitle='BORNE'; `$env:PYTHONIOENCODING='utf-8'; cd '$client'; 'python borne.py CGY-01-B01            charge complète'; 'python borne.py CGY-01-B01 --badge 4  badge bloqué'; 'ncat 127.0.0.1 5000                    netcat en client'"

Start-Sleep -Seconds 2
Start-Process "http://localhost:8000/"
""
"Site : http://localhost:8000/  (claire.martin, transports.durand ou admin.voltoise ; mot de passe dans `$env:VOLTOISE_MDP)"
"Pour tout arrêter : arreter_tout.ps1"
