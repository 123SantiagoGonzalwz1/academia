<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(403); exit('Solo CLI'); }
require __DIR__.'/src/coordinador.php';
$lock=fopen(__DIR__.'/estado/coordinador.lock','c');
if (!$lock || !flock($lock,LOCK_EX|LOCK_NB)) { fwrite(STDERR,"Otro coordinador está activo.\n"); exit(2); }
try {
    $c=new Coordinador(require __DIR__.'/config/nodos.php',__DIR__.'/estado/diario.sqlite');
    $cmd=$argv[1] ?? 'ayuda';
    switch ($cmd) {
      case 'aplicar':
        $ruta=$argv[2] ?? ''; if (!is_file($ruta)) throw new RuntimeException('Indicar archivo JSON.');
        $fallo=$argv[3] ?? '';
        if (!in_array($fallo,['','rechazo','antes_decision','despues_commit'],true)) throw new RuntimeException('Fallo desconocido.');
        $c->ejecutar(json_decode(file_get_contents($ruta),true,512,JSON_THROW_ON_ERROR),$fallo); break;
      case 'recuperar': $c->recuperar(); break;
      case 'verificar': $c->verificar(); break;
      case 'estado': $c->estado(); break;
      default: echo "Uso: php academia.php aplicar archivo.json [rechazo|antes_decision|despues_commit]\n";
        echo "     php academia.php recuperar|verificar|estado\n";
    }
} catch (Throwable $e) { fwrite(STDERR,'ERROR: '.$e->getMessage()."\n"); exit(1); }
finally { flock($lock,LOCK_UN); fclose($lock); }