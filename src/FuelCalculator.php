<?php
/**
 * src/FuelCalculator.php
 * Motor de cálculo de consumos y gastos referenciado al repostaje anterior y actual.
 * Compatible con PHP 5.6, 7.x y 8.x.
 * Reproduce con precisión matemática todas las fórmulas de la hoja de cálculo original
 * y permite agregar resultados de múltiples vehículos (vista consolidada).
 */

class FuelCalculator {

    /**
     * Procesa una lista de repostajes activos de un vehículo (ordenados cronológicamente por km/fecha)
     * y añade todas las columnas calculadas referenciadas al repostaje anterior.
     *
     * @param array $rows Registros de veh_repostajes de un mismo vehículo ordenados ASC
     * @return array Lista enriquecida + resumen estadístico del vehículo
     */
    public static function calculateVehicleSeries(array $rows) {
        $enriched = array();
        $totalLitrosAcum = 0.0;
        $totalEurosAcum  = 0.0;

        $consumos100 = array();
        $precios     = array();
        $kmInicial   = null;
        $kmFinal     = null;
        $totalKmRecorridos = 0.0;
        $litrosConsumidosTramos = 0.0;

        $count = count($rows);
        for ($i = 0; $i < $count; $i++) {
            $r = $rows[$i];
            $litros  = (float)$r['litros'];
            $precio  = (float)$r['precio_litro'];
            $importe = (float)$r['importe_total'];
            $km      = (float)$r['km_odometro'];

            if ($kmInicial === null) {
                $kmInicial = $km;
            }
            $kmFinal = $km;

            $totalLitrosAcum += $litros;
            $totalEurosAcum  += $importe;

            if ($precio > 0) {
                $precios[] = $precio;
            }

            $calculoRepostaje = $litros * $precio;
            $difImporte       = $importe - $calculoRepostaje;

            $difKm        = 0.0;
            $kmPorLitro   = null;
            $litrosPor100 = null;
            $kmPorEuro    = null;
            $eurosPorKm   = null;
            $litrosPorKm  = null;

            if ($i > 0) {
                $prev = $rows[$i - 1];
                $prevKm      = (float)$prev['km_odometro'];
                $prevLitros  = (float)$prev['litros'];
                $prevPrecio  = (float)$prev['precio_litro'];
                $prevImporte = (float)$prev['importe_total'];

                $difKm = $km - $prevKm;
                if ($difKm > 0 && $prevLitros > 0) {
                    $totalKmRecorridos      += $difKm;
                    $litrosConsumidosTramos += $prevLitros;

                    $kmPorLitro   = $difKm / $prevLitros;
                    $litrosPorKm  = $prevLitros / $difKm;
                    $litrosPor100 = $litrosPorKm * 100.0;
                    $kmPorEuro    = ($prevImporte > 0) ? ($difKm / $prevImporte) : null;
                    $eurosPorKm   = $litrosPorKm * $prevPrecio;

                    $consumos100[] = $litrosPor100;
                }
            }

            $baseKm = ($kmInicial !== null) ? $kmInicial : 0.0;
            $distanciaDesdeInicio = ($km - $baseKm);
            $costeMedio100Acum = ($distanciaDesdeInicio > 0)
                ? (($totalEurosAcum / $distanciaDesdeInicio) * 100.0)
                : null;
            $consumoMedioL100Acum = ($distanciaDesdeInicio > 0 && $litrosConsumidosTramos > 0)
                ? (($litrosConsumidosTramos / $distanciaDesdeInicio) * 100.0)
                : null;

            $enriched[] = array(
                'id'                      => (int)$r['id'],
                'vehiculo_id'             => (int)$r['vehiculo_id'],
                'vehiculo_nombre'         => isset($r['vehiculo_nombre']) ? $r['vehiculo_nombre'] : '',
                'vehiculo_matricula'      => isset($r['vehiculo_matricula']) ? $r['vehiculo_matricula'] : '',
                'fecha'                   => $r['fecha'],
                'litros'                  => round($litros, 3),
                'precio_litro'            => round($precio, 4),
                'importe_total'           => round($importe, 2),
                'km_odometro'             => round($km, 2),
                'dif_km'                  => round($difKm, 2),
                'total_litros_acum'       => round($totalLitrosAcum, 3),
                'total_euros_acum'        => round($totalEurosAcum, 2),
                'km_por_litro'            => $kmPorLitro !== null ? round($kmPorLitro, 4) : null,
                'litros_por_100'          => $litrosPor100 !== null ? round($litrosPor100, 4) : null,
                'km_por_euro'             => $kmPorEuro !== null ? round($kmPorEuro, 4) : null,
                'euros_por_km'            => $eurosPorKm !== null ? round($eurosPorKm, 6) : null,
                'litros_por_km'           => $litrosPorKm !== null ? round($litrosPorKm, 6) : null,
                'calculo_repostaje'       => round($calculoRepostaje, 4),
                'dif_importe'             => round($difImporte, 4),
                'coste_medio_100_acum'    => $costeMedio100Acum !== null ? round($costeMedio100Acum, 4) : null,
                'consumo_medio_l100_acum' => $consumoMedioL100Acum !== null ? round($consumoMedioL100Acum, 4) : null,
                'estacion_servicio'       => isset($r['estacion_servicio']) ? $r['estacion_servicio'] : null,
                'notas'                   => isset($r['notas']) ? $r['notas'] : null,
                'foto_ticket_path'        => isset($r['foto_ticket_path']) ? $r['foto_ticket_path'] : null,
                'foto_odometro_path'      => isset($r['foto_odometro_path']) ? $r['foto_odometro_path'] : null,
            );
        }

        $consumoMax = !empty($consumos100) ? max($consumos100) : 0.0;
        $consumoMin = !empty($consumos100) ? min($consumos100) : 0.0;
        $precioMax  = !empty($precios) ? max($precios) : 0.0;
        $precioMin  = !empty($precios) ? min($precios) : 0.0;

        $consumoMedioPonderado = ($totalKmRecorridos > 0)
            ? (($litrosConsumidosTramos / $totalKmRecorridos) * 100.0)
            : 0.0;
        $consumoMedioAritmetico = !empty($consumos100)
            ? (array_sum($consumos100) / count($consumos100))
            : 0.0;
        $costeMedio100Global = ($totalKmRecorridos > 0)
            ? (($totalEurosAcum / $totalKmRecorridos) * 100.0)
            : 0.0;
        $costePorKmGlobal = ($totalKmRecorridos > 0)
            ? ($totalEurosAcum / $totalKmRecorridos)
            : 0.0;

        $summary = array(
            'num_repostajes'           => $count,
            'total_litros'             => round($totalLitrosAcum, 2),
            'total_euros'              => round($totalEurosAcum, 2),
            'km_inicial'               => round($kmInicial !== null ? $kmInicial : 0.0, 2),
            'km_actual'                => round($kmFinal !== null ? $kmFinal : 0.0, 2),
            'total_km_recorridos'      => round($totalKmRecorridos, 2),
            'consumo_maximo'           => round($consumoMax, 4),
            'consumo_minimo'           => round($consumoMin, 4),
            'diferencia_consumo'       => round($consumoMax - $consumoMin, 4),
            'precio_maximo'            => round($precioMax, 4),
            'precio_minimo'            => round($precioMin, 4),
            'diferencia_precio'        => round($precioMax - $precioMin, 4),
            'consumo_medio_l100'       => round($consumoMedioPonderado, 4),
            'consumo_medio_aritmetico' => round($consumoMedioAritmetico, 4),
            'coste_medio_100km'        => round($costeMedio100Global, 4),
            'euros_por_km_medio'       => round($costePorKmGlobal, 4),
        );

        return array(
            'repostajes' => $enriched,
            'resumen'    => $summary,
        );
    }

