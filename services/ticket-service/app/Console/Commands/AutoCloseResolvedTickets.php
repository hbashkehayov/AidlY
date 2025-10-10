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
    protected $description = 'Automatically close tickets that have been resolved for more than 2 days';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting auto-close process for resolved tickets...');

        try {
            DB::beginTransaction();
            $cutoffTime = Carbon::now()->subDays(2);

            // Close tickets that are "resolved" for more than 2 days
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
                        'reason' => 'auto_close_resolved_after_2_days',
                        'resolved_at' => $ticket->resolved_at->toISOString(),
                        'days_in_resolved' => Carbon::parse($ticket->resolved_at)->diffInDays(Carbon::now()),
                    ]
                ]);

                $resolvedClosedCount++;
                $this->info("Closed resolved ticket #{$ticket->ticket_number} (resolved " .
                    Carbon::parse($ticket->resolved_at)->diffForHumans() . ")");
            }

            DB::commit();

            $this->info("Successfully closed {$resolvedClosedCount} resolved ticket(s)");
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
