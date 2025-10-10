<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketHistory;
use App\Models\Category;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class TicketTest extends TestCase
{
    /**
     * Test ticket creation with valid data
     */
    public function test_it_creates_ticket_with_valid_data()
    {
        $ticketData = [
            'subject' => 'Test Ticket Subject',
            'description' => 'Test ticket description',
            'client_id' => (string) Str::uuid(),
            'priority' => Ticket::PRIORITY_MEDIUM,
            'status' => Ticket::STATUS_OPEN,
            'source' => Ticket::SOURCE_EMAIL,
        ];

        $ticket = Ticket::create($ticketData);

        $this->assertInstanceOf(Ticket::class, $ticket);
        $this->assertDatabaseHas('tickets', [
            'subject' => 'Test Ticket Subject',
            'priority' => Ticket::PRIORITY_MEDIUM,
        ]);

        // Test UUID is generated
        $this->assertNotNull($ticket->id);
        $this->assertTrue(Str::isUuid($ticket->id));

        // Test ticket number is generated
        $this->assertNotNull($ticket->ticket_number);
        $this->assertStringStartsWith('TKT-', $ticket->ticket_number);
    }

    /**
     * Test ticket constants are defined correctly
     */
    public function test_it_has_correct_status_constants()
    {
        $this->assertEquals('new', Ticket::STATUS_NEW);
        $this->assertEquals('open', Ticket::STATUS_OPEN);
        $this->assertEquals('pending', Ticket::STATUS_PENDING);
        $this->assertEquals('on_hold', Ticket::STATUS_ON_HOLD);
        $this->assertEquals('resolved', Ticket::STATUS_RESOLVED);
        $this->assertEquals('closed', Ticket::STATUS_CLOSED);
        $this->assertEquals('cancelled', Ticket::STATUS_CANCELLED);
    }

    /**
     * Test ticket priority constants
     */
    public function test_it_has_correct_priority_constants()
    {
        $this->assertEquals('low', Ticket::PRIORITY_LOW);
        $this->assertEquals('medium', Ticket::PRIORITY_MEDIUM);
        $this->assertEquals('high', Ticket::PRIORITY_HIGH);
        $this->assertEquals('urgent', Ticket::PRIORITY_URGENT);
    }

    /**
     * Test active scope filters out deleted and archived tickets
     */
    public function test_active_scope_filters_deleted_and_archived()
    {
        // Create active ticket
        $activeTicket = Ticket::create([
            'subject' => 'Active Ticket',
            'description' => 'Active',
            'client_id' => (string) Str::uuid(),
            'status' => Ticket::STATUS_OPEN,
            'source' => Ticket::SOURCE_WEB_FORM,
        ]);

        // Create deleted ticket
        $deletedTicket = Ticket::create([
            'subject' => 'Deleted Ticket',
            'description' => 'Deleted',
            'client_id' => (string) Str::uuid(),
            'status' => Ticket::STATUS_CLOSED,
            'source' => Ticket::SOURCE_WEB_FORM,
            'is_deleted' => true,
        ]);

        // Create archived ticket
        $archivedTicket = Ticket::create([
            'subject' => 'Archived Ticket',
            'description' => 'Archived',
            'client_id' => (string) Str::uuid(),
            'status' => Ticket::STATUS_CLOSED,
            'source' => Ticket::SOURCE_WEB_FORM,
            'is_archived' => true,
        ]);

        $activeTickets = Ticket::active()->get();

        $this->assertCount(1, $activeTickets);
        $this->assertTrue($activeTickets->contains($activeTicket));
        $this->assertFalse($activeTickets->contains($deletedTicket));
        $this->assertFalse($activeTickets->contains($archivedTicket));
    }

    /**
     * Test byStatus scope
     */
    public function test_by_status_scope_filters_correctly()
    {
        Ticket::create([
            'subject' => 'Open Ticket',
            'description' => 'Test',
            'client_id' => (string) Str::uuid(),
            'status' => Ticket::STATUS_OPEN,
            'source' => Ticket::SOURCE_EMAIL,
        ]);

        Ticket::create([
            'subject' => 'Closed Ticket',
            'description' => 'Test',
            'client_id' => (string) Str::uuid(),
            'status' => Ticket::STATUS_CLOSED,
            'source' => Ticket::SOURCE_EMAIL,
        ]);

        $openTickets = Ticket::byStatus(Ticket::STATUS_OPEN)->get();
        $closedTickets = Ticket::byStatus(Ticket::STATUS_CLOSED)->get();

        $this->assertCount(1, $openTickets);
        $this->assertCount(1, $closedTickets);
        $this->assertEquals(Ticket::STATUS_OPEN, $openTickets->first()->status);
        $this->assertEquals(Ticket::STATUS_CLOSED, $closedTickets->first()->status);
    }

    /**
     * Test byPriority scope
     */
    public function test_by_priority_scope_filters_correctly()
    {
        Ticket::create([
            'subject' => 'High Priority',
            'description' => 'Test',
            'client_id' => (string) Str::uuid(),
            'status' => Ticket::STATUS_OPEN,
            'priority' => Ticket::PRIORITY_HIGH,
            'source' => Ticket::SOURCE_EMAIL,
        ]);

        Ticket::create([
            'subject' => 'Low Priority',
            'description' => 'Test',
            'client_id' => (string) Str::uuid(),
            'status' => Ticket::STATUS_OPEN,
            'priority' => Ticket::PRIORITY_LOW,
            'source' => Ticket::SOURCE_EMAIL,
        ]);

        $highPriorityTickets = Ticket::byPriority(Ticket::PRIORITY_HIGH)->get();

        $this->assertCount(1, $highPriorityTickets);
        $this->assertEquals(Ticket::PRIORITY_HIGH, $highPriorityTickets->first()->priority);
    }

    /**
     * Test unassigned scope
     */
    public function test_unassigned_scope_returns_tickets_without_agent()
    {
        $unassignedTicket = Ticket::create([
            'subject' => 'Unassigned',
            'description' => 'Test',
            'client_id' => (string) Str::uuid(),
            'status' => Ticket::STATUS_OPEN,
            'source' => Ticket::SOURCE_EMAIL,
            'assigned_agent_id' => null,
        ]);

        $assignedTicket = Ticket::create([
            'subject' => 'Assigned',
            'description' => 'Test',
            'client_id' => (string) Str::uuid(),
            'status' => Ticket::STATUS_OPEN,
            'source' => Ticket::SOURCE_EMAIL,
            'assigned_agent_id' => (string) Str::uuid(),
        ]);

        $unassignedTickets = Ticket::unassigned()->get();

        $this->assertCount(1, $unassignedTickets);
        $this->assertTrue($unassignedTickets->contains($unassignedTicket));
        $this->assertFalse($unassignedTickets->contains($assignedTicket));
    }

    /**
     * Test isOpen() method
     */
    public function test_is_open_returns_true_for_open_statuses()
    {
        $openTicket = Ticket::create([
            'subject' => 'Open',
            'description' => 'Test',
            'client_id' => (string) Str::uuid(),
            'status' => Ticket::STATUS_OPEN,
            'source' => Ticket::SOURCE_EMAIL,
        ]);

        $closedTicket = Ticket::create([
            'subject' => 'Closed',
            'description' => 'Test',
            'client_id' => (string) Str::uuid(),
            'status' => Ticket::STATUS_CLOSED,
            'source' => Ticket::SOURCE_EMAIL,
        ]);

        $this->assertTrue($openTicket->isOpen());
        $this->assertFalse($closedTicket->isOpen());
    }

    /**
     * Test isClosed() method
     */
    public function test_is_closed_returns_true_for_closed_statuses()
    {
        $resolvedTicket = Ticket::create([
            'subject' => 'Resolved',
            'description' => 'Test',
            'client_id' => (string) Str::uuid(),
            'status' => Ticket::STATUS_RESOLVED,
            'source' => Ticket::SOURCE_EMAIL,
        ]);

        $openTicket = Ticket::create([
            'subject' => 'Open',
            'description' => 'Test',
            'client_id' => (string) Str::uuid(),
            'status' => Ticket::STATUS_OPEN,
            'source' => Ticket::SOURCE_EMAIL,
        ]);

        $this->assertTrue($resolvedTicket->isClosed());
        $this->assertFalse($openTicket->isClosed());
    }

    /**
     * Test assign() method
     */
    public function test_assign_method_assigns_agent_and_opens_ticket()
    {
        $ticket = Ticket::create([
            'subject' => 'New Ticket',
            'description' => 'Test',
            'client_id' => (string) Str::uuid(),
            'status' => Ticket::STATUS_NEW,
            'source' => Ticket::SOURCE_EMAIL,
        ]);

        $agentId = (string) Str::uuid();
        $result = $ticket->assign($agentId);

        $this->assertTrue($result);
        $this->assertEquals($agentId, $ticket->assigned_agent_id);
        $this->assertEquals(Ticket::STATUS_OPEN, $ticket->status);
    }

    /**
     * Test resolve() method
     */
    public function test_resolve_method_sets_status_and_timestamp()
    {
        $ticket = Ticket::create([
            'subject' => 'Test',
            'description' => 'Test',
            'client_id' => (string) Str::uuid(),
            'status' => Ticket::STATUS_OPEN,
            'source' => Ticket::SOURCE_EMAIL,
        ]);

        $result = $ticket->resolve();

        $this->assertTrue($result);
        $this->assertEquals(Ticket::STATUS_RESOLVED, $ticket->status);
        $this->assertNotNull($ticket->resolved_at);
        $this->assertInstanceOf(Carbon::class, $ticket->resolved_at);
    }

    /**
     * Test resolve() fails if ticket is already closed
     */
    public function test_resolve_method_fails_if_ticket_is_closed()
    {
        $ticket = Ticket::create([
            'subject' => 'Test',
            'description' => 'Test',
            'client_id' => (string) Str::uuid(),
            'status' => Ticket::STATUS_CLOSED,
            'source' => Ticket::SOURCE_EMAIL,
        ]);

        $result = $ticket->resolve();

        $this->assertFalse($result);
    }

    /**
     * Test close() method
     */
    public function test_close_method_closes_resolved_ticket()
    {
        $ticket = Ticket::create([
            'subject' => 'Test',
            'description' => 'Test',
            'client_id' => (string) Str::uuid(),
            'status' => Ticket::STATUS_RESOLVED,
            'source' => Ticket::SOURCE_EMAIL,
        ]);

        $result = $ticket->close();

        $this->assertTrue($result);
        $this->assertEquals(Ticket::STATUS_CLOSED, $ticket->status);
        $this->assertNotNull($ticket->closed_at);
    }

    /**
     * Test setPriority() method
     */
    public function test_set_priority_updates_priority()
    {
        $ticket = Ticket::create([
            'subject' => 'Test',
            'description' => 'Test',
            'client_id' => (string) Str::uuid(),
            'status' => Ticket::STATUS_OPEN,
            'priority' => Ticket::PRIORITY_LOW,
            'source' => Ticket::SOURCE_EMAIL,
        ]);

        $result = $ticket->setPriority(Ticket::PRIORITY_URGENT);

        $this->assertTrue($result);
        $this->assertEquals(Ticket::PRIORITY_URGENT, $ticket->priority);
    }

    /**
     * Test setPriority() rejects invalid priority
     */
    public function test_set_priority_rejects_invalid_value()
    {
        $ticket = Ticket::create([
            'subject' => 'Test',
            'description' => 'Test',
            'client_id' => (string) Str::uuid(),
            'status' => Ticket::STATUS_OPEN,
            'priority' => Ticket::PRIORITY_LOW,
            'source' => Ticket::SOURCE_EMAIL,
        ]);

        $result = $ticket->setPriority('invalid');

        $this->assertFalse($result);
        $this->assertEquals(Ticket::PRIORITY_LOW, $ticket->priority);
    }

    /**
     * Test status transition automatically sets timestamps
     */
    public function test_status_transition_sets_resolved_at_timestamp()
    {
        $ticket = Ticket::create([
            'subject' => 'Test',
            'description' => 'Test',
            'client_id' => (string) Str::uuid(),
            'status' => Ticket::STATUS_OPEN,
            'source' => Ticket::SOURCE_EMAIL,
        ]);

        $this->assertNull($ticket->resolved_at);

        $ticket->status = Ticket::STATUS_RESOLVED;
        $ticket->save();

        $ticket->refresh();
        $this->assertNotNull($ticket->resolved_at);
    }

    /**
     * Test reopening a resolved ticket clears resolved_at
     */
    public function test_reopening_resolved_ticket_clears_timestamp()
    {
        $ticket = Ticket::create([
            'subject' => 'Test',
            'description' => 'Test',
            'client_id' => (string) Str::uuid(),
            'status' => Ticket::STATUS_RESOLVED,
            'source' => Ticket::SOURCE_EMAIL,
            'resolved_at' => now(),
        ]);

        $this->assertNotNull($ticket->resolved_at);

        $ticket->status = Ticket::STATUS_OPEN;
        $ticket->save();

        $ticket->refresh();
        $this->assertNull($ticket->resolved_at);
    }

    /**
     * Test AI helper methods
     */
    public function test_has_ai_enabled_returns_true_when_features_enabled()
    {
        $ticket = Ticket::create([
            'subject' => 'Test',
            'description' => 'Test',
            'client_id' => (string) Str::uuid(),
            'status' => Ticket::STATUS_OPEN,
            'source' => Ticket::SOURCE_EMAIL,
            'ai_categorization_enabled' => true,
        ]);

        $this->assertTrue($ticket->hasAIEnabled());
    }

    /**
     * Test needsAIProcessing method
     */
    public function test_needs_ai_processing_returns_true_when_conditions_met()
    {
        $ticket = Ticket::create([
            'subject' => 'Test',
            'description' => 'Test',
            'client_id' => (string) Str::uuid(),
            'status' => Ticket::STATUS_OPEN,
            'source' => Ticket::SOURCE_EMAIL,
            'ai_suggestions_enabled' => true,
            'ai_processing_status' => null,
        ]);

        $this->assertTrue($ticket->needsAIProcessing());
    }

    /**
     * Test getAIConfidenceLevel method
     */
    public function test_get_ai_confidence_level_returns_correct_label()
    {
        $ticket = Ticket::create([
            'subject' => 'Test',
            'description' => 'Test',
            'client_id' => (string) Str::uuid(),
            'status' => Ticket::STATUS_OPEN,
            'source' => Ticket::SOURCE_EMAIL,
            'ai_confidence_score' => 0.9,
        ]);

        $this->assertEquals('high', $ticket->getAIConfidenceLevel());

        $ticket->ai_confidence_score = 0.7;
        $this->assertEquals('medium', $ticket->getAIConfidenceLevel());

        $ticket->ai_confidence_score = 0.4;
        $this->assertEquals('low', $ticket->getAIConfidenceLevel());

        $ticket->ai_confidence_score = null;
        $this->assertEquals('none', $ticket->getAIConfidenceLevel());
    }

    /**
     * Test comments relationship
     */
    public function test_comments_relationship_works()
    {
        $ticket = Ticket::create([
            'subject' => 'Test',
            'description' => 'Test',
            'client_id' => (string) Str::uuid(),
            'status' => Ticket::STATUS_OPEN,
            'source' => Ticket::SOURCE_EMAIL,
        ]);

        // This test assumes TicketComment model exists
        // Remove or modify if TicketComment is not yet implemented
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $ticket->comments());
    }

    /**
     * Test history relationship
     */
    public function test_history_relationship_works()
    {
        $ticket = Ticket::create([
            'subject' => 'Test',
            'description' => 'Test',
            'client_id' => (string) Str::uuid(),
            'status' => Ticket::STATUS_OPEN,
            'source' => Ticket::SOURCE_EMAIL,
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $ticket->history());
    }
}
