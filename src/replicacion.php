<?php
declare(strict_types=1);

final class Replicacion
{
    public static function conectar(array $nodo): PDO
    {
        $p = new PDO("mysql:host={$nodo['host']};port={$nodo['port']};dbname=academia;charset=utf8mb4",
            $nodo['user'], $nodo['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES=>false, PDO::ATTR_TIMEOUT=>5,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS=>false]);
        $version = (string)$p->query('SELECT VERSION()')->fetchColumn();
        if (!str_contains($version, 'MariaDB') || version_compare($version, '10.5.2', '<')) {
            throw new RuntimeException('Se requiere MariaDB >= 10.5.2; usar 10.11 o posterior.');
        }
        $p->exec('SET SESSION TRANSACTION ISOLATION LEVEL SERIALIZABLE');
        $p->exec('SET SESSION innodb_lock_wait_timeout=5');
        $p->exec("SET SESSION sql_mode='STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        return $p;
    }
    public static function aplicar(PDO $p, array $operaciones): void
    {
        foreach ($operaciones as $op) {

            if (!preg_match('/^\s*(INSERT|UPDATE|DELETE)\s/i', $op['sql'] ?? '')) {
                throw new RuntimeException('El lote solo admite DML; no DDL ni control transaccional.');
            }
            $p->prepare($op['sql'])->execute($op['params'] ?? []);
        }

        $reglas = [
          'Tutor sin asignatura troncal' => "SELECT COUNT(*) FROM grupo g WHERE NOT EXISTS
            (SELECT 1 FROM docencia d JOIN asignatura a ON a.codigo=d.asignatura
             WHERE d.grupo=g.codigo AND d.profesor=g.tutor AND a.tipo='TRONCAL')",
          'Troncal asignada a otro profesor' => "SELECT COUNT(*) FROM docencia d JOIN grupo g ON g.codigo=d.grupo
            JOIN asignatura a ON a.codigo=d.asignatura WHERE a.tipo='TRONCAL' AND d.profesor<>g.tutor",
          'Selección sin llamadas, entrevista o tres evaluaciones' => "SELECT COUNT(*) FROM proceso p
            WHERE p.estado<>'ABIERTO' AND (NOT EXISTS(SELECT 1 FROM llamada l WHERE l.proceso=p.id)
            OR NOT EXISTS(SELECT 1 FROM entrevista e WHERE e.proceso=p.id)
            OR (SELECT COUNT(*) FROM evaluacion v WHERE v.proceso=p.id)<>3)",
        ];
        foreach ($reglas as $mensaje=>$sql) {
            if ((int)$p->query($sql)->fetchColumn() !== 0) throw new RuntimeException($mensaje);
        }
    }
}