    /**
     * Calcula el resultado conjunto (consolidado) integrando múltiples vehículos de un usuario.
     *
     * @param array $rowsByVehicle Mapa [vehiculo_id => array de filas ordenadas ASC]
     * @return array Resultado consolidado + desglose por vehículo
     */
    public static function calculateConsolidatedFleet(array $rowsByVehicle) {
        $allRepostajes = array();
        $perVehicleSummaries = array();

        $totalRepostajes = 0;
        $totalLitros     = 0.0;
        $totalEuros      = 0.0;
        $totalKm         = 0.0;
        $totalLitrosTramos = 0.0;

        $allConsumos = array();
        $allPrecios  = array();

        foreach ($rowsByVehicle as $vehId => $rows) {
            $calc = self::calculateVehicleSeries($rows);
            $perVehicleSummaries[$vehId] = $calc['resumen'];
            $totalRepostajes += $calc['resumen']['num_repostajes'];
            $totalLitros     += $calc['resumen']['total_litros'];
            $totalEuros      += $calc['resumen']['total_euros'];
            $totalKm         += $calc['resumen']['total_km_recorridos'];

            foreach ($calc['repostajes'] as $idx => $rep) {
                $allRepostajes[] = $rep;
                if ($rep['precio_litro'] > 0) {
                    $allPrecios[] = (float)$rep['precio_litro'];
                }
                if ($rep['litros_por_100'] !== null) {
                    $allConsumos[] = (float)$rep['litros_por_100'];
                }
                if ($idx > 0 && $rep['dif_km'] > 0) {
                    $totalLitrosTramos += (float)$calc['repostajes'][$idx - 1]['litros'];
                }
            }
        }

        // Ordenar todos los repostajes por fecha DESC, km DESC para vista unificada
        usort($allRepostajes, function ($a, $b) {
            $cmp = strcmp($b['fecha'], $a['fecha']);
            if ($cmp !== 0) return $cmp;
            if ($b['km_odometro'] == $a['km_odometro']) return 0;
            return ($b['km_odometro'] > $a['km_odometro']) ? 1 : -1;
        });

        $consumoMax = !empty($allConsumos) ? max($allConsumos) : 0.0;
        $consumoMin = !empty($allConsumos) ? min($allConsumos) : 0.0;
        $precioMax  = !empty($allPrecios) ? max($allPrecios) : 0.0;
        $precioMin  = !empty($allPrecios) ? min($allPrecios) : 0.0;

        $consumoMedioPonderado = ($totalKm > 0) ? (($totalLitrosTramos / $totalKm) * 100.0) : 0.0;
        $consumoMedioAritmetico = !empty($allConsumos) ? (array_sum($allConsumos) / count($allConsumos)) : 0.0;
        $costeMedio100Global = ($totalKm > 0) ? (($totalEuros / $totalKm) * 100.0) : 0.0;
        $costePorKmGlobal = ($totalKm > 0) ? ($totalEuros / $totalKm) : 0.0;

        return array(
            'repostajes' => $allRepostajes,
            'resumen_consolidado' => array(
                'num_vehiculos'            => count($rowsByVehicle),
                'num_repostajes'           => $totalRepostajes,
                'total_litros'             => round($totalLitros, 2),
                'total_euros'              => round($totalEuros, 2),
                'total_km_recorridos'      => round($totalKm, 2),
                'consumo_maximo'           => round($consumoMax, 4),
                'consumo_minimo'           => round($consumoMin, 4),
                'diferencia_consumo'       => round($consumoMax - $consumoMin, 4),
                'precio_maximo'            => round($precioMax, 4),
                'precio_minimo'            => round($precioMin, 4),
                'diferencia_precio'        => round($precioMax - $precioMin, 4),
                'consumo_medio_l100'       => round($consumoMedioPonderado, 4),
                'consumo_medio_aritmetico' => round($consumoMedioAritmetico, 4),
                'coste_medio_100km'        => round($costeMedio100Global, 4),
                'euros_por_km_medio'       => round($costePorKmGlobal, 4),
            ),
            'resumen_por_vehiculo' => $perVehicleSummaries,
        );
    }
}
