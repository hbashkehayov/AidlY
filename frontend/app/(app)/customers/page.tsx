'use client';

import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
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
  Search,
  Filter,
  Ticket,
  Star,
  Ban,
  Users,
  MessageSquare,
  ChevronLeft,
  ChevronRight,
  Eye,
  Trash2,
} from 'lucide-react';
import api from '@/lib/api';
import { format } from 'date-fns';
import { cn } from '@/lib/utils';
import { toast } from 'sonner';
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip';
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

function CustomerRow({ customer }: any) {
  const queryClient = useQueryClient();

  const toggleVIPMutation = useMutation({
    mutationFn: async () => {
      return await api.clients.update(customer.id, {
        is_vip: !customer.is_vip
      });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['customers'] });
      toast.success(customer.is_vip ? 'VIP status removed' : 'Marked as VIP');
    },
    onError: () => {
      toast.error('Failed to update VIP status');
    }
  });

  const toggleBlockMutation = useMutation({
    mutationFn: async () => {
      return await api.clients.update(customer.id, {
        is_blocked: !customer.is_blocked
      });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['customers'] });
      toast.success(customer.is_blocked ? 'Customer unblocked' : 'Customer blocked');
    },
    onError: () => {
      toast.error('Failed to update block status');
    }
  });

  const deleteMutation = useMutation({
    mutationFn: async () => {
      return await api.clients.delete(customer.id);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['customers'] });
      toast.success('Customer deleted successfully');
    },
    onError: (error: any) => {
      const errorMessage = error?.response?.data?.message || 'Failed to delete customer';
      toast.error(errorMessage);
    }
  });

  const initials = customer.name
    ? customer.name.split(' ').map((n: string) => n[0]).join('').toUpperCase()
    : 'U';
  return (
    <TableRow className="cursor-pointer hover:bg-accent/50">
      <TableCell>
        <div className="flex items-center gap-3">
          <Avatar className="h-9 w-9">
            <AvatarImage src={customer.avatar_url} />
            <AvatarFallback>{initials}</AvatarFallback>
          </Avatar>
          <div>
            <div className="flex items-center gap-2">
              <p className="font-medium">{customer.name || 'Unknown'}</p>
              {customer.is_vip && (
                <Star className="h-3 w-3 fill-yellow-500 text-yellow-500" />
              )}
              {customer.is_blocked && (
                <Ban className="h-3 w-3 text-red-500" />
              )}
            </div>
            <p className="text-sm text-muted-foreground">{customer.email}</p>
          </div>
        </div>
      </TableCell>
      <TableCell>
        <div>
          <p className="text-sm">{customer.company || '-'}</p>
          {(customer.city || customer.country) && (
            <p className="text-xs text-muted-foreground">
              {[customer.city, customer.country].filter(Boolean).join(', ')}
            </p>
          )}
        </div>
      </TableCell>
      <TableCell>
        <div className="space-y-2">
          <div className="flex items-center gap-2">
            <div className="text-2xl font-bold text-gray-900">
              {customer.total_tickets || 0}
            </div>
            <span className="text-sm text-muted-foreground">total</span>
          </div>
          <div className="flex items-center gap-2">
            <Badge variant="default" className="text-xs font-semibold">
              {customer.open_tickets || 0} Open
            </Badge>
            <Badge variant="secondary" className="text-xs">
              {customer.closed_tickets || 0} Closed
            </Badge>
          </div>
        </div>
      </TableCell>
      <TableCell>
        {customer.created_at ? (
          <div className="text-sm">
            <p>{format(new Date(customer.created_at), 'MMM d, yyyy')}</p>
            <p className="text-xs text-muted-foreground">
              {format(new Date(customer.created_at), 'h:mm a')}
            </p>
          </div>
        ) : (
          <span className="text-sm text-muted-foreground">-</span>
        )}
      </TableCell>
      <TableCell className="text-right">
        <TooltipProvider>
          <div className="flex items-center justify-end gap-1">
            <Tooltip>
              <TooltipTrigger asChild>
                <Button
                  variant="ghost"
                  size="icon"
                  className="h-8 w-8"
                  onClick={() => window.location.href = `/customers/${customer.id}`}
                >
                  <Eye className="h-4 w-4" />
                </Button>
              </TooltipTrigger>
              <TooltipContent>View Profile</TooltipContent>
            </Tooltip>

            <Tooltip>
              <TooltipTrigger asChild>
                <Button
                  variant="ghost"
                  size="icon"
                  className={cn(
                    "h-8 w-8",
                    customer.is_vip && "text-yellow-500 hover:text-yellow-600"
                  )}
                  onClick={() => toggleVIPMutation.mutate()}
                  disabled={toggleVIPMutation.isPending}
                >
                  <Star className={cn("h-4 w-4", customer.is_vip && "fill-current")} />
                </Button>
              </TooltipTrigger>
              <TooltipContent>
                {customer.is_vip ? 'Remove VIP Status' : 'Mark as VIP'}
              </TooltipContent>
            </Tooltip>

            <Tooltip>
              <TooltipTrigger asChild>
                <Button
                  variant="ghost"
                  size="icon"
                  className={cn(
                    "h-8 w-8",
                    customer.is_blocked && "text-red-500 hover:text-red-600"
                  )}
                  onClick={() => toggleBlockMutation.mutate()}
                  disabled={toggleBlockMutation.isPending}
                >
                  <Ban className="h-4 w-4" />
                </Button>
              </TooltipTrigger>
              <TooltipContent>
                {customer.is_blocked ? 'Unblock Customer' : 'Block Customer'}
              </TooltipContent>
            </Tooltip>

            <AlertDialog>
              <Tooltip>
                <TooltipTrigger asChild>
                  <AlertDialogTrigger asChild>
                    <Button
                      variant="ghost"
                      size="icon"
                      className="h-8 w-8 text-red-500 hover:text-red-600 hover:bg-red-50"
                      disabled={deleteMutation.isPending}
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </AlertDialogTrigger>
                </TooltipTrigger>
                <TooltipContent>Delete Customer</TooltipContent>
              </Tooltip>
              <AlertDialogContent>
                <AlertDialogHeader>
                  <AlertDialogTitle>Are you absolutely sure?</AlertDialogTitle>
                  <AlertDialogDescription>
                    This will permanently delete <strong>{customer.name || customer.email}</strong> and all associated data.
                    This action cannot be undone.
                  </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                  <AlertDialogCancel>Cancel</AlertDialogCancel>
                  <AlertDialogAction
                    onClick={() => deleteMutation.mutate()}
                    className="bg-red-500 hover:bg-red-600"
                  >
                    {deleteMutation.isPending ? 'Deleting...' : 'Delete'}
                  </AlertDialogAction>
                </AlertDialogFooter>
              </AlertDialogContent>
            </AlertDialog>
          </div>
        </TooltipProvider>
      </TableCell>
    </TableRow>
  );
}

