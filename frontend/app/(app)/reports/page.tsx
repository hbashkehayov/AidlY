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
  ChevronLeft,
  ChevronRight,
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
import { getStatusColor, getStatusLabel, getPriorityColor, getPriorityLabel, getPriorityChartColor } from '@/lib/colors';

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
  const [auditLogsPage, setAuditLogsPage] = useState(1);
  const [auditLogsPerPage, setAuditLogsPerPage] = useState(20);
  const [ticketHistoryPage, setTicketHistoryPage] = useState(1);
  const [ticketHistoryPerPage, setTicketHistoryPerPage] = useState(20);

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

  // Fetch all users (for lookups)
  const { data: allUsersData } = useQuery({
    queryKey: ['all-users-reports'],
    queryFn: async () => {
      const response = await api.users.list();
      return response.data?.data || response.data || [];
    },
  });

  // Filter agents for display
  const agentsData = allUsersData?.filter((u: any) => u.role === 'agent') || [];

  // Fetch all clients
  const { data: clientsResponse } = useQuery({
    queryKey: ['all-clients-reports'],
    queryFn: async () => {
      const response = await api.clients.list({ per_page: 1000 });
      return response.data || {};
    },
  });

  const clientsData = clientsResponse?.data || [];
  const totalClients = clientsResponse?.meta?.total || clientsData?.length || 0;

  // Helper function to get client name from ticket
  const getClientNameFromTicket = (ticket: any) => {
    // First check if ticket has embedded client object
    if (ticket.client?.name) return ticket.client.name;
    // Then check for client_name field
    if (ticket.client_name) return ticket.client_name;
    // Finally, look up by client_id
    if (ticket.client_id) {
      const client = clientsData.find((c: any) => c.id === ticket.client_id);
      return client?.name || 'Unknown';
    }
    return 'Unknown';
  };

  // Helper function to get agent/user name from ticket
  const getAgentNameFromTicket = (ticket: any) => {
    // First check if ticket has embedded assigned_agent object
    if (ticket.assigned_agent?.name) return ticket.assigned_agent.name;
    // Then check for assigned_agent_name field
    if (ticket.assigned_agent_name) return ticket.assigned_agent_name;
    // Finally, look up by assigned_agent_id
    if (ticket.assigned_agent_id) {
      const user = allUsersData?.find((u: any) => u.id === ticket.assigned_agent_id);
      return user?.name || 'Unassigned';
    }
    return 'Unassigned';
  };

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
  const getAllRecentTickets = () => {
    if (!filteredTickets || filteredTickets.length === 0) return [];

    return filteredTickets
      .sort((a: any, b: any) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime());
  };

  const allRecentTickets = getAllRecentTickets();

  // Paginate ticket history
  const totalTicketHistory = allRecentTickets.length;
  const totalTicketHistoryPages = Math.ceil(totalTicketHistory / ticketHistoryPerPage);
  const ticketHistoryStartIndex = (ticketHistoryPage - 1) * ticketHistoryPerPage;
  const ticketHistoryEndIndex = ticketHistoryStartIndex + ticketHistoryPerPage;
  const paginatedTicketHistory = allRecentTickets.slice(ticketHistoryStartIndex, ticketHistoryEndIndex);

  // Simulated audit logs (in production, this would come from a dedicated audit log table)
  const generateAuditLogs = () => {
    const logs: any[] = [];
    const recentTickets = allRecentTickets.slice(0, 20);

    recentTickets.forEach((ticket: any) => {
      // Creation log
      logs.push({
        id: `${ticket.id}-created`,
        action: 'created',
        entity: 'ticket',
        entity_id: ticket.id,
        user_name: ticket.client_name || 'System',
        description: `Created ticket #${ticket.ticket_number}: ${ticket.subject || '(No Subject)'}`,
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

  const allAuditLogs = generateAuditLogs();

  // Paginate audit logs
  const totalAuditLogs = allAuditLogs.length;
  const totalAuditPages = Math.ceil(totalAuditLogs / auditLogsPerPage);
  const startIndex = (auditLogsPage - 1) * auditLogsPerPage;
  const endIndex = startIndex + auditLogsPerPage;
  const paginatedAuditLogs = allAuditLogs.slice(startIndex, endIndex);

  // Prepare chart data
  const statusChartData = [
    { name: 'New', value: stats.new, color: '#3b82f6' },
    { name: 'Open', value: stats.open, color: '#eab308' },
    { name: 'Pending', value: stats.pending, color: '#f97316' },
    { name: 'Resolved', value: stats.resolved, color: '#22c55e' },
    { name: 'Closed', value: stats.closed, color: '#6b7280' },
  ].filter(item => item.value > 0);

  const priorityChartData = [
    { name: 'Urgent', value: priorityBreakdown.urgent || 0, color: getPriorityChartColor('urgent') },
    { name: 'High', value: priorityBreakdown.high || 0, color: getPriorityChartColor('high') },
    { name: 'Medium', value: priorityBreakdown.medium || 0, color: getPriorityChartColor('medium') },
    { name: 'Low', value: priorityBreakdown.low || 0, color: getPriorityChartColor('low') },
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
    const colors = getStatusColor(status);
    return (
      <Badge variant={`status-${status.toLowerCase()}` as any} className="gap-1.5">
        <span className={cn('h-2 w-2 rounded-full', colors.dot)} />
        {getStatusLabel(status)}
      </Badge>
    );
  };

  const getPriorityBadge = (priority: string) => {
    const colors = getPriorityColor(priority);
    return (
      <Badge variant={`priority-${priority.toLowerCase()}` as any} className="gap-1.5">
        <span className={cn('h-2 w-2 rounded-full', colors.dot)} />
        {getPriorityLabel(priority)}
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
              {totalClients}
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

          {/* Agent Performance Metrics */}
          <Card>
            <CardHeader>
              <CardTitle>Agent Performance</CardTitle>
              <CardDescription>Resolved tickets per agent</CardDescription>
            </CardHeader>
            <CardContent>
              {isLoadingTickets ? (
                <div className="flex justify-center py-8">
                  <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                </div>
              ) : (() => {
                // Calculate agent performance
                const agentPerformance: Record<string, { name: string; resolved: number; total: number }> = {};

                filteredTickets?.forEach((ticket: any) => {
                  const agentId = ticket.assigned_agent_id;
                  if (agentId) {
                    if (!agentPerformance[agentId]) {
                      const agentName = getAgentNameFromTicket(ticket);
                      agentPerformance[agentId] = {
                        name: agentName !== 'Unassigned' ? agentName : `Agent ${agentId.substring(0, 8)}`,
                        resolved: 0,
                        total: 0
                      };
                    }
                    agentPerformance[agentId].total += 1;
                    if (ticket.status === 'resolved' || ticket.status === 'closed') {
                      agentPerformance[agentId].resolved += 1;
                    }
                  }
                });

                const agentChartData = Object.values(agentPerformance)
                  .sort((a, b) => b.resolved - a.resolved)
                  .map(agent => ({
                    name: agent.name,
                    resolved: agent.resolved,
                    total: agent.total,
                    percentage: agent.total > 0 ? Math.round((agent.resolved / agent.total) * 100) : 0
                  }));

                return agentChartData.length > 0 ? (
                  <>
                    <ResponsiveContainer width="100%" height={Math.max(300, agentChartData.length * 50)}>
                      <BarChart data={agentChartData} layout="vertical">
                        <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
                        <XAxis type="number" className="text-xs" />
                        <YAxis dataKey="name" type="category" width={120} className="text-xs" />
                        <Tooltip
                          content={({ active, payload }) => {
                            if (active && payload && payload.length) {
                              const data = payload[0].payload;
                              return (
                                <div className="bg-background border rounded p-3 shadow-lg">
                                  <p className="text-sm font-medium mb-2">{data.name}</p>
                                  <p className="text-xs text-green-600">
                                    Resolved: {data.resolved} tickets
                                  </p>
                                  <p className="text-xs text-blue-600">
                                    Total Assigned: {data.total} tickets
                                  </p>
                                  <p className="text-xs text-muted-foreground">
                                    Resolution Rate: {data.percentage}%
                                  </p>
                                </div>
                              );
                            }
                            return null;
                          }}
                        />
                        <Legend />
                        <Bar dataKey="resolved" fill="#22c55e" name="Resolved Tickets" radius={[0, 4, 4, 0]} />
                      </BarChart>
                    </ResponsiveContainer>
                  </>
                ) : (
                  <div className="flex justify-center items-center py-8 text-muted-foreground">
                    No agent performance data available for selected date range
                  </div>
                );
              })()}
            </CardContent>
          </Card>
        </TabsContent>

        {/* Ticket History Tab */}
        <TabsContent value="history" className="space-y-4">
          <Card>
            <CardHeader>
              <div className="flex items-center justify-between">
                <div>
                  <CardTitle>Recent Ticket History</CardTitle>
                  <CardDescription>
                    {dateRange?.from && dateRange?.to
                      ? `Tickets from ${format(dateRange.from, 'MMM d, yyyy')} to ${format(dateRange.to, 'MMM d, yyyy')}`
                      : dateRange?.from
                      ? `Tickets from ${format(dateRange.from, 'MMM d, yyyy')}`
                      : 'All tickets'}
                  </CardDescription>
                </div>
                <Select value={ticketHistoryPerPage.toString()} onValueChange={(value) => {
                  setTicketHistoryPerPage(Number(value));
                  setTicketHistoryPage(1);
                }}>
                  <SelectTrigger className="w-[100px]">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="10">10</SelectItem>
                    <SelectItem value="20">20</SelectItem>
                    <SelectItem value="50">50</SelectItem>
                    <SelectItem value="100">100</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </CardHeader>
            <CardContent>
              {isLoadingTickets ? (
                <div className="flex justify-center py-8">
                  <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                </div>
              ) : paginatedTicketHistory.length > 0 ? (
                <>
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
                      {paginatedTicketHistory.map((ticket: any) => (
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
                          <TableCell>{getClientNameFromTicket(ticket)}</TableCell>
                          <TableCell>{getStatusBadge(ticket.status)}</TableCell>
                          <TableCell>{getPriorityBadge(ticket.priority)}</TableCell>
                          <TableCell>{getAgentNameFromTicket(ticket)}</TableCell>
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

                  {/* Pagination */}
                  {totalTicketHistoryPages > 1 && (
                    <div className="flex items-center justify-between pt-4 mt-4 border-t">
                      <p className="text-sm text-muted-foreground">
                        Showing {ticketHistoryStartIndex + 1} to {Math.min(ticketHistoryEndIndex, totalTicketHistory)} of {totalTicketHistory} tickets
                      </p>
                      <div className="flex items-center gap-1">
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => setTicketHistoryPage(p => Math.max(1, p - 1))}
                          disabled={ticketHistoryPage === 1}
                          className="gap-1"
                        >
                          <ChevronLeft className="h-4 w-4" />
                          Previous
                        </Button>

                        {/* Page Numbers */}
                        <div className="flex items-center gap-1 mx-2">
                          {(() => {
                            const pages = [];
                            const maxVisiblePages = 5;

                            if (totalTicketHistoryPages <= maxVisiblePages) {
                              // Show all pages if total is small
                              for (let i = 1; i <= totalTicketHistoryPages; i++) {
                                pages.push(i);
                              }
                            } else {
                              // Smart pagination logic
                              if (ticketHistoryPage <= 3) {
                                // Near the beginning
                                for (let i = 1; i <= 4; i++) {
                                  pages.push(i);
                                }
                                pages.push('...');
                                pages.push(totalTicketHistoryPages);
                              } else if (ticketHistoryPage >= totalTicketHistoryPages - 2) {
                                // Near the end
                                pages.push(1);
                                pages.push('...');
                                for (let i = totalTicketHistoryPages - 3; i <= totalTicketHistoryPages; i++) {
                                  pages.push(i);
                                }
                              } else {
                                // In the middle
                                pages.push(1);
                                pages.push('...');
                                pages.push(ticketHistoryPage - 1);
                                pages.push(ticketHistoryPage);
                                pages.push(ticketHistoryPage + 1);
                                pages.push('...');
                                pages.push(totalTicketHistoryPages);
                              }
                            }

                            return pages.map((page, index) => {
                              if (page === '...') {
                                return (
                                  <span key={`ellipsis-${index}`} className="px-2 text-muted-foreground">
                                    ...
                                  </span>
                                );
                              }
                              return (
                                <Button
                                  key={page}
                                  variant={ticketHistoryPage === page ? 'default' : 'outline'}
                                  size="sm"
                                  onClick={() => setTicketHistoryPage(page as number)}
                                  className="w-8 h-8 p-0"
                                >
                                  {page}
                                </Button>
                              );
                            });
                          })()}
                        </div>

                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => setTicketHistoryPage(p => Math.min(totalTicketHistoryPages, p + 1))}
                          disabled={ticketHistoryPage === totalTicketHistoryPages}
                          className="gap-1"
                        >
                          Next
                          <ChevronRight className="h-4 w-4" />
                        </Button>
                      </div>
                    </div>
                  )}
                </>
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
              <div className="flex items-center justify-between">
                <div>
                  <CardTitle>System Audit Logs</CardTitle>
                  <CardDescription>
                    Recent system activities and changes
                  </CardDescription>
                </div>
                <Select value={auditLogsPerPage.toString()} onValueChange={(value) => {
                  setAuditLogsPerPage(Number(value));
                  setAuditLogsPage(1);
                }}>
                  <SelectTrigger className="w-[100px]">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="10">10</SelectItem>
                    <SelectItem value="20">20</SelectItem>
                    <SelectItem value="50">50</SelectItem>
                    <SelectItem value="100">100</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </CardHeader>
            <CardContent>
              {isLoadingTickets ? (
                <div className="flex justify-center py-8">
                  <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                </div>
              ) : paginatedAuditLogs.length > 0 ? (
                <>
                  <div className="space-y-4">
                    {paginatedAuditLogs.map((log: any) => {
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

                  {/* Pagination */}
                  {totalAuditPages > 1 && (
                    <div className="flex items-center justify-between pt-4 mt-4 border-t">
                      <p className="text-sm text-muted-foreground">
                        Showing {startIndex + 1} to {Math.min(endIndex, totalAuditLogs)} of {totalAuditLogs} logs
                      </p>
                      <div className="flex items-center gap-1">
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => setAuditLogsPage(p => Math.max(1, p - 1))}
                          disabled={auditLogsPage === 1}
                          className="gap-1"
                        >
                          <ChevronLeft className="h-4 w-4" />
                          Previous
                        </Button>

                        {/* Page Numbers */}
                        <div className="flex items-center gap-1 mx-2">
                          {(() => {
                            const pages = [];
                            const maxVisiblePages = 5;

                            if (totalAuditPages <= maxVisiblePages) {
                              // Show all pages if total is small
                              for (let i = 1; i <= totalAuditPages; i++) {
                                pages.push(i);
                              }
                            } else {
                              // Smart pagination logic
                              if (auditLogsPage <= 3) {
                                // Near the beginning
                                for (let i = 1; i <= 4; i++) {
                                  pages.push(i);
                                }
                                pages.push('...');
                                pages.push(totalAuditPages);
                              } else if (auditLogsPage >= totalAuditPages - 2) {
                                // Near the end
                                pages.push(1);
                                pages.push('...');
                                for (let i = totalAuditPages - 3; i <= totalAuditPages; i++) {
                                  pages.push(i);
                                }
                              } else {
                                // In the middle
                                pages.push(1);
                                pages.push('...');
                                pages.push(auditLogsPage - 1);
                                pages.push(auditLogsPage);
                                pages.push(auditLogsPage + 1);
                                pages.push('...');
                                pages.push(totalAuditPages);
                              }
                            }

                            return pages.map((page, index) => {
                              if (page === '...') {
                                return (
                                  <span key={`ellipsis-${index}`} className="px-2 text-muted-foreground">
                                    ...
                                  </span>
                                );
                              }
                              return (
                                <Button
                                  key={page}
                                  variant={auditLogsPage === page ? 'default' : 'outline'}
                                  size="sm"
                                  onClick={() => setAuditLogsPage(page as number)}
                                  className="w-8 h-8 p-0"
                                >
                                  {page}
                                </Button>
                              );
                            });
                          })()}
                        </div>

                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => setAuditLogsPage(p => Math.min(totalAuditPages, p + 1))}
                          disabled={auditLogsPage === totalAuditPages}
                          className="gap-1"
                        >
                          Next
                          <ChevronRight className="h-4 w-4" />
                        </Button>
                      </div>
                    </div>
                  )}
                </>
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