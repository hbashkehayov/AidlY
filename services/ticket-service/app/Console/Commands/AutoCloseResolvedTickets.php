<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Ticket;
use App\Models\TicketHistory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AutoCloseResolvedTickets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tickets:auto-close-resolved';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically close tickets that have been resolved or pending for more than 24 hours';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting auto-close process...');

        try {
            DB::beginTransaction();
            $cutoffTime = Carbon::now()->subHours(24);

            // 1. Close tickets that are "resolved" for more than 24 hours
            $resolvedTickets = Ticket::where('status', Ticket::STATUS_RESOLVED)
                ->where('resolved_at', '<=', $cutoffTime)
                ->where('is_deleted', false)
                ->get();

            $resolvedClosedCount = 0;
            foreach ($resolvedTickets as $ticket) {
                $ticket->status = Ticket::STATUS_CLOSED;
                $ticket->closed_at = Carbon::now();
                $ticket->save();

                TicketHistory::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => null,
                    'action' => 'status_changed',
                    'old_value' => Ticket::STATUS_RESOLVED,
                    'new_value' => Ticket::STATUS_CLOSED,
                    'metadata' => [
                        'auto_changed' => true,
                        'reason' => 'auto_close_resolved_after_24h',
                        'resolved_at' => $ticket->resolved_at->toISOString(),
                    ]
                ]);

                $resolvedClosedCount++;
                $this->info("Closed resolved ticket #{$ticket->ticket_number}");
            }

            // 2. Close tickets that are "pending" for more than 24 hours
            $pendingTickets = Ticket::where('status', Ticket::STATUS_PENDING)
                ->where('updated_at', '<=', $cutoffTime)
                ->where('is_deleted', false)
                ->get();

            $pendingClosedCount = 0;
            foreach ($pendingTickets as $ticket) {
                // Double-check by looking at ticket history to find when it became pending
                $lastPendingChange = TicketHistory::where('ticket_id', $ticket->id)
                    ->where('new_value', Ticket::STATUS_PENDING)
                    ->orderBy('created_at', 'desc')
                    ->first();

                // If last pending change was more than 24 hours ago, close it
                if ($lastPendingChange && $lastPendingChange->created_at <= $cutoffTime) {
                    $ticket->status = Ticket::STATUS_CLOSED;
                    $ticket->closed_at = Carbon::now();
                    $ticket->save();

                    TicketHistory::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => null,
                        'action' => 'status_changed',
                        'old_value' => Ticket::STATUS_PENDING,
                        'new_value' => Ticket::STATUS_CLOSED,
                        'metadata' => [
                            'auto_changed' => true,
                            'reason' => 'auto_close_pending_after_24h',
                            'pending_since' => $lastPendingChange->created_at->toISOString(),
                        ]
                    ]);

                    $pendingClosedCount++;
                    $this->info("Closed pending ticket #{$ticket->ticket_number}");
                }
            }

            DB::commit();

            $totalClosed = $resolvedClosedCount + $pendingClosedCount;
            $this->info("Successfully closed {$totalClosed} ticket(s) ({$resolvedClosedCount} resolved, {$pendingClosedCount} pending)");
            return 0;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error auto-closing tickets: ' . $e->getMessage());
            \Log::error('Auto-close tickets failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
}
