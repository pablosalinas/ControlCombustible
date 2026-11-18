class Vehiculo {
  final int id;
  final int usuarioId;
  final String nombre;
  final String matricula;
  final String marca;
  final String modelo;
  final String tipoCombustible;
  final double kmIniciales;
  final String notas;

  Vehiculo({
    required this.id,
    required this.usuarioId,
    required this.nombre,
    required this.matricula,
    required this.marca,
    required this.modelo,
    required this.tipoCombustible,
    required this.kmIniciales,
    required this.notas,
  });

  factory Vehiculo.fromJson(Map<String, dynamic> j) {
    return Vehiculo(
      id: int.tryParse(j['id'].toString()) ?? 0,
      usuarioId: int.tryParse(j['usuario_id'].toString()) ?? 0,
      nombre: (j['nombre'] ?? '').toString(),
      matricula: (j['matricula'] ?? '').toString(),
      marca: (j['marca'] ?? '').toString(),
      modelo: (j['modelo'] ?? '').toString(),
      tipoCombustible: (j['tipo_combustible'] ?? 'Diésel').toString(),
      kmIniciales: double.tryParse(j['km_iniciales'].toString()) ?? 0.0,
      notas: (j['notas'] ?? '').toString(),
    );
  }
}

class Repostaje {
  final int id;
  final int vehiculoId;
  final String vehiculoNombre;
  final String vehiculoMatricula;
  final String fecha;
  final double litros;
  final double precioLitro;
  final double importeTotal;
  final double kmOdometro;
  final double difKm;
  final double totalLitrosAcum;
  final double totalEurosAcum;
  final double? kmPorLitro;
  final double? litrosPor100;
  final double? kmPorEuro;
  final double? eurosPorKm;
  final double? litrosPorKm;
  final double calculoRepostaje;
  final double difImporte;
  final double? costeMedio100Acum;
  final double? consumoMedioL100Acum;
  final String estacionServicio;
  final String notas;

  Repostaje({
    required this.id,
    required this.vehiculoId,
    required this.vehiculoNombre,
    required this.vehiculoMatricula,
    required this.fecha,
    required this.litros,
    required this.precioLitro,
    required this.importeTotal,
    required this.kmOdometro,
    required this.difKm,
    required this.totalLitrosAcum,
    required this.totalEurosAcum,
    this.kmPorLitro,
    this.litrosPor100,
    this.kmPorEuro,
    this.eurosPorKm,
    this.litrosPorKm,
    required this.calculoRepostaje,
    required this.difImporte,
    this.costeMedio100Acum,
    this.consumoMedioL100Acum,
    required this.estacionServicio,
    required this.notas,
  });

  static double? _toNullableDouble(dynamic v) {
    if (v == null) return null;
    return double.tryParse(v.toString());
  }

  factory Repostaje.fromJson(Map<String, dynamic> j) {
    return Repostaje(
      id: int.tryParse(j['id'].toString()) ?? 0,
      vehiculoId: int.tryParse(j['vehiculo_id'].toString()) ?? 0,
      vehiculoNombre: (j['vehiculo_nombre'] ?? '').toString(),
      vehiculoMatricula: (j['vehiculo_matricula'] ?? '').toString(),
      fecha: (j['fecha'] ?? '').toString(),
      litros: double.tryParse(j['litros'].toString()) ?? 0.0,
      precioLitro: double.tryParse(j['precio_litro'].toString()) ?? 0.0,
      importeTotal: double.tryParse(j['importe_total'].toString()) ?? 0.0,
      kmOdometro: double.tryParse(j['km_odometro'].toString()) ?? 0.0,
      difKm: double.tryParse(j['dif_km'].toString()) ?? 0.0,
      totalLitrosAcum: double.tryParse(j['total_litros_acum'].toString()) ?? 0.0,
      totalEurosAcum: double.tryParse(j['total_euros_acum'].toString()) ?? 0.0,
      kmPorLitro: _toNullableDouble(j['km_por_litro']),
      litrosPor100: _toNullableDouble(j['litros_por_100']),
      kmPorEuro: _toNullableDouble(j['km_por_euro']),
      eurosPorKm: _toNullableDouble(j['euros_por_km']),
      litrosPorKm: _toNullableDouble(j['litros_por_km']),
      calculoRepostaje: double.tryParse(j['calculo_repostaje'].toString()) ?? 0.0,
      difImporte: double.tryParse(j['dif_importe'].toString()) ?? 0.0,
      costeMedio100Acum: _toNullableDouble(j['coste_medio_100_acum']),
      consumoMedioL100Acum: _toNullableDouble(j['consumo_medio_l100_acum']),
      estacionServicio: (j['estacion_servicio'] ?? '').toString(),
      notas: (j['notas'] ?? '').toString(),
    );
  }
}

class EstacionServicio {
  final int id;
  final int usuarioId;
  final String nombre;
  final String marca;
  final String localidad;
  final String direccion;
  final String notas;

  EstacionServicio({
    required this.id,
    required this.usuarioId,
    required this.nombre,
    required this.marca,
    required this.localidad,
    required this.direccion,
    required this.notas,
  });

  factory EstacionServicio.fromJson(Map<String, dynamic> j) {
    return EstacionServicio(
      id: int.tryParse(j['id'].toString()) ?? 0,
      usuarioId: int.tryParse(j['usuario_id'].toString()) ?? 0,
      nombre: (j['nombre'] ?? '').toString(),
      marca: (j['marca'] ?? '').toString(),
      localidad: (j['localidad'] ?? '').toString(),
      direccion: (j['direccion'] ?? '').toString(),
      notas: (j['notas'] ?? '').toString(),
    );
  }
}

class Proveedor {
  final int id;
  final int usuarioId;
  final String tipo; // 'taller', 'aseguradora', 'itv'
  final String nombre;
  final String telefono;
  final String localidad;
  final String direccion;
  final String notas;

  Proveedor({
    required this.id,
    required this.usuarioId,
    required this.tipo,
    required this.nombre,
    required this.telefono,
    required this.localidad,
    required this.direccion,
    required this.notas,
  });

  String get tipoLabel {
    switch (tipo) {
      case 'aseguradora':
        return 'Aseguradora';
      case 'itv':
        return 'ITV';
      default:
        return 'Taller';
    }
  }

  factory Proveedor.fromJson(Map<String, dynamic> j) {
    return Proveedor(
      id: int.tryParse((j['id'] ?? 0).toString()) ?? 0,
      usuarioId: int.tryParse((j['usuario_id'] ?? 0).toString()) ?? 0,
      tipo: (j['tipo'] ?? 'taller').toString(),
      nombre: (j['nombre'] ?? '').toString(),
      telefono: (j['telefono'] ?? '').toString(),
      localidad: (j['localidad'] ?? '').toString(),
      direccion: (j['direccion'] ?? '').toString(),
      notas: (j['notas'] ?? '').toString(),
    );
  }
}

class ResumenEstadisticas {
  final int numRepostajes;
  final double totalLitros;
  final double totalEuros;
  final double totalKmRecorridos;
  final double consumoMaximo;
  final double consumoMinimo;
  final double diferenciaConsumo;
  final double precioMaximo;
  final double precioMinimo;
  final double diferenciaPrecio;
  final double consumoMedioL100;
  final double costeMedio100Km;
  final double eurosPorKmMedio;

  ResumenEstadisticas({
    required this.numRepostajes,
    required this.totalLitros,
    required this.totalEuros,
    required this.totalKmRecorridos,
    required this.consumoMaximo,
    required this.consumoMinimo,
    required this.diferenciaConsumo,
    required this.precioMaximo,
    required this.precioMinimo,
    required this.diferenciaPrecio,
    required this.consumoMedioL100,
    required this.costeMedio100Km,
    required this.eurosPorKmMedio,
  });

  factory ResumenEstadisticas.fromJson(Map<String, dynamic> j) {
    return ResumenEstadisticas(
      numRepostajes: int.tryParse((j['num_repostajes'] ?? 0).toString()) ?? 0,
      totalLitros: double.tryParse((j['total_litros'] ?? 0).toString()) ?? 0.0,
      totalEuros: double.tryParse((j['total_euros'] ?? 0).toString()) ?? 0.0,
      totalKmRecorridos: double.tryParse((j['total_km_recorridos'] ?? 0).toString()) ?? 0.0,
      consumoMaximo: double.tryParse((j['consumo_maximo'] ?? 0).toString()) ?? 0.0,
      consumoMinimo: double.tryParse((j['consumo_minimo'] ?? 0).toString()) ?? 0.0,
      diferenciaConsumo: double.tryParse((j['diferencia_consumo'] ?? 0).toString()) ?? 0.0,
      precioMaximo: double.tryParse((j['precio_maximo'] ?? 0).toString()) ?? 0.0,
      precioMinimo: double.tryParse((j['precio_minimo'] ?? 0).toString()) ?? 0.0,
      diferenciaPrecio: double.tryParse((j['diferencia_precio'] ?? 0).toString()) ?? 0.0,
      consumoMedioL100: double.tryParse((j['consumo_medio_l100'] ?? 0).toString()) ?? 0.0,
      costeMedio100Km: double.tryParse((j['coste_medio_100km'] ?? 0).toString()) ?? 0.0,
      eurosPorKmMedio: double.tryParse((j['euros_por_km_medio'] ?? 0).toString()) ?? 0.0,
    );
  }
}

class TareaMaestro {
  final int id;
  final int usuarioId;
  final String nombre;
  final String descripcion;
  final String tipoPeriodicidad; // 'tiempo', 'kilometros', 'ambos'
  final int? periodoDias;
  final double? periodoKm;
  final bool avisarPorTiempoSiNoKm;

  TareaMaestro({
    required this.id,
    required this.usuarioId,
    required this.nombre,
    required this.descripcion,
    required this.tipoPeriodicidad,
    this.periodoDias,
    this.periodoKm,
    required this.avisarPorTiempoSiNoKm,
  });

  factory TareaMaestro.fromJson(Map<String, dynamic> j) {
    return TareaMaestro(
      id: int.tryParse((j['id'] ?? 0).toString()) ?? 0,
      usuarioId: int.tryParse((j['usuario_id'] ?? 0).toString()) ?? 0,
      nombre: (j['nombre'] ?? '').toString(),
      descripcion: (j['descripcion'] ?? '').toString(),
      tipoPeriodicidad: (j['tipo_periodicidad'] ?? 'ambos').toString(),
      periodoDias: j['periodo_dias'] != null ? int.tryParse(j['periodo_dias'].toString()) : null,
      periodoKm: j['periodo_km'] != null ? double.tryParse(j['periodo_km'].toString()) : null,
      avisarPorTiempoSiNoKm: (j['avisar_por_tiempo_si_no_km'] ?? 1).toString() != '0',
    );
  }
}

class VehiculoTarea {
  final int id;
  final int vehiculoId;
  final int? tareaMaestroId;
  final int? proveedorId;
  final String proveedorNombre;
  final String proveedorTipo;
  final String vehiculoNombre;
  final String vehiculoMatricula;
  final String descripcion;
  final String tipoPeriodicidad;
  final int? periodoDias;
  final double? periodoKm;
  final bool avisarPorTiempoSiNoKm;
  final String ultimaFecha;
  final double? ultimoKm;
  final String proximaFecha;
  final double? proximoKm;
  final String estado; // 'pendiente' | 'finalizada'
  final String fechaFinalizacion;
  final double? kmFinalizacion;
  final double kmActual;
  final double? kmRestantes;
  final int? diasRestantes;
  final bool enAlerta;
  final bool vencida;
  final String motivoAlerta;
  final String notas;
  final double? coste;
  final double? costeAnterior;
  final double? diferenciaCoste;
  final double? porcentajeCoste;
  final int relacionadasCount;
  final int? garantiaMeses;
  final String garantiaHasta;

  VehiculoTarea({
    required this.id,
    required this.vehiculoId,
    this.tareaMaestroId,
    this.proveedorId,
    required this.proveedorNombre,
    required this.proveedorTipo,
    required this.vehiculoNombre,
    required this.vehiculoMatricula,
    required this.descripcion,
    required this.tipoPeriodicidad,
    this.periodoDias,
    this.periodoKm,
    required this.avisarPorTiempoSiNoKm,
    required this.ultimaFecha,
    this.ultimoKm,
    required this.proximaFecha,
    this.proximoKm,
    required this.estado,
    required this.fechaFinalizacion,
    this.kmFinalizacion,
    required this.kmActual,
    this.kmRestantes,
    this.diasRestantes,
    required this.enAlerta,
    required this.vencida,
    required this.motivoAlerta,
    required this.notas,
    this.coste,
    this.costeAnterior,
    this.diferenciaCoste,
    this.porcentajeCoste,
    required this.relacionadasCount,
    this.garantiaMeses,
    required this.garantiaHasta,
  });

  factory VehiculoTarea.fromJson(Map<String, dynamic> j) {
    return VehiculoTarea(
      id: int.tryParse((j['id'] ?? 0).toString()) ?? 0,
      vehiculoId: int.tryParse((j['vehiculo_id'] ?? 0).toString()) ?? 0,
      tareaMaestroId: j['tarea_maestro_id'] != null ? int.tryParse(j['tarea_maestro_id'].toString()) : null,
      proveedorId: j['proveedor_id'] != null ? int.tryParse(j['proveedor_id'].toString()) : null,
      proveedorNombre: (j['proveedor_nombre'] ?? '').toString(),
      proveedorTipo: (j['proveedor_tipo'] ?? '').toString(),
      vehiculoNombre: (j['vehiculo_nombre'] ?? '').toString(),
      vehiculoMatricula: (j['vehiculo_matricula'] ?? '').toString(),
      descripcion: (j['descripcion'] ?? '').toString(),
      tipoPeriodicidad: (j['tipo_periodicidad'] ?? 'ambos').toString(),
      periodoDias: j['periodo_dias'] != null ? int.tryParse(j['periodo_dias'].toString()) : null,
      periodoKm: j['periodo_km'] != null ? double.tryParse(j['periodo_km'].toString()) : null,
      avisarPorTiempoSiNoKm: (j['avisar_por_tiempo_si_no_km'] ?? 1).toString() != '0',
      ultimaFecha: (j['ultima_fecha'] ?? '').toString(),
      ultimoKm: j['ultimo_km'] != null ? double.tryParse(j['ultimo_km'].toString()) : null,
      proximaFecha: (j['proxima_fecha'] ?? '').toString(),
      proximoKm: j['proximo_km'] != null ? double.tryParse(j['proximo_km'].toString()) : null,
      estado: (j['estado'] ?? 'pendiente').toString(),
      fechaFinalizacion: (j['fecha_finalizacion'] ?? '').toString(),
      kmFinalizacion: j['km_finalizacion'] != null ? double.tryParse(j['km_finalizacion'].toString()) : null,
      kmActual: double.tryParse((j['km_actual'] ?? 0).toString()) ?? 0.0,
      kmRestantes: j['km_restantes'] != null ? double.tryParse(j['km_restantes'].toString()) : null,
      diasRestantes: j['dias_restantes'] != null ? int.tryParse(j['dias_restantes'].toString()) : null,
      enAlerta: j['en_alerta'] == true || j['en_alerta'].toString() == '1',
      vencida: j['vencida'] == true || j['vencida'].toString() == '1',
      motivoAlerta: (j['motivo_alerta'] ?? '').toString(),
      notas: (j['notas'] ?? '').toString(),
      coste: j['coste'] != null ? double.tryParse(j['coste'].toString()) : null,
      costeAnterior: j['coste_anterior'] != null ? double.tryParse(j['coste_anterior'].toString()) : null,
      diferenciaCoste: j['diferencia_coste'] != null ? double.tryParse(j['diferencia_coste'].toString()) : null,
      porcentajeCoste: j['porcentaje_coste'] != null ? double.tryParse(j['porcentaje_coste'].toString()) : null,
      relacionadasCount: int.tryParse((j['relacionadas_count'] ?? 0).toString()) ?? 0,
      garantiaMeses: j['garantia_meses'] != null ? int.tryParse(j['garantia_meses'].toString()) : null,
      garantiaHasta: (j['garantia_hasta'] ?? '').toString(),
    );
  }
}
