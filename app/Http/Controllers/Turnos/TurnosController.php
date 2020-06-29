<?php

namespace App\Http\Controllers\Turnos;
use Illuminate\Support\Facades\DB; 
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;

  

class TurnosController extends ApiController
{
    
    var $hora_desde;
    var $hora_hasta;

    function __construct()
    {
        $this->hora_desde = '03:00:00';
        $this->hora_hasta = '02:59:59';
    }


    public function setNumero(Request $request)
    {
      
    // obtengo el proximo numero para el sector
    // valido que tenga algun dato el arreglo
    $proximo = $this->obtenerUltimoNumero($request->sector_id);
    if (count($proximo)>0) {
        $proximo_numero = $proximo[0]['proximo'];
    } else {
        $proximo_numero = 1;
    }
     try {

        $id = DB::table('numero')->insertGetId([
            'numero' => $proximo_numero, 
            'sector_id' => $request->sector_id,        
            'estado' => 'PENDIENTE',    
            'fecha_creacion' =>  date("Y-m-d H:i:s")
        ]);    
            
        //DEVUELVO EL ESTADO DEL NUMERO actual
        if ($id) {
            $actual = $this->obtenerUltimoNumero($request->sector_id);

            return response()->json($actual, "200");
        } else {
            return  response()->json('NO SE PUDO CREAR EL TURNO', "500");
        }
     } catch (\Throwable $th) {
         return  response()->json('NO SE PUDO CREAR EL TURNO ERROR :'. $th, "500");
     }
   
    }

/* -------------------------------------------------------------------------- */
/*              OBTENGO EL PROXIMO NUMERO A LLAMAR PARA UN SECTOR             */
/* -------------------------------------------------------------------------- */

