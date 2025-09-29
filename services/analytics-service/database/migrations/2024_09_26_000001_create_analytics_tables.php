<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAnalyticsTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Create ticket_metrics table for aggregated ticket data
        Schema::create('ticket_metrics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('date')->index();
            $table->integer('total_tickets')->default(0);
            $table->integer('new_tickets')->default(0);
            $table->integer('open_tickets')->default(0);
            $table->integer('resolved_tickets')->default(0);
            $table->integer('closed_tickets')->default(0);
            $table->decimal('avg_resolution_time', 10, 2)->nullable(); // in hours
            $table->decimal('avg_first_response_time', 10, 2)->nullable(); // in hours
            $table->integer('urgent_tickets')->default(0);
            $table->integer('high_priority_tickets')->default(0);
            $table->jsonb('category_breakdown')->nullable();
            $table->jsonb('source_breakdown')->nullable();
            $table->timestamp('aggregated_at')->nullable();
            $table->timestamps();

            $table->unique('date');
            $table->index('aggregated_at');
        });

        // Create agent_metrics table for agent performance data
        Schema::create('agent_metrics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('agent_id')->index();
            $table->date('period_start')->index();
            $table->date('period_end')->index();
            $table->integer('tickets_assigned')->default(0);
            $table->integer('tickets_resolved')->default(0);
            $table->integer('tickets_closed')->default(0);
            $table->decimal('avg_resolution_time', 10, 2)->nullable(); // in hours
            $table->decimal('avg_first_response_time', 10, 2)->nullable(); // in hours
            $table->decimal('fastest_response_time', 10, 2)->nullable(); // in minutes
            $table->integer('total_responses')->default(0);
            $table->decimal('satisfaction_score', 3, 2)->nullable(); // 0-5 scale
            $table->integer('total_ratings')->default(0);
            $table->integer('active_days')->default(0);
            $table->jsonb('performance_data')->nullable();
            $table->timestamp('aggregated_at')->nullable();
            $table->timestamps();

            $table->unique(['agent_id', 'period_start', 'period_end']);
            $table->foreign('agent_id')->references('id')->on('users')->onDelete('cascade');
        });

        // Create analytics_events table for tracking system events
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('event_type', 100)->index();
            $table->string('event_category', 50)->index();
            $table->uuid('user_id')->nullable()->index();
            $table->uuid('client_id')->nullable()->index();
            $table->uuid('ticket_id')->nullable()->index();
            $table->jsonb('properties')->nullable();
            $table->string('session_id', 255)->nullable()->index();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index('created_at');
            $table->index(['event_type', 'created_at']);
            $table->index(['event_category', 'created_at']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('set null');
            $table->foreign('ticket_id')->references('id')->on('tickets')->onDelete('set null');
        });

        // Create reports table (already exists, but adding if not)
        if (!Schema::hasTable('reports')) {
            Schema::create('reports', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name', 255);
                $table->text('description')->nullable();
                $table->string('type', 50); // dashboard, export, custom
                $table->jsonb('parameters')->nullable(); // report configuration
                $table->jsonb('filters')->nullable();
                $table->string('format', 20)->default('json'); // json, csv, pdf, excel
                $table->uuid('created_by')->nullable();
                $table->boolean('is_scheduled')->default(false);
                $table->string('schedule_frequency', 50)->nullable(); // daily, weekly, monthly
                $table->jsonb('schedule_config')->nullable();
                $table->timestamp('last_run_at')->nullable();
                $table->timestamp('next_run_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('type');
                $table->index('is_scheduled');
                $table->index('next_run_at');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            });
        }

        // Create report_executions table
        if (!Schema::hasTable('report_executions')) {
            Schema::create('report_executions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('report_id');
                $table->uuid('executed_by')->nullable();
                $table->string('status', 50)->default('pending'); // pending, processing, completed, failed
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->integer('execution_time_ms')->nullable();
                $table->integer('record_count')->nullable();
                $table->string('file_path', 500)->nullable();
                $table->bigInteger('file_size')->nullable();
                $table->jsonb('parameters_used')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();

                $table->index('status');
                $table->index('created_at');
                $table->foreign('report_id')->references('id')->on('reports')->onDelete('cascade');
                $table->foreign('executed_by')->references('id')->on('users')->onDelete('set null');
            });
        }

        // Create scheduled_reports table
        if (!Schema::hasTable('scheduled_reports')) {
            Schema::create('scheduled_reports', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('report_id');
                $table->string('frequency', 50); // hourly, daily, weekly, monthly
                $table->string('day_of_week', 20)->nullable(); // for weekly reports
                $table->integer('day_of_month')->nullable(); // for monthly reports
                $table->time('time_of_day')->nullable();
                $table->string('timezone', 50)->default('UTC');
                $table->jsonb('recipients')->nullable(); // email addresses
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_run_at')->nullable();
                $table->timestamp('next_run_at')->nullable();
                $table->integer('run_count')->default(0);
                $table->integer('failure_count')->default(0);
                $table->timestamps();

                $table->index('is_active');
                $table->index('next_run_at');
                $table->foreign('report_id')->references('id')->on('reports')->onDelete('cascade');
            });
        }

        // Create ticket_category_metrics table for category-specific metrics
        Schema::create('ticket_category_metrics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('date')->index();
            $table->uuid('category_id')->nullable();
            $table->integer('ticket_count')->default(0);
            $table->decimal('avg_resolution_time', 10, 2)->nullable();
            $table->decimal('avg_response_time', 10, 2)->nullable();
            $table->timestamps();

            $table->unique(['date', 'category_id']);
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
        });

        // Create client_metrics table for client-specific metrics
        Schema::create('client_metrics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->date('period_start')->index();
            $table->date('period_end')->index();
            $table->integer('total_tickets')->default(0);
            $table->integer('open_tickets')->default(0);
            $table->integer('resolved_tickets')->default(0);
            $table->decimal('avg_resolution_time', 10, 2)->nullable();
            $table->decimal('satisfaction_score', 3, 2)->nullable();
            $table->integer('total_feedback')->default(0);
            $table->jsonb('issue_categories')->nullable();
            $table->timestamp('aggregated_at')->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'period_start', 'period_end']);
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
        });

        // Create sla_metrics table for SLA tracking
        Schema::create('sla_metrics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('date')->index();
            $table->integer('total_tickets_with_sla')->default(0);
            $table->integer('sla_met_count')->default(0);
            $table->integer('sla_breached_count')->default(0);
            $table->decimal('compliance_rate', 5, 2)->nullable();
            $table->jsonb('breach_reasons')->nullable();
            $table->jsonb('priority_breakdown')->nullable();
            $table->timestamps();

            $table->unique('date');
        });

        // Add indexes for better query performance (skip if table doesn't exist)
        if (Schema::hasTable('tickets')) {
            Schema::table('tickets', function (Blueprint $table) {
                if (!Schema::hasColumn('tickets', 'first_response_at')) {
                    $table->timestamp('first_response_at')->nullable();
                }
                if (!Schema::hasColumn('tickets', 'resolved_at')) {
                    $table->timestamp('resolved_at')->nullable();
                }
                if (!Schema::hasColumn('tickets', 'sla_deadline')) {
                    $table->timestamp('sla_deadline')->nullable();
                }

                // Only add indexes if columns exist
                if (Schema::hasColumn('tickets', 'first_response_at')) {
                    $table->index('first_response_at');
                }
                if (Schema::hasColumn('tickets', 'resolved_at')) {
                    $table->index('resolved_at');
                }
                if (Schema::hasColumn('tickets', 'sla_deadline')) {
                    $table->index('sla_deadline');
                }
                if (Schema::hasColumn('tickets', 'status')) {
                    $table->index(['status', 'created_at']);
                }
                if (Schema::hasColumn('tickets', 'assigned_to') && Schema::hasColumn('tickets', 'status')) {
                    $table->index(['assigned_to', 'status']);
                }
            });
        }

        // Create ticket_feedback table if not exists
        if (!Schema::hasTable('ticket_feedback')) {
            Schema::create('ticket_feedback', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('ticket_id');
                $table->uuid('client_id')->nullable();
                $table->integer('rating'); // 1-5 scale
                $table->text('comment')->nullable();
                $table->jsonb('feedback_data')->nullable();
                $table->timestamps();

                $table->index('rating');
                $table->index('created_at');
                $table->foreign('ticket_id')->references('id')->on('tickets')->onDelete('cascade');
                $table->foreign('client_id')->references('id')->on('clients')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Drop indexes from tickets table if it exists
        if (Schema::hasTable('tickets')) {
            Schema::table('tickets', function (Blueprint $table) {
                if (Schema::hasColumn('tickets', 'status')) {
                    $table->dropIndex(['status', 'created_at']);
                }
                if (Schema::hasColumn('tickets', 'assigned_to') && Schema::hasColumn('tickets', 'status')) {
                    $table->dropIndex(['assigned_to', 'status']);
                }
                if (Schema::hasColumn('tickets', 'first_response_at')) {
                    $table->dropIndex(['first_response_at']);
                }
                if (Schema::hasColumn('tickets', 'resolved_at')) {
                    $table->dropIndex(['resolved_at']);
                }
                if (Schema::hasColumn('tickets', 'sla_deadline')) {
                    $table->dropIndex(['sla_deadline']);
                }
            });
        }

        // Drop tables in reverse order to handle foreign key constraints
        Schema::dropIfExists('sla_metrics');
        Schema::dropIfExists('client_metrics');
        Schema::dropIfExists('ticket_category_metrics');
        Schema::dropIfExists('ticket_feedback');
        Schema::dropIfExists('scheduled_reports');
        Schema::dropIfExists('report_executions');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('analytics_events');
        Schema::dropIfExists('agent_metrics');
        Schema::dropIfExists('ticket_metrics');
    }
}