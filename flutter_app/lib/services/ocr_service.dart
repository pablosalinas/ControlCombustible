import 'package:google_mlkit_text_recognition/google_mlkit_text_recognition.dart';
import 'package:image_picker/image_picker.dart';

class DetectedStation {
  final String nombre;
  final String marca;
  final String cif;
  final String direccion;
  final String localidad;

  DetectedStation({
    required this.nombre,
    this.marca = '',
    this.cif = '',
    this.direccion = '',
    this.localidad = '',
  });
}

class TicketScanResult {
  final String? fecha;
  final double? litros;
  final double? precioLitro;
  final double? importeTotal;
  final DetectedStation? estacion;
  final String? imagePath;
  final String rawText;

  TicketScanResult({
    this.fecha,
    this.litros,
    this.precioLitro,
    this.importeTotal,
    this.estacion,
    this.imagePath,
    required this.rawText,
  });
}

class OdometerScanResult {
  final double? km;
  final String? imagePath;

  OdometerScanResult({this.km, this.imagePath});
}

class OcrService {
  final ImagePicker _picker = ImagePicker();

  /// Captura una fotografía o imagen de un ticket/factura de combustible
  /// y extrae automáticamente Fecha, Litros, Precio/L e Importe Total.
  Future<TicketScanResult?> scanFuelTicket({ImageSource source = ImageSource.camera}) async {
    final XFile? image = await _picker.pickImage(source: source, imageQuality: 90);
    if (image == null) return null;

    final inputImage = InputImage.fromFilePath(image.path);
    final recognizer = TextRecognizer(script: TextRecognitionScript.latin);

    try {
      final RecognizedText recognized = await recognizer.processImage(inputImage);
      final String rawText = recognized.text;
      // Normalizar espacios alrededor de comas/puntos decimales (ej. "35. 31" -> "35.31")
      final String text = rawText.replaceAllMapped(
        RegExp(r'(\d)\s*([,\.])\s*(\d)'),
        (m) => '${m.group(1)}${m.group(2)}${m.group(3)}',
      );

      // 1. Extraer Fecha (DD/MM/YYYY o DD/MM/YY, priorizando línea FECHA y evitando VENCIMIENTO)
      String? detectedDate;
      final dateReg = RegExp(r'(\d{2})\s*[\/\-\.]\s*(\d{2})\s*[\/\-\.]\s*(20\d{2}|\d{2})');
      final allLines = text.split(RegExp(r'\r?\n')).map((l) => l.trim()).where((l) => l.isNotEmpty).toList();
      RegExpMatch? dateMatch;
      for (final l in allLines) {
        if (RegExp(r'\bFECHA\b', caseSensitive: false).hasMatch(l) &&
            !RegExp(r'VENCIMIENTO', caseSensitive: false).hasMatch(l)) {
          dateMatch = dateReg.firstMatch(l);
          if (dateMatch != null) break;
        }
      }
      dateMatch ??= dateReg.firstMatch(text);
      if (dateMatch != null) {
        final dd = dateMatch.group(1)!;
        final mm = dateMatch.group(2)!;
        var yyyy = dateMatch.group(3)!;
        if (yyyy.length == 2) yyyy = '20$yyyy';
        detectedDate = '$yyyy-$mm-$dd';
      }

      // 2. Detectar Importe Total por palabras clave (TOTAL EUROS / TOTAL FACTURA / TOTAL PAGAR)
      double? hintImporte;
      final numReg = RegExp(r'(\d{1,4}[,\.]\d{1,3})');
      for (final l in allLines) {
        if (RegExp(r'\b(TOTAL\s*EUROS|TOTAL\s*FACTURA|TOTAL\s*PAGAR|IMPORTE\s*TOTAL|TOTAL\s*COBRADO)\b', caseSensitive: false).hasMatch(l)) {
          final ms = numReg.allMatches(l).toList();
          if (ms.isNotEmpty) {
            final v = double.tryParse((ms.last.group(1) ?? '').replaceAll(',', '.'));
            if (v != null && v > 0) {
              hintImporte = v;
              break;
            }
          }
        }
      }

      // 3. Extraer números decimales (primero filtrando líneas fiscales puras, luego fallback con todo el texto)
      final ignoredRe = RegExp(
        r'\b(BASE\s*IMPONIBLE|CUOTA\s*IVA|ESTATALES|AUTONOMIC|IMPUESTO|EUX1000|TURNO|TELF|N\.?I\.?F|C\.?I\.?F|KM\.?\s*\d)\b',
        caseSensitive: false,
      );

      List<double> extractCandidates(List<String> srcLines, bool applyFilter) {
        final List<double> list = [];
        for (final line in srcLines) {
          if (applyFilter && ignoredRe.hasMatch(line)) continue;
          for (final m in numReg.allMatches(line)) {
            final val = double.tryParse((m.group(1) ?? '').replaceAll(',', '.'));
            if (val != null && val > 0 && val < 1000) {
              list.add(val);
              if (val >= 75 && val <= 300) {
                list.add(double.parse((val / 100).toStringAsFixed(4)));
              }
            }
          }
        }
        return list;
      }

      double? precioLitro;
      double? importeTotal;
      double? litros;

      void findBestTriplet(List<double> cands) {
        final prices = cands.where((d) => d >= 0.75 && d <= 3.00).toList();
        final others = cands.where((d) => d > 2.50 && d <= 500.0).toList();
        double bestScore = -999999;

        for (final p in prices) {
          for (final l in others) {
            for (final imp in others) {
              if (l == imp) continue;
              if (p < 1.05 ? imp <= l * 0.5 : imp <= l) continue;
              final diff = (l * p - imp).abs();
              final tol = (imp * 0.015) > 0.12 ? (imp * 0.015) : 0.12;
              if (diff <= tol) {
                double score = 100 - diff * 100;
                if (hintImporte != null && (imp - hintImporte).abs() < 0.05) {
                  score += 80;
                }
                if (score > bestScore) {
                  bestScore = score;
                  precioLitro = p;
                  litros = l;
                  importeTotal = imp;
                }
              }
            }
          }
        }
      }

      final filteredCands = extractCandidates(allLines, true);
      findBestTriplet(filteredCands);

      // Fallback sin filtro de líneas si ML Kit agrupó Base/IVA en la misma línea que Litros/Precio
      if (importeTotal == null) {
        final allCands = extractCandidates(allLines, false);
        findBestTriplet(allCands);

        if (importeTotal == null) {
          final prices = allCands.where((d) => d >= 0.75 && d <= 3.00).toList();
          final larger = allCands.where((d) => d > 3.0 && d <= 500.0).toList()..sort((a, b) => b.compareTo(a));
          if (prices.isNotEmpty) {
            precioLitro = prices.first;
            if (hintImporte != null) {
              importeTotal = hintImporte;
              litros = double.parse((importeTotal! / precioLitro!).toStringAsFixed(2));
            } else if (larger.length >= 2) {
              importeTotal = larger[0];
              litros = larger[1];
            } else if (larger.length == 1 && precioLitro! > 0) {
              importeTotal = larger[0];
              litros = double.parse((importeTotal! / precioLitro!).toStringAsFixed(2));
            }
          } else if (hintImporte != null) {
            importeTotal = hintImporte;
          }
        }
      }

      final DetectedStation? detectedStation = _extractStationFromText(text);

      return TicketScanResult(
        fecha: detectedDate,
        litros: litros,
        precioLitro: precioLitro,
        importeTotal: importeTotal,
        estacion: detectedStation,
        imagePath: image.path,
        rawText: text,
      );
    } finally {
      await recognizer.close();
    }
  }

