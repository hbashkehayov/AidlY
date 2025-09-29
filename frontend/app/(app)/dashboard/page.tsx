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
  Users,
  Ticket,
  Clock,
  AlertCircle,
  ArrowRight,
  MoreVertical,
  Calendar,
  Filter,
} from 'lucide-react';
import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { format, subDays } from 'date-fns';
import Link from 'next/link';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, PieChart, Pie, Cell } from 'recharts';
import { format as formatDate } from 'date-fns';

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
  const statusColors: any = {
    new: 'default',
    open: 'secondary',
    pending: 'warning',
    resolved: 'success',
    closed: 'outline',
  };

  const priorityColors: any = {
    low: 'outline',
    medium: 'default',
    high: 'warning',
    urgent: 'destructive',
  };

  return (
    <div className="flex items-center justify-between p-4 hover:bg-accent/50 rounded-lg transition-colors">
      <div className="flex items-center space-x-4">
        <Avatar className="h-9 w-9">
          <AvatarFallback>C</AvatarFallback>
        </Avatar>
        <div className="space-y-1">
          <div className="flex items-center space-x-2">
            <p className="text-sm font-medium">{ticket.ticket_number}</p>
            <Badge variant={statusColors[ticket.status]}>{ticket.status}</Badge>
            <Badge variant={priorityColors[ticket.priority]}>{ticket.priority}</Badge>
          </div>
          <p className="text-sm text-muted-foreground">{ticket.subject}</p>
          <p className="text-xs text-muted-foreground">
            Client {ticket.client_id?.slice(-8) || 'Unknown'} • {ticket.assigned_agent_id ? `Agent ${ticket.assigned_agent_id.slice(-8)}` : 'Unassigned'}
          </p>
        </div>
      </div>
      <div className="flex items-center space-x-2">
        <p className="text-xs text-muted-foreground">
          {format(new Date(ticket.created_at), 'MMM d, h:mm a')}
        </p>
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" size="icon">
              <MoreVertical className="h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem>View Details</DropdownMenuItem>
            <DropdownMenuItem>Assign Agent</DropdownMenuItem>
            <DropdownMenuItem>Change Priority</DropdownMenuItem>
            <DropdownMenuItem>Close Ticket</DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </div>
  );
}

export default function DashboardPage() {
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

  // Transform priority data from stats
  const priorityData = stats ? stats.priority_distribution?.map((item: any) => ({
    name: item.priority.charAt(0).toUpperCase() + item.priority.slice(1),
    value: item.count,
    color: item.priority === 'low' ? '#10b981' :
           item.priority === 'medium' ? '#3b82f6' :
           item.priority === 'high' ? '#f59e0b' : '#ef4444'
  })) || [] : [
    { name: 'Low', value: 0, color: '#10b981' },
    { name: 'Medium', value: 0, color: '#3b82f6' },
    { name: 'High', value: 0, color: '#f59e0b' },
    { name: 'Urgent', value: 0, color: '#ef4444' },
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
        <div className="flex items-center space-x-2">
          <Button variant="outline" size="sm">
            <Calendar className="mr-2 h-4 w-4" />
            Today
          </Button>
          <Button variant="outline" size="sm">
            <Filter className="mr-2 h-4 w-4" />
            Filter
          </Button>
          <Button>
            Create Ticket
          </Button>
        </div>
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
          title="Active Customers"
          value={statsLoading ? '...' : stats?.active_customers || '0'}
          change={statsLoading ? '0' : Math.abs(stats?.active_customers_change || 0).toString()}
          trend={stats?.active_customers_change >= 0 ? "up" : "down"}
          icon={Users}
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
              <AreaChart data={ticketTrendData}>
                <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
                <XAxis
                  dataKey="date"
                  className="text-xs"
                />
                <YAxis className="text-xs" />
                <Tooltip
                  content={({ active, payload, label }) => {
                    if (active && payload && payload.length) {
                      return (
                        <div className="bg-background border rounded p-2 shadow-lg">
                          <p className="text-sm font-medium">{label}</p>
                          <p className="text-xs text-blue-600">
                            Total: {payload[0]?.value || 0} tickets
                          </p>
                          <p className="text-xs text-green-600">
                            Resolved: {payload[1]?.value || 0} tickets
                          </p>
                          <p className="text-xs text-orange-600">
                            Open: {payload[2]?.value || 0} tickets
                          </p>
                        </div>
                      );
                    }
                    return null;
                  }}
                />
                <Area
                  type="monotone"
                  dataKey="total"
                  stackId="1"
                  stroke="#3b82f6"
                  fill="#3b82f6"
                  fillOpacity={0.6}
                  name="Total Tickets"
                />
                <Area
                  type="monotone"
                  dataKey="resolved"
                  stackId="2"
                  stroke="#10b981"
                  fill="#10b981"
                  fillOpacity={0.6}
                  name="Resolved"
                />
                <Area
                  type="monotone"
                  dataKey="open"
                  stackId="3"
                  stroke="#f59e0b"
                  fill="#f59e0b"
                  fillOpacity={0.6}
                  name="Open"
                />
              </AreaChart>
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
                <Tooltip />
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