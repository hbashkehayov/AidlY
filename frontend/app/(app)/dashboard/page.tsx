'use client';

import React from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
  TrendingUp,
  TrendingDown,
  CheckCircle2,
  Ticket,
  Clock,
  AlertCircle,
  ArrowRight,
} from 'lucide-react';
import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { format, subDays } from 'date-fns';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, PieChart, Pie, Cell, Legend, LineChart, Line, BarChart, Bar } from 'recharts';
import { format as formatDate } from 'date-fns';
import { getStatusColor, getStatusLabel, getPriorityColor, getPriorityLabel, getAllPriorityChartColors, getPriorityChartColor } from '@/lib/colors';
import { cn } from '@/lib/utils';

function StatCard({ title, value, change, trend, icon: Icon }: any) {
  return (
    <Card className="hover:shadow-lg transition-shadow">
      <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
        <CardTitle className="text-sm font-medium">{title}</CardTitle>
        <Icon className="h-4 w-4 text-muted-foreground" />
      </CardHeader>
      <CardContent>
        <div className="text-2xl font-bold">{value}</div>
        <div className="flex items-center space-x-1 text-xs">
          {trend === 'up' ? (
            <TrendingUp className="h-3 w-3 text-green-500" />
          ) : (
            <TrendingDown className="h-3 w-3 text-red-500" />
          )}
          <span className={trend === 'up' ? 'text-green-500' : 'text-red-500'}>
            {change}%
          </span>
          <span className="text-muted-foreground">from last week</span>
        </div>
      </CardContent>
    </Card>
  );
}

function TicketRow({ ticket }: any) {
  return (
    <Link href={`/tickets/${ticket.id}`}>
      <div className="flex items-center justify-between p-4 hover:bg-accent/50 rounded-lg transition-colors cursor-pointer">
        <div className="flex items-center space-x-4">
          <Avatar className="h-9 w-9">
            <AvatarFallback>C</AvatarFallback>
          </Avatar>
          <div className="space-y-1">
            <div className="flex items-center space-x-2">
              <p className="text-sm font-medium">{ticket.ticket_number}</p>
              <Badge variant={`status-${ticket.status.toLowerCase()}` as any} className="gap-1.5">
                <span className={cn('h-2 w-2 rounded-full', getStatusColor(ticket.status).dot)} />
                {getStatusLabel(ticket.status)}
              </Badge>
              <Badge variant={`priority-${ticket.priority.toLowerCase()}` as any} className="gap-1.5">
                <span className={cn('h-2 w-2 rounded-full', getPriorityColor(ticket.priority).dot)} />
                {getPriorityLabel(ticket.priority)}
              </Badge>
            </div>
            <p className="text-sm text-muted-foreground">{ticket.subject}</p>
            <p className="text-xs text-muted-foreground">
              {ticket.client?.name || 'Unknown Client'} • {ticket.assigned_agent?.name || 'Unassigned'}
            </p>
          </div>
        </div>
        <div className="flex items-center space-x-2">
          <p className="text-xs text-muted-foreground">
            {format(new Date(ticket.created_at), 'MMM d, h:mm a')}
          </p>
        </div>
      </div>
    </Link>
  );
}

export default function DashboardPage() {
  const router = useRouter();

  // Redirect agents to their dedicated dashboard
  React.useEffect(() => {
    const user = typeof window !== 'undefined' ? JSON.parse(localStorage.getItem('user') || '{}') : {};
    if (user?.role === 'agent') {
      router.push('/dashboard/agent');
    }
  }, [router]);

  const { data: stats, isLoading: statsLoading } = useQuery({
    queryKey: ['dashboard-stats'],
    queryFn: async () => {
      const response = await api.analytics.dashboard.stats();
      return response.data.data;
    },
  });

  const { data: recentTickets, isLoading: recentTicketsLoading } = useQuery({
    queryKey: ['recent-tickets'],
    queryFn: async () => {
      const response = await api.tickets.list({ per_page: 5, sort: 'created_at', direction: 'desc' });
      return response.data.data || [];
    },
  });

  // Fetch ticket trends from analytics service
  const { data: ticketTrends, isLoading: trendsLoading } = useQuery({
    queryKey: ['ticket-trends'],
    queryFn: async () => {
      try {
        const response = await api.analytics.dashboard.trends({ days: 7 });
        return response.data.data || [];
      } catch (error) {
        console.error('Error fetching ticket trends:', error);
        return [];
      }
    },
  });

  // Process trend data for the chart
  const ticketTrendData = React.useMemo(() => {
    if (!ticketTrends || ticketTrends.length === 0) {
      // Return default data structure with zeros
      const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
      const today = new Date();
      return days.map((day, index) => {
        const date = subDays(today, 6 - index);
        return {
          name: day,
          date: formatDate(date, 'MMM d'),
          total: 0,
          resolved: 0,
          open: 0,
        };
      });
    }

    // Transform analytics service data for chart
    return ticketTrends.map((item: any) => {
      const date = new Date(item.date);
      return {
        name: formatDate(date, 'EEE'),
        date: formatDate(date, 'MMM d'),
        total: item.tickets || 0,
        resolved: item.resolved || 0,
        open: item.open || 0,
      };
    });
  }, [ticketTrends]);

  // Transform priority data from stats using centralized colors
  const priorityData = stats ? stats.priority_distribution?.map((item: any) => ({
    name: getPriorityLabel(item.priority),
    value: item.count,
    color: getPriorityChartColor(item.priority)
  })) || [] : [
    { name: 'Low', value: 0, color: getPriorityChartColor('low') },
    { name: 'Medium', value: 0, color: getPriorityChartColor('medium') },
    { name: 'High', value: 0, color: getPriorityChartColor('high') },
    { name: 'Urgent', value: 0, color: getPriorityChartColor('urgent') },
  ];

  return (
    <div className="flex-1 space-y-4 p-8 pt-6">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-3xl font-bold tracking-tight">Dashboard</h2>
          <p className="text-muted-foreground">
            Welcome back! Here's what's happening with your support team today.
          </p>
        </div>
        {/* Action buttons removed */}
      </div>

      {/* Stats Grid */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <StatCard
          title="Open Tickets"
          value={statsLoading ? '...' : stats?.open_tickets || '0'}
          change={statsLoading ? '0' : Math.abs(stats?.open_tickets_change || 0).toString()}
          trend={stats?.open_tickets_change >= 0 ? "up" : "down"}
          icon={Ticket}
        />
        <StatCard
          title="Avg. Response Time"
          value={statsLoading ? '...' : stats?.avg_response_time || '0 hrs'}
          change={statsLoading ? '0' : Math.abs(stats?.avg_response_time_change || 0).toString()}
          trend={stats?.avg_response_time_change >= 0 ? "up" : "down"}
          icon={Clock}
        />
        <StatCard
          title="Pending Tickets"
          value={statsLoading ? '...' : stats?.pending_tickets || '0'}
          change={statsLoading ? '0' : Math.abs(stats?.pending_tickets_change || 0).toString()}
          trend={stats?.pending_tickets_change >= 0 ? "up" : "down"}
          icon={AlertCircle}
        />
        <StatCard
          title="Resolved Tickets"
          value={statsLoading ? '...' : stats?.resolved_tickets || '0'}
          change={statsLoading ? '0' : Math.abs(stats?.resolved_tickets_change || 0).toString()}
          trend={stats?.resolved_tickets_change >= 0 ? "up" : "down"}
          icon={CheckCircle2}
        />
      </div>

      {/* Charts and Recent Activity */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-7">
        <Card className="col-span-4">
          <CardHeader>
            <CardTitle>Ticket Overview</CardTitle>
            <CardDescription>
              {trendsLoading
                ? "Loading ticket data..."
                : "Daily ticket volume and resolution rate for the past week (real data)"}
            </CardDescription>
          </CardHeader>
          <CardContent>
            <ResponsiveContainer width="100%" height={300}>
              <BarChart data={ticketTrendData}>
                <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
                <XAxis
                  dataKey="date"
                  className="text-xs"
                />
                <YAxis className="text-xs" allowDecimals={false} />
                <Tooltip
                  content={({ active, payload, label }) => {
                    if (active && payload && payload.length) {
                      return (
                        <div className="bg-background border rounded p-2 shadow-lg">
                          <p className="text-sm font-medium">{label}</p>
                          <p className="text-xs" style={{ color: '#6b7280' }}>
                            Total: {payload.find(p => p.dataKey === 'total')?.value || 0} tickets
                          </p>
                          <p className="text-xs" style={{ color: '#10b981' }}>
                            Resolved: {payload.find(p => p.dataKey === 'resolved')?.value || 0} tickets
                          </p>
                          <p className="text-xs" style={{ color: '#3b82f6' }}>
                            Open: {payload.find(p => p.dataKey === 'open')?.value || 0} tickets
                          </p>
                        </div>
                      );
                    }
                    return null;
                  }}
                />
                <Legend
                  wrapperStyle={{ fontSize: '12px' }}
                  iconType="square"
                />
                <Bar
                  dataKey="total"
                  fill="#6b7280"
                  fillOpacity={0.3}
                  name="Total Tickets"
                  radius={[4, 4, 0, 0]}
                />
                <Bar
                  dataKey="open"
                  fill="#3b82f6"
                  fillOpacity={0.7}
                  name="Open"
                  radius={[4, 4, 0, 0]}
                />
                <Bar
                  dataKey="resolved"
                  fill="#10b981"
                  fillOpacity={0.9}
                  name="Resolved"
                  radius={[4, 4, 0, 0]}
                />
              </BarChart>
            </ResponsiveContainer>
          </CardContent>
        </Card>

        <Card className="col-span-3">
          <CardHeader>
            <CardTitle>Priority Distribution</CardTitle>
            <CardDescription>
              Current ticket distribution by priority level
            </CardDescription>
          </CardHeader>
          <CardContent>
            <ResponsiveContainer width="100%" height={300}>
              <PieChart>
                <Pie
                  data={priorityData}
                  cx="50%"
                  cy="50%"
                  labelLine={false}
                  label={(entry) => `${entry.name}: ${entry.value}`}
                  outerRadius={80}
                  fill="#8884d8"
                  dataKey="value"
                >
                  {priorityData.map((entry: any, index: number) => (
                    <Cell key={`cell-${index}`} fill={entry.color} />
                  ))}
                </Pie>
                <Tooltip
                  content={({ active, payload }) => {
                    if (active && payload && payload.length) {
                      const data = payload[0].payload;
                      return (
                        <div className="bg-background border rounded p-2 shadow-lg">
                          <div className="flex items-center gap-2">
                            <div
                              className="h-3 w-3 rounded-full"
                              style={{ backgroundColor: data.color }}
                            />
                            <span className="text-sm font-medium">{data.name}</span>
                          </div>
                          <p className="text-xs text-muted-foreground mt-1">
                            {data.value} tickets
                          </p>
                        </div>
                      );
                    }
                    return null;
                  }}
                />
                <Legend
                  wrapperStyle={{ fontSize: '12px' }}
                  iconType="circle"
                  formatter={(value, entry: any) => {
                    return <span style={{ color: entry.color }}>{value}</span>;
                  }}
                />
              </PieChart>
            </ResponsiveContainer>
          </CardContent>
        </Card>
      </div>

      {/* Recent Tickets */}
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <div>
              <CardTitle>Recent Tickets</CardTitle>
              <CardDescription>
                Latest support tickets requiring attention
              </CardDescription>
            </div>
            <Button variant="ghost" size="sm" asChild>
              <Link href="/tickets">
                View All
                <ArrowRight className="ml-2 h-4 w-4" />
              </Link>
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          <div className="space-y-2">
            {recentTicketsLoading ? (
              <div className="flex justify-center py-4">
                <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-primary"></div>
              </div>
            ) : recentTickets && recentTickets.length > 0 ? (
              recentTickets.map((ticket: any) => (
                <TicketRow key={ticket.id} ticket={ticket} />
              ))
            ) : (
              <p className="text-sm text-muted-foreground text-center py-4">No recent tickets found</p>
            )}
          </div>
        </CardContent>
      </Card>
    </div>
  );
}