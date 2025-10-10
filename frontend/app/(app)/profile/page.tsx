'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { format } from 'date-fns';
import { useAuth } from '@/lib/auth';
import {
  ArrowLeft,
  Mail,
  Phone,
  Building,
  Calendar,
  Ticket,
  Edit,
  User as UserIcon,
  Shield,
  Clock,
  CheckCircle,
  XCircle,
  AlertCircle,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';

const statusConfig = {
  new: { label: 'New', color: 'bg-blue-500', icon: AlertCircle },
  open: { label: 'Open', color: 'bg-yellow-500', icon: Clock },
  pending: { label: 'Pending', color: 'bg-orange-500', icon: Clock },
  on_hold: { label: 'On Hold', color: 'bg-gray-500', icon: Clock },
  resolved: { label: 'Resolved', color: 'bg-green-500', icon: CheckCircle },
  closed: { label: 'Closed', color: 'bg-gray-400', icon: XCircle },
  cancelled: { label: 'Cancelled', color: 'bg-red-500', icon: XCircle },
};

const priorityConfig = {
  low: { label: 'Low', color: 'default' },
  medium: { label: 'Medium', color: 'secondary' },
  high: { label: 'High', color: 'warning' },
  urgent: { label: 'Urgent', color: 'destructive' },
};

const roleConfig = {
  admin: { label: 'Administrator', color: 'destructive', icon: Shield },
  agent: { label: 'Agent', color: 'default', icon: UserIcon },
};

export default function ProfilePage() {
  const router = useRouter();
  const { user } = useAuth();
  const [currentPage, setCurrentPage] = useState(1);

  // Check if user is admin
  const isAdmin = user?.role === 'admin';

  // Base params for filtering
  const baseParams = isAdmin ? {} : { assigned_agent_id: user?.id };

  // Fetch tickets for display (paginated)
  const { data: ticketsResponse, isLoading: isLoadingTickets } = useQuery({
    queryKey: ['user-tickets', user?.id, user?.role, currentPage],
    queryFn: async () => {
      const params = { ...baseParams, page: currentPage, per_page: 20 };
      const response = await api.tickets.list(params);
      return response.data;
    },
    enabled: !!user?.id,
  });

  // Get TOTAL count from metadata
  const { data: totalCountResponse } = useQuery({
    queryKey: ['user-tickets-total', user?.id, user?.role],
    queryFn: async () => {
      const params = { ...baseParams, page: 1, per_page: 1 };
      const response = await api.tickets.list(params);
      return response.data;
    },
    enabled: !!user?.id,
  });

  // Get OPEN tickets count - we need to sum multiple statuses
  // Query for 'new' status
  const { data: newCountResponse } = useQuery({
    queryKey: ['user-tickets-new', user?.id, user?.role],
    queryFn: async () => {
      const params = { ...baseParams, status: 'new', page: 1, per_page: 1 };
      const response = await api.tickets.list(params);
      return response.data;
    },
    enabled: !!user?.id,
  });

  // Query for 'open' status
  const { data: openStatusCountResponse } = useQuery({
    queryKey: ['user-tickets-open-status', user?.id, user?.role],
    queryFn: async () => {
      const params = { ...baseParams, status: 'open', page: 1, per_page: 1 };
      const response = await api.tickets.list(params);
      return response.data;
    },
    enabled: !!user?.id,
  });

  // Query for 'pending' status
  const { data: pendingCountResponse } = useQuery({
    queryKey: ['user-tickets-pending', user?.id, user?.role],
    queryFn: async () => {
      const params = { ...baseParams, status: 'pending', page: 1, per_page: 1 };
      const response = await api.tickets.list(params);
      return response.data;
    },
    enabled: !!user?.id,
  });

  // Query for 'on_hold' status
  const { data: onHoldCountResponse } = useQuery({
    queryKey: ['user-tickets-on-hold', user?.id, user?.role],
    queryFn: async () => {
      const params = { ...baseParams, status: 'on_hold', page: 1, per_page: 1 };
      const response = await api.tickets.list(params);
      return response.data;
    },
    enabled: !!user?.id,
  });

  // Query for 'resolved' status
  const { data: resolvedStatusCountResponse } = useQuery({
    queryKey: ['user-tickets-resolved-status', user?.id, user?.role],
    queryFn: async () => {
      const params = { ...baseParams, status: 'resolved', page: 1, per_page: 1 };
      const response = await api.tickets.list(params);
      return response.data;
    },
    enabled: !!user?.id,
  });

  // Query for 'closed' status
  const { data: closedCountResponse } = useQuery({
    queryKey: ['user-tickets-closed', user?.id, user?.role],
    queryFn: async () => {
      const params = { ...baseParams, status: 'closed', page: 1, per_page: 1 };
      const response = await api.tickets.list(params);
      return response.data;
    },
    enabled: !!user?.id,
  });

  const tickets = ticketsResponse?.data || [];
  const totalTickets = totalCountResponse?.meta?.total || 0;

  // Sum up the open ticket statuses
  const openTickets = (newCountResponse?.meta?.total || 0) +
                      (openStatusCountResponse?.meta?.total || 0) +
                      (pendingCountResponse?.meta?.total || 0) +
                      (onHoldCountResponse?.meta?.total || 0);

  // Sum up the resolved ticket statuses
  const resolvedTickets = (resolvedStatusCountResponse?.meta?.total || 0) +
                          (closedCountResponse?.meta?.total || 0);

  const totalPages = ticketsResponse?.meta?.last_page || 1;

  const getStatusBadge = (status: string) => {
    const config = statusConfig[status as keyof typeof statusConfig];
    return (
      <Badge variant="outline" className="gap-1">
        <span className={cn('h-2 w-2 rounded-full', config.color)} />
        {config.label}
      </Badge>
    );
  };

  const getPriorityBadge = (priority: string) => {
    const config = priorityConfig[priority as keyof typeof priorityConfig];
    return (
      <Badge variant={config.color as any}>
        {config.label}
      </Badge>
    );
  };

  const getRoleBadge = (role: string) => {
    const config = roleConfig[role as keyof typeof roleConfig] || roleConfig.agent;
    const Icon = config.icon;
    return (
      <Badge variant={`role-${role}` as any} className="gap-1">
        <Icon className="h-3 w-3" />
        {config.label}
      </Badge>
    );
  };

  const initials = user?.name
    ? user.name.split(' ').map((n: string) => n[0]).join('').toUpperCase()
    : 'U';

  if (!user) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary"></div>
      </div>
    );
  }


  return (
    <div className="flex-1 space-y-4 p-8 pt-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-4">
          <Button
            variant="ghost"
            size="icon"
            onClick={() => router.push('/dashboard')}
          >
            <ArrowLeft className="h-4 w-4" />
          </Button>
          <div>
            <h2 className="text-3xl font-bold tracking-tight">My Profile</h2>
            <p className="text-muted-foreground">
              View and manage your profile information
            </p>
          </div>
        </div>
        <Button variant="outline" onClick={() => router.push('/settings')}>
          <Edit className="h-4 w-4 mr-2" />
          Edit Profile
        </Button>
      </div>

      {/* Profile Overview */}
      <Card>
        <CardContent className="pt-6">
          <div className="flex items-start gap-6">
            <Avatar className="h-24 w-24">
              <AvatarImage src={user.avatar_url} />
              <AvatarFallback className="text-2xl">{initials}</AvatarFallback>
            </Avatar>
            <div className="flex-1">
              <div className="flex items-center gap-3 mb-2">
                <h3 className="text-2xl font-bold">{user.name || 'Unknown'}</h3>
                {getRoleBadge(user.role)}
                {user.is_active ? (
                  <Badge variant="outline" className="gap-1">
                    <span className="h-2 w-2 rounded-full bg-green-500" />
                    Active
                  </Badge>
                ) : (
                  <Badge variant="outline" className="gap-1">
                    <span className="h-2 w-2 rounded-full bg-gray-400" />
                    Inactive
                  </Badge>
                )}
              </div>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <div className="flex items-center gap-2 text-muted-foreground">
                  <Mail className="h-4 w-4" />
                  <span>{user.email}</span>
                </div>
                <div className="flex items-center gap-2 text-muted-foreground">
                  <Calendar className="h-4 w-4" />
                  <span>
                    Member since {user.created_at ? format(new Date(user.created_at), 'MMMM yyyy') : '-'}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Stats Cards */}
      <div className="grid gap-4 md:grid-cols-3">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Total Tickets</CardTitle>
            <Ticket className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{totalTickets}</div>
            <p className="text-xs text-muted-foreground">
              {isAdmin ? 'Total in system' : 'Assigned to you'}
            </p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Open Tickets</CardTitle>
            <AlertCircle className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{openTickets}</div>
            <p className="text-xs text-muted-foreground">
              {isAdmin ? 'Currently open' : 'Requires attention'}
            </p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Resolved Tickets</CardTitle>
            <CheckCircle className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{resolvedTickets}</div>
            <p className="text-xs text-muted-foreground">
              {isAdmin ? 'Total resolved' : 'Successfully resolved'}
            </p>
          </CardContent>
        </Card>
      </div>

      {/* Tabs */}
      <Tabs defaultValue="tickets" className="space-y-4">
        <TabsList>
          <TabsTrigger value="tickets">My Tickets</TabsTrigger>
          <TabsTrigger value="details">Account Details</TabsTrigger>
        </TabsList>

        <TabsContent value="tickets" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>{isAdmin ? 'All Tickets' : 'Assigned Tickets'}</CardTitle>
              <CardDescription>
                {isAdmin ? 'All tickets in the system' : 'Tickets currently assigned to you'}
              </CardDescription>
            </CardHeader>
            <CardContent>
              {isLoadingTickets ? (
                <div className="flex justify-center py-8">
                  <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                </div>
              ) : tickets && tickets.length > 0 ? (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Ticket</TableHead>
                      <TableHead>Customer</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead>Priority</TableHead>
                      <TableHead>Created</TableHead>
                      <TableHead>Last Updated</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {tickets.map((ticket: any) => (
                      <TableRow
                        key={ticket.id}
                        className="cursor-pointer hover:bg-accent/50"
                        onClick={() => router.push(`/tickets/${ticket.id}`)}
                      >
                        <TableCell>
                          <div>
                            <p className="font-medium">{ticket.subject || '(No Subject)'}</p>
                            <p className="text-sm text-muted-foreground">#{ticket.ticket_number}</p>
                          </div>
                        </TableCell>
                        <TableCell>
                          {ticket.client?.name || ticket.client?.email || 'Unknown'}
                        </TableCell>
                        <TableCell>{getStatusBadge(ticket.status)}</TableCell>
                        <TableCell>{getPriorityBadge(ticket.priority)}</TableCell>
                        <TableCell>
                          {ticket.created_at ? format(new Date(ticket.created_at), 'MMM d, yyyy') : '-'}
                        </TableCell>
                        <TableCell>
                          {ticket.updated_at ? format(new Date(ticket.updated_at), 'MMM d, yyyy') : '-'}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              ) : (
                <div className="text-center py-8 text-muted-foreground">
                  {isAdmin ? 'No tickets in the system yet' : 'No tickets assigned to you yet'}
                </div>
              )}

              {/* Pagination Controls */}
              {tickets && tickets.length > 0 && totalPages > 1 && (
                <div className="flex items-center justify-between px-2 py-4 border-t">
                  <div className="text-sm text-muted-foreground">
                    Page {currentPage} of {totalPages} ({totalTickets} total tickets)
                  </div>
                  <div className="flex items-center gap-2">
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => setCurrentPage(Math.max(1, currentPage - 1))}
                      disabled={currentPage === 1}
                    >
                      Previous
                    </Button>
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => setCurrentPage(Math.min(totalPages, currentPage + 1))}
                      disabled={currentPage === totalPages}
                    >
                      Next
                    </Button>
                  </div>
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="details" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Account Information</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <p className="text-sm font-medium text-muted-foreground">Full Name</p>
                  <p className="text-sm">{user.name}</p>
                </div>
                <div>
                  <p className="text-sm font-medium text-muted-foreground">Email Address</p>
                  <p className="text-sm">{user.email}</p>
                </div>
                <div>
                  <p className="text-sm font-medium text-muted-foreground">Role</p>
                  <div className="text-sm">{getRoleBadge(user.role)}</div>
                </div>
                <div>
                  <p className="text-sm font-medium text-muted-foreground">Account Status</p>
                  <div className="text-sm">
                    {user.is_active ? (
                      <Badge variant="outline" className="gap-1">
                        <span className="h-2 w-2 rounded-full bg-green-500" />
                        Active
                      </Badge>
                    ) : (
                      <Badge variant="outline" className="gap-1">
                        <span className="h-2 w-2 rounded-full bg-gray-400" />
                        Inactive
                      </Badge>
                    )}
                  </div>
                </div>
                <div>
                  <p className="text-sm font-medium text-muted-foreground">User ID</p>
                  <p className="text-sm font-mono text-xs">{user.id}</p>
                </div>
                <div>
                  <p className="text-sm font-medium text-muted-foreground">Account Created</p>
                  <p className="text-sm">
                    {user.created_at ? format(new Date(user.created_at), 'PPP') : '-'}
                  </p>
                </div>
              </div>
              <Separator />
              <div className="flex gap-2">
                <Button variant="outline" onClick={() => router.push('/settings')}>
                  <Edit className="h-4 w-4 mr-2" />
                  Edit Profile
                </Button>
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  );
}