'use client';

import React from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Progress } from '@/components/ui/progress';
import {
  Ticket,
  Clock,
  CheckCircle2,
  MessageSquare,
  Flame,
} from 'lucide-react';
import Link from 'next/link';
import { format } from 'date-fns';
import { cn } from '@/lib/utils';
import { getPriorityColor, getPriorityLabel } from '@/lib/colors';

// Helper function to clean HTML and email metadata from content
const cleanReplyContent = (content: string): string => {
  if (!content) return '';

  // Remove HTML tags
  let cleaned = content.replace(/<[^>]*>/g, '');

  // Remove common email footer patterns
  const footerPatterns = [
    /On\s+.+?(wrote|said):/i,
    /Ticket Update:/i,
    /From:/i,
    /View Full Ticket/i,
    /This is an automated message/i,
    /Best regards/i,
    /Sincerely/i,
    /Regards/i,
    /Sent from/i,
    /Ticket\s*#[A-Z]+-\d+/i,
    /[-_]{2,}/,
  ];

  // Find earliest footer pattern and cut there
  let cutoffIndex = cleaned.length;
  for (const pattern of footerPatterns) {
    const match = cleaned.match(pattern);
    if (match && match.index !== undefined && match.index < cutoffIndex) {
      cutoffIndex = match.index;
    }
  }

  cleaned = cleaned.substring(0, cutoffIndex);

  // Remove quoted text and excessive whitespace
  cleaned = cleaned
    .replace(/^>.*$/gm, '')
    .replace(/\s+/g, ' ')
    .trim();

  return cleaned;
};

