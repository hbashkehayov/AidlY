<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Dompdf\Dompdf;
use Dompdf\Options;

class ExportController extends Controller
{
    /**
     * Export reports data in the specified format
     */
    public function reports(Request $request)
    {
        $this->validate($request, [
            'format' => 'required|in:excel,pdf',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'filters' => 'nullable|array',
        ]);

        $format = $request->input('format');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $filters = $request->input('filters', []);

        try {
            // Fetch all required data for the report
            $reportData = $this->gatherReportData($dateFrom, $dateTo, $filters);

            // Generate the export file based on format
            if ($format === 'excel') {
                $filePath = $this->generateExcelReport($reportData);
                $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                $fileName = 'report_' . date('Y-m-d_His') . '.xlsx';
            } else {
                $filePath = $this->generatePdfReport($reportData);
                $contentType = 'application/pdf';
                $fileName = 'report_' . date('Y-m-d_His') . '.pdf';
            }

            // Read file content
            $fileContent = file_get_contents($filePath);

            // Delete the file
            @unlink($filePath);

            // Return file download response
            return response($fileContent, 200, [
                'Content-Type' => $contentType,
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
                'Content-Length' => strlen($fileContent),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Gather all data needed for the report
     */
    private function gatherReportData($dateFrom = null, $dateTo = null, $filters = [])
    {
        $ticketService = env('TICKET_SERVICE_URL', 'http://localhost:8002');
        $clientService = env('CLIENT_SERVICE_URL', 'http://localhost:8003');
        $authService = env('AUTH_SERVICE_URL', 'http://localhost:8001');

        // Build query for tickets
        $ticketsQuery = DB::table('tickets');

        if ($dateFrom) {
            $ticketsQuery->where('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $ticketsQuery->where('created_at', '<=', $dateTo . ' 23:59:59');
        }

        $tickets = $ticketsQuery->get();

        // Get client and agent names from other services
        $clients = DB::table('clients')->get()->keyBy('id');
        $users = DB::table('users')->get()->keyBy('id');

        // Calculate statistics
        $stats = [
            'total_tickets' => $tickets->count(),
            'new_tickets' => $tickets->where('status', 'new')->count(),
            'open_tickets' => $tickets->where('status', 'open')->count(),
            'pending_tickets' => $tickets->where('status', 'pending')->count(),
            'resolved_tickets' => $tickets->where('status', 'resolved')->count(),
            'closed_tickets' => $tickets->where('status', 'closed')->count(),
            'urgent_priority' => $tickets->where('priority', 'urgent')->count(),
            'high_priority' => $tickets->where('priority', 'high')->count(),
            'medium_priority' => $tickets->where('priority', 'medium')->count(),
            'low_priority' => $tickets->where('priority', 'low')->count(),
            'total_clients' => $clients->count(),
            'total_agents' => $users->where('role', 'agent')->count(),
            'date_from' => $dateFrom ?: 'All time',
            'date_to' => $dateTo ?: 'Present',
        ];

        return [
            'stats' => $stats,
            'tickets' => $tickets,
            'clients' => $clients,
            'users' => $users,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }

    /**
     * Generate Excel report with PhpSpreadsheet
     */
    private function generateExcelReport($data)
    {
        $fileName = 'report_' . uniqid() . '.xlsx';
        $filePath = storage_path('exports/' . $fileName);

        // Ensure directory exists
        if (!file_exists(storage_path('exports'))) {
            mkdir(storage_path('exports'), 0755, true);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Analytics Report');

        $row = 1;
        $total = $data['stats']['total_tickets'] > 0 ? $data['stats']['total_tickets'] : 1;

        // Header Section
        $sheet->mergeCells("A{$row}:K{$row}");
        $sheet->setCellValue("A{$row}", '📊 AidlY Analytics Report - Comprehensive Dashboard Export');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFF');
        $sheet->getStyle("A{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('4F46E5');
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(30);
        $row++;

        // Meta information
        $sheet->setCellValue("A{$row}", 'Generated:');
        $sheet->setCellValue("B{$row}", date('F d, Y H:i:s'));
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;

        $sheet->setCellValue("A{$row}", 'Report Period:');
        $sheet->setCellValue("B{$row}", ($data['date_from'] ?: 'All time') . ' to ' . ($data['date_to'] ?: 'Present'));
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;

        $sheet->setCellValue("A{$row}", 'Total Records:');
        $sheet->setCellValue("B{$row}", count($data['tickets']));
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row += 2;

        // Key Metrics Section
        $sheet->mergeCells("A{$row}:C{$row}");
        $sheet->setCellValue("A{$row}", '📈 KEY METRICS');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle("A{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('E5E7EB');
        $row++;

        // Headers
        $sheet->setCellValue("A{$row}", 'Metric');
        $sheet->setCellValue("B{$row}", 'Count');
        $sheet->setCellValue("C{$row}", 'Percentage');
        $sheet->getStyle("A{$row}:C{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:C{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('F3F4F6');
        $row++;

        // Metrics data
        $metrics = [
            ['Total Tickets', $data['stats']['total_tickets'], '100%'],
            ['New Tickets', $data['stats']['new_tickets'], round(($data['stats']['new_tickets'] / $total) * 100, 1) . '%'],
            ['Open Tickets', $data['stats']['open_tickets'], round(($data['stats']['open_tickets'] / $total) * 100, 1) . '%'],
            ['Pending Tickets', $data['stats']['pending_tickets'], round(($data['stats']['pending_tickets'] / $total) * 100, 1) . '%'],
            ['Resolved Tickets', $data['stats']['resolved_tickets'], round(($data['stats']['resolved_tickets'] / $total) * 100, 1) . '%'],
            ['Closed Tickets', $data['stats']['closed_tickets'], round(($data['stats']['closed_tickets'] / $total) * 100, 1) . '%'],
        ];

        foreach ($metrics as $metric) {
            $sheet->setCellValue("A{$row}", $metric[0]);
            $sheet->setCellValue("B{$row}", $metric[1]);
            $sheet->setCellValue("C{$row}", $metric[2]);
            $row++;
        }
        $row++;

        // Priority Distribution
        $sheet->mergeCells("A{$row}:C{$row}");
        $sheet->setCellValue("A{$row}", '🎯 PRIORITY DISTRIBUTION');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle("A{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('E5E7EB');
        $row++;

        $sheet->setCellValue("A{$row}", 'Priority Level');
        $sheet->setCellValue("B{$row}", 'Count');
        $sheet->setCellValue("C{$row}", 'Percentage');
        $sheet->getStyle("A{$row}:C{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:C{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('F3F4F6');
        $row++;

        $priorities = [
            ['Urgent', $data['stats']['urgent_priority'], 'EF4444'],
            ['High', $data['stats']['high_priority'], 'F97316'],
            ['Medium', $data['stats']['medium_priority'], 'EAB308'],
            ['Low', $data['stats']['low_priority'], '3B82F6'],
        ];

        foreach ($priorities as $priority) {
            $percentage = round(($priority[1] / $total) * 100, 1);
            $sheet->setCellValue("A{$row}", $priority[0]);
            $sheet->setCellValue("B{$row}", $priority[1]);
            $sheet->setCellValue("C{$row}", $percentage . '%');
            $sheet->getStyle("A{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($priority[2]);
            $sheet->getStyle("A{$row}")->getFont()->getColor()->setARGB('FFFFFF');
            $row++;
        }
        $row++;

        // Team Overview Section
        $sheet->mergeCells("A{$row}:C{$row}");
        $sheet->setCellValue("A{$row}", '👥 TEAM OVERVIEW');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle("A{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('E5E7EB');
        $row++;

        $sheet->setCellValue("A{$row}", 'Metric');
        $sheet->setCellValue("B{$row}", 'Value');
        $sheet->getStyle("A{$row}:B{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:B{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('F3F4F6');
        $row++;

        $teamMetrics = [
            ['Total Agents', $data['stats']['total_agents']],
            ['Total Clients', $data['stats']['total_clients']],
            ['Resolution Rate', round((($data['stats']['resolved_tickets'] + $data['stats']['closed_tickets']) / $total) * 100, 1) . '%'],
        ];

        foreach ($teamMetrics as $metric) {
            $sheet->setCellValue("A{$row}", $metric[0]);
            $sheet->setCellValue("B{$row}", $metric[1]);
            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'C') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Save the file
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        return $filePath;
    }

    /**
     * Generate PDF report with DomPDF
     */
    private function generatePdfReport($data)
    {
        $fileName = 'report_' . uniqid() . '.pdf';
        $filePath = storage_path('exports/' . $fileName);

        // Ensure directory exists
        if (!file_exists(storage_path('exports'))) {
            mkdir(storage_path('exports'), 0755, true);
        }

        // Generate HTML content for PDF using new generator
        $html = PdfReportGenerator::generateHtml($data);

        // Configure DomPDF options
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');
        $options->set('enable_php', false);

        // Create PDF
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Save PDF to file
        file_put_contents($filePath, $dompdf->output());

        return $filePath;
    }

    /**
     * Generate HTML content matching the exact reports page design
     */
    private function generateReportHtml($data)
    {
        // Calculate additional metrics
        $total = $data['stats']['total_tickets'] > 0 ? $data['stats']['total_tickets'] : 1;
        $resolutionRate = round((($data['stats']['resolved_tickets'] + $data['stats']['closed_tickets']) / $total) * 100, 1);

        // Prepare chart data
        $statusData = [
            ['New', $data['stats']['new_tickets'], '#3b82f6'],
            ['Open', $data['stats']['open_tickets'], '#eab308'],
            ['Pending', $data['stats']['pending_tickets'], '#f97316'],
            ['Resolved', $data['stats']['resolved_tickets'], '#22c55e'],
            ['Closed', $data['stats']['closed_tickets'], '#6b7280'],
        ];

        $priorityData = [
            ['Urgent', $data['stats']['urgent_priority'], '#ef4444'],
            ['High', $data['stats']['high_priority'], '#f97316'],
            ['Medium', $data['stats']['medium_priority'], '#eab308'],
            ['Low', $data['stats']['low_priority'], '#3b82f6'],
        ];

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>AidlY Analytics Report</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    background: #f9fafb;
                }
                .container { max-width: 1200px; margin: 0 auto; padding: 40px; }

                /* Header */
                .header {
                    background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
                    color: white;
                    padding: 40px;
                    border-radius: 12px;
                    margin-bottom: 30px;
                    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                }
                .header h1 { font-size: 32px; font-weight: 700; margin-bottom: 10px; }
                .header .subtitle { font-size: 16px; opacity: 0.9; }
                .meta {
                    display: flex;
                    gap: 30px;
                    margin-top: 20px;
                    padding-top: 20px;
                    border-top: 1px solid rgba(255,255,255,0.2);
                }
                .meta-item { display: flex; flex-direction: column; }
                .meta-label { font-size: 12px; opacity: 0.8; text-transform: uppercase; }
                .meta-value { font-size: 18px; font-weight: 600; margin-top: 4px; }

                /* Stats Grid */
                .stats-grid {
                    display: grid;
                    grid-template-columns: repeat(4, 1fr);
                    gap: 20px;
                    margin: 30px 0;
                }
                .stat-card {
                    background: white;
                    padding: 24px;
                    border-radius: 12px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                    border-left: 4px solid #4F46E5;
                    transition: transform 0.2s;
                }
                .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
                .stat-label {
                    color: #6b7280;
                    font-size: 14px;
                    font-weight: 500;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                .stat-value {
                    font-size: 36px;
                    font-weight: 700;
                    color: #1f2937;
                    margin: 8px 0;
                }
                .stat-change {
                    font-size: 13px;
                    color: #6b7280;
                }
                .stat-change.positive { color: #22c55e; }
                .stat-change.negative { color: #ef4444; }

                /* Charts */
                .charts-grid {
                    display: grid;
                    grid-template-columns: repeat(2, 1fr);
                    gap: 20px;
                    margin: 30px 0;
                }
                .chart-card {
                    background: white;
                    padding: 24px;
                    border-radius: 12px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                }
                .chart-title {
                    font-size: 18px;
                    font-weight: 600;
                    color: #1f2937;
                    margin-bottom: 20px;
                    padding-bottom: 12px;
                    border-bottom: 2px solid #e5e7eb;
                }
                .bar-chart {
                    display: flex;
                    flex-direction: column;
                    gap: 12px;
                    margin-top: 20px;
                }
                .bar-row {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                }
                .bar-label {
                    width: 80px;
                    font-size: 14px;
                    font-weight: 500;
                    color: #4b5563;
                }
                .bar-container {
                    flex: 1;
                    height: 32px;
                    background: #f3f4f6;
                    border-radius: 6px;
                    overflow: hidden;
                    position: relative;
                }
                .bar-fill {
                    height: 100%;
                    border-radius: 6px;
                    display: flex;
                    align-items: center;
                    padding: 0 10px;
                    color: white;
                    font-size: 12px;
                    font-weight: 600;
                    transition: width 0.3s ease;
                }
                .bar-value {
                    width: 60px;
                    text-align: right;
                    font-size: 14px;
                    font-weight: 600;
                    color: #1f2937;
                }

                /* Priority badges */
                .priority-urgent { background: #ef4444; }
                .priority-high { background: #f97316; }
                .priority-medium { background: #eab308; }
                .priority-low { background: #3b82f6; }

                /* Status badges */
                .status-new { background: #3b82f6; }
                .status-open { background: #eab308; }
                .status-pending { background: #f97316; }
                .status-resolved { background: #22c55e; }
                .status-closed { background: #6b7280; }

                /* Tables */
                .section {
                    background: white;
                    padding: 24px;
                    border-radius: 12px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                    margin: 20px 0;
                }
                .section-title {
                    font-size: 20px;
                    font-weight: 600;
                    color: #1f2937;
                    margin-bottom: 20px;
                    padding-bottom: 12px;
                    border-bottom: 2px solid #e5e7eb;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    font-size: 14px;
                }
                thead tr {
                    background: #f9fafb;
                    border-bottom: 2px solid #e5e7eb;
                }
                th {
                    padding: 12px;
                    text-align: left;
                    font-weight: 600;
                    color: #4b5563;
                    text-transform: uppercase;
                    font-size: 12px;
                    letter-spacing: 0.5px;
                }
                td {
                    padding: 12px;
                    border-bottom: 1px solid #f3f4f6;
                    color: #1f2937;
                }
                tbody tr:hover { background: #f9fafb; }

                .badge {
                    display: inline-block;
                    padding: 4px 12px;
                    border-radius: 12px;
                    font-size: 11px;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }

                /* Footer */
                .footer {
                    margin-top: 40px;
                    padding-top: 20px;
                    border-top: 2px solid #e5e7eb;
                    text-align: center;
                    color: #6b7280;
                    font-size: 13px;
                }

                /* Print styles */
                @media print {
                    body { background: white; }
                    .container { padding: 20px; }
                    .stat-card, .chart-card, .section {
                        break-inside: avoid;
                        box-shadow: none;
                        border: 1px solid #e5e7eb;
                    }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <!-- Header -->
                <div class="header">
                    <h1>📊 AidlY Analytics Report</h1>
                    <div class="subtitle">Comprehensive Dashboard Export & Performance Insights</div>
                    <div class="meta">
                        <div class="meta-item">
                            <span class="meta-label">Generated</span>
                            <span class="meta-value"><?= date('F d, Y \a\t H:i:s') ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Report Period</span>
                            <span class="meta-value"><?= htmlspecialchars($data['date_from'] ?: 'All time') ?> - <?= htmlspecialchars($data['date_to'] ?: 'Present') ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Total Records</span>
                            <span class="meta-value"><?= count($data['tickets']) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Key Metrics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-label">Total Tickets</div>
                        <div class="stat-value"><?= $data['stats']['total_tickets'] ?></div>
                        <div class="stat-change">All time</div>
                    </div>
                    <div class="stat-card" style="border-left-color: #22c55e;">
                        <div class="stat-label">Resolved</div>
                        <div class="stat-value"><?= $data['stats']['resolved_tickets'] + $data['stats']['closed_tickets'] ?></div>
                        <div class="stat-change positive"><?= $resolutionRate ?>% resolution rate</div>
                    </div>
                    <div class="stat-card" style="border-left-color: #eab308;">
                        <div class="stat-label">Active Tickets</div>
                        <div class="stat-value"><?= $data['stats']['new_tickets'] + $data['stats']['open_tickets'] + $data['stats']['pending_tickets'] ?></div>
                        <div class="stat-change">Needs attention</div>
                    </div>
                    <div class="stat-card" style="border-left-color: #8b5cf6;">
                        <div class="stat-label">Customers</div>
                        <div class="stat-value"><?= $data['stats']['total_clients'] ?></div>
                        <div class="stat-change"><?= $data['stats']['total_agents'] ?> agents</div>
                    </div>
                </div>

                <!-- Charts -->
                <div class="charts-grid">
                    <!-- Status Distribution -->
                    <div class="chart-card">
                        <div class="chart-title">📈 Status Distribution</div>
                        <div class="bar-chart">
                            <?php foreach ($statusData as list($label, $value, $color)):
                                $percentage = $total > 0 ? ($value / $total) * 100 : 0;
                            ?>
                            <div class="bar-row">
                                <div class="bar-label"><?= $label ?></div>
                                <div class="bar-container">
                                    <div class="bar-fill status-<?= strtolower($label) ?>" style="width: <?= $percentage ?>%;">
                                        <?php if ($percentage > 15): ?><?= $value ?><?php endif; ?>
                                    </div>
                                </div>
                                <div class="bar-value"><?= $value ?> (<?= round($percentage, 1) ?>%)</div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Priority Distribution -->
                    <div class="chart-card">
                        <div class="chart-title">🎯 Priority Distribution</div>
                        <div class="bar-chart">
                            <?php foreach ($priorityData as list($label, $value, $color)):
                                $percentage = $total > 0 ? ($value / $total) * 100 : 0;
                            ?>
                            <div class="bar-row">
                                <div class="bar-label"><?= $label ?></div>
                                <div class="bar-container">
                                    <div class="bar-fill priority-<?= strtolower($label) ?>" style="width: <?= $percentage ?>%;">
                                        <?php if ($percentage > 15): ?><?= $value ?><?php endif; ?>
                                    </div>
                                </div>
                                <div class="bar-value"><?= $value ?> (<?= round($percentage, 1) ?>%)</div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Status by Priority Matrix -->
                <div class="section">
                    <div class="section-title">🔍 Status by Priority Breakdown</div>
                    <table>
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th style="text-align: center;">Urgent</th>
                                <th style="text-align: center;">High</th>
                                <th style="text-align: center;">Medium</th>
                                <th style="text-align: center;">Low</th>
                                <th style="text-align: center;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $statusPriorityMatrix = [];
                            foreach ($data['tickets'] as $ticket) {
                                $key = $ticket->status . '_' . $ticket->priority;
                                $statusPriorityMatrix[$key] = ($statusPriorityMatrix[$key] ?? 0) + 1;
                            }
                            $statuses = ['new', 'open', 'pending', 'resolved', 'closed'];
                            foreach ($statuses as $status):
                                $rowTotal = 0;
                            ?>
                            <tr>
                                <td><span class="badge status-<?= $status ?>"><?= ucfirst($status) ?></span></td>
                                <?php foreach (['urgent', 'high', 'medium', 'low'] as $priority):
                                    $count = $statusPriorityMatrix[$status . '_' . $priority] ?? 0;
                                    $rowTotal += $count;
                                ?>
                                <td style="text-align: center; font-weight: 600;"><?= $count ?></td>
                                <?php endforeach; ?>
                                <td style="text-align: center; font-weight: 700; background: #f9fafb;"><?= $rowTotal ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Recent Tickets -->
                <div class="section">
                    <div class="section-title">📋 Recent Ticket Activity (Last 50)</div>
                    <table>
                        <thead>
                            <tr>
                                <th>Ticket #</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Client</th>
                                <th>Agent</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $recentTickets = array_slice($data['tickets']->toArray(), 0, 50);
                            foreach ($recentTickets as $ticket):
                                $client = $data['clients']->get($ticket->client_id);
                                $clientName = $client?->name ?? 'Unknown';
                                $agentName = $ticket->assigned_agent_id ? ($data['users']->get($ticket->assigned_agent_id)?->name ?? 'Unknown') : 'Unassigned';
                            ?>
                            <tr>
                                <td style="font-weight: 600; color: #4F46E5;"><?= htmlspecialchars($ticket->ticket_number) ?></td>
                                <td><?= htmlspecialchars(mb_substr($ticket->subject, 0, 60)) ?><?= mb_strlen($ticket->subject) > 60 ? '...' : '' ?></td>
                                <td><span class="badge status-<?= $ticket->status ?>"><?= ucfirst($ticket->status) ?></span></td>
                                <td><span class="badge priority-<?= $ticket->priority ?>"><?= ucfirst($ticket->priority) ?></span></td>
                                <td><?= htmlspecialchars($clientName) ?></td>
                                <td><?= htmlspecialchars($agentName) ?></td>
                                <td><?= date('M d, Y H:i', strtotime($ticket->created_at)) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Footer -->
                <div class="footer">
                    <p>© <?= date('Y') ?> AidlY Support Platform - Confidential Report</p>
                    <p style="margin-top: 8px;">This report contains sensitive business data. Handle with care.</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    public function tickets(Request $request)
    {
        return response()->json([
            'success' => true,
            'export_id' => 'export-tickets-123',
            'message' => 'Ticket export initiated'
        ]);
    }

    public function agents(Request $request)
    {
        return response()->json([
            'success' => true,
            'export_id' => 'export-agents-456',
            'message' => 'Agent export initiated'
        ]);
    }

    public function custom(Request $request)
    {
        return response()->json([
            'success' => true,
            'export_id' => 'export-custom-789',
            'message' => 'Custom export initiated'
        ]);
    }

    public function download(Request $request, $id)
    {
        return response()->json([
            'success' => true,
            'download_url' => "/downloads/{$id}",
            'message' => 'Export ready for download'
        ]);
    }

    public function status(Request $request, $id)
    {
        return response()->json([
            'success' => true,
            'status' => 'completed',
            'progress' => 100,
            'message' => 'Export completed'
        ]);
    }
}