'use client';

import { useState, useEffect } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useRouter } from 'next/navigation';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Checkbox } from '@/components/ui/checkbox';
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
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Textarea } from '@/components/ui/textarea';
import {
  Search,
  Plus,
  MoreHorizontal,
  Clock,
  User,
  Tag,
  AlertCircle,
  CheckCircle,
  XCircle,
  MessageSquare,
  Calendar,
  ChevronLeft,
  ChevronRight,
  Trash2,
} from 'lucide-react';
import api from '@/lib/api';
import { format } from 'date-fns';
import { cn } from '@/lib/utils';
import { getStatusColor, getStatusLabel, getPriorityColor, getPriorityLabel } from '@/lib/colors';

const statusConfig = {
  open: { label: 'Open', icon: Clock },
  pending: { label: 'Pending', icon: Clock },
  resolved: { label: 'Resolved', icon: CheckCircle },
  closed: { label: 'Closed', icon: XCircle },
  cancelled: { label: 'Cancelled', icon: XCircle },
  new: { label: 'New', icon: AlertCircle },
};

// Real tickets data now comes from the API

export default function TicketsPage() {
  const router = useRouter();
  const queryClient = useQueryClient();
  const [selectedStatus, setSelectedStatus] = useState('all');
  const [selectedPriority, setSelectedPriority] = useState('all');
  const [searchQuery, setSearchQuery] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const [itemsPerPage, setItemsPerPage] = useState(10);
  const [isCreateDialogOpen, setIsCreateDialogOpen] = useState(false);
  const [selectedTickets, setSelectedTickets] = useState<string[]>([]);
  const [isDeleteDialogOpen, setIsDeleteDialogOpen] = useState(false);
  const [ticketToDelete, setTicketToDelete] = useState<string | null>(null);
  const [isDeleting, setIsDeleting] = useState(false);
  const [isExiting, setIsExiting] = useState(false);

  // Get current user to check role
  const user = typeof window !== 'undefined' ? JSON.parse(localStorage.getItem('user') || '{}') : {};
  const isAgent = user?.role === 'agent';
  const isAdmin = user?.role === 'admin';

  const showBulkActions = selectedTickets.length > 0 || isExiting;

  const { data: tickets, isLoading } = useQuery({
    queryKey: ['tickets', selectedStatus, selectedPriority, searchQuery, currentPage, itemsPerPage, isAgent ? user?.id : null],
    queryFn: async () => {
      const params = new URLSearchParams();
      if (selectedStatus !== 'all') params.append('status', selectedStatus);
      if (selectedPriority !== 'all') params.append('priority', selectedPriority);
      if (searchQuery) params.append('search', searchQuery);
      params.append('page', currentPage.toString());
      params.append('per_page', itemsPerPage.toString());

      // If user is an agent, only show tickets assigned to them
      if (isAgent && user?.id) {
        params.append('assigned_agent_id', user.id);
      }

      const response = await api.tickets.list(Object.fromEntries(params));
      // The API returns { success: true, data: ticketsArray, meta: pagination }
      // We need to return the whole response to access both data and meta
      if (response.data?.success) {
        return response.data; // Return { success, data, meta }
      }
      // Fallback for different response structure
      return response.data;
    },
  });

  // Reset to first page when filters change
  useEffect(() => {
    setCurrentPage(1);
  }, [selectedStatus, selectedPriority, searchQuery, itemsPerPage]);

  const getStatusBadge = (status: string) => {
    const config = statusConfig[status as keyof typeof statusConfig];
    const colors = getStatusColor(status);
    return (
      <Badge variant={`status-${status.toLowerCase()}` as any} className="gap-1.5">
        <span className={cn('h-2 w-2 rounded-full', colors.dot)} />
        {config?.label || getStatusLabel(status)}
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

  const handleSelectAll = (checked: boolean) => {
    if (checked && tickets?.data) {
      setSelectedTickets(tickets.data.map((t: any) => t.id));
    } else {
      setSelectedTickets([]);
    }
  };

  const handleSelectTicket = (ticketId: string, checked: boolean) => {
    if (checked) {
      setSelectedTickets([...selectedTickets, ticketId]);
    } else {
      setSelectedTickets(selectedTickets.filter(id => id !== ticketId));
    }
  };

  const handleClearSelection = () => {
    setIsExiting(true);
    setTimeout(() => {
      setSelectedTickets([]);
      setIsExiting(false);
    }, 300);
  };

  const handleBulkDelete = async () => {
    if (selectedTickets.length === 0) return;

    setIsDeleting(true);
    try {
      await Promise.all(selectedTickets.map(id => api.tickets.delete(id)));
      handleClearSelection();
      // Invalidate and refetch tickets without full page reload
      await queryClient.invalidateQueries({ queryKey: ['tickets'] });
    } catch (error) {
      console.error('Failed to delete tickets:', error);
      alert('Failed to delete some tickets. Please try again.');
    } finally {
      setIsDeleting(false);
    }
  };

  const handleDeleteTicket = async (ticketId: string) => {
    setIsDeleting(true);
    try {
      await api.tickets.delete(ticketId);
      setIsDeleteDialogOpen(false);
      setTicketToDelete(null);
      // Invalidate and refetch tickets without full page reload
      await queryClient.invalidateQueries({ queryKey: ['tickets'] });
    } catch (error) {
      console.error('Failed to delete ticket:', error);
      alert('Failed to delete ticket. Please try again.');
    } finally {
      setIsDeleting(false);
    }
  };

  return (
    <div className="flex-1 space-y-4 p-8 pt-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-3xl font-bold tracking-tight">Tickets</h2>
          <p className="text-muted-foreground">
            Manage and respond to customer support tickets
          </p>
        </div>
        {/* New Ticket button removed */}
        <Dialog open={isCreateDialogOpen} onOpenChange={setIsCreateDialogOpen}>
          <DialogContent className="sm:max-w-[625px]">
            <DialogHeader>
              <DialogTitle>Create New Ticket</DialogTitle>
              <DialogDescription>
                Create a new support ticket for a customer
              </DialogDescription>
            </DialogHeader>
            <div className="grid gap-4 py-4">
              <div className="grid gap-2">
                <Label htmlFor="subject">Subject</Label>
                <Input id="subject" placeholder="Brief description of the issue" />
              </div>
              <div className="grid gap-2">
                <Label htmlFor="description">Description</Label>
                <Textarea
                  id="description"
                  placeholder="Detailed description of the issue"
                  rows={4}
                />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-2">
                  <Label htmlFor="priority">Priority</Label>
                  <Select>
                    <SelectTrigger>
                      <SelectValue placeholder="Select priority" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="low">Low</SelectItem>
                      <SelectItem value="medium">Medium</SelectItem>
                      <SelectItem value="high">High</SelectItem>
                      <SelectItem value="urgent">Urgent</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="category">Category</Label>
                  <Select>
                    <SelectTrigger>
                      <SelectValue placeholder="Select category" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="technical">Technical Support</SelectItem>
                      <SelectItem value="billing">Billing</SelectItem>
                      <SelectItem value="general">General Inquiry</SelectItem>
                      <SelectItem value="feature">Feature Request</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>
              <div className="grid gap-2">
                <Label htmlFor="customer">Customer Email</Label>
                <Input id="customer" type="email" placeholder="customer@example.com" />
              </div>
            </div>
            <DialogFooter>
              <Button variant="outline" onClick={() => setIsCreateDialogOpen(false)}>
                Cancel
              </Button>
              <Button onClick={() => setIsCreateDialogOpen(false)}>
                Create Ticket
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>

      {/* Filters and Search */}
      <Card>
        <CardContent className="p-4">
          <div className="flex flex-col sm:flex-row gap-4">
            <div className="relative flex-1">
              <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                placeholder="Search tickets..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="pl-9"
              />
            </div>
            <Select value={selectedStatus} onValueChange={setSelectedStatus}>
              <SelectTrigger className="w-full sm:w-[180px]">
                <SelectValue placeholder="All Status" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Status</SelectItem>
                <SelectItem value="new">New</SelectItem>
                <SelectItem value="open">Open</SelectItem>
                <SelectItem value="pending">Pending</SelectItem>
                <SelectItem value="resolved">Resolved</SelectItem>
                <SelectItem value="closed">Closed</SelectItem>
              </SelectContent>
            </Select>
            <Select value={selectedPriority} onValueChange={setSelectedPriority}>
              <SelectTrigger className="w-full sm:w-[180px]">
                <SelectValue placeholder="All Priorities" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Priorities</SelectItem>
                <SelectItem value="low">Low</SelectItem>
                <SelectItem value="medium">Medium</SelectItem>
                <SelectItem value="high">High</SelectItem>
                <SelectItem value="urgent">Urgent</SelectItem>
              </SelectContent>
            </Select>
            <Select value={itemsPerPage.toString()} onValueChange={(value) => setItemsPerPage(Number(value))}>
              <SelectTrigger className="w-full sm:w-[100px]">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="5">5</SelectItem>
                <SelectItem value="10">10</SelectItem>
                <SelectItem value="20">20</SelectItem>
                <SelectItem value="50">50</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </CardContent>
      </Card>

      {/* Bulk Actions Bar - Admin Only */}
      {isAdmin && showBulkActions && (
        <Card className={cn(
          "bg-accent/50 transition-all duration-300",
          isExiting
            ? "animate-out fade-out slide-out-to-top-2"
            : "animate-in fade-in slide-in-from-top-2"
        )}>
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <p className={cn(
                "text-sm font-medium transition-all duration-500",
                isExiting ? "animate-out fade-out" : "animate-in fade-in"
              )}>
                {selectedTickets.length} ticket{selectedTickets.length > 1 ? 's' : ''} selected
              </p>
              <div className={cn(
                "flex gap-2 transition-all duration-500",
                isExiting
                  ? "animate-out fade-out slide-out-to-right-2"
                  : "animate-in fade-in slide-in-from-right-2"
              )}>
                <AlertDialog>
                  <AlertDialogTrigger asChild>
                    <Button
                      variant="destructive"
                      size="sm"
                      disabled={isDeleting}
                      className="bg-red-500/90 hover:bg-red-600 transition-all duration-200"
                    >
                      <Trash2 className="h-4 w-4 mr-2" />
                      Delete Selected
                    </Button>
                  </AlertDialogTrigger>
                  <AlertDialogContent>
                    <AlertDialogHeader>
                      <AlertDialogTitle>Delete Tickets?</AlertDialogTitle>
                      <AlertDialogDescription>
                        Are you sure you want to delete {selectedTickets.length} ticket{selectedTickets.length > 1 ? 's' : ''}?
                        This action cannot be undone and will permanently delete all ticket data.
                      </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                      <AlertDialogCancel>Cancel</AlertDialogCancel>
                      <AlertDialogAction
                        onClick={handleBulkDelete}
                        className="bg-red-500 hover:bg-red-600"
                      >
                        {isDeleting ? 'Deleting...' : 'Delete Tickets'}
                      </AlertDialogAction>
                    </AlertDialogFooter>
                  </AlertDialogContent>
                </AlertDialog>

                <Button
                  variant="ghost"
                  size="sm"
                  onClick={handleClearSelection}
                  className="transition-all duration-200"
                >
                  Clear Selection
                </Button>
              </div>
            </div>
          </CardContent>
        </Card>
      )}

      {/* Tickets Table */}
      <Card>
        <CardContent className="p-0">
          <Table>
            <TableHeader>
              <TableRow>
                {isAdmin && (
                  <TableHead className="w-[40px]">
                    <Checkbox
                      checked={selectedTickets.length === tickets?.data?.length && tickets?.data?.length > 0}
                      onCheckedChange={handleSelectAll}
                    />
                  </TableHead>
                )}
                <TableHead>Ticket</TableHead>
                <TableHead>Customer</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Priority</TableHead>
                <TableHead>Assigned To</TableHead>
                <TableHead>Created</TableHead>
                {isAdmin && <TableHead className="text-right">Actions</TableHead>}
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading ? (
                <TableRow>
                  <TableCell colSpan={isAdmin ? 8 : 6} className="text-center py-8">
                    <div className="flex justify-center">
                      <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                    </div>
                  </TableCell>
                </TableRow>
              ) : !tickets?.data || tickets.data.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={isAdmin ? 8 : 6} className="text-center py-8">
                    No tickets found
                  </TableCell>
                </TableRow>
              ) : (
                tickets?.data?.map((ticket: any) => (
                  <TableRow
                    key={ticket.id}
                    className="cursor-pointer hover:bg-accent/50"
                    onClick={(e) => {
                      // Don't navigate if clicking checkbox or actions
                      if ((e.target as HTMLElement).closest('[data-no-navigate]')) {
                        e.stopPropagation();
                        return;
                      }
                      router.push(`/tickets/${ticket.id}`);
                    }}
                  >
                    {isAdmin && (
                      <TableCell data-no-navigate onClick={(e) => e.stopPropagation()}>
                        <Checkbox
                          checked={selectedTickets.includes(ticket.id)}
                          onCheckedChange={(checked) => handleSelectTicket(ticket.id, checked as boolean)}
                        />
                      </TableCell>
                    )}
                    <TableCell>
                      <div className="space-y-1">
                        <div className="flex items-center gap-2">
                          <span className="font-medium">{ticket.ticket_number}</span>
                          {ticket.comments_count && ticket.comments_count > 0 && (
                            <div className="flex items-center gap-1 text-xs text-muted-foreground">
                              <MessageSquare className="h-3 w-3" />
                              {ticket.comments_count}
                            </div>
                          )}
                        </div>
                        <p className="text-sm text-muted-foreground line-clamp-1">
                          {ticket.subject}
                        </p>
                      </div>
                    </TableCell>
                    <TableCell>
                      <div className="flex items-center gap-2">
                        <Avatar className="h-8 w-8">
                          <AvatarFallback>
                            {ticket.client?.name?.charAt(0)?.toUpperCase() || 'C'}
                          </AvatarFallback>
                        </Avatar>
                        <div>
                          <p className="text-sm font-medium">{ticket.client?.name || 'Unknown Client'}</p>
                          {ticket.client?.email && (
                            <p className="text-xs text-muted-foreground">{ticket.client.email}</p>
                          )}
                        </div>
                      </div>
                    </TableCell>
                    <TableCell>{getStatusBadge(ticket.status)}</TableCell>
                    <TableCell>{getPriorityBadge(ticket.priority)}</TableCell>
                    <TableCell>
                      {ticket.assigned_agent ? (
                        <div className="flex items-center gap-2">
                          <Avatar className="h-6 w-6">
                            <AvatarFallback className="text-xs">
                              {ticket.assigned_agent.name?.charAt(0)?.toUpperCase() || 'A'}
                            </AvatarFallback>
                          </Avatar>
                          <span className="text-sm">{ticket.assigned_agent.name}</span>
                        </div>
                      ) : (
                        <span className="text-sm text-muted-foreground">Unassigned</span>
                      )}
                    </TableCell>
                    <TableCell>
                      <div className="text-sm">
                        <p>{format(new Date(ticket.created_at), 'MMM d, yyyy')}</p>
                        <p className="text-xs text-muted-foreground">
                          {format(new Date(ticket.created_at), 'h:mm a')}
                        </p>
                      </div>
                    </TableCell>
                    {isAdmin && (
                      <TableCell className="text-right" data-no-navigate>
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={(e) => {
                            e.stopPropagation();
                            setTicketToDelete(ticket.id);
                            setIsDeleteDialogOpen(true);
                          }}
                          className="text-red-600 hover:text-red-700 hover:bg-red-50"
                        >
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </TableCell>
                    )}
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>

          {/* Pagination */}
          {tickets?.meta && tickets.meta.last_page > 1 && (
            <div className="flex items-center justify-between px-6 py-4 border-t">
              <p className="text-sm text-muted-foreground">
                Showing {tickets.meta.from || 1} to{' '}
                {tickets.meta.to || 0} of{' '}
                {tickets.meta.total} tickets
              </p>
              <div className="flex items-center gap-1">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setCurrentPage(p => Math.max(1, p - 1))}
                  disabled={currentPage === 1}
                  className="gap-1"
                >
                  <ChevronLeft className="h-4 w-4" />
                  Previous
                </Button>

                {/* Page Numbers */}
                <div className="flex items-center gap-1 mx-2">
                  {(() => {
                    const totalPages = tickets.meta.last_page;
                    const pages = [];
                    const maxVisiblePages = 5;

                    if (totalPages <= maxVisiblePages) {
                      // Show all pages if total is small
                      for (let i = 1; i <= totalPages; i++) {
                        pages.push(i);
                      }
                    } else {
                      // Smart pagination logic
                      if (currentPage <= 3) {
                        // Near the beginning
                        for (let i = 1; i <= 4; i++) {
                          pages.push(i);
                        }
                        pages.push('...');
                        pages.push(totalPages);
                      } else if (currentPage >= totalPages - 2) {
                        // Near the end
                        pages.push(1);
                        pages.push('...');
                        for (let i = totalPages - 3; i <= totalPages; i++) {
                          pages.push(i);
                        }
                      } else {
                        // In the middle
                        pages.push(1);
                        pages.push('...');
                        for (let i = currentPage - 1; i <= currentPage + 1; i++) {
                          pages.push(i);
                        }
                        pages.push('...');
                        pages.push(totalPages);
                      }
                    }

                    return pages.map((page, index) => {
                      if (page === '...') {
                        return (
                          <span key={`ellipsis-${index}`} className="px-2 py-1 text-sm text-muted-foreground">
                            ...
                          </span>
                        );
                      }

                      return (
                        <Button
                          key={page}
                          variant={currentPage === page ? "default" : "outline"}
                          size="sm"
                          className="w-8 h-8 p-0"
                          onClick={() => setCurrentPage(page as number)}
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
                  onClick={() => setCurrentPage(p => Math.min(tickets.meta.last_page, p + 1))}
                  disabled={currentPage === tickets.meta.last_page}
                  className="gap-1"
                >
                  Next
                  <ChevronRight className="h-4 w-4" />
                </Button>
              </div>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Delete Confirmation Dialog */}
      <AlertDialog open={isDeleteDialogOpen} onOpenChange={setIsDeleteDialogOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete Ticket?</AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to delete this ticket? This action cannot be undone and will permanently delete all ticket data.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel
              onClick={() => {
                setIsDeleteDialogOpen(false);
                setTicketToDelete(null);
              }}
              disabled={isDeleting}
            >
              Cancel
            </AlertDialogCancel>
            <AlertDialogAction
              onClick={() => ticketToDelete && handleDeleteTicket(ticketToDelete)}
              disabled={isDeleting}
              className="bg-red-500 hover:bg-red-600"
            >
              {isDeleting ? 'Deleting...' : 'Delete Ticket'}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}