export default function AgentDashboardPage() {
  // Fetch agent queue
  const { data: queueData, isLoading: queueLoading, error: queueError } = useQuery({
    queryKey: ['agent-queue'],
    queryFn: async () => {
      console.log('[Agent Dashboard] Fetching queue data...');
      const response = await api.analytics.agent.queue();
      console.log('[Agent Dashboard] Queue response:', response.data);
      return response.data.data;
    },
    refetchInterval: 30000, // Auto-refresh every 30 seconds
  });

  // Fetch agent stats
  const { data: stats, isLoading: statsLoading, error: statsError } = useQuery({
    queryKey: ['agent-stats'],
    queryFn: async () => {
      console.log('[Agent Dashboard] Fetching stats data...');
      const response = await api.analytics.agent.stats();
      console.log('[Agent Dashboard] Stats response:', response.data);
      return response.data.data;
    },
    refetchInterval: 30000,
  });

  // Fetch agent's latest client replies
  const { data: replies, isLoading: repliesLoading, error: repliesError } = useQuery({
    queryKey: ['agent-replies'],
    queryFn: async () => {
      console.log('[Agent Dashboard] Fetching client replies data...');
      const response = await api.analytics.agent.replies();
      console.log('[Agent Dashboard] Replies response:', response.data);
      return response.data;
    },
    refetchInterval: 30000,
  });

  // Fetch productivity data
  const { data: productivity, isLoading: productivityLoading, error: productivityError } = useQuery({
    queryKey: ['agent-productivity'],
    queryFn: async () => {
      console.log('[Agent Dashboard] Fetching productivity data...');
      const response = await api.analytics.agent.productivity();
      console.log('[Agent Dashboard] Productivity response:', response.data);
      return response.data.data;
    },
  });

  // Debug: Log errors
  React.useEffect(() => {
    if (queueError) console.error('[Agent Dashboard] Queue error:', queueError);
    if (statsError) console.error('[Agent Dashboard] Stats error:', statsError);
    if (repliesError) console.error('[Agent Dashboard] Replies error:', repliesError);
    if (productivityError) console.error('[Agent Dashboard] Productivity error:', productivityError);
  }, [queueError, statsError, repliesError, productivityError]);

  const tickets = queueData?.active || [];

  return (
    <div className="flex-1 space-y-4 p-8 pt-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-3xl font-bold tracking-tight">My Work Queue</h2>
          <p className="text-muted-foreground">
            Your personal dashboard for managing tickets and tracking productivity
          </p>
        </div>
      </div>

      {/* Compact Stats Row */}
      <div className="grid gap-4 md:grid-cols-4">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Assigned to Me</CardTitle>
            <Ticket className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {statsLoading ? '...' : stats?.assigned_to_me || '0'}
            </div>
            <p className="text-xs text-muted-foreground mt-1">Active tickets</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Resolved Today</CardTitle>
            <CheckCircle2 className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {statsLoading ? '...' : stats?.resolved_today || '0'}
            </div>
            <p className="text-xs text-muted-foreground mt-1">Tickets completed</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Avg Response Time</CardTitle>
            <Clock className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {statsLoading ? '...' : stats?.avg_response_time || '0h 0m'}
            </div>
            <p className="text-xs text-muted-foreground mt-1">Last 30 days</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Active Replies</CardTitle>
            <MessageSquare className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {statsLoading ? '...' : stats?.active_replies || '0'}
            </div>
            <p className="text-xs text-muted-foreground mt-1">Comments sent today</p>
          </CardContent>
        </Card>
      </div>

      {/* 3-Column Layout */}
      <div className="grid gap-6 lg:grid-cols-7">
        {/* LEFT: Work Queue (3 columns) */}
        <div className="lg:col-span-3">
          <Card className="flex flex-col">
            <CardHeader>
              <CardTitle>My Open Tickets</CardTitle>
              <CardDescription>Tickets currently assigned to you</CardDescription>
            </CardHeader>
            <CardContent className="flex-1 min-h-0">
              {queueLoading ? (
                <div className="flex justify-center py-8">
                  <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                </div>
              ) : tickets.length === 0 ? (
                <div className="text-center py-8 text-muted-foreground">
                  <CheckCircle2 className="h-12 w-12 mx-auto mb-2 opacity-50" />
                  <p>No open tickets</p>
                </div>
              ) : (
                <ScrollArea className={cn(tickets.length > 3 ? "h-[500px]" : "h-auto")}>
                  <div className="space-y-2">
                    {tickets.map((ticket: any) => (
                      <Link key={ticket.id} href={`/tickets/${ticket.id}`}>
                        <div className="p-4 rounded-lg border hover:bg-accent/50 transition-colors cursor-pointer">
                          <div className="flex items-start justify-between">
                            <div className="space-y-1 flex-1">
                              <div className="flex items-center gap-2">
                                <span className="font-medium text-sm">{ticket.ticket_number}</span>
                                <Badge variant={`priority-${ticket.priority}` as any} className="gap-1.5">
                                  <span className={cn('h-2 w-2 rounded-full', getPriorityColor(ticket.priority).dot)} />
                                  {getPriorityLabel(ticket.priority)}
                                </Badge>
                                {ticket.unread_count && (
                                  <Badge variant="secondary" className="bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                    {ticket.unread_count} new
                                  </Badge>
                                )}
                              </div>
                              <p className="text-sm text-muted-foreground line-clamp-2">{ticket.subject}</p>
                              <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                <Clock className="h-3 w-3" />
                                {format(new Date(ticket.created_at), 'MMM d, h:mm a')}
                              </div>
                            </div>
                          </div>
                        </div>
                      </Link>
                    ))}
                  </div>
                </ScrollArea>
              )}
            </CardContent>
          </Card>
        </div>

        {/* CENTER: Latest Client Replies (2 columns) */}
        <div className="lg:col-span-2">
          <Card className="flex flex-col">
            <CardHeader>
              <CardTitle>Latest Client Replies</CardTitle>
              <CardDescription>Recent responses from your customers</CardDescription>
            </CardHeader>
            <CardContent className="flex-1 min-h-0">
              {repliesLoading ? (
                <div className="flex justify-center py-8">
                  <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                </div>
              ) : replies?.data?.length === 0 ? (
                <div className="text-center py-8 text-muted-foreground">
                  <MessageSquare className="h-12 w-12 mx-auto mb-2 opacity-50" />
                  <p>No client replies yet</p>
                </div>
              ) : (
                <ScrollArea className={cn(replies?.data?.length > 3 ? "h-[500px]" : "h-auto")}>
                  <div className="space-y-3">
                    {replies?.data?.map((reply: any) => (
                      <Link key={reply.id} href={`/tickets/${reply.ticket_id}`}>
                        <div className="p-3 rounded-lg border hover:bg-accent/50 transition-colors cursor-pointer">
                          <div className="space-y-2">
                            <div className="flex items-center justify-between">
                              <div className="flex items-center gap-2">
                                <span className="font-medium text-sm">{reply.ticket_number}</span>
                                <Badge variant={`priority-${reply.ticket_priority}` as any} className="gap-1.5">
                                  <span className={cn('h-2 w-2 rounded-full', getPriorityColor(reply.ticket_priority).dot)} />
                                  {getPriorityLabel(reply.ticket_priority)}
                                </Badge>
                              </div>
                              <span className="text-xs text-muted-foreground">{reply.timestamp}</span>
                            </div>
                            <p className="text-xs text-muted-foreground line-clamp-1">{reply.ticket_subject}</p>
                            <div className="flex items-center gap-2 text-xs">
                              <span className="font-medium">{reply.client_name}</span>
                              <span className="text-muted-foreground">•</span>
                              <span className="text-muted-foreground">{reply.client_email}</span>
                            </div>
                            <p className="text-sm leading-relaxed line-clamp-2 bg-muted/50 p-2 rounded">
                              {cleanReplyContent(reply.content_preview)}
                            </p>
                          </div>
                        </div>
                      </Link>
                    ))}
                  </div>
                </ScrollArea>
              )}
            </CardContent>
          </Card>
        </div>

        {/* RIGHT: Productivity (2 columns) */}
        <div className="lg:col-span-2">
          {/* Productivity Tracker */}
          <Card>
            <CardHeader>
              <CardTitle>This Week</CardTitle>
              <CardDescription>Your productivity metrics</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              {productivityLoading ? (
                <div className="flex justify-center py-4">
                  <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-primary"></div>
                </div>
              ) : (
                <>
                  <div className="flex items-center justify-between">
                    <span className="text-sm font-medium">Total Resolved</span>
                    <span className="text-2xl font-bold text-green-600">{productivity?.total_this_week || 0}</span>
                  </div>

                  <div className="p-3 bg-green-50/50 dark:bg-green-900/10 rounded-lg border border-green-200 dark:border-green-800">
                    <p className="text-xs text-green-800 dark:text-green-200 text-center">
                      <span className="font-medium">Daily Target:</span> 10 resolved tickets
                    </p>
                  </div>

                  {productivity?.streak_days > 0 && (
                    <div className="flex items-center gap-2 p-3 bg-orange-50 dark:bg-orange-900/20 rounded-lg">
                      <Flame className="h-5 w-5 text-orange-600" />
                      <div>
                        <p className="text-sm font-medium">{productivity.streak_days} Day Streak!</p>
                        <p className="text-xs text-muted-foreground">Keep up the great work</p>
                      </div>
                    </div>
                  )}

                  <div className="space-y-2">
                    {productivity?.week_data?.map((day: any) => (
                      <div key={day.date} className="flex items-center gap-2">
                        <span className={cn(
                          "text-xs w-8",
                          day.is_today ? "font-bold text-primary" : "text-muted-foreground"
                        )}>
                          {day.day}
                        </span>
                        <div className="flex-1">
                          <Progress value={(day.count / 10) * 100} className="h-2" />
                        </div>
                        <span className="text-xs text-muted-foreground w-8 text-right">{day.count}</span>
                      </div>
                    ))}
                  </div>
                </>
              )}
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}