export default function CustomersPage() {
  const [searchQuery, setSearchQuery] = useState('');
  const [filterVIP, setFilterVIP] = useState('all');
  const [currentPage, setCurrentPage] = useState(1);
  const [itemsPerPage] = useState(20);

  const { data: customers, isLoading } = useQuery({
    queryKey: ['customers', searchQuery, filterVIP, currentPage, itemsPerPage],
    queryFn: async () => {
      try {
        // Build query parameters
        const params: any = {
          page: currentPage,
          limit: itemsPerPage,
        };

        if (searchQuery) {
          params.search = searchQuery;
        }

        if (filterVIP === 'vip') {
          params.is_vip = true;
        } else if (filterVIP === 'regular') {
          params.is_vip = false;
        }

        // Fetch from real API
        const response = await api.clients.list(params);

        // Handle response format from API
        if (response.data?.success && response.data?.data) {
          return {
            data: response.data.data,
            meta: response.data.meta || { total: response.data.data.length },
          };
        }

        // Fallback for different response formats
        return {
          data: response.data?.data || response.data || [],
          meta: response.data?.meta || { total: (response.data?.data || response.data || []).length },
        };
      } catch (error) {
        console.error('Failed to fetch customers:', error);
        // Return empty data on error
        return {
          data: [],
          meta: { total: 0 },
        };
      }
    },
  });

  // Reset to first page when filters change
  useEffect(() => {
    setCurrentPage(1);
  }, [searchQuery, filterVIP]);

  const stats = {
    total: customers?.meta?.total || 0,
    vip: customers?.data?.filter((c: any) => c.is_vip).length || 0,
    active: customers?.data?.filter((c: any) => c.active_tickets > 0).length || 0,
    new_this_month: customers?.meta?.new_this_month || 0,
  };

  return (
    <div className="flex-1 space-y-4 p-8 pt-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-3xl font-bold tracking-tight">Customers</h2>
          <p className="text-muted-foreground">
            Manage your customer relationships and support history
          </p>
        </div>
      </div>

      {/* Stats Cards */}
      <div className="grid gap-4 md:grid-cols-4">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Total Customers</CardTitle>
            <Users className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{stats.total}</div>
            <p className="text-xs text-muted-foreground">
              +{stats.new_this_month} new this month
            </p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">VIP Customers</CardTitle>
            <Star className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{stats.vip}</div>
            <p className="text-xs text-muted-foreground">
              High value accounts
            </p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Active Support</CardTitle>
            <MessageSquare className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{stats.active}</div>
            <p className="text-xs text-muted-foreground">
              With open tickets
            </p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Total Tickets</CardTitle>
            <Ticket className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {customers?.meta?.total_tickets_overall || 0}
            </div>
            <p className="text-xs text-muted-foreground">
              Across all customers
            </p>
          </CardContent>
        </Card>
      </div>

      {/* Filters and Search */}
      <Card>
        <CardContent className="p-4">
          <div className="flex flex-col sm:flex-row gap-4">
            <div className="relative flex-1">
              <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                placeholder="Search by name, email, or company..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="pl-9"
              />
            </div>
            <Select value={filterVIP} onValueChange={setFilterVIP}>
              <SelectTrigger className="w-full sm:w-[180px]">
                <SelectValue placeholder="All Customers" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Customers</SelectItem>
                <SelectItem value="vip">VIP Only</SelectItem>
                <SelectItem value="regular">Regular</SelectItem>
              </SelectContent>
            </Select>
            <Button variant="outline" size="icon">
              <Filter className="h-4 w-4" />
            </Button>
          </div>
        </CardContent>
      </Card>

      {/* Customers Table */}
      <Card>
        <CardContent className="p-0">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Customer</TableHead>
                <TableHead>Company</TableHead>
                <TableHead>Tickets</TableHead>
                <TableHead>Created At</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading ? (
                <TableRow>
                  <TableCell colSpan={5} className="text-center py-8">
                    <div className="flex justify-center">
                      <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                    </div>
                  </TableCell>
                </TableRow>
              ) : customers?.data?.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={5} className="text-center py-8">
                    No customers found
                  </TableCell>
                </TableRow>
              ) : (
                customers?.data?.map((customer: any) => (
                  <CustomerRow key={customer.id} customer={customer} />
                ))
              )}
            </TableBody>
          </Table>

          {/* Pagination */}
          {customers?.meta && customers.meta.last_page > 1 && (
            <div className="flex items-center justify-between px-6 py-4 border-t">
              <p className="text-sm text-muted-foreground">
                Showing {customers.meta.from || 1} to{' '}
                {customers.meta.to || 0} of{' '}
                {customers.meta.total} customers
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
                    const totalPages = customers.meta.last_page;
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
                  onClick={() => setCurrentPage(p => Math.min(customers.meta.last_page, p + 1))}
                  disabled={currentPage === customers.meta.last_page}
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
    </div>
  );
}