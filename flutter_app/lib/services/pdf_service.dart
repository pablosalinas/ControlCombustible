import 'dart:typed_data';
import 'package:flutter/services.dart' show rootBundle;
import 'package:intl/intl.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';
import '../models/models.dart';

class PdfReportService {
  static Future<Uint8List> generateAndSharePdf({
    required String scopeTitle,
    required ResumenEstadisticas resumen,
    required List<Repostaje> repostajes,
    bool sendOnly = false,
  }) async {
    // Cargar fuente TrueType Unicode (Roboto) para soportar € y caracteres españoles sin Internet
    final fontData = await rootBundle.load('assets/fonts/Roboto-Regular.ttf');
    final ttfFont = pw.Font.ttf(fontData);

    final doc = pw.Document(
      theme: pw.ThemeData.withFont(
        base: ttfFont,
        bold: ttfFont,
        italic: ttfFont,
        boldItalic: ttfFont,
      ),
    );

    final nowFooter = DateFormat('dd/MM/yyyy HH:mm').format(DateTime.now());
    doc.addPage(
      pw.MultiPage(
        pageFormat: PdfPageFormat.a4, // A4 Vertical (Portrait) optimizado
        margin: const pw.EdgeInsets.symmetric(horizontal: 18, vertical: 20),
        footer: (context) => pw.Container(
          alignment: pw.Alignment.center,
          margin: const pw.EdgeInsets.only(top: 6),
          child: pw.Text(
            'Página ${context.pageNumber} de ${context.pagesCount} · generado y enviado por www.moratalla-murcia.com ($nowFooter)',
            style: const pw.TextStyle(fontSize: 7.5, color: PdfColors.grey600),
          ),
        ),
        build: (context) => [
          pw.Container(
            padding: const pw.EdgeInsets.only(bottom: 6),
            decoration: const pw.BoxDecoration(
              border: pw.Border(bottom: pw.BorderSide(color: PdfColors.teal700, width: 1.5)),
            ),
            child: pw.Row(
              mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
              crossAxisAlignment: pw.CrossAxisAlignment.end,
              children: [
                pw.Column(
                  crossAxisAlignment: pw.CrossAxisAlignment.start,
                  children: [
                    pw.Text(
                      'INFORME DE CONTROL DE COMBUSTIBLE Y CONSUMO',
                      style: pw.TextStyle(
                        fontSize: 12,
                        fontWeight: pw.FontWeight.bold,
                        color: PdfColors.teal800,
                      ),
                    ),
                    pw.SizedBox(height: 2),
                    pw.Text(
                      'Ámbito: $scopeTitle',
                      style: const pw.TextStyle(fontSize: 8.5, color: PdfColors.grey800),
                    ),
                  ],
                ),
                pw.Text(
                  'Fecha: ${DateTime.now().toIso8601String().substring(0, 10)}',
                  style: const pw.TextStyle(fontSize: 8, color: PdfColors.grey700),
                ),
              ],
            ),
          ),
          pw.SizedBox(height: 6),
          pw.Container(
            padding: const pw.EdgeInsets.symmetric(horizontal: 8, vertical: 6),
            decoration: pw.BoxDecoration(
              color: PdfColors.grey100,
              borderRadius: pw.BorderRadius.circular(4),
              border: pw.Border.all(color: PdfColors.grey300, width: 0.5),
            ),
            child: pw.Row(
              mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
              children: [
                _kpiBox('Consumo Medio', '${resumen.consumoMedioL100.toStringAsFixed(2)} L/100'),
                _kpiBox('Máx / Mín (L/100)', '${resumen.consumoMaximo.toStringAsFixed(2)} / ${resumen.consumoMinimo.toStringAsFixed(2)}'),
                _kpiBox('Precio Máx / Mín', '${resumen.precioMaximo.toStringAsFixed(3)} / ${resumen.precioMinimo.toStringAsFixed(3)} €'),
                _kpiBox('Total Litros', '${resumen.totalLitros.toStringAsFixed(2)} L'),
                _kpiBox('Total Gasto', '${resumen.totalEuros.toStringAsFixed(2)} €'),
                _kpiBox('Km Recorridos', '${resumen.totalKmRecorridos.toStringAsFixed(0)} km'),
              ],
            ),
          ),
          pw.SizedBox(height: 8),
          pw.TableHelper.fromTextArray(
            headers: const [
              'Fecha',
              'Litros',
              '€/L',
              'Importe',
              'KM.',
              'DIF.',
              'L/100',
              'Km/L',
              '€/Km',
              'T.Litros',
              'Total €',
              'Notas',
            ],
            data: repostajes.map((r) => [
              r.fecha,
              r.litros.toStringAsFixed(2),
              r.precioLitro.toStringAsFixed(3),
              '${r.importeTotal.toStringAsFixed(2)} €',
              r.kmOdometro.toStringAsFixed(0),
              r.difKm > 0 ? '+${r.difKm.toStringAsFixed(0)}' : '-',
              r.litrosPor100 != null ? r.litrosPor100!.toStringAsFixed(2) : '-',
              r.kmPorLitro != null ? r.kmPorLitro!.toStringAsFixed(2) : '-',
              r.eurosPorKm != null ? r.eurosPorKm!.toStringAsFixed(3) : '-',
              r.totalLitrosAcum.toStringAsFixed(1),
              '${r.totalEurosAcum.toStringAsFixed(1)} €',
              r.notas.length > 18 ? '${r.notas.substring(0, 18)}…' : r.notas,
            ]).toList(),
            headerStyle: pw.TextStyle(
              fontWeight: pw.FontWeight.bold,
              color: PdfColors.white,
              fontSize: 6.8,
            ),
            headerDecoration: const pw.BoxDecoration(color: PdfColors.teal700),
            cellStyle: const pw.TextStyle(fontSize: 6.5),
            cellPadding: const pw.EdgeInsets.symmetric(horizontal: 2.5, vertical: 2.2),
            cellAlignments: {
              0: pw.Alignment.centerLeft,
              1: pw.Alignment.centerRight,
              2: pw.Alignment.centerRight,
              3: pw.Alignment.centerRight,
              4: pw.Alignment.centerRight,
              5: pw.Alignment.centerRight,
              6: pw.Alignment.centerRight,
              7: pw.Alignment.centerRight,
              8: pw.Alignment.centerRight,
              9: pw.Alignment.centerRight,
              10: pw.Alignment.centerRight,
              11: pw.Alignment.centerLeft,
            },
          ),
        ],
      ),
    );

    final bytes = await doc.save();
    if (!sendOnly) {
      await Printing.layoutPdf(
        onLayout: (PdfPageFormat format) async => bytes,
        name: 'Informe_Combustible_A4_${DateTime.now().toIso8601String().substring(0, 10)}.pdf',
      );
    }
    return bytes;
  }

  static Future<Uint8List> generateAndShareTareasPdf({
    required String scopeTitle,
    required String filterLabel,
    required List<VehiculoTarea> tareas,
    bool sendOnly = false,
  }) async {
    final fontData = await rootBundle.load('assets/fonts/Roboto-Regular.ttf');
    final roboto = pw.Font.ttf(fontData);
    final doc = pw.Document(
      theme: pw.ThemeData.withFont(base: roboto, bold: roboto, italic: roboto, boldItalic: roboto),
    );
    final esInt = NumberFormat('#,##0', 'es_ES');

    final nowFooter = DateFormat('dd/MM/yyyy HH:mm').format(DateTime.now());
    doc.addPage(
      pw.MultiPage(
        pageFormat: PdfPageFormat.a4,
        margin: const pw.EdgeInsets.all(20),
        footer: (ctx) => pw.Container(
          alignment: pw.Alignment.center,
          margin: const pw.EdgeInsets.only(top: 8),
          child: pw.Text(
            'Página ${ctx.pageNumber} de ${ctx.pagesCount} · generado y enviado por www.moratalla-murcia.com ($nowFooter)',
            style: const pw.TextStyle(fontSize: 7.5, color: PdfColors.grey600),
          ),
        ),
        header: (ctx) => pw.Container(
          margin: const pw.EdgeInsets.only(bottom: 10),
          padding: const pw.EdgeInsets.all(10),
          decoration: pw.BoxDecoration(
            color: PdfColors.teal700,
            borderRadius: pw.BorderRadius.circular(6),
          ),
          child: pw.Column(
            crossAxisAlignment: pw.CrossAxisAlignment.start,
            children: [
              pw.Text(
                'INFORME DE MANTENIMIENTO Y TAREAS PERIÓDICAS',
                style: pw.TextStyle(color: PdfColors.white, fontSize: 13, fontWeight: pw.FontWeight.bold),
              ),
              pw.SizedBox(height: 3),
              pw.Text(
                'Ámbito: $scopeTitle · Filtro: $filterLabel · ${tareas.length} tareas',
                style: const pw.TextStyle(color: PdfColors.white, fontSize: 8.5),
              ),
            ],
          ),
        ),
        build: (ctx) => [
          pw.TableHelper.fromTextArray(
            headers: ['Vehículo', 'Tarea / Control', 'Próx. Fecha', 'Próx. Km', 'Estado / Margen'],
            data: tareas.map((t) {
              final estadoTxt = t.estado == 'finalizada'
                  ? 'Finalizada (${t.fechaFinalizacion})'
                  : (t.enAlerta ? 'AVISO: ${t.motivoAlerta}' : 'En plazo');
              return [
                t.vehiculoNombre,
                t.descripcion,
                t.proximaFecha.isNotEmpty ? t.proximaFecha : '-',
                t.proximoKm != null ? '${esInt.format(t.proximoKm)} km' : '-',
                estadoTxt,
              ];
            }).toList(),
            headerStyle: pw.TextStyle(color: PdfColors.white, fontSize: 8, fontWeight: pw.FontWeight.bold),
            headerDecoration: const pw.BoxDecoration(color: PdfColors.blueGrey900),
            cellStyle: const pw.TextStyle(fontSize: 7.8),
            cellPadding: const pw.EdgeInsets.symmetric(horizontal: 5, vertical: 4),
          ),
        ],
      ),
    );

    final bytes = await doc.save();
    if (!sendOnly) {
      await Printing.layoutPdf(
        onLayout: (PdfPageFormat format) async => bytes,
        name: 'Informe_Tareas_${DateTime.now().toIso8601String().substring(0, 10)}.pdf',
      );
    }
    return bytes;
  }

  static pw.Widget _kpiBox(String label, String value) {
    return pw.Column(
      crossAxisAlignment: pw.CrossAxisAlignment.start,
      children: [
        pw.Text(label, style: const pw.TextStyle(fontSize: 6.8, color: PdfColors.grey700)),
        pw.SizedBox(height: 1.5),
        pw.Text(value, style: pw.TextStyle(fontSize: 8.2, fontWeight: pw.FontWeight.bold, color: PdfColors.teal900)),
      ],
    );
  }
}
