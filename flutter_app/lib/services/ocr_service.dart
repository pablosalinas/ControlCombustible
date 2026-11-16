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

      // 2. Extraer números decimales para Precio/L, Litros e Importe
      final numReg = RegExp(r'(\d{1,3}[,\.]\d{2,3})');
      final List<double> decimals = numReg
          .allMatches(text)
          .map((m) => double.tryParse((m.group(1) ?? '').replaceAll(',', '.')))
          .whereType<double>()
          .toList();

      double? precioLitro;
      for (final d in decimals) {
        if (d >= 0.85 && d <= 2.60) {
          precioLitro = d;
          break;
        }
      }

      final List<double> larger = decimals.where((d) => d > 3.0 && d < 350.0).toList()
        ..sort((a, b) => b.compareTo(a));

      double? importeTotal;
      double? litros;

      if (larger.length >= 2) {
        importeTotal = larger[0];
        litros = larger[1];
      } else if (larger.length == 1 && precioLitro != null && precioLitro > 0) {
        importeTotal = larger[0];
        litros = double.parse((importeTotal / precioLitro).toStringAsFixed(2));
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