  DetectedStation? _extractStationFromText(String text) {
    final rawLines = text
        .split(RegExp(r'\r?\n'))
        .map((l) => l.trim())
        .where((l) => l.isNotEmpty)
        .toList();
    if (rawLines.isEmpty) return null;

    int stopIdx = rawLines.indexWhere(
      (l) =>
          RegExp(r'^CLIENTE\b', caseSensitive: false).hasMatch(l) ||
          RegExp(r'\b(ARTICULO|PRODUCTO|CANTIDAD)\b', caseSensitive: false).hasMatch(l),
    );
    final headerLines = stopIdx > 0
        ? rawLines.sublist(0, stopIdx)
        : rawLines.sublist(0, rawLines.length > 18 ? 18 : rawLines.length);

    String nombre = '';
    final brandRe = RegExp(
      r'\b(E\.?[ \t]*S\.?[ \t]+[A-ZÁÉÍÓÚÑ0-9\.\,\- \t]{3,45}|ESTACI[ÓO]N[ \t]+DE[ \t]+SERVICIO[ \t]+[A-ZÁÉÍÓÚÑ0-9\.\,\- \t]{3,45}|REPSOL|CEPSA|BP|GALP|SHELL|PLENOIL|BALLENOIL|PETROPRIX|MOEVE|AVIA|ALCAMPO|CARREFOUR|BONAREA|Q8|TAMOIL)',
      caseSensitive: false,
    );

    for (final l in headerLines) {
      final m = brandRe.firstMatch(l);
      if (m != null) {
        nombre = (m.group(1) ?? '').replaceAll(RegExp(r'\s+'), ' ').trim();
        nombre = nombre.replaceAll(RegExp(r'\s+Inv\.?Des\.?.*$', caseSensitive: false), '').trim();
        break;
      }
    }
    if (nombre.isEmpty) {
      for (final l in headerLines) {
        if (l.length >= 4 &&
            !RegExp(r'^(FACTURA|NUM\.|FECHA|EMISOR|PAG|ALBARAN)', caseSensitive: false).hasMatch(l) &&
            !RegExp(r'^\d+$').hasMatch(l)) {
          nombre = l;
          break;
        }
      }
    }

    final headerBlock = headerLines.join('\n');
    final cifMatch = RegExp(r'\b([A-Z]\-?\d{8}|[A-Z]\d{7}[A-Z])\b').firstMatch(headerBlock);
    final cif = cifMatch != null ? (cifMatch.group(1) ?? '').replaceAll('-', '') : '';

    String direccion = '';
    for (final l in headerLines) {
      if (RegExp(r'\b(CTRA|CARRETERA|AVDA|AVENIDA|C\/|CALLE|POLIGONO|POL\.|KM\.?)\b', caseSensitive: false).hasMatch(l)) {
        direccion = l.replaceAll(RegExp(r'\s+\d{5}\s+.*$'), '').trim();
        break;
      }
    }

    String localidad = '';
    for (final l in headerLines) {
      final cpMatch = RegExp(r'\b(\d{5})[ \t]+([A-ZÁÉÍÓÚÑ]+(?:[ \t]+[A-ZÁÉÍÓÚÑ]+)?)', caseSensitive: false).firstMatch(l);
      if (cpMatch != null) {
        localidad = '${cpMatch.group(1)} ${(cpMatch.group(2) ?? '').trim()}';
        break;
      }
    }
    if (localidad.isEmpty) {
      for (final l in headerLines) {
        if (RegExp(r'\b(TOTANA|MURCIA|LORCA|CARAVACA|MORATALLA)\b', caseSensitive: false).hasMatch(l)) {
          localidad = l.trim();
          break;
        }
      }
    }

    if (nombre.isEmpty && cif.isEmpty && direccion.isEmpty) return null;
    return DetectedStation(
      nombre: nombre.isNotEmpty ? nombre : 'Estación $cif',
      marca: nombre,
      cif: cif,
      direccion: direccion,
      localidad: localidad,
    );
  }

