<?php
// Accès à PostgreSQL avec l'extension pgsql. Toute requête passe par pg_query_params :
// les valeurs voyagent à part du texte SQL, une saisie ne peut donc pas devenir du SQL.
defined('VOLTOISE') or exit;

class BaseIndisponible extends RuntimeException {}

function db()
{
    static $cnx = null;
    if ($cnx === null) {
        $c = $GLOBALS['config']['db'];
        $chaine = sprintf("host='%s' port='%s' dbname='%s' user='%s' password='%s' connect_timeout=5",
            addcslashes($c['host'], "'\\"), addcslashes($c['port'], "'\\"),
            addcslashes($c['dbname'], "'\\"), addcslashes($c['user'], "'\\"),
            addcslashes($c['password'], "'\\"));
        $cnx = @pg_connect($chaine);
        if ($cnx === false) {
            throw new BaseIndisponible('connexion PostgreSQL impossible');
        }
        pg_set_client_encoding($cnx, 'UTF8');
    }
    return $cnx;
}

function db_requete($sql, array $params = [])
{
    $res = @pg_query_params(db(), $sql, $params);
    if ($res === false) {
        throw new RuntimeException('SQL : ' . pg_last_error(db()));
    }
    return $res;
}

function db_lignes($sql, array $params = [])
{
    $res = db_requete($sql, $params);
    $lignes = pg_fetch_all($res) ?: [];
    pg_free_result($res);
    return $lignes;
}

function db_ligne($sql, array $params = [])
{
    $res = db_requete($sql, $params);
    $ligne = pg_fetch_assoc($res);
    pg_free_result($res);
    return $ligne === false ? null : $ligne;
}

function db_valeur($sql, array $params = [])
{
    $ligne = db_ligne($sql, $params);
    return $ligne === null ? null : reset($ligne);
}

// Pour INSERT / UPDATE / DELETE : renvoie le nombre de lignes touchées.
function db_executer($sql, array $params = [])
{
    $res = db_requete($sql, $params);
    $n = pg_affected_rows($res);
    pg_free_result($res);
    return $n;
}

// Plusieurs ordres tout ou rien : si l'un échoue, aucun n'est gardé.
function db_transaction(callable $travail)
{
    db_requete('BEGIN');
    try {
        $resultat = $travail();
        db_requete('COMMIT');
        return $resultat;
    } catch (Throwable $e) {
        @pg_query(db(), 'ROLLBACK');
        throw $e;
    }
}

// PostgreSQL renvoie les booléens sous forme de texte 't' / 'f'.
function db_bool($valeur)
{
    return $valeur === 't' || $valeur === true;
}
