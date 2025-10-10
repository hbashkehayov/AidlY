'use client';

import { useState, useEffect } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useRouter } from 'next/navigation';
import { useRealtimeUpdates } from '@/lib/use-realtime-updates';
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
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
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
  CheckCircle,
  XCircle,
  MessageSquare,
  ChevronLeft,
  ChevronRight,
  Inbox,
  ClipboardList,
  Lock,
  Archive,
  Filter,
  ArrowUpDown,
  List,
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
};

// Real tickets data now comes from the API

export default function TicketsPage() {
  const router = useRouter();
  const queryClient = useQueryClient();
  // Get current user to check role
  const user = typeof window !== 'undefined' ? JSON.parse(localStorage.getItem('user') || '{}') : {};
  const isAdmin = user?.role === 'admin';

  const [activeTab, setActiveTab] = useState(isAdmin ? 'all' : 'my-tickets');
  const [selectedStatus, setSelectedStatus] = useState('all');
  const [selectedPriority, setSelectedPriority] = useState('all');
  const [searchQuery, setSearchQuery] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const [itemsPerPage, setItemsPerPage] = useState(10);
  const [sortBy, setSortBy] = useState('default');
  const [isCreateDialogOpen, setIsCreateDialogOpen] = useState(false);
  const [selectedTickets, setSelectedTickets] = useState<string[]>([]);
  const [isArchiveDialogOpen, setIsArchiveDialogOpen] = useState(false);
  const [ticketToArchive, setTicketToArchive] = useState<string | null>(null);
  const [isArchiving, setIsArchiving] = useState(false);
  const [isExiting, setIsExiting] = useState(false);
  const [isRestoreDialogOpen, setIsRestoreDialogOpen] = useState(false);
  const [ticketToRestore, setTicketToRestore] = useState<string | null>(null);
  const [isRestoring, setIsRestoring] = useState(false);

  const showBulkActions = selectedTickets.length > 0 || isExiting;

  // Real-time updates hook
  const { checkForNewTickets, hasPermission } = useRealtimeUpdates({
    enableBrowserNotifications: true,
    enableSound: true,
    onNewTicket: (ticket) => {
      console.log('New ticket received:', ticket);
    },
  });

  const { data: tickets, isLoading } = useQuery({
    queryKey: ['tickets', activeTab, selectedStatus, selectedPriority, searchQuery, currentPage, itemsPerPage, sortBy],
    queryFn: async () => {
      const params = new URLSearchParams();

      // Apply tab-based filtering
      // Backend now handles visibility: non-admins see only their tickets + unassigned
      if (activeTab === 'my-tickets') {
        // Show only tickets assigned to current user
        params.append('assigned_agent_id', user?.id || '');
        // Apply status filter if selected
        if (selectedStatus !== 'all') {
          params.append('status', selectedStatus);
        } else {
          // Exclude closed tickets when showing all statuses
          params.append('exclude_status', 'closed');
        }
      } else if (activeTab === 'available') {
        // Show only unassigned tickets
        params.append('unassigned', 'true');
        // Apply status filter if selected
        if (selectedStatus !== 'all') {
          params.append('status', selectedStatus);
        } else {
          // Exclude closed tickets when showing all statuses
          params.append('exclude_status', 'closed');
        }
      } else if (activeTab === 'closed') {
        params.append('status', 'closed');
      } else if (activeTab === 'archived') {
        params.append('archived', 'true');
      } else if (activeTab === 'all') {
        // 'all' tab shows both assigned to me + unassigned (handled by backend)
        // Apply status filter if selected
        if (selectedStatus !== 'all') {
          params.append('status', selectedStatus);
        } else {
          // Exclude closed tickets when showing all statuses
          params.append('exclude_status', 'closed');
        }
      }
      if (selectedPriority !== 'all') params.append('priority', selectedPriority);
      if (searchQuery) params.append('search', searchQuery);
      params.append('page', currentPage.toString());
      params.append('per_page', itemsPerPage.toString());

      // Apply sorting
      if (sortBy === 'default') {
        // Default: unread priority + most recent
        params.append('sort', 'default');
      } else if (sortBy === 'recent') {
        params.append('sort', 'updated_at');
        params.append('direction', 'desc');
      } else if (sortBy === 'oldest') {
        params.append('sort', 'created_at');
        params.append('direction', 'asc');
      } else if (sortBy === 'priority') {
        params.append('sort', 'priority');
        params.append('direction', 'desc');
      } else if (sortBy === 'status') {
        params.append('sort', 'status');
        params.append('direction', 'asc');
      }

      const response = await api.tickets.list(Object.fromEntries(params));
      // The API returns { success: true, data: ticketsArray, meta: pagination }
      // We need to return the whole response to access both data and meta
      return response.data?.success ? response.data : response.data;
    },
    // Real-time updates - more aggressive polling
    refetchInterval: 3000, // Poll every 3 seconds for near real-time updates
    refetchOnWindowFocus: true, // Refetch when window regains focus
    refetchIntervalInBackground: true, // Continue polling in background for notifications
    refetchOnMount: true, // Always refetch on mount
    refetchOnReconnect: true, // Refetch when connection is restored
  });

  // Check for new tickets when data updates
  useEffect(() => {
    if (tickets) {
      checkForNewTickets();
    }
  }, [tickets, checkForNewTickets]);

  // Reset to first page when filters change
  useEffect(() => {
    setCurrentPage(1);
  }, [activeTab, selectedStatus, selectedPriority, searchQuery, itemsPerPage, sortBy]);

  // Reset status filter when switching to tabs that don't support it
  useEffect(() => {
    if (activeTab === 'closed' || activeTab === 'archived') {
      setSelectedStatus('all');
    }
  }, [activeTab]);

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

  const handleBulkArchive = async () => {
    if (selectedTickets.length === 0) return;

    setIsArchiving(true);
    try {
      // Update tickets to mark them as archived
      await Promise.all(selectedTickets.map(id => api.tickets.update(id, { is_archived: true })));
      handleClearSelection();
      // Invalidate and refetch tickets without full page reload
      await queryClient.invalidateQueries({ queryKey: ['tickets'] });
    } catch (error) {
      console.error('Failed to archive tickets:', error);
      alert('Failed to archive some tickets. Please try again.');
    } finally {
      setIsArchiving(false);
    }
  };

  const handleArchiveTicket = async (ticketId: string) => {
    setIsArchiving(true);
    try {
      // Soft delete by marking as archived
      await api.tickets.update(ticketId, { is_archived: true });
      setIsArchiveDialogOpen(false);
      setTicketToArchive(null);
      // Invalidate and refetch tickets without full page reload
      await queryClient.invalidateQueries({ queryKey: ['tickets'] });
    } catch (error) {
      console.error('Failed to archive ticket:', error);
      alert('Failed to archive ticket. Please try again.');
    } finally {
      setIsArchiving(false);
    }
  };

  const handleBulkRestore = async () => {
    if (selectedTickets.length === 0) return;

    setIsRestoring(true);
    try {
      // Update tickets to restore them from archive
      await Promise.all(selectedTickets.map(id => api.tickets.update(id, { is_archived: false })));
      handleClearSelection();
      // Invalidate and refetch tickets without full page reload
      await queryClient.invalidateQueries({ queryKey: ['tickets'] });
    } catch (error) {
      console.error('Failed to restore tickets:', error);
      alert('Failed to restore some tickets. Please try again.');
    } finally {
      setIsRestoring(false);
    }
  };

  const handleRestoreTicket = async (ticketId: string) => {
    setIsRestoring(true);
    try {
      // Restore by marking as not archived
      await api.tickets.update(ticketId, { is_archived: false });
      setIsRestoreDialogOpen(false);
      setTicketToRestore(null);
      // Invalidate and refetch tickets without full page reload
      await queryClient.invalidateQueries({ queryKey: ['tickets'] });
    } catch (error) {
      console.error('Failed to restore ticket:', error);
      alert('Failed to restore ticket. Please try again.');
    } finally {
      setIsRestoring(false);
    }
  };

  return (
    <div className="flex-1 space-y-4 p-8 pt-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div>
            <h2 className="text-3xl font-bold tracking-tight">Tickets</h2>
            <p className="text-muted-foreground">
              Manage and respond to customer support tickets
              {hasPermission && <span className="ml-2 text-xs">(Browser notifications enabled)</span>}
            </p>
          </div>
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

      {/* Tabs for filtering */}
      <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
        <TabsList className={`grid w-full ${isAdmin ? 'grid-cols-5' : 'grid-cols-3'} lg:w-auto lg:inline-grid`}>
          {isAdmin && (
            <TabsTrigger value="all" className="gap-2">
              <Inbox className="h-4 w-4" />
              <span>All Tickets</span>
            </TabsTrigger>
          )}
          <TabsTrigger value="my-tickets" className="gap-2">
            <User className="h-4 w-4" />
            <span>My Tickets</span>
          </TabsTrigger>
          <TabsTrigger value="available" className="gap-2">
            <ClipboardList className="h-4 w-4" />
            <span>Available</span>
          </TabsTrigger>
          <TabsTrigger value="closed" className="gap-2">
            <Lock className="h-4 w-4" />
            <span>Closed</span>
          </TabsTrigger>
          {isAdmin && (
            <TabsTrigger value="archived" className="gap-2">
              <Archive className="h-4 w-4" />
              <span>Archived</span>
            </TabsTrigger>
          )}
        </TabsList>
      </Tabs>

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
            {/* Show status filter on tabs that support it */}
            {(activeTab === 'all' || activeTab === 'my-tickets' || activeTab === 'available') && (
              <Select value={selectedStatus} onValueChange={setSelectedStatus}>
                <SelectTrigger className="w-full sm:w-[180px]">
                  <Filter className="h-4 w-4 mr-2" />
                  <SelectValue placeholder="All Status" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Status</SelectItem>
                  <SelectItem value="open">Open</SelectItem>
                  <SelectItem value="pending">Pending</SelectItem>
                  <SelectItem value="resolved">Resolved</SelectItem>
                  <SelectItem value="closed">Closed</SelectItem>
                </SelectContent>
              </Select>
            )}
            <Select value={selectedPriority} onValueChange={setSelectedPriority}>
              <SelectTrigger className="w-full sm:w-[180px]">
                <Filter className="h-4 w-4 mr-2" />
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
            <Select value={sortBy} onValueChange={setSortBy}>
              <SelectTrigger className="w-full sm:w-[180px]">
                <ArrowUpDown className="h-4 w-4 mr-2" />
                <SelectValue placeholder="Sort By" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="default">Default</SelectItem>
                <SelectItem value="recent">Most Recent</SelectItem>
                <SelectItem value="oldest">Oldest First</SelectItem>
                <SelectItem value="priority">Priority</SelectItem>
                <SelectItem value="status">Status</SelectItem>
              </SelectContent>
            </Select>
            <Select value={itemsPerPage.toString()} onValueChange={(value) => setItemsPerPage(Number(value))}>
              <SelectTrigger className="w-full sm:w-[100px]">
                <List className="h-4 w-4 mr-2" />
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
                {activeTab === 'archived' ? (
                  // Restore button for archived tickets
                  <AlertDialog>
                    <AlertDialogTrigger asChild>
                      <Button
                        variant="outline"
                        size="sm"
                        disabled={isRestoring}
                        className="border-green-300 text-green-700 hover:bg-green-50 transition-all duration-200"
                      >
                        <CheckCircle className="h-4 w-4 mr-2" />
                        Restore Selected
                      </Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                      <AlertDialogHeader>
                        <AlertDialogTitle>Restore Tickets?</AlertDialogTitle>
                        <AlertDialogDescription>
                          Are you sure you want to restore {selectedTickets.length} ticket{selectedTickets.length > 1 ? 's' : ''}?
                          Restored tickets will be moved back to active tickets.
                        </AlertDialogDescription>
                      </AlertDialogHeader>
                      <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                          onClick={handleBulkRestore}
                          className="bg-green-600 hover:bg-green-700"
                        >
                          {isRestoring ? 'Restoring...' : 'Restore Tickets'}
                        </AlertDialogAction>
                      </AlertDialogFooter>
                    </AlertDialogContent>
                  </AlertDialog>
                ) : (
                  // Archive button for active tickets
                  <AlertDialog>
                    <AlertDialogTrigger asChild>
                      <Button
                        variant="outline"
                        size="sm"
                        disabled={isArchiving}
                        className="border-orange-300 text-orange-700 hover:bg-orange-50 transition-all duration-200"
                      >
                        <Archive className="h-4 w-4 mr-2" />
                        Archive Selected
                      </Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                      <AlertDialogHeader>
                        <AlertDialogTitle>Archive Tickets?</AlertDialogTitle>
                        <AlertDialogDescription>
                          Are you sure you want to archive {selectedTickets.length} ticket{selectedTickets.length > 1 ? 's' : ''}?
                          Archived tickets can be viewed in the Archived tab.
                        </AlertDialogDescription>
                      </AlertDialogHeader>
                      <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                          onClick={handleBulkArchive}
                          className="bg-orange-600 hover:bg-orange-700"
                        >
                          {isArchiving ? 'Archiving...' : 'Archive Tickets'}
                        </AlertDialogAction>
                      </AlertDialogFooter>
                    </AlertDialogContent>
                  </AlertDialog>
                )}

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
                tickets?.data?.map((ticket: any) => {
                  // Check if ticket is unassigned (no agent assigned)
                  const isUnassigned = !ticket.assigned_agent_id;

                  return (
                    <TableRow
                      key={ticket.id}
                      className={cn(
                        "transition-colors duration-200",
                        activeTab === 'archived'
                          ? "cursor-not-allowed opacity-60"
                          : "cursor-pointer",
                        isUnassigned
                          ? "bg-purple-50/60 hover:bg-accent/50 dark:bg-purple-950/20 dark:hover:bg-accent/50"
                          : activeTab !== 'archived' && "hover:bg-accent/50"
                      )}
                      onClick={(e) => {
                        // Don't allow navigation for archived tickets
                        if (activeTab === 'archived') {
                          e.stopPropagation();
                          return;
                        }

                        // Don't navigate if clicking checkbox or actions
                        if ((e.target as HTMLElement).closest('[data-no-navigate]')) {
                          e.stopPropagation();
                          return;
                        }

                        // Optimistically update the cache to hide unread counters immediately
                        queryClient.setQueryData(
                          ['tickets', activeTab, selectedStatus, selectedPriority, searchQuery, currentPage, itemsPerPage],
                          (oldData: any) => {
                            if (!oldData?.data) return oldData;

                            return {
                              ...oldData,
                              data: oldData.data.map((t: any) =>
                                t.id === ticket.id
                                  ? { ...t, unread_comments_count: 0, unread_internal_notes_count: 0 }
                                  : t
                              )
                            };
                          }
                        );

                        // Navigate to the ticket
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
                          {/* Total comments count - always show if there are comments */}
                          {ticket.comments_count > 0 && (
                            <div className="flex items-center gap-1 text-xs text-muted-foreground">
                              <MessageSquare className="h-3 w-3" />
                              {ticket.comments_count}
                            </div>
                          )}
                          {/* Unread regular comments indicator (blue) - only show when > 0 */}
                          {(ticket.unread_comments_count ?? 0) > 0 && (
                            <div className="flex items-center justify-center h-5 min-w-5 px-1.5 bg-blue-500 text-white text-xs font-semibold rounded-full">
                              {ticket.unread_comments_count}
                            </div>
                          )}
                          {/* Unread internal notes indicator (yellow) - only show when > 0 */}
                          {(ticket.unread_internal_notes_count ?? 0) > 0 && (
                            <div className="flex items-center justify-center h-5 min-w-5 px-1.5 bg-yellow-500 text-white text-xs font-semibold rounded-full">
                              {ticket.unread_internal_notes_count}
                            </div>
                          )}
                        </div>
                        <p className="text-sm text-muted-foreground line-clamp-1">
                          {ticket.subject || '(No Subject)'}
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
                      <TableCell className="text-right opacity-100" data-no-navigate>
                        {activeTab === 'archived' ? (
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={(e) => {
                              e.stopPropagation();
                              setTicketToRestore(ticket.id);
                              setIsRestoreDialogOpen(true);
                            }}
                            className="text-green-700 border-2 border-green-500 bg-green-50 hover:bg-green-100 hover:text-green-800 hover:border-green-600 opacity-100 shadow-md"
                          >
                            <CheckCircle className="h-5 w-5 stroke-[3]" />
                          </Button>
                        ) : (
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={(e) => {
                              e.stopPropagation();
                              setTicketToArchive(ticket.id);
                              setIsArchiveDialogOpen(true);
                            }}
                            className="text-orange-600 hover:text-orange-700 hover:bg-orange-50"
                          >
                            <Archive className="h-4 w-4" />
                          </Button>
                        )}
                      </TableCell>
                    )}
                  </TableRow>
                );
                })
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

      {/* Archive Confirmation Dialog */}
      <AlertDialog open={isArchiveDialogOpen} onOpenChange={setIsArchiveDialogOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Archive Ticket?</AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to archive this ticket? Archived tickets can be viewed in the Archived tab and can be restored if needed.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel
              onClick={() => {
                setIsArchiveDialogOpen(false);
                setTicketToArchive(null);
              }}
              disabled={isArchiving}
            >
              Cancel
            </AlertDialogCancel>
            <AlertDialogAction
              onClick={() => ticketToArchive && handleArchiveTicket(ticketToArchive)}
              disabled={isArchiving}
              className="bg-orange-600 hover:bg-orange-700"
            >
              {isArchiving ? 'Archiving...' : 'Archive Ticket'}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* Restore Confirmation Dialog */}
      <AlertDialog open={isRestoreDialogOpen} onOpenChange={setIsRestoreDialogOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Restore Ticket?</AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to restore this ticket? It will be moved back to active tickets.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel
              onClick={() => {
                setIsRestoreDialogOpen(false);
                setTicketToRestore(null);
              }}
              disabled={isRestoring}
            >
              Cancel
            </AlertDialogCancel>
            <AlertDialogAction
              onClick={() => ticketToRestore && handleRestoreTicket(ticketToRestore)}
              disabled={isRestoring}
              className="bg-green-600 hover:bg-green-700"
            >
              {isRestoring ? 'Restoring...' : 'Restore Ticket'}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}