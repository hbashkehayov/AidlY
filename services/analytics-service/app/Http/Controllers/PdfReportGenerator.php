<?php

namespace App\Http\Controllers;

class PdfReportGenerator
{
    public static function generateHtml($data)
    {
        $total = $data['stats']['total_tickets'] > 0 ? $data['stats']['total_tickets'] : 1;
        $resolutionRate = round((($data['stats']['resolved_tickets'] + $data['stats']['closed_tickets']) / $total) * 100, 1);

        $activeTickets = $data['stats']['new_tickets'] + $data['stats']['open_tickets'] + $data['stats']['pending_tickets'];

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>AidlY Analytics Report</title>
            <style>
                @page {
                    margin: 15mm;
                    size: A4 portrait;
                }
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                body {
                    font-family: 'Segoe UI', -apple-system, sans-serif;
                    font-size: 11pt;
                    line-height: 1.5;
                    color: #1f2937;
                    background: #ffffff;
                }

                /* Prevent page breaks inside elements */
                .metric-card, .chart-card, .table-card {
                    page-break-inside: avoid;
                }
                .charts-grid {
                    page-break-inside: avoid;
                }

                /* Header matching reports page */
                .page-header {
                    background: #ffffff;
                    padding: 20px 0;
                    border-bottom: 2px solid #e5e7eb;
                    margin-bottom: 30px;
                }
                .page-header h1 {
                    font-size: 28px;
                    font-weight: 700;
                    color: #111827;
                    margin-bottom: 4px;
                    letter-spacing: -0.025em;
                }
                .page-header p {
                    color: #6b7280;
                    font-size: 13px;
                }

                /* Key Metrics Grid - exact match */
                .metrics-grid {
                    display: grid;
                    grid-template-columns: repeat(4, 1fr);
                    gap: 16px;
                    margin-bottom: 30px;
                }
                .metric-card {
                    background: #ffffff;
                    border: 1px solid #e5e7eb;
                    border-radius: 8px;
                    padding: 20px;
                }
                .metric-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    margin-bottom: 8px;
                }
                .metric-title {
                    font-size: 12px;
                    font-weight: 500;
                    color: #6b7280;
                    text-transform: none;
                }
                .metric-icon {
                    width: 16px;
                    height: 16px;
                    color: #9ca3af;
                }
                .metric-value {
                    font-size: 28px;
                    font-weight: 700;
                    color: #111827;
                    margin-bottom: 4px;
                }
                .metric-subtitle {
                    font-size: 11px;
                    color: #6b7280;
                }

                /* Charts Section matching page layout */
                .charts-section {
                    margin-top: 16px;
                }
                .charts-grid {
                    width: 100%;
                    margin-bottom: 16px;
                    page-break-inside: avoid;
                }
                .charts-row {
                    display: table;
                    width: 100%;
                    margin-bottom: 16px;
                }
                .chart-col {
                    display: table-cell;
                    width: 49%;
                    vertical-align: top;
                }
                .chart-col:first-child {
                    padding-right: 8px;
                }
                .chart-col:last-child {
                    padding-left: 8px;
                }
                .chart-card {
                    background: #ffffff;
                    border: 1px solid #e5e7eb;
                    border-radius: 8px;
                    padding: 24px;
                }
                .chart-header {
                    margin-bottom: 16px;
                }
                .chart-title {
                    font-size: 16px;
                    font-weight: 600;
                    color: #111827;
                }
                .chart-description {
                    font-size: 12px;
                    color: #6b7280;
                    margin-top: 2px;
                }

                /* Charts using CSS (DomPDF compatible) */
                .chart-container {
                    padding: 20px 0;
                }

                /* Pie Chart as CSS bars */
                .status-chart-item {
                    margin-bottom: 12px;
                }
                .status-chart-label {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 4px;
                    font-size: 11px;
                }
                .status-chart-bar {
                    height: 30px;
                    border-radius: 4px;
                    display: flex;
                    align-items: center;
                    padding: 0 10px;
                    color: white;
                    font-weight: 600;
                    font-size: 11px;
                }

                /* Priority bars */
                .priority-chart-item {
                    margin-bottom: 12px;
                }
                .priority-chart-label {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 4px;
                    font-size: 11px;
                }
                .priority-chart-bar {
                    height: 30px;
                    border-radius: 4px;
                    display: flex;
                    align-items: center;
                    padding: 0 10px;
                    color: white;
                    font-weight: 600;
                    font-size: 11px;
                }