  /// Captura una fotografía del marcador del surtidor
  /// y extrae Litros, Precio/L e Importe Total (incluyendo céntimos/L).
  Future<TicketScanResult?> scanPumpDisplay({ImageSource source = ImageSource.camera}) async {
    final XFile? image = await _picker.pickImage(source: source, imageQuality: 90);
    if (image == null) return null;

    final inputImage = InputImage.fromFilePath(image.path);
    final recognizer = TextRecognizer(script: TextRecognitionScript.latin);

    try {
      final RecognizedText recognized = await recognizer.processImage(inputImage);
      final String text = recognized.text.replaceAllMapped(
        RegExp(r'(\d)\s*([,\.])\s*(\d)'),
        (m) => '${m.group(1)}${m.group(2)}${m.group(3)}',
      );

      final numReg = RegExp(r'(\d{1,4}[,\.]\d{1,3})');
      final List<double> rawNums = [];
      for (final m in numReg.allMatches(text)) {
        final v = double.tryParse((m.group(1) ?? '').replaceAll(',', '.'));
        if (v != null && v > 0 && v < 1000) rawNums.add(v);
      }

      final List<double> expanded = List<double>.from(rawNums);
      for (final n in rawNums) {
        if (n >= 75 && n <= 300) {
          expanded.add(double.parse((n / 100).toStringAsFixed(4)));
        }
      }

      double? precioLitro;
      double? importeTotal;
      double? litros;
      double bestScore = -999999;

      final prices = expanded.where((d) => d >= 0.75 && d <= 3.00).toList();
      final others = expanded.where((d) => d > 2.50 && d <= 500.0).toList();

      for (final p in prices) {
        for (final l in others) {
          for (final imp in others) {
            if (l == imp) continue;
            if (p < 1.05 ? imp <= l * 0.5 : imp <= l) continue;
            final diff = (l * p - imp).abs();
            final tol = (imp * 0.015) > 0.12 ? (imp * 0.015) : 0.12;
            if (diff <= tol) {
              final score = 100 - diff * 100;
              if (score > bestScore) {
                bestScore = score;
                precioLitro = p;
                litros = l;
                importeTotal = imp;
              }
            }
          }
        }
      }

      if (importeTotal == null) {
        final sorted = List<double>.from(rawNums)..sort((a, b) => b.compareTo(a));
        for (int i = 0; i < sorted.length; i++) {
          for (int j = i + 1; j < sorted.length; j++) {
            final imp = sorted[i];
            final lit = sorted[j];
            if (lit > 1 && imp > lit) {
              final ratio = imp / lit;
              if (ratio >= 0.75 && ratio <= 3.00) {
                importeTotal = imp;
                litros = lit;
                precioLitro = double.parse(ratio.toStringAsFixed(3));
                break;
              }
            }
          }
          if (importeTotal != null) break;
        }
      }

      return TicketScanResult(
        litros: litros,
        precioLitro: precioLitro,
        importeTotal: importeTotal,
        imagePath: image.path,
        rawText: text,
      );
    } finally {
      await recognizer.close();
    }
  }

  /// Captura una fotografía del cuentakilómetros (odómetro) del vehículo
  /// y extrae la lectura de kilómetros junto con la ruta de la imagen.
  Future<OdometerScanResult?> scanOdometerWithImage({ImageSource source = ImageSource.camera}) async {
    final XFile? image = await _picker.pickImage(source: source, imageQuality: 90);
    if (image == null) return null;

    final inputImage = InputImage.fromFilePath(image.path);
    final recognizer = TextRecognizer(script: TextRecognitionScript.latin);

    try {
      final RecognizedText recognized = await recognizer.processImage(inputImage);
      final String compact = recognized.text.replaceAll(' ', '');
      final kmReg = RegExp(r'\b(\d{4,7})\b');
      final matches = kmReg
          .allMatches(compact)
          .map((m) => m.group(1) ?? '')
          .where((s) => s.isNotEmpty && !RegExp(r'^202\d$').hasMatch(s))
          .toList();

      double? km;
      if (matches.isNotEmpty) {
        matches.sort((a, b) => b.length.compareTo(a.length));
        km = double.tryParse(matches.first);
      }
      return OdometerScanResult(km: km, imagePath: image.path);
    } finally {
      await recognizer.close();
    }
  }

  /// Compatibilidad: devuelve solo el número de kilómetros.
  Future<double?> scanOdometer({ImageSource source = ImageSource.camera}) async {
    final res = await scanOdometerWithImage(source: source);
    return res?.km;
  }
}
