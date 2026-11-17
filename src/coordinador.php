<?php
declare(strict_types=1);
require_once __DIR__.'/replicacion.php';
/** 2PC didáctico. El llamador mantiene un flock exclusivo durante toda la operación. */
final class Coordinador
{
    private PDO $diario;
    public function __construct(private array $nodos, string $ruta)
    {
        if (count($nodos)<2) throw new RuntimeException('Se requieren al menos dos nodos.');
        $this->diario = new PDO('sqlite:'.$ruta, null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $this->diario->exec('PRAGMA synchronous=FULL');
        $this->diario->exec('CREATE TABLE IF NOT EXISTS tx (id TEXT PRIMARY KEY, hash TEXT NOT NULL,
            topologia TEXT NOT NULL, decision TEXT NOT NULL, completa INTEGER NOT NULL DEFAULT 0)');
    }
    private function consultar(string $id): array|false
    {
        $s=$this->diario->prepare('SELECT * FROM tx WHERE id=?'); $s->execute([$id]);
        return $s->fetch(PDO::FETCH_ASSOC);
    }
    private function decidir(string $id, string $decision): void
    {
        $s=$this->diario->prepare("UPDATE tx SET decision=? WHERE id=? AND decision='INICIO'");
        $s->execute([$decision,$id]); // Autocommit SQLite con synchronous FULL.
    }
    private function topologia(): string
    {
        $t=[];
        foreach ($this->nodos as $id=>$n) $t[$id]=[$n['host'],$n['port']];
        return json_encode($t, JSON_THROW_ON_ERROR);
    }
    public function ejecutar(array $lote, string $fallo=''): void
    {
        $id=$lote['id'] ?? '';
        if (!is_string($id) || !preg_match('/^[a-zA-Z0-9_-]{1,40}$/D',$id)) throw new RuntimeException('ID inválido.');
        if (!isset($lote['operaciones']) || !is_array($lote['operaciones'])) throw new RuntimeException('Lote inválido.');
        $hash=hash('sha256',json_encode($lote,JSON_THROW_ON_ERROR));
        $this->recuperar(); // No iniciar nuevas escrituras con ramas pendientes.
        $anterior=$this->consultar($id);
        if ($anterior) {
            if ($anterior['hash']!==$hash) throw new RuntimeException('ID reutilizado con otro contenido.');
            if ($anterior['decision']==='COMMIT') { echo "$id ya confirmado; no se duplica.\n"; return; }
            throw new RuntimeException('Lote abortado. Corregir causa y usar un ID nuevo para reintentar.');
        }
        $s=$this->diario->prepare("INSERT INTO tx(id,hash,topologia,decision) VALUES(?,?,?,'INICIO')");
        $s->execute([$id,$hash,$this->topologia()]);

        try { $this->preparar($id,$lote['operaciones'],$fallo); }
        catch (Throwable $e) {
            echo "Interrupción: {$e->getMessage()}\n";
            $this->recuperar();
            throw new RuntimeException('Operación interrumpida; revisar decisión en estado.',0,$e);
        }
        $this->recuperar();
    }
    private function preparar(string $id, array $ops, string $fallo): void
    {
        $conexiones=[];
        foreach ($this->nodos as $nombre=>$n) $conexiones[$nombre]=Replicacion::conectar($n);
        $i=0;
        foreach ($conexiones as $nombre=>$p) {
            $x=$p->quote('aca_'.$id);
            $p->exec("XA START $x");
            try {
                Replicacion::aplicar($p,$ops);
                $p->prepare('INSERT INTO tx_aplicada(id) VALUES(?)')->execute([$id]);
            } catch (Throwable $e) {
                $p->exec("XA END $x"); $p->exec("XA ROLLBACK $x"); throw $e;
            }
            $p->exec("XA END $x"); $p->exec("XA PREPARE $x");
            echo "$nombre PREPARED $id\n";
            if (++$i===1 && $fallo==='antes_decision') exit(20);
            if ($i===1 && $fallo==='rechazo') throw new RuntimeException('Voto NO simulado.');
        }

        $this->decidir($id,'COMMIT'); echo "Decisión durable COMMIT $id\n";
        $i=0;
        foreach ($conexiones as $nombre=>$p) {
            $p->exec('XA COMMIT '.$p->quote('aca_'.$id)); echo "$nombre COMMIT $id\n";
            if (++$i===1 && $fallo==='despues_commit') exit(21);
        }
    }
    public function recuperar(): void
    {
        $conexiones=[];
        foreach ($this->nodos as $nombre=>$n) $conexiones[$nombre]=Replicacion::conectar($n);

        $preparadas=[];
        foreach ($conexiones as $nombre=>$p) {
            $preparadas[$nombre]=[];
            foreach ($p->query('XA RECOVER')->fetchAll(PDO::FETCH_ASSOC) as $rama) {
                $x=$rama['data'];
                if ((int)$rama['bqual_length']!==0 || !str_starts_with($x,'aca_')) {
                    throw new RuntimeException("Rama XA ajena en $nombre; revisar manualmente.");
                }
                if (!$this->consultar(substr($x,4))) throw new RuntimeException('Rama sin diario; restaurar diario correcto.');
                $preparadas[$nombre][$x]=true;
            }
            foreach ($p->query('SELECT id FROM tx_aplicada')->fetchAll(PDO::FETCH_COLUMN) as $id) {
                if (!$this->consultar($id)) throw new RuntimeException('Marcador sin diario; no recrear estado vacío.');
            }
        }
        foreach ($this->diario->query('SELECT * FROM tx WHERE completa=0 ORDER BY rowid')->fetchAll(PDO::FETCH_ASSOC) as $tx) {
            $id=$tx['id'];
            if ($tx['topologia']!==$this->topologia()) throw new RuntimeException('Topología modificada con transacción pendiente.');
            if ($tx['decision']==='INICIO') { $this->decidir($id,'ABORT'); $tx['decision']='ABORT'; }
            foreach ($conexiones as $nombre=>$p) {
                $x='aca_'.$id;
                if (isset($preparadas[$nombre][$x])) {
                    $verbo=$tx['decision']==='COMMIT' ? 'COMMIT' : 'ROLLBACK';
                    $p->exec('XA '.$verbo.' '.$p->quote($x));
                }
                $s=$p->prepare('SELECT COUNT(*) FROM tx_aplicada WHERE id=?'); $s->execute([$id]);
                $existe=(int)$s->fetchColumn()===1;
                if ($existe!==($tx['decision']==='COMMIT')) {
                    throw new RuntimeException("Estado incompatible en $nombre para $id; NO compensar automáticamente.");
                }
            }
            $this->diario->prepare('UPDATE tx SET completa=1 WHERE id=?')->execute([$id]);
            echo "$id {$tx['decision']} COMPLETA\n";
        }
    }
    public function estado(): void
    {
        echo json_encode($this->diario->query('SELECT * FROM tx')->fetchAll(PDO::FETCH_ASSOC),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
        foreach ($this->nodos as $nombre=>$n) {
            try { $p=Replicacion::conectar($n); echo $nombre.' XA '.json_encode($p->query('XA RECOVER')->fetchAll(PDO::FETCH_ASSOC))."\n"; }
            catch (Throwable $e) { echo "$nombre NO DISPONIBLE: {$e->getMessage()}\n"; }
        }
    }
    public function verificar(): void
    {
        $this->recuperar();
        $huellas=[];
        foreach ($this->nodos as $nombre=>$n) {
            $p=Replicacion::conectar($n); $tablas=[];
            foreach ($p->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_NUM) as $t) {
                $tabla=$t[0]; $filas=[];
                foreach ($p->query('SELECT * FROM `'.str_replace('`','``',$tabla).'`')->fetchAll(PDO::FETCH_ASSOC) as $fila) {
                    $filas[]=json_encode($fila,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
                }
                sort($filas,SORT_STRING); $tablas[$tabla]=hash('sha256',implode("\n",$filas));
            }
            ksort($tablas); $huellas[$nombre]=hash('sha256',json_encode($tablas,JSON_THROW_ON_ERROR));
            echo "$nombre ".count($tablas)." tablas SHA256 {$huellas[$nombre]}\n";
        }
        if (count(array_unique($huellas))!==1) throw new RuntimeException('DIVERGENCIA: detener escrituras e investigar.');
        echo "COPIAS IGUALES (datos completos; no verifica DDL).\n";
    }
}