    private function obtenerUltimoNumero($sector_id){

         $tomorrow = date("Y-m-d", strtotime("+1 day"));
         $hoy = date("Y-m-d");
         $fecha_desde =   date('Y-m-d H:i:s', strtotime("$hoy $this->hora_desde"));
         $fecha_hasta =   date('Y-m-d H:i:s', strtotime("$tomorrow $this->hora_hasta"));

        $res = DB::select( DB::raw(
        "SELECT numero.id,numero.numero, (numero.numero +1) AS proximo, numero.fecha_creacion, numero.llamando, numero.atendido, numero.estado , 
        numero.sector_usuario_id,sector.id AS sector_id, sector.sector_nombre, sector.sector_abreviado 
        FROM numero, sector 
        WHERE numero.sector_id = sector.id AND sector.estado = 'ACTIVO' AND sector_id = :sector_id AND  numero.fecha_creacion BETWEEN :fecha_desde AND :fecha_hasta ORDER by numero.numero DESC LIMIT 1
       "), array(                       
            'sector_id' => $sector_id,
            'fecha_desde' => $fecha_desde,
            'fecha_hasta' => $fecha_hasta
          ));
    
          $resultArray = json_decode(json_encode($res), true);
          return $resultArray;
    }



/* -------------------------------------------------------------------------- */
/*              OBTENGO EL PROXIMO NUMERO A LLAMAR PARA UN SECTOR  QUE ESTA PENDIENTE           */
/* -------------------------------------------------------------------------- */

private function obtenerUltimoNumeroBySector($sector_usuario_id){

    $tomorrow = date("Y-m-d", strtotime("+1 day"));
    $hoy = date("Y-m-d");
    $fecha_desde =   date('Y-m-d H:i:s', strtotime("$hoy $this->hora_desde"));
    $fecha_hasta =   date('Y-m-d H:i:s', strtotime("$tomorrow $this->hora_hasta"));

   $res = DB::select( DB::raw(
   "SELECT numero.id,numero.numero, (numero.numero +1) AS proximo, numero.fecha_creacion, numero.llamando, numero.atendido, numero.estado , 
   sector_usuario.id AS sector_usuario_id,sector.id AS sector_id, sector.sector_nombre, sector.sector_abreviado , sector_usuario.puesto_defecto ,  users.id as usuario_id, sector_usuario.id as sector_usuario_id
   FROM numero, sector ,sector_usuario , users
   WHERE numero.sector_id = sector.id 
   AND sector.estado = 'ACTIVO' 
   AND sector_usuario.usuario_id = :sector_usuario_id
   AND numero.estado ='PENDIENTE' 
   AND sector_usuario.sector_id = sector.id 
   AND sector_usuario.usuario_id = users.id
   AND  numero.fecha_creacion BETWEEN :fecha_desde AND :fecha_hasta ORDER BY numero.fecha_creacion ASC 
  "), array(                       
       'sector_usuario_id' => $sector_usuario_id,
       'fecha_desde' => $fecha_desde,
       'fecha_hasta' => $fecha_hasta
     ));

     $resultArray = json_decode(json_encode($res), true);
     return $resultArray;
}




/* -------------------------------------------------------------------------- */
/*              OBTENGO EL PROXIMO NUMERO A LLAMAR PARA UN SECTOR  QUE ESTA PENDIENTE           */
/* -------------------------------------------------------------------------- */

private function obtenerUltimoNumeroBySectorAsociado($sector_usuario_id){

    $tomorrow = date("Y-m-d", strtotime("+1 day"));
    $hoy = date("Y-m-d");
    $fecha_desde =   date('Y-m-d H:i:s', strtotime("$hoy $this->hora_desde"));
    $fecha_hasta =   date('Y-m-d H:i:s', strtotime("$tomorrow $this->hora_hasta"));

   $res = DB::select( DB::raw(
   "SELECT numero.id,numero.numero, (numero.numero +1) AS proximo, numero.fecha_creacion, numero.llamando, numero.atendido, numero.estado , 
   sector_usuario.id AS sector_usuario_id,sector.id AS sector_id, sector.sector_nombre, sector.sector_abreviado, sector_usuario.puesto_defecto,
     users.id as usuario_id, sector_usuario.id as sector_usuario_id
   FROM numero, sector, sector_usuario, sector_usuario_asociado, users 
   WHERE numero.sector_id = sector.id AND numero.sector_id = sector_usuario_asociado.sector_id 
   AND sector_usuario_asociado.sector_usuario_id = sector_usuario.id 
   AND sector_usuario.usuario_id = users.id 
   AND sector.estado = 'ACTIVO'  
   AND numero.estado ='PENDIENTE' 
   AND numero.sector_id != sector_usuario.sector_id 
   AND sector_usuario.id = :sector_usuario_id
   AND  numero.fecha_creacion BETWEEN :fecha_desde AND :fecha_hasta ORDER BY   numero.fecha_creacion ASC 
  "), array(                       
       'sector_usuario_id' => $sector_usuario_id,
       'fecha_desde' => $fecha_desde,
       'fecha_hasta' => $fecha_hasta
     ));

     $resultArray = json_decode(json_encode($res), true);
     return $resultArray;
}






    public function llamar(Request $request) {
        
        $sector_usuario_id = $request->input('sector_usuario_id');

        // VERIFICO SI HAY NUMERO PARA EL SECTOR
        $proximo = $this->obtenerUltimoNumeroBySector($sector_usuario_id);      
        // SI OBTENGO EL NUMERO ACTUALIZO EL ANTERIOR AL QUE LLAME Y LO COLOCO ATENDIDO CON SU HORA 
        if($proximo){

        } else {
        // SI NO HAY PARA EL SECTOR PIDO SECTORES ASOCIADOS 
        $proximo = $this->obtenerUltimoNumeroBySectorAsociado($sector_usuario_id);
     //   echo $proximo[0]['id'];
        }
      

        // VERIFICO REGLAS

        //ACTUALIZO EL LLAMADO Y DEVUELVO
       
        return response()->json($proximo, "200");
    }
    
}