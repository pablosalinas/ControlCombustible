import 'package:google_mlkit_text_recognition/google_mlkit_text_recognition.dart';
import 'package:image_picker/image_picker.dart';

class TicketScanResult {
  final String? fecha;
  final double? litros;
  final double? precioLitro;
  final double? importeTotal;
  final String rawText;

  TicketScanResult({
    this.fecha,
    this.litros,
    this.precioLitro,
    this.importeTotal,
    required this.rawText,
  });
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
      final String text = recognized.text;

      // 1. Extraer Fecha (DD/MM/YYYY o YYYY-MM-DD)
      String? detectedDate;
      final dateReg = RegExp(r'(\d{2})[\/\-](\d{2})[\/\-](20\d{2})');
      final dateMatch = dateReg.firstMatch(text);
      if (dateMatch != null) {
        detectedDate = '${dateMatch.group(3)}-${dateMatch.group(2)}-${dateMatch.group(1)}';
      }

      // 2. Extraer números decimales para Precio/L, Litros e Importe con verificación matemática
      final lines = text.split(RegExp(r'\r?\n'));
      final ignoredRe = RegExp(
        r'\b(BASE|IMPONIBLE|IVA|CUOTA|ESTATALES|AUTONOMIC|IMPUESTO|EUX1000|TURNO|TELF|N\.?I\.?F|C\.?I\.?F|KM\.?\s*\d)\b',
        caseSensitive: false,
      );
      final numReg = RegExp(r'(\d{1,4}[,\.]\d{1,3})');
      final List<double> candidates = [];
      for (final line in lines) {
        if (ignoredRe.hasMatch(line)) continue;
        for (final m in numReg.allMatches(line)) {
          final val = double.tryParse((m.group(1) ?? '').replaceAll(',', '.'));
          if (val != null && val > 0 && val < 1000) {
            candidates.add(val);
            if (val >= 75 && val <= 300) {
              candidates.add(double.parse((val / 100).toStringAsFixed(4)));
            }
          }
        }
      }

      double? precioLitro;
      double? importeTotal;
      double? litros;

      final prices = candidates.where((d) => d >= 0.75 && d <= 3.00).toList();
      final others = candidates.where((d) => d > 2.50 && d <= 500.0).toList();
      double bestDiff = 999999;

      for (final p in prices) {
        for (final l in others) {
          for (final imp in others) {
            if (l == imp || imp <= l) continue;
            final diff = (l * p - imp).abs();
            final tol = (imp * 0.015) > 0.12 ? (imp * 0.015) : 0.12;
            if (diff <= tol && diff < bestDiff) {
              bestDiff = diff;
              precioLitro = p;
              litros = l;
              importeTotal = imp;
            }
          }
        }
      }

      if (importeTotal == null && prices.isNotEmpty) {
        precioLitro = prices.first;
        final larger = others.where((d) => d > 3.0).toList()..sort((a, b) => b.compareTo(a));
        if (larger.length >= 2) {
          importeTotal = larger[0];
          litros = larger[1];
        } else if (larger.length == 1 && precioLitro > 0) {
          importeTotal = larger[0];
          litros = double.parse((importeTotal / precioLitro).toStringAsFixed(2));
        }
      }

      return TicketScanResult(
        fecha: detectedDate,
        litros: litros,
        precioLitro: precioLitro,
        importeTotal: importeTotal,
        rawText: text,
      );
    } finally {
      await recognizer.close();
    }
  }

  /// Captura una fotografía del cuentakilómetros (odómetro) del vehículo
  /// y extrae la lectura de kilómetros.
  Future<double?> scanOdometer({ImageSource source = ImageSource.camera}) async {
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
          .where((s) => s.isNotEmpty)
          .toList();

      if (matches.isEmpty) return null;
      matches.sort((a, b) => b.length.compareTo(a.length));
      return double.tryParse(matches.first);
    } finally {
      await recognizer.close();
    }
  }
}
