'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { useAuth } from '@/lib/auth';
import api from '@/lib/api';
import { format, isWithinInterval } from 'date-fns';
import { DateRange } from 'react-day-picker';
import { toast } from 'sonner';
import {
  BarChart3,
  TrendingUp,
  TrendingDown,
  Users,
  Ticket,
  Clock,
  CheckCircle,
  AlertCircle,
  Calendar,
  Download,
  Activity,
  History,
  FileText,
  User,
  Mail,
  Edit,
  Trash,
  UserPlus,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import { ExportDialog } from '@/components/export-dialog';
import { cn } from '@/lib/utils';
import { AreaChart, Area, BarChart, Bar, PieChart, Pie, Cell, LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Legend } from 'recharts';

const statusConfig = {
  new: { label: 'New', color: 'bg-blue-500' },
  open: { label: 'Open', color: 'bg-yellow-500' },
  pending: { label: 'Pending', color: 'bg-orange-500' },
  on_hold: { label: 'On Hold', color: 'bg-gray-500' },
  resolved: { label: 'Resolved', color: 'bg-green-500' },
  closed: { label: 'Closed', color: 'bg-gray-400' },
  cancelled: { label: 'Cancelled', color: 'bg-red-500' },
};

const actionIcons: Record<string, any> = {
  created: UserPlus,
  updated: Edit,
  deleted: Trash,
  assigned: User,
  comment: Mail,
  status_changed: Activity,
  priority_changed: AlertCircle,
};

export default function ReportsPage() {
  const router = useRouter();
  const { user } = useAuth();
  const [dateRange, setDateRange] = useState<DateRange | undefined>({
    from: new Date(new Date().setDate(new Date().getDate() - 30)),
    to: new Date(),
  });
  const [exportDialogOpen, setExportDialogOpen] = useState(false);

  // Check if user is admin
  if (user?.role !== 'admin') {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <Card className="w-96">
          <CardHeader>
            <CardTitle>Access Denied</CardTitle>
            <CardDescription>
              You need administrator privileges to access reports.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <Button onClick={() => router.push('/dashboard')} className="w-full">
              Go to Dashboard
            </Button>
          </CardContent>
        </Card>
      </div>
    );
  }

  // Fetch all tickets for statistics
  const { data: allTicketsData, isLoading: isLoadingTickets } = useQuery({
    queryKey: ['all-tickets-reports'],
    queryFn: async () => {
      const response = await api.tickets.list({ per_page: 100 });
      return response.data?.data || response.data || [];
    },
  });

  // Fetch agents only (not all users)
  const { data: agentsData } = useQuery({
    queryKey: ['agents-reports'],
    queryFn: async () => {
      const response = await api.users.list();
      const users = response.data?.data || response.data || [];
      // Filter to show only agents
      return users.filter((u: any) => u.role === 'agent');
    },
  });

  // Fetch all clients
  const { data: clientsData } = useQuery({
    queryKey: ['all-clients-reports'],
    queryFn: async () => {
      const response = await api.clients.list({ per_page: 100 });
      return response.data?.data || response.data || [];
    },
  });

  // Filter tickets by date range
  const getFilteredTickets = () => {
    if (!allTicketsData) return [];
    if (!dateRange?.from) return allTicketsData;

    return allTicketsData.filter((ticket: any) => {
      const ticketDate = new Date(ticket.created_at);
      if (dateRange.to) {
        return isWithinInterval(ticketDate, { start: dateRange.from!, end: dateRange.to });
      }
      return ticketDate >= dateRange.from!;
    });
  };

  const filteredTickets = getFilteredTickets();

  // Calculate statistics from filtered ticket data
  const calculateStats = () => {
    if (!filteredTickets || filteredTickets.length === 0) return {
      total: 0,
      new: 0,
      open: 0,
      pending: 0,
      resolved: 0,
      closed: 0,
      avgResponseTime: 0,
      resolutionRate: 0,
    };

    const total = filteredTickets.length;
    const statusCounts = filteredTickets.reduce((acc: any, ticket: any) => {
      acc[ticket.status] = (acc[ticket.status] || 0) + 1;
      return acc;
    }, {});

    const resolvedCount = (statusCounts.resolved || 0) + (statusCounts.closed || 0);
    const resolutionRate = total > 0 ? ((resolvedCount / total) * 100).toFixed(1) : 0;

    return {
      total,
      new: statusCounts.new || 0,
      open: statusCounts.open || 0,
      pending: statusCounts.pending || 0,
      resolved: statusCounts.resolved || 0,
      closed: statusCounts.closed || 0,
      avgResponseTime: '2.4', // Placeholder - would need actual timestamp data
      resolutionRate,
    };
  };

  const stats = calculateStats();

  // Calculate priority breakdown from filtered data
  const priorityBreakdown = filteredTickets?.reduce((acc: any, ticket: any) => {
    acc[ticket.priority] = (acc[ticket.priority] || 0) + 1;
    return acc;
  }, {}) || {};

  // Get recent tickets from filtered data
  const getRecentTickets = () => {
    if (!filteredTickets || filteredTickets.length === 0) return [];

    return filteredTickets
      .sort((a: any, b: any) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime())
      .slice(0, 50);
  };

  // Simulated audit logs (in production, this would come from a dedicated audit log table)
  const generateAuditLogs = () => {
    const logs: any[] = [];
    const recentTickets = getRecentTickets().slice(0, 20);

    recentTickets.forEach((ticket: any) => {
      // Creation log
      logs.push({
        id: `${ticket.id}-created`,
        action: 'created',
        entity: 'ticket',
        entity_id: ticket.id,
        user_name: ticket.client_name || 'System',
        description: `Created ticket #${ticket.ticket_number}: ${ticket.subject}`,
        timestamp: ticket.created_at,
      });

      // Status change log (if updated)
      if (ticket.updated_at !== ticket.created_at && ticket.status !== 'new') {
        logs.push({
          id: `${ticket.id}-status`,
          action: 'status_changed',
          entity: 'ticket',
          entity_id: ticket.id,
          user_name: ticket.assigned_agent_name || 'Agent',
          description: `Changed ticket #${ticket.ticket_number} status to ${ticket.status}`,
          timestamp: ticket.updated_at,
        });
      }

      // Assignment log (if assigned)
      if (ticket.assigned_agent_id) {
        logs.push({
          id: `${ticket.id}-assigned`,
          action: 'assigned',
          entity: 'ticket',
          entity_id: ticket.id,
          user_name: 'System',
          description: `Assigned ticket #${ticket.ticket_number} to ${ticket.assigned_agent_name || 'Agent'}`,
          timestamp: ticket.updated_at,
        });
      }
    });

    return logs.sort((a, b) => new Date(b.timestamp).getTime() - new Date(a.timestamp).getTime());
  };

  const auditLogs = generateAuditLogs();

  // Prepare chart data
  const statusChartData = [
    { name: 'New', value: stats.new, color: '#3b82f6' },
    { name: 'Open', value: stats.open, color: '#eab308' },
    { name: 'Pending', value: stats.pending, color: '#f97316' },
    { name: 'Resolved', value: stats.resolved, color: '#22c55e' },
    { name: 'Closed', value: stats.closed, color: '#6b7280' },
  ].filter(item => item.value > 0);

  const priorityChartData = [
    { name: 'Urgent', value: priorityBreakdown.urgent || 0, color: '#ef4444' },
    { name: 'High', value: priorityBreakdown.high || 0, color: '#f97316' },
    { name: 'Medium', value: priorityBreakdown.medium || 0, color: '#eab308' },
    { name: 'Low', value: priorityBreakdown.low || 0, color: '#3b82f6' },
  ].filter(item => item.value > 0);

  // Calculate daily trend data for the selected date range
  const getDailyTrendData = () => {
    if (!filteredTickets || filteredTickets.length === 0) return [];

    // Get date range from filters or use default
    const startDate = dateRange?.from || new Date(new Date().setDate(new Date().getDate() - 30));
    const endDate = dateRange?.to || new Date();

    // Create a map for all dates in range
    const dailyData: any = {};
    const currentDate = new Date(startDate);

    // Initialize all dates in the range with zero values
    while (currentDate <= endDate) {
      const dateKey = format(currentDate, 'yyyy-MM-dd');
      const displayDate = format(currentDate, 'MMM dd');
      dailyData[dateKey] = {
        date: displayDate,
        dateKey: dateKey,
        created: 0,
        resolved: 0,
        open: 0
      };
      currentDate.setDate(currentDate.getDate() + 1);
    }

    // Populate with actual ticket data
    filteredTickets.forEach((ticket: any) => {
      const ticketDate = new Date(ticket.created_at);
      const dateKey = format(ticketDate, 'yyyy-MM-dd');

      if (dailyData[dateKey]) {
        dailyData[dateKey].created += 1;
        if (ticket.status === 'resolved' || ticket.status === 'closed') {
          dailyData[dateKey].resolved += 1;
        } else if (ticket.status === 'open' || ticket.status === 'new') {
          dailyData[dateKey].open += 1;
        }
      }
    });

    // Convert to array and sort by date (earliest to latest)
    return Object.values(dailyData).sort((a: any, b: any) =>
      new Date(a.dateKey).getTime() - new Date(b.dateKey).getTime()
    );
  };

  const trendData = getDailyTrendData();

  // Response and Resolution Time Analysis
  const getResponseTimeByPriority = () => {
    if (!filteredTickets || filteredTickets.length === 0) return [];

    const priorityStats: any = {
      urgent: { name: 'Urgent', responseTimes: [], resolutionTimes: [], avgResponseTime: 0, avgResolutionTime: 0 },
      high: { name: 'High', responseTimes: [], resolutionTimes: [], avgResponseTime: 0, avgResolutionTime: 0 },
      medium: { name: 'Medium', responseTimes: [], resolutionTimes: [], avgResponseTime: 0, avgResolutionTime: 0 },
      low: { name: 'Low', responseTimes: [], resolutionTimes: [], avgResponseTime: 0, avgResolutionTime: 0 },
    };

    filteredTickets.forEach((ticket: any) => {
      const priority = ticket.priority;
      if (priorityStats[priority]) {
        // Calculate response time (first_response_at - created_at) in hours
        if (ticket.first_response_at && ticket.created_at) {
          const responseTime = (new Date(ticket.first_response_at).getTime() - new Date(ticket.created_at).getTime()) / (1000 * 60 * 60);
          priorityStats[priority].responseTimes.push(responseTime);
        }

        // Calculate resolution time (resolved_at - created_at) in hours
        if (ticket.resolved_at && ticket.created_at) {
          const resolutionTime = (new Date(ticket.resolved_at).getTime() - new Date(ticket.created_at).getTime()) / (1000 * 60 * 60);
          priorityStats[priority].resolutionTimes.push(resolutionTime);
        }
      }
    });

    // Calculate averages
    Object.keys(priorityStats).forEach((priority) => {
      const stat = priorityStats[priority];

      // Average response time
      if (stat.responseTimes.length > 0) {
        const sum = stat.responseTimes.reduce((a: number, b: number) => a + b, 0);
        stat.avgResponseTime = parseFloat((sum / stat.responseTimes.length).toFixed(1));
      }

      // Average resolution time
      if (stat.resolutionTimes.length > 0) {
        const sum = stat.resolutionTimes.reduce((a: number, b: number) => a + b, 0);
        stat.avgResolutionTime = parseFloat((sum / stat.resolutionTimes.length).toFixed(1));
      }

      // Clean up temporary arrays
      delete stat.responseTimes;
      delete stat.resolutionTimes;
    });

    return Object.values(priorityStats).filter((p: any) => p.avgResponseTime > 0 || p.avgResolutionTime > 0);
  };

  const responseTimeData = getResponseTimeByPriority();

  // Ticket Source Distribution
  const getSourceDistribution = () => {
    if (!filteredTickets || filteredTickets.length === 0) return [];

    const sourceStats: any = {};

    filteredTickets.forEach((ticket: any) => {
      const source = ticket.source || 'unknown';
      if (!sourceStats[source]) {
        sourceStats[source] = { name: source.charAt(0).toUpperCase() + source.slice(1), value: 0 };
      }
      sourceStats[source].value += 1;
    });

    return Object.values(sourceStats);
  };

  const sourceDistributionData = getSourceDistribution();

  const getStatusBadge = (status: string) => {
    const config = statusConfig[status as keyof typeof statusConfig] || statusConfig.new;
    return (
      <Badge variant="outline" className="gap-1">
        <span className={cn('h-2 w-2 rounded-full', config.color)} />
        {config.label}
      </Badge>
    );
  };

  // Handle export
  const handleExport = async (exportFormat: 'excel' | 'pdf') => {
    try {
      const params: any = {};

      if (dateRange?.from) {
        params.date_from = format(dateRange.from, 'yyyy-MM-dd');
      }
      if (dateRange?.to) {
        params.date_to = format(dateRange.to, 'yyyy-MM-dd');
      }

      // Call the export API
      const response = await api.analytics.exports.reports(exportFormat, params);

      // Create a download link for the file
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;

      const fileName = `aidly_report_${format(new Date(), 'yyyy-MM-dd_HHmmss')}.${exportFormat === 'excel' ? 'csv' : 'pdf'}`;
      link.setAttribute('download', fileName);

      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);

      toast.success(`Report exported successfully as ${exportFormat.toUpperCase()}`);
    } catch (error: any) {
      console.error('Export failed:', error);
      toast.error('Failed to export report: ' + (error?.response?.data?.message || error.message));
    }
  };

  return (
    <div className="flex-1 space-y-4 p-8 pt-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-3xl font-bold tracking-tight">Reports & Analytics</h2>
          <p className="text-muted-foreground">
            Comprehensive insights and system activity logs
          </p>
        </div>
        <div className="flex items-center gap-2">
          <DateRangePicker
            date={dateRange}
            onDateChange={setDateRange}
            placeholder="Select date range"
          />
          <Button variant="outline" onClick={() => setExportDialogOpen(true)}>
            <Download className="mr-2 h-4 w-4" />
            Export
          </Button>
        </div>
      </div>

      {/* Export Dialog */}
      <ExportDialog
        open={exportDialogOpen}
        onOpenChange={setExportDialogOpen}
        onExport={handleExport}
        title="Export Report"
        description="Choose a format to export your analytics report"
      />

      {/* Key Metrics Overview */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Total Tickets</CardTitle>
            <Ticket className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {isLoadingTickets ? '...' : stats.total}
            </div>
            <p className="text-xs text-muted-foreground">All time tickets</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Active Tickets</CardTitle>
            <Clock className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {isLoadingTickets ? '...' : stats.new + stats.open + stats.pending}
            </div>
            <p className="text-xs text-muted-foreground">New, open, pending</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Resolution Rate</CardTitle>
            <CheckCircle className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {isLoadingTickets ? '...' : `${stats.resolutionRate}%`}
            </div>
            <p className="text-xs text-muted-foreground">{stats.resolved + stats.closed} resolved</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Total Customers</CardTitle>
            <Users className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {clientsData?.length || 0}
            </div>
            <p className="text-xs text-muted-foreground">Registered clients</p>
          </CardContent>
        </Card>
      </div>

      {/* Tabs for Different Report Views */}
      <Tabs defaultValue="overview" className="space-y-4">
        <TabsList>
          <TabsTrigger value="overview">Overview</TabsTrigger>
          <TabsTrigger value="history">Ticket History</TabsTrigger>
          <TabsTrigger value="audit">Audit Logs</TabsTrigger>
        </TabsList>

        {/* Overview Tab */}
        <TabsContent value="overview" className="space-y-4">
          <div className="grid gap-4 md:grid-cols-2">
            <Card>
              <CardHeader>
                <CardTitle>Ticket Status Distribution</CardTitle>
                <CardDescription>Current ticket status breakdown</CardDescription>
              </CardHeader>
              <CardContent>
                {isLoadingTickets ? (
                  <div className="flex justify-center py-8">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                  </div>
                ) : statusChartData.length > 0 ? (
                  <ResponsiveContainer width="100%" height={300}>
                    <PieChart>
                      <Pie
                        data={statusChartData}
                        cx="50%"
                        cy="50%"
                        labelLine={false}
                        label={(entry) => `${entry.name}: ${entry.value}`}
                        outerRadius={90}
                        fill="#8884d8"
                        dataKey="value"
                      >
                        {statusChartData.map((entry: any, index: number) => (
                          <Cell key={`cell-${index}`} fill={entry.color} />
                        ))}
                      </Pie>
                      <Tooltip />
                    </PieChart>
                  </ResponsiveContainer>
                ) : (
                  <div className="flex justify-center items-center py-8 text-muted-foreground">
                    No data available for selected date range
                  </div>
                )}
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Priority Breakdown</CardTitle>
                <CardDescription>Tickets by priority level</CardDescription>
              </CardHeader>
              <CardContent>
                {isLoadingTickets ? (
                  <div className="flex justify-center py-8">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                  </div>
                ) : priorityChartData.length > 0 ? (
                  <ResponsiveContainer width="100%" height={300}>
                    <BarChart data={priorityChartData}>
                      <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
                      <XAxis dataKey="name" className="text-xs" />
                      <YAxis className="text-xs" />
                      <Tooltip />
                      <Bar dataKey="value" radius={[8, 8, 0, 0]}>
                        {priorityChartData.map((entry: any, index: number) => (
                          <Cell key={`cell-${index}`} fill={entry.color} />
                        ))}
                      </Bar>
                    </BarChart>
                  </ResponsiveContainer>
                ) : (
                  <div className="flex justify-center items-center py-8 text-muted-foreground">
                    No data available for selected date range
                  </div>
                )}
              </CardContent>
            </Card>
          </div>

          {/* Ticket Trends Chart */}
          <Card>
            <CardHeader>
              <CardTitle>Ticket Trends</CardTitle>
              <CardDescription>Daily ticket creation and resolution trends</CardDescription>
            </CardHeader>
            <CardContent>
              {isLoadingTickets ? (
                <div className="flex justify-center py-8">
                  <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                </div>
              ) : trendData.length > 0 ? (
                <ResponsiveContainer width="100%" height={350}>
                  <AreaChart data={trendData}>
                    <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
                    <XAxis dataKey="date" className="text-xs" />
                    <YAxis className="text-xs" />
                    <Tooltip
                      content={({ active, payload, label }) => {
                        if (active && payload && payload.length) {
                          return (
                            <div className="bg-background border rounded p-2 shadow-lg">
                              <p className="text-sm font-medium">{label}</p>
                              <p className="text-xs text-blue-600">
                                Created: {payload[0]?.value || 0} tickets
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
                    <Legend />
                    <Area
                      type="monotone"
                      dataKey="created"
                      stackId="1"
                      stroke="#3b82f6"
                      fill="#3b82f6"
                      fillOpacity={0.6}
                      name="Created"
                    />
                    <Area
                      type="monotone"
                      dataKey="resolved"
                      stackId="2"
                      stroke="#22c55e"
                      fill="#22c55e"
                      fillOpacity={0.6}
                      name="Resolved"
                    />
                    <Area
                      type="monotone"
                      dataKey="open"
                      stackId="3"
                      stroke="#f97316"
                      fill="#f97316"
                      fillOpacity={0.6}
                      name="Open"
                    />
                  </AreaChart>
                </ResponsiveContainer>
              ) : (
                <div className="flex justify-center items-center py-8 text-muted-foreground">
                  No trend data available for selected date range
                </div>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Team Overview</CardTitle>
              <CardDescription>Agent and customer statistics</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="grid gap-4 md:grid-cols-3">
                <div className="flex flex-col">
                  <span className="text-sm text-muted-foreground">Total Agents</span>
                  <span className="text-2xl font-bold">{agentsData?.length || 0}</span>
                </div>
                <div className="flex flex-col">
                  <span className="text-sm text-muted-foreground">Active Agents</span>
                  <span className="text-2xl font-bold">
                    {agentsData?.filter((u: any) => u.is_active).length || 0}
                  </span>
                </div>
                <div className="flex flex-col">
                  <span className="text-sm text-muted-foreground">Total Customers</span>
                  <span className="text-2xl font-bold">{clientsData?.length || 0}</span>
                </div>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        {/* Ticket History Tab */}
        <TabsContent value="history" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Recent Ticket History</CardTitle>
              <CardDescription>
                {dateRange?.from && dateRange?.to
                  ? `Tickets from ${format(dateRange.from, 'MMM d, yyyy')} to ${format(dateRange.to, 'MMM d, yyyy')}`
                  : dateRange?.from
                  ? `Tickets from ${format(dateRange.from, 'MMM d, yyyy')}`
                  : 'All tickets'}
              </CardDescription>
            </CardHeader>
            <CardContent>
              {isLoadingTickets ? (
                <div className="flex justify-center py-8">
                  <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                </div>
              ) : getRecentTickets().length > 0 ? (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Ticket</TableHead>
                      <TableHead>Customer</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead>Priority</TableHead>
                      <TableHead>Assigned To</TableHead>
                      <TableHead>Created</TableHead>
                      <TableHead>Updated</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {getRecentTickets().map((ticket: any) => (
                      <TableRow
                        key={ticket.id}
                        className="cursor-pointer hover:bg-accent/50"
                        onClick={() => router.push(`/tickets/${ticket.id}`)}
                      >
                        <TableCell>
                          <div>
                            <p className="font-medium">{ticket.subject}</p>
                            <p className="text-sm text-muted-foreground">#{ticket.ticket_number}</p>
                          </div>
                        </TableCell>
                        <TableCell>{ticket.client_name || 'Unknown'}</TableCell>
                        <TableCell>{getStatusBadge(ticket.status)}</TableCell>
                        <TableCell>
                          <Badge variant={
                            ticket.priority === 'urgent' ? 'destructive' :
                            ticket.priority === 'high' ? 'warning' : 'default'
                          }>
                            {ticket.priority}
                          </Badge>
                        </TableCell>
                        <TableCell>{ticket.assigned_agent_name || 'Unassigned'}</TableCell>
                        <TableCell>
                          <div className="text-sm">
                            {ticket.created_at ? format(new Date(ticket.created_at), 'MMM d, yyyy') : '-'}
                          </div>
                        </TableCell>
                        <TableCell>
                          <div className="text-sm">
                            {ticket.updated_at ? format(new Date(ticket.updated_at), 'MMM d, yyyy') : '-'}
                          </div>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              ) : (
                <div className="text-center py-8 text-muted-foreground">
                  No tickets found in the selected time range
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        {/* Audit Logs Tab */}
        <TabsContent value="audit" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>System Audit Logs</CardTitle>
              <CardDescription>
                Recent system activities and changes
              </CardDescription>
            </CardHeader>
            <CardContent>
              {isLoadingTickets ? (
                <div className="flex justify-center py-8">
                  <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                </div>
              ) : auditLogs.length > 0 ? (
                <div className="space-y-4">
                  {auditLogs.map((log: any) => {
                    const Icon = actionIcons[log.action] || Activity;
                    return (
                      <div key={log.id} className="flex items-start gap-4 pb-4 border-b last:border-0">
                        <div className="rounded-full bg-accent p-2">
                          <Icon className="h-4 w-4 text-muted-foreground" />
                        </div>
                        <div className="flex-1 space-y-1">
                          <div className="flex items-center justify-between">
                            <p className="text-sm font-medium">{log.description}</p>
                            <span className="text-xs text-muted-foreground">
                              {log.timestamp ? format(new Date(log.timestamp), 'MMM d, HH:mm') : '-'}
                            </span>
                          </div>
                          <div className="flex items-center gap-2">
                            <Badge variant="outline" className="text-xs">
                              {log.action.replace('_', ' ')}
                            </Badge>
                            <span className="text-xs text-muted-foreground">by {log.user_name}</span>
                          </div>
                        </div>
                      </div>
                    );
                  })}
                </div>
              ) : (
                <div className="text-center py-8 text-muted-foreground">
                  No audit logs available
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  );
}