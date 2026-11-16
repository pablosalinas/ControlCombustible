import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';
import '../models/models.dart';

class PdfReportService {
  static Future<void> generateAndSharePdf({
    required String scopeTitle,
    required ResumenEstadisticas resumen,
    required List<Repostaje> repostajes,
  }) async {
    final doc = pw.Document();

    doc.addPage(
      pw.MultiPage(
        pageFormat: PdfPageFormat.a4.landscape,
        margin: const pw.EdgeInsets.all(20),
        build: (context) => [
          pw.Header(
            level: 0,
            child: pw.Row(
              mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
              children: [
                pw.Text(
                  'Informe de Control de Combustible y Consumo',
                  style: pw.TextStyle(
                    fontSize: 16,
                    fontWeight: pw.FontWeight.bold,
                    color: PdfColors.teal800,
                  ),
                ),
                pw.Text(
                  scopeTitle,
                  style: const pw.TextStyle(fontSize: 11, color: PdfColors.grey700),
                ),
              ],
            ),
          ),
          pw.SizedBox(height: 8),
          pw.Container(
            padding: const pw.EdgeInsets.all(10),
            decoration: pw.BoxDecoration(
              color: PdfColors.grey100,
              borderRadius: pw.BorderRadius.circular(6),
            ),
            child: pw.Row(
              mainAxisAlignment: pw.MainAxisAlignment.spaceAround,
              children: [
                _kpiBox('Consumo Medio', '${resumen.consumoMedioL100.toStringAsFixed(2)} L/100'),
                _kpiBox('Consumo Máx/Mín', '${resumen.consumoMaximo.toStringAsFixed(2)} / ${resumen.consumoMinimo.toStringAsFixed(2)}'),
                _kpiBox('Precio Máx/Mín', '${resumen.precioMaximo.toStringAsFixed(3)} / ${resumen.precioMinimo.toStringAsFixed(3)} €'),
                _kpiBox('Total Litros', '${resumen.totalLitros.toStringAsFixed(2)} L'),
                _kpiBox('Total Gasto', '${resumen.totalEuros.toStringAsFixed(2)} €'),
                _kpiBox('Km Recorridos', '${resumen.totalKmRecorridos.toStringAsFixed(0)} km'),
              ],
            ),
          ),
          pw.SizedBox(height: 12),
          pw.TableHelper.fromTextArray(
            headers: const [
              'Fecha',
              'Vehículo',
              'Litros',
              '€/L',
              'Importe',
              'KM.',
              'DIF.',
              'LTS.x.100',
              'KM.x.LTS',
              '€ x 1KM.',
              'T.Litros',
              'Total €',
              'Notas',
            ],
            data: repostajes.map((r) => [
              r.fecha,
              r.vehiculoNombre,
              r.litros.toStringAsFixed(2),
              r.precioLitro.toStringAsFixed(3),
              '${r.importeTotal.toStringAsFixed(2)} €',
              r.kmOdometro.toStringAsFixed(0),
              r.difKm > 0 ? '+${r.difKm.toStringAsFixed(0)}' : '-',
              r.litrosPor100 != null ? r.litrosPor100!.toStringAsFixed(2) : '-',
              r.kmPorLitro != null ? r.kmPorLitro!.toStringAsFixed(2) : '-',
              r.eurosPorKm != null ? r.eurosPorKm!.toStringAsFixed(4) : '-',
              r.totalLitrosAcum.toStringAsFixed(1),
              '${r.totalEurosAcum.toStringAsFixed(2)} €',
              r.notas,
            ]).toList(),
            headerStyle: pw.TextStyle(
              fontWeight: pw.FontWeight.bold,
              color: PdfColors.white,
              fontSize: 8,
            ),
            headerDecoration: const pw.BoxDecoration(color: PdfColors.teal700),
            cellStyle: const pw.TextStyle(fontSize: 7.5),
            cellAlignment: pw.Alignment.centerRight,
          ),
        ],
      ),
    );

    await Printing.layoutPdf(
      onLayout: (PdfPageFormat format) async => doc.save(),
      name: 'Informe_Combustible_${DateTime.now().toIso8601String().substring(0, 10)}.pdf',
    );
  }

  static pw.Widget _kpiBox(String label, String value) {
    return pw.Column(
      crossAxisAlignment: pw.CrossAxisAlignment.start,
      children: [
        pw.Text(label, style: const pw.TextStyle(fontSize: 8, color: PdfColors.grey700)),
        pw.SizedBox(height: 2),
        pw.Text(value, style: pw.TextStyle(fontSize: 10, fontWeight: pw.FontWeight.bold)),
      ],
    );
  }
}
