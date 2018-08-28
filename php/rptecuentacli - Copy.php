<?php
require 'vendor/autoload.php';
require_once 'db.php';

header('Content-Type: application/json');

$app = new \Slim\Slim();
$app->response->headers->set('Content-Type', 'application/json');

$app->post('/rptecuentacli', function(){

	//echo "entro a la app";

    $d = json_decode(file_get_contents('php://input'));

    //$d = $d->data;

	//var_dump($d);
	
    try{
		//echo 'entro al try';
		
        $db = new dbcpm();

        $sqlh = "having saldo<>0";
        $sqlwhr = "";
        if(!empty($d->clistr)){
            $sqlwhr = "where a.id = ".$d->clistr;
        }

        /*if($d->detalle == 1){
            $sqlh = "";
        }*/

        $qrmoneda = "select distinct a.idmoneda, b.nommoneda,b.simbolo from sayet.compra a inner join sayet.moneda b on a.idmoneda=b.id";

        $mnd = $db->getQuery($qrmoneda);
        $idarraymnd = 0;
        $detmon = array();

        foreach($mnd as $dmon) {
            $idarraymnd++;
            $msumsaldo = 0.00;

            $querydet1 = "SELECT a.nombre,b.venta,b.factura,b.serie,b.fecha,
                    round(b.monto,2) as saldo,round(b.totalfac,2) as totalfac, b.retisr , substr(b.concepto,1,31) as concepto, b.contrato, b.proyecto, b.nomproyecto, b.apagar
                from sayet.cliente a
                inner join (

                    select a.orden,a.cliente,a.venta,a.fecha,a.factura,a.serie,
                        a.concepto,(a.monto-(ifnull(sum(b.monto),0)+a.retisr)) as monto,a.codigo,a.tc_cambio,a.fecpago,a.dias,a.monto as totalfac,a.retisr,a.contrato,a.proyecto,a.nomproyecto,
						(a.monto-a.retisr) as apagar
                    from (
                        SELECT 1 as orden,c.idcliente as cliente,c.id as venta,c.fecha,c.numero as factura,c.serie,c.conceptomayor as concepto,
                            (c.total) as monto,e.simbolo as codigo,c.tipocambio as tc_cambio,
                            if(c.fechapago is not null, c.fechapago,c.fecha) as fecpago,datediff('" . $d->falstr . "',if(c.fechapago is not null, c.fechapago,c.fecha)) as dias,
							c.retisr, a.id as contrato, b.id as proyecto, b.nomproyecto
                        from sayet.factura c
                            inner join sayet.moneda e on c.idmoneda=e.id
							left join sayet.contrato a on c.idcontrato=a.id
                            left join sayet.proyecto b on b.id=a.idproyecto
                        where c.anulada=0
                            and c.fecha<='" . $d->falstr . "'
                            and c.idmoneda = " . $dmon->idmoneda . "
                            and c.idempresa=".$d->idempresa;
            
			$querydet2 = " order by c.fecha
                    ) as a
                    left join(
                        select orden,cliente,venta,fecha,documento,tipo,monto,codigo,tc_cambio from (

                            SELECT 2 as orden,a.idcliente as cliente,a.id as venta,c.fecha,d.numero as documento,'R' as tipo, (b.monto) as monto,
                                'Q' as codigo,a.tipocambio as tc_cambio
                            from sayet.factura a
                                inner join sayet.detcobroventa b on a.id=b.idfactura
                                inner join sayet.recibocli c on b.idrecibocli=c.id
                                left join sayet.tranban d on c.idtranban=d.id
                            where c.anulado=0
                                and c.fecha<='" . $d->falstr . "'
                                and a.idmoneda = " . $dmon->idmoneda . "
                                and a.idempresa=".$d->idempresa."
                        ) as b
                    ) as b on a.venta=b.venta
                    group by a.venta order by a.venta
                ) as b on a.id=b.cliente " . $sqlwhr . "
                group by a.id,b.venta " . $sqlh . " order by a.nombre,b.fecha,b.serie,b.factura";

			
			$query = "Select distinct proyecto, nomproyecto from (".$querydet1.$querydet2.") as a order by nomproyecto;";
			
			//echo $query;
			
			$prycli = $db->getQuery($query);
			
			$detpry = array();
			
			$idpryarray = 0;
			
			foreach ($prycli as $pyc) {
				
				$idpryarray++;
			
				$queryproy = " and b.id = ".$pyc->proyecto;
				
				$query = $querydet1.$queryproy.$querydet2;
				
				//echo $query;

				$ancl = $db->getQuery($query);

				$detrepo = array();
				$det = array();
				$detfac = array();
				$ultnom = '';
				$idarray = 0;
				$sumasaldo = 0.00;

				foreach ($ancl as $hac) {

					if ($hac->nombre != $ultnom) {
						$idarray++;
						$ultnom = $hac->nombre;

						$det = array();

						$sumasaldo = 0.00;
					}

					$sumasaldo += $hac->saldo;
					$msumsaldo += $hac->saldo;

					if ($d->detalle == 1) {
						$detfac = array();

						$qdetpago = "SELECT a.idcliente as cliente,c.id as venta,c.fecha,if(d.numero is null,c.id,d.numero) as documento,if(d.tipotrans is null,'P',d.tipotrans) as tipotrans, round((b.monto*a.tipocambio),2) as monto
								from sayet.factura a
									inner join sayet.detcobroventa b on a.id=b.idfactura
									inner join sayet.recibocli c on b.idrecibocli=c.id
									left join sayet.tranban d on c.idtranban=d.id
								where c.anulado=0
									and c.fecha<='" . $d->falstr . "' and a.id=" . $hac->venta . " and a.idmoneda = " . $dmon->idmoneda . " and a.idempresa=".$d->idempresa."";

						$qdfac = $db->getQuery($qdetpago);
						//echo $qdetpago;
						foreach ($qdfac as $row) {

							//var_dump($row);

							array_push($detfac,
								array(
									'monto' => $row->monto,
									'venta' => $row->venta,
									'tipotrans' => $row->tipotrans,
									'documento' => $row->documento,
									'fecha' => $row->fecha
								)
							);
						}
						//$detfac = $db->doSelectASJson($qdetpago);
					}

					array_push($det,
						array(
							'factura' => $hac->factura,
							'serie' => $hac->serie,
							'fecha' => $hac->fecha,
							'saldo' => $hac->saldo,
							'totalfac' => $hac->totalfac,
							'dfac' => $detfac,
							'isr' => $hac->retisr,
							'concepto' => $hac->concepto,
							'apagar' => $hac->apagar
						)
					);
					//
					if ($idarray > 0) {
						$detrepo[$idarray] = [
							'nombre' => $hac->nombre,
							'tsaldo' => $sumasaldo,
							'dec' => $det
						];
					}
				}
				
				if ($idpryarray > 0) {
					array_push($detpry,
						array(
							'idproyecto' => $pyc->proyecto,
							'nomproyecto' => $pyc->nomproyecto,
							'dproy' => $detrepo
						)
					);
					/*$detpry[] = [
						'idproyecto' => $pyc->proyecto,
						'nomproyecto' => $pyc->nomproyecto,
						'dproy' => $detrepo
					];*/
				}
				
			}
			
            if ($idarraymnd > 0) {
                $detmon[$idarraymnd] = [
                    'idmoneda' => $dmon->idmoneda,
                    'moneda' => $dmon->nommoneda,
                    'simbolo' => $dmon->simbolo,
                    'saldo' => round($msumsaldo, 2),
                    'dmon' => $detpry
                ];
            }
        }

        $strjson = array();
        foreach($detmon as $rdet){
            array_push($strjson,$rdet);
            //$strjson .= json_encode($rdet);
        }

        print json_encode($strjson);
        //print '['.json_encode($detrepo[]).']';
        //print $detrepo;

    }catch(Exception $e){
        $error = "Mensaje: ".$e->getMessage()." -- Linea: ".$e->getLine()." -- Objeto: ".json_encode($d);
        $query = "SELECT '".$error."' AS nombre, 0 AS vigente, 0 AS a15, 0 AS a30, 0 AS a60, 0 AS a90, 0 AS total";
        print $db->doSelectASJson($query);
    }
});

$app->run();