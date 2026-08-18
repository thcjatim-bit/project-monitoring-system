<?php

namespace App\Exports;

use Carbon\CarbonInterface;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/**
 * Renders the Portfolio Cockpit read model as an Excel-compatible workbook.
 *
 * SpreadsheetML 2003 is deliberately generated without a third-party report
 * builder. It supports multiple worksheets, opens in Excel, and keeps the
 * application free from a new binary-file dependency for this read-only
 * export. Every value comes from PortfolioCockpitQuery's already-authorized
 * payload; this class does not query domain tables or recalculate metrics.
 */
class PortfolioCockpitExport
{
    private const MIME_TYPE = 'application/vnd.ms-excel; charset=UTF-8';

    /**
     * @param  array<string, mixed>  $payload
     */
    public function download(array $payload, int $status = 200): Response
    {
        $periode = (string) ($payload['filters']['periode'] ?? now()->format('Y-m'));
        $filename = 'portfolio-cockpit-'.$periode.'.xls';

        return response($this->workbook($payload), $status, [
            'Content-Type' => self::MIME_TYPE,
            'Content-Disposition' => 'attachment; filename='.$filename,
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function workbook(array $payload): string
    {
        return implode("\n", [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<?mso-application progid="Excel.Sheet"?>',
            '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"',
            ' xmlns:o="urn:schemas-microsoft-com:office:office"',
            ' xmlns:x="urn:schemas-microsoft-com:office:excel"',
            ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"',
            ' xmlns:html="http://www.w3.org/TR/REC-html40">',
            $this->documentProperties($payload),
            $this->styles(),
            $this->summaryWorksheet($payload),
            $this->healthMatrixWorksheet($payload),
            $this->decisionQueueWorksheet($payload),
            $this->trendWorksheet($payload),
            '</Workbook>',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function documentProperties(array $payload): string
    {
        $generatedAt = $this->dateValue($payload['generatedAt'] ?? null);

        return '<DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">'
            .'<Title>Portfolio Cockpit</Title>'
            .'<Subject>Ringkasan Portfolio Cockpit</Subject>'
            .'<Author>Project Monitoring System</Author>'
            .'<Created>'.$this->escape($generatedAt).'</Created>'
            .'</DocumentProperties>';
    }

    private function styles(): string
    {
        return <<<'XML'
<Styles>
<Style ss:ID="Default" ss:Name="Normal"><Alignment ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="11"/></Style>
<Style ss:ID="Title"><Font ss:Bold="1" ss:Size="16" ss:Color="#0C4A6E"/></Style>
<Style ss:ID="Section"><Font ss:Bold="1" ss:Size="12" ss:Color="#0C4A6E"/></Style>
<Style ss:ID="Header"><Font ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#0F766E" ss:Pattern="Solid"/></Style>
<Style ss:ID="Number"><NumberFormat ss:Format="0.00"/></Style>
<Style ss:ID="Integer"><NumberFormat ss:Format="0"/></Style>
<Style ss:ID="Currency"><NumberFormat ss:Format="#,##0.00"/></Style>
</Styles>
XML;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function summaryWorksheet(array $payload): string
    {
        $filters = $payload['filters'] ?? [];
        $kpis = $payload['kpis'] ?? [];
        $canReadProgress = (bool) ($payload['canReadProgress'] ?? false);
        $canReadMaterial = (bool) ($payload['canReadMaterial'] ?? false);
        $rows = [];

        $rows[] = $this->row([
            $this->cell('Portfolio Cockpit', 'String', 'Title'),
        ]);
        $rows[] = $this->row([
            $this->cell('Data diperbarui'),
            $this->cell($this->dateValue($payload['generatedAt'] ?? null)),
        ]);
        $rows[] = $this->row([
            $this->cell('Dihitung s.d.'),
            $this->cell($this->dateValue($filters['as_of'] ?? null)),
        ]);
        $rows[] = $this->row([
            $this->cell('Filter aktif'),
            $this->cell($this->filterSummary($payload)),
        ]);
        $rows[] = $this->row([
            $this->cell('Status hasil'),
            $this->cell($this->stateMessage($payload)),
        ]);
        $rows[] = $this->row([]);
        $rows[] = $this->row([
            $this->cell('KPI kesehatan portofolio', 'String', 'Section'),
        ]);
        $rows[] = $this->row([
            $this->cell('KPI', 'String', 'Header'),
            $this->cell('Nilai', 'String', 'Header'),
            $this->cell('Catatan', 'String', 'Header'),
        ]);
        $rows[] = $this->row([
            $this->cell('Project aktif'),
            $this->numberCell($kpis['active_projects'] ?? 0, 'Integer'),
            $this->cell('dari '.(int) ($payload['scopedProjectCount'] ?? 0).' Project dalam cakupan akses.'),
        ]);
        $rows[] = $this->row([
            $this->cell('Realisasi jasa terverifikasi'),
            $this->cell($this->percentage($kpis['verified_percent'] ?? null, $canReadProgress)),
            $this->cell($canReadProgress
                ? 'Progres pending '.$this->percentage($kpis['pending_percent'] ?? null, true).' tidak menaikkan realisasi.'
                : 'Butuh izin membaca Progres jasa.'),
        ]);
        $rows[] = $this->row([
            $this->cell('SPI portofolio'),
            $this->cell($canReadProgress ? (string) ($kpis['spi_label'] ?? 'N/A') : 'Terbatas'),
            $this->cell($canReadProgress
                ? 'Dihitung dari '.(int) ($kpis['baselined_projects'] ?? 0).' Project dengan baseline berlaku (kumulatif '.$this->percentage($kpis['plan_percent'] ?? null, true).').'
                : 'Butuh izin membaca Progres jasa.'),
        ]);
        $rows[] = $this->row([
            $this->cell('Project perlu perhatian'),
            $canReadProgress
                ? $this->numberCell($kpis['attention_projects'] ?? 0, 'Integer')
                : $this->cell('Terbatas'),
            $this->cell($canReadProgress ? 'SPI kuning atau merah sesuai ADR-0010.' : 'Butuh izin membaca Progres jasa.'),
        ]);
        $rows[] = $this->row([
            $this->cell('Kesiapan Material'),
            $this->cell($this->percentage($kpis['material_readiness_percent'] ?? null, $canReadMaterial)),
            $this->cell($canReadMaterial
                ? 'Rata-rata kesiapan '.(int) ($kpis['material_projects'] ?? 0).' Project; Transit tidak dihitung sebagai tersedia. '.(int) ($kpis['material_transit_projects'] ?? 0).' Project masih memiliki Transit.'
                : 'Butuh izin membaca Material Project.'),
        ]);
        $rows[] = $this->row([
            $this->cell('Nilai RAB Jasa aktif'),
            $this->numberCell($kpis['active_rab_value'] ?? 0, 'Currency'),
            $this->cell('Grand total RAB Jasa Project aktif dalam filter ini.'),
        ]);
        $rows[] = $this->row([]);
        $rows[] = $this->row([
            $this->cell('Catatan keamanan', 'String', 'Section'),
        ]);
        $rows[] = $this->row([
            $this->cell('Export ini hanya memuat read model Portfolio Cockpit. Komentar Internal, password hash, lampiran PKS mentah, binary Foto Pekerjaan, dan data lintas tenant tidak disertakan.'),
        ]);

        return $this->worksheet('Ringkasan', $rows, [190, 125, 480]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function healthMatrixWorksheet(array $payload): string
    {
        $canReadProgress = (bool) ($payload['canReadProgress'] ?? false);
        $canReadMaterial = (bool) ($payload['canReadMaterial'] ?? false);
        $rows = [
            $this->row([
                $this->cell('Health Matrix', 'String', 'Title'),
            ]),
            $this->row([
                $this->cell('Project', 'String', 'Header'),
                $this->cell('Nama Project', 'String', 'Header'),
                $this->cell('Mitra', 'String', 'Header'),
                $this->cell('Progres jasa terverifikasi', 'String', 'Header'),
                $this->cell('Progres pending', 'String', 'Header'),
                $this->cell('SPI', 'String', 'Header'),
                $this->cell('Kesiapan Material', 'String', 'Header'),
                $this->cell('Status risiko', 'String', 'Header'),
                $this->cell('Status Project', 'String', 'Header'),
            ]),
        ];

        foreach ($this->collection($payload['healthMatrix'] ?? []) as $row) {
            $rows[] = $this->row([
                $this->cell($row['id_project'] ?? ''),
                $this->cell($row['nama'] ?? ''),
                $this->cell($row['mitra'] ?? ''),
                $this->cell($canReadProgress ? $this->percentage($row['verified_percent'] ?? null, true) : 'Terbatas'),
                $this->cell($canReadProgress ? $this->percentage($row['pending_percent'] ?? null, true) : 'Terbatas'),
                $this->cell($canReadProgress ? ($row['spi_label'] ?? 'N/A') : 'Terbatas'),
                $this->cell($canReadMaterial
                    ? $this->percentage($row['material_readiness_percent'] ?? null, true)
                    : 'Terbatas'),
                $this->cell($row['risk_label'] ?? 'N/A'),
                $this->cell($row['status_project_label'] ?? $row['status_project'] ?? ''),
            ]);
        }

        if (count($rows) === 2) {
            $rows[] = $this->row([
                $this->cell('Belum ada Project yang cocok dengan filter yang sedang berlaku.'),
            ]);
        }

        return $this->worksheet('Health Matrix', $rows, [125, 180, 150, 145, 120, 85, 135, 110, 120]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function decisionQueueWorksheet(array $payload): string
    {
        $rows = [
            $this->row([
                $this->cell('Decision Queue', 'String', 'Title'),
            ]),
            $this->row([
                $this->cell('Kategori', 'String', 'Header'),
                $this->cell('Risiko', 'String', 'Header'),
                $this->cell('Project', 'String', 'Header'),
                $this->cell('Nama Project', 'String', 'Header'),
                $this->cell('Mitra', 'String', 'Header'),
                $this->cell('Judul', 'String', 'Header'),
                $this->cell('Alasan', 'String', 'Header'),
                $this->cell('Diperbarui', 'String', 'Header'),
                $this->cell('Sumber', 'String', 'Header'),
                $this->cell('Tautan sumber', 'String', 'Header'),
            ]),
        ];

        foreach ($this->collection($payload['decisionQueue'] ?? []) as $item) {
            $rows[] = $this->row([
                $this->cell($item['category_label'] ?? ''),
                $this->cell($item['risk_label'] ?? ''),
                $this->cell($item['id_project'] ?? ''),
                $this->cell($item['project_name'] ?? ''),
                $this->cell($item['mitra'] ?? ''),
                $this->cell($item['title'] ?? ''),
                $this->cell($item['description'] ?? $item['reason'] ?? ''),
                $this->cell($this->dateValue($item['updated_at'] ?? null)),
                $this->cell($item['source_label'] ?? $item['source'] ?? ''),
                $this->cell($item['source_url'] ?? $item['url'] ?? ''),
            ]);
        }

        if (count($rows) === 2) {
            $rows[] = $this->row([
                $this->cell('Tidak ada pengecualian yang perlu ditindaklanjuti untuk filter aktif.'),
            ]);
        }

        return $this->worksheet('Decision Queue', $rows, [145, 90, 125, 180, 150, 250, 430, 145, 220, 300]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function trendWorksheet(array $payload): string
    {
        $canReadProgress = (bool) ($payload['canReadProgress'] ?? false);
        $rows = [
            $this->row([
                $this->cell('Tren realisasi jasa', 'String', 'Title'),
            ]),
            $this->row([
                $this->cell('Periode'),
                $this->cell((string) ($payload['trend']['periode_label'] ?? '')),
                $this->cell('Dihitung s.d.'),
                $this->cell((string) ($payload['trend']['as_of'] ?? '')),
            ]),
            $this->row([
                $this->cell('Tanggal', 'String', 'Header'),
                $this->cell('Realisasi jasa terverifikasi', 'String', 'Header'),
                $this->cell('Target kumulatif', 'String', 'Header'),
                $this->cell('Nilai realisasi', 'String', 'Header'),
                $this->cell('Nilai target', 'String', 'Header'),
            ]),
        ];

        if ($canReadProgress) {
            foreach (($payload['trend']['points'] ?? []) as $point) {
                $rows[] = $this->row([
                    $this->cell($point['date'] ?? ''),
                    $this->cell($this->percentage($point['verified_percent'] ?? null, true)),
                    $this->cell($point['target_available'] ?? false
                        ? $this->percentage($point['target_percent'] ?? null, true)
                        : 'N/A'),
                    $this->numberCell($point['verified_value'] ?? 0, 'Currency'),
                    $point['target_available'] ?? false
                        ? $this->numberCell($point['target_value'] ?? 0, 'Currency')
                        : $this->cell('N/A'),
                ]);
            }
        }

        if (! $canReadProgress || count($rows) === 3) {
            $rows[] = $this->row([
                $this->cell($canReadProgress
                    ? 'Belum ada data tren untuk filter yang sedang berlaku.'
                    : 'Tren realisasi jasa membutuhkan izin membaca Progres jasa.'),
            ]);
        }

        return $this->worksheet('Tren', $rows, [125, 180, 150, 140, 140]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function filterSummary(array $payload): string
    {
        $filters = $payload['filters'] ?? [];
        $projects = $this->collection($payload['options']['projects'] ?? []);
        $mitras = $this->collection($payload['options']['mitras'] ?? []);
        $project = $filters['project'] ?? null;
        $mitra = $filters['mitra'] ?? null;
        $projectOption = $project === null ? null : $projects->firstWhere('id', $project);
        $mitraOption = $mitra === null ? null : $mitras->firstWhere('id', $mitra);

        return implode(' · ', [
            $project === null ? 'Semua Project' : ($projectOption?->id_project ?? 'Project #'.$project),
            $mitra === null ? 'Semua Mitra' : ($mitraOption?->nama ?? 'Mitra #'.$mitra),
            $filters['periode_label'] ?? $filters['periode'] ?? '',
            $filters['risiko_label'] ?? 'Semua status risiko',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function stateMessage(array $payload): string
    {
        if ($payload['portfolioError'] ?? null) {
            return (string) $payload['portfolioError'];
        }

        if ((int) ($payload['scopedProjectCount'] ?? 0) === 0) {
            return 'Belum ada Project dalam cakupan akses Anda.';
        }

        if ((int) ($payload['matchedProjectCount'] ?? 0) === 0) {
            return 'Tidak ada Project aktif yang cocok dengan filter yang sedang berlaku.';
        }

        return 'Data tersedia.';
    }

    private function worksheet(string $name, array $rows, array $widths): string
    {
        $columns = implode('', array_map(
            fn (int|float $width): string => '<Column ss:Width="'.$this->escape((string) $width).'"/>',
            $widths,
        ));

        return '<Worksheet ss:Name="'.$this->escape($name).'">'
            .'<Table ss:ExpandedColumnCount="'.count($widths).'" ss:ExpandedRowCount="'.count($rows).'">'
            .$columns.implode('', $rows)
            .'</Table>'
            .'<WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel"><FreezePanes/><FrozenNoSplit/><SplitHorizontal>2</SplitHorizontal><TopRowBottomPane>2</TopRowBottomPane><ActivePane>2</ActivePane></WorksheetOptions>'
            .'</Worksheet>';
    }

    private function row(array $cells): string
    {
        return '<Row>'.implode('', $cells).'</Row>';
    }

    private function cell(mixed $value, string $type = 'String', ?string $style = null): string
    {
        $attributes = $style === null ? '' : ' ss:StyleID="'.$this->escape($style).'"';
        $value = $value ?? '';

        return '<Cell'.$attributes.'><Data ss:Type="'.$this->escape($type).'">'.$this->escape((string) $value).'</Data></Cell>';
    }

    private function numberCell(mixed $value, ?string $style = null): string
    {
        $numeric = is_numeric($value) ? $value : 0;

        return $this->cell((string) $numeric, 'Number', $style);
    }

    private function percentage(mixed $value, bool $allowed): string
    {
        if (! $allowed) {
            return 'Terbatas';
        }

        return $value === null ? 'N/A' : number_format((float) $value, 2, '.', '').'%';
    }

    private function dateValue(mixed $value): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return is_scalar($value) ? (string) $value : '';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function collection(mixed $value): Collection
    {
        return $value instanceof Collection ? $value : collect($value);
    }
}