                /* Ticket History Table */
                .table-card {
                    background: #ffffff;
                    border: 1px solid #e5e7eb;
                    border-radius: 8px;
                    padding: 24px;
                    margin-top: 16px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    font-size: 11px;
                }
                thead {
                    background: #f9fafb;
                    border-bottom: 1px solid #e5e7eb;
                }
                th {
                    padding: 10px 12px;
                    text-align: left;
                    font-weight: 600;
                    color: #6b7280;
                    font-size: 10px;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                }
                td {
                    padding: 10px 12px;
                    border-bottom: 1px solid #f3f4f6;
                    color: #374151;
                }
                tbody tr:last-child td {
                    border-bottom: none;
                }

                /* Status Badges */
                .badge {
                    display: inline-flex;
                    align-items: center;
                    padding: 2px 8px;
                    border-radius: 12px;
                    font-size: 10px;
                    font-weight: 600;
                    gap: 4px;
                }
                .badge-dot {
                    width: 6px;
                    height: 6px;
                    border-radius: 50%;
                }
                .badge-new { background: #dbeafe; color: #1e40af; }
                .badge-new .badge-dot { background: #3b82f6; }
                .badge-open { background: #fef3c7; color: #92400e; }
                .badge-open .badge-dot { background: #eab308; }
                .badge-pending { background: #fed7aa; color: #9a3412; }
                .badge-pending .badge-dot { background: #f97316; }
                .badge-resolved { background: #d1fae5; color: #065f46; }
                .badge-resolved .badge-dot { background: #22c55e; }
                .badge-closed { background: #f3f4f6; color: #374151; }
                .badge-closed .badge-dot { background: #6b7280; }

                .badge-urgent { background: #fee2e2; color: #991b1b; }
                .badge-high { background: #fed7aa; color: #9a3412; }
                .badge-medium { background: #fef3c7; color: #92400e; }
                .badge-low { background: #dbeafe; color: #1e40af; }

                /* Footer */
                .page-footer {
                    margin-top: 40px;
                    padding-top: 20px;
                    border-top: 1px solid #e5e7eb;
                    text-align: center;
                    color: #6b7280;
                    font-size: 10px;
                }
            </style>
        </head>
        <body>
            <!-- Header matching reports page -->
            <div class="page-header">
                <h1>Reports & Analytics</h1>
                <p>Comprehensive insights and system activity logs</p>
            </div>

            <!-- Key Metrics Cards matching exact layout -->
            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-header">
                        <div class="metric-title">Total Tickets</div>
                        <svg class="metric-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                    <div class="metric-value"><?= $data['stats']['total_tickets'] ?></div>
                    <div class="metric-subtitle">All time tickets</div>
                </div>

                <div class="metric-card">
                    <div class="metric-header">
                        <div class="metric-title">Active Tickets</div>
                        <svg class="metric-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="metric-value"><?= $activeTickets ?></div>
                    <div class="metric-subtitle">New, open, pending</div>
                </div>

                <div class="metric-card">
                    <div class="metric-header">
                        <div class="metric-title">Resolution Rate</div>
                        <svg class="metric-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="metric-value"><?= $resolutionRate ?>%</div>
                    <div class="metric-subtitle"><?= $data['stats']['resolved_tickets'] + $data['stats']['closed_tickets'] ?> resolved</div>
                </div>

                <div class="metric-card">
                    <div class="metric-header">
                        <div class="metric-title">Total Customers</div>
                        <svg class="metric-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                    <div class="metric-value"><?= $data['stats']['total_clients'] ?></div>
                    <div class="metric-subtitle">Registered clients</div>
                </div>
            </div>

            <!-- Charts Section matching page -->
            <div class="charts-section">
                <div class="charts-grid">
                    <div class="charts-row">
                        <div class="chart-col">
                            <!-- Status Distribution Chart -->
                            <div class="chart-card">
                        <div class="chart-header">
                            <div class="chart-title">Ticket Status Distribution</div>
                            <div class="chart-description">Current ticket status breakdown</div>
                        </div>
                        <div class="chart-container">
                            <?php
                            $statusData = [
                                ['New', $data['stats']['new_tickets'], '#3b82f6'],
                                ['Open', $data['stats']['open_tickets'], '#eab308'],
                                ['Pending', $data['stats']['pending_tickets'], '#f97316'],
                                ['Resolved', $data['stats']['resolved_tickets'], '#22c55e'],
                                ['Closed', $data['stats']['closed_tickets'], '#6b7280'],
                            ];
                            foreach ($statusData as $status):
                                if ($status[1] > 0):
                                    $percentage = round(($status[1] / $total) * 100, 1);
                                    $width = $percentage;
                            ?>
                            <div class="status-chart-item">
                                <div class="status-chart-label">
                                    <span style="font-weight: 600; color: #374151;"><?= $status[0] ?></span>
                                    <span style="color: #6b7280;"><?= $status[1] ?> (<?= $percentage ?>%)</span>
                                </div>
                                <div style="background: #f3f4f6; border-radius: 4px; height: 30px; position: relative;">
                                    <div class="status-chart-bar" style="width: <?= $width ?>%; background: <?= $status[2] ?>;">
                                        <?php if ($percentage > 10): ?>
                                            <?= $status[1] ?> tickets
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; endforeach; ?>
                        </div>
                            </div>
                        </div>

                        <div class="chart-col">
                            <!-- Priority Distribution Chart -->
                            <div class="chart-card">
                        <div class="chart-header">
                            <div class="chart-title">Priority Breakdown</div>
                            <div class="chart-description">Tickets by priority level</div>
                        </div>
                        <div class="chart-container">
                            <?php
                            $priorityData = [
                                ['Urgent', $data['stats']['urgent_priority'], '#ef4444'],
                                ['High', $data['stats']['high_priority'], '#f97316'],
                                ['Medium', $data['stats']['medium_priority'], '#eab308'],
                                ['Low', $data['stats']['low_priority'], '#3b82f6'],
                            ];
                            foreach ($priorityData as $priority):
                                if ($priority[1] > 0):
                                    $percentage = round(($priority[1] / $total) * 100, 1);
                                    $width = $percentage;
                            ?>
                            <div class="priority-chart-item">
                                <div class="priority-chart-label">
                                    <span style="font-weight: 600; color: #374151;"><?= $priority[0] ?></span>
                                    <span style="color: #6b7280;"><?= $priority[1] ?> (<?= $percentage ?>%)</span>
                                </div>
                                <div style="background: #f3f4f6; border-radius: 4px; height: 30px; position: relative;">
                                    <div class="priority-chart-bar" style="width: <?= $width ?>%; background: <?= $priority[2] ?>;">
                                        <?php if ($percentage > 10): ?>
                                            <?= $priority[1] ?> tickets
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; endforeach; ?>
                        </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ticket Trends Chart -->
                <div class="chart-card" style="margin-top: 16px; page-break-inside: avoid;">
                    <div class="chart-header">
                        <div class="chart-title">Ticket Trends</div>
                        <div class="chart-description">Daily ticket creation and resolution trends</div>
                    </div>
                    <div class="chart-container">
                        <div style="padding: 20px; text-align: center; background: #f9fafb; border-radius: 8px; border: 2px dashed #e5e7eb;">
                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px;">
                                <div>
                                    <div style="color: #3b82f6; font-size: 32px; font-weight: 700;"><?= $data['stats']['new_tickets'] + $data['stats']['open_tickets'] ?></div>
                                    <div style="color: #6b7280; font-size: 11px; margin-top: 4px;">Created Tickets</div>
                                </div>
                                <div>
                                    <div style="color: #22c55e; font-size: 32px; font-weight: 700;"><?= $data['stats']['resolved_tickets'] ?></div>
                                    <div style="color: #6b7280; font-size: 11px; margin-top: 4px;">Resolved Tickets</div>
                                </div>
                                <div>
                                    <div style="color: #f97316; font-size: 32px; font-weight: 700;"><?= $data['stats']['pending_tickets'] ?></div>
                                    <div style="color: #6b7280; font-size: 11px; margin-top: 4px;">Pending Tickets</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Team Overview Section -->
                <div class="table-card" style="page-break-inside: avoid;">
                    <div class="chart-header">
                        <div class="chart-title">Team Overview</div>
                        <div class="chart-description">Agent and customer statistics</div>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 32px; padding: 20px 0;">
                        <div>
                            <div style="color: #6b7280; font-size: 12px; margin-bottom: 4px;">Total Agents</div>
                            <div style="font-size: 28px; font-weight: 700; color: #111827;"><?= $data['stats']['total_agents'] ?></div>
                        </div>
                        <div>
                            <div style="color: #6b7280; font-size: 12px; margin-bottom: 4px;">Active Agents</div>
                            <div style="font-size: 28px; font-weight: 700; color: #111827;"><?= $data['stats']['total_agents'] ?></div>
                        </div>
                        <div>
                            <div style="color: #6b7280; font-size: 12px; margin-bottom: 4px;">Total Customers</div>
                            <div style="font-size: 28px; font-weight: 700; color: #111827;"><?= $data['stats']['total_clients'] ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="page-footer">
                <p>Generated on <?= date('F d, Y \a\t H:i:s') ?> | © <?= date('Y') ?> AidlY Support Platform</p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}