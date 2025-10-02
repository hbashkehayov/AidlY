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

  // Fetch assigned tickets for this user
  const { data: tickets, isLoading: isLoadingTickets } = useQuery({
    queryKey: ['user-tickets', user?.id],
    queryFn: async () => {
      const response = await api.tickets.list({ assigned_to: user?.id });
      return response.data?.data || response.data || [];
    },
    enabled: !!user?.id,
  });

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

  // Calculate ticket statistics
  const openTickets = tickets?.filter((t: any) =>
    ['new', 'open', 'pending', 'on_hold'].includes(t.status)
  ).length || 0;

  const closedTickets = tickets?.filter((t: any) =>
    ['resolved', 'closed'].includes(t.status)
  ).length || 0;

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
            <div className="text-2xl font-bold">{tickets?.length || 0}</div>
            <p className="text-xs text-muted-foreground">Assigned to you</p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Open Tickets</CardTitle>
            <AlertCircle className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{openTickets}</div>
            <p className="text-xs text-muted-foreground">Requires attention</p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Resolved Tickets</CardTitle>
            <CheckCircle className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{closedTickets}</div>
            <p className="text-xs text-muted-foreground">Successfully resolved</p>
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
              <CardTitle>Assigned Tickets</CardTitle>
              <CardDescription>Tickets currently assigned to you</CardDescription>
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
                            <p className="font-medium">{ticket.subject}</p>
                            <p className="text-sm text-muted-foreground">#{ticket.ticket_number}</p>
                          </div>
                        </TableCell>
                        <TableCell>
                          {ticket.client_name || 'Unknown'}
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
                  No tickets assigned to you yet
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
                <Button variant="outline" onClick={() => router.push('/settings?tab=security')}>
                  <Shield className="h-4 w-4 mr-2" />
                  Security Settings
                </Button>
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  );
}