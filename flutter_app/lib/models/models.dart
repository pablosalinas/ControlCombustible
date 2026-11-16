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
