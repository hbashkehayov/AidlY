'use client';

import { useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { format } from 'date-fns';
import Link from 'next/link';
import {
  ArrowLeft,
  Mail,
  Phone,
  Building,
  MapPin,
  Calendar,
  Ticket,
  Star,
  Ban,
  Edit,
  MoreVertical,
  MessageSquare,
  Clock,
  CheckCircle,
  XCircle,
  AlertCircle,
  User,
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
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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

export default function CustomerProfilePage() {
  const params = useParams();
  const router = useRouter();
  const customerId = params.id as string;

  // Fetch customer data
  const { data: customer, isLoading: isLoadingCustomer } = useQuery({
    queryKey: ['customer', customerId],
    queryFn: async () => {
      const response = await api.clients.get(customerId);
      return response.data?.data || response.data;
    },
  });

  // Fetch customer tickets
  const { data: ticketsData, isLoading: isLoadingTickets } = useQuery({
    queryKey: ['customer-tickets', customerId],
    queryFn: async () => {
      const response = await api.tickets.list({ client_id: customerId, limit: 1000 });
      // Handle paginated response
      if (response.data?.success && response.data?.data) {
        return response.data.data;
      }
      return response.data?.data || response.data || [];
    },
  });

  const tickets = ticketsData || [];

  // Calculate ticket counts from actual tickets
  const ticketStats = {
    total: tickets.length,
    open: tickets.filter((t: any) => ['new', 'open', 'pending'].includes(t.status)).length,
    resolved: tickets.filter((t: any) => t.status === 'resolved').length,
  };

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

  const initials = customer?.name
    ? customer.name.split(' ').map((n: string) => n[0]).join('').toUpperCase()
    : 'U';

  if (isLoadingCustomer) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary"></div>
      </div>
    );
  }

  if (!customer) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="text-center">
          <h2 className="text-2xl font-bold mb-2">Customer not found</h2>
          <Button onClick={() => router.push('/customers')}>
            Back to Customers
          </Button>
        </div>
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
            onClick={() => router.push('/customers')}
          >
            <ArrowLeft className="h-4 w-4" />
          </Button>
          <div>
            <h2 className="text-3xl font-bold tracking-tight">Customer Profile</h2>
            <p className="text-muted-foreground">
              View and manage customer information
            </p>
          </div>
        </div>
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="outline">
              <MoreVertical className="h-4 w-4 mr-2" />
              Actions
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuLabel>Actions</DropdownMenuLabel>
            <DropdownMenuItem>
              <Edit className="h-4 w-4 mr-2" />
              Edit Customer
            </DropdownMenuItem>
            <DropdownMenuItem>
              <Mail className="h-4 w-4 mr-2" />
              Send Email
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            {customer.is_vip ? (
              <DropdownMenuItem>
                <Star className="h-4 w-4 mr-2" />
                Remove VIP Status
              </DropdownMenuItem>
            ) : (
              <DropdownMenuItem>
                <Star className="h-4 w-4 mr-2" />
                Mark as VIP
              </DropdownMenuItem>
            )}
            {customer.is_blocked ? (
              <DropdownMenuItem>
                <Ban className="h-4 w-4 mr-2" />
                Unblock Customer
              </DropdownMenuItem>
            ) : (
              <DropdownMenuItem className="text-red-600">
                <Ban className="h-4 w-4 mr-2" />
                Block Customer
              </DropdownMenuItem>
            )}
          </DropdownMenuContent>
        </DropdownMenu>
      </div>

      {/* Profile Overview */}
      <Card>
        <CardContent className="pt-6">
          <div className="flex items-start gap-6">
            <Avatar className="h-24 w-24">
              <AvatarImage src={customer.avatar_url} />
              <AvatarFallback className="text-2xl">{initials}</AvatarFallback>
            </Avatar>
            <div className="flex-1">
              <div className="flex items-center gap-3 mb-2">
                <h3 className="text-2xl font-bold">{customer.name || 'Unknown'}</h3>
                {customer.is_vip && (
                  <Badge variant="default" className="gap-1">
                    <Star className="h-3 w-3 fill-current" />
                    VIP
                  </Badge>
                )}
                {customer.is_blocked && (
                  <Badge variant="destructive" className="gap-1">
                    <Ban className="h-3 w-3" />
                    Blocked
                  </Badge>
                )}
              </div>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <div className="flex items-center gap-2 text-muted-foreground">
                  <Mail className="h-4 w-4" />
                  <span>{customer.email}</span>
                </div>
                {customer.phone && (
                  <div className="flex items-center gap-2 text-muted-foreground">
                    <Phone className="h-4 w-4" />
                    <span>{customer.phone}</span>
                  </div>
                )}
                {customer.company && (
                  <div className="flex items-center gap-2 text-muted-foreground">
                    <Building className="h-4 w-4" />
                    <span>{customer.company}</span>
                  </div>
                )}
                {(customer.city || customer.country) && (
                  <div className="flex items-center gap-2 text-muted-foreground">
                    <MapPin className="h-4 w-4" />
                    <span>{[customer.city, customer.country].filter(Boolean).join(', ')}</span>
                  </div>
                )}
              </div>
              {customer.tags && customer.tags.length > 0 && (
                <div className="flex flex-wrap gap-2 mt-4">
                  {customer.tags.map((tag: string) => (
                    <Badge key={tag} variant="secondary">
                      {tag}
                    </Badge>
                  ))}
                </div>
              )}
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Stats Cards */}
      <div className="grid gap-4 md:grid-cols-4">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Total Tickets</CardTitle>
            <Ticket className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{ticketStats.total}</div>
            <p className="text-xs text-muted-foreground">All time</p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Open Tickets</CardTitle>
            <AlertCircle className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{ticketStats.open}</div>
            <p className="text-xs text-muted-foreground">Requires attention</p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Resolved Tickets</CardTitle>
            <CheckCircle className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{ticketStats.resolved}</div>
            <p className="text-xs text-muted-foreground">Successfully resolved</p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Member Since</CardTitle>
            <Calendar className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {customer.created_at ? format(new Date(customer.created_at), 'MMM yyyy') : '-'}
            </div>
            <p className="text-xs text-muted-foreground">
              {customer.first_contact_at
                ? `First contact: ${format(new Date(customer.first_contact_at), 'MMM d, yyyy')}`
                : 'No contact yet'}
            </p>
          </CardContent>
        </Card>
      </div>

      {/* Tabs */}
      <Tabs defaultValue="tickets" className="space-y-4">
        <TabsList>
          <TabsTrigger value="tickets">Tickets</TabsTrigger>
          <TabsTrigger value="details">Details</TabsTrigger>
          <TabsTrigger value="activity">Activity</TabsTrigger>
        </TabsList>

        <TabsContent value="tickets" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Tickets</CardTitle>
              <CardDescription>All support tickets from this customer</CardDescription>
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
                  No tickets found for this customer
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="details" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Contact Information</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <p className="text-sm font-medium text-muted-foreground">Email</p>
                  <p className="text-sm">{customer.email}</p>
                </div>
                <div>
                  <p className="text-sm font-medium text-muted-foreground">Phone</p>
                  <p className="text-sm">{customer.phone || '-'}</p>
                </div>
                <div>
                  <p className="text-sm font-medium text-muted-foreground">Mobile</p>
                  <p className="text-sm">{customer.mobile || '-'}</p>
                </div>
                <div>
                  <p className="text-sm font-medium text-muted-foreground">Company</p>
                  <p className="text-sm">{customer.company || '-'}</p>
                </div>
              </div>
              <Separator />
              <div>
                <p className="text-sm font-medium text-muted-foreground mb-2">Address</p>
                <div className="space-y-1 text-sm">
                  {customer.address_line1 && <p>{customer.address_line1}</p>}
                  {customer.address_line2 && <p>{customer.address_line2}</p>}
                  <p>
                    {[customer.city, customer.state, customer.postal_code]
                      .filter(Boolean)
                      .join(', ') || '-'}
                  </p>
                  {customer.country && <p>{customer.country}</p>}
                </div>
              </div>
              <Separator />
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <p className="text-sm font-medium text-muted-foreground">Timezone</p>
                  <p className="text-sm">{customer.timezone || '-'}</p>
                </div>
                <div>
                  <p className="text-sm font-medium text-muted-foreground">Language</p>
                  <p className="text-sm">{customer.language || '-'}</p>
                </div>
              </div>
            </CardContent>
          </Card>

          {customer.custom_fields && Object.keys(customer.custom_fields).length > 0 && (
            <Card>
              <CardHeader>
                <CardTitle>Custom Fields</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="grid grid-cols-2 gap-4">
                  {Object.entries(customer.custom_fields).map(([key, value]: [string, any]) => (
                    <div key={key}>
                      <p className="text-sm font-medium text-muted-foreground">{key}</p>
                      <p className="text-sm">{value?.toString() || '-'}</p>
                    </div>
                  ))}
                </div>
              </CardContent>
            </Card>
          )}
        </TabsContent>

        <TabsContent value="activity" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Activity Timeline</CardTitle>
              <CardDescription>Recent activity and interactions</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="space-y-4">
                {customer.first_contact_at && (
                  <div className="flex gap-4">
                    <div className="flex flex-col items-center">
                      <div className="rounded-full bg-primary p-2">
                        <User className="h-4 w-4 text-primary-foreground" />
                      </div>
                      <div className="h-full w-px bg-border mt-2" />
                    </div>
                    <div className="flex-1 pb-4">
                      <p className="font-medium">First Contact</p>
                      <p className="text-sm text-muted-foreground">
                        {format(new Date(customer.first_contact_at), 'PPpp')}
                      </p>
                    </div>
                  </div>
                )}
                {customer.last_contact_at && (
                  <div className="flex gap-4">
                    <div className="flex flex-col items-center">
                      <div className="rounded-full bg-blue-500 p-2">
                        <MessageSquare className="h-4 w-4 text-white" />
                      </div>
                    </div>
                    <div className="flex-1">
                      <p className="font-medium">Last Contact</p>
                      <p className="text-sm text-muted-foreground">
                        {format(new Date(customer.last_contact_at), 'PPpp')}
                      </p>
                    </div>
                  </div>
                )}
                {!customer.first_contact_at && !customer.last_contact_at && (
                  <div className="text-center py-8 text-muted-foreground">
                    No activity recorded yet
                  </div>
                )}
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  );
}