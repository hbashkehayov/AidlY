'use client';

import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
  Search,
  Plus,
  Filter,
  MoreHorizontal,
  Mail,
  Phone,
  Building,
  MapPin,
  Calendar,
  Ticket,
  Star,
  Ban,
  UserPlus,
  Users,
  History,
  MessageSquare,
  DollarSign,
  TrendingUp,
} from 'lucide-react';
import api from '@/lib/api';
import { format } from 'date-fns';
import { cn } from '@/lib/utils';

// Mock data for development
const mockCustomers = [
  {
    id: '1',
    name: 'John Doe',
    email: 'john@example.com',
    company: 'Acme Corp',
    phone: '+1 (555) 123-4567',
    avatar_url: null,
    is_vip: false,
    is_blocked: false,
    tags: ['enterprise', 'active'],
    lifetime_value: 15000,
    created_at: '2023-06-15T10:30:00Z',
    last_contact_at: '2024-01-15T14:20:00Z',
    total_tickets: 12,
    open_tickets: 2,
    city: 'New York',
    country: 'USA',
  },
  {
    id: '2',
    name: 'Alice Smith',
    email: 'alice@techsolutions.com',
    company: 'Tech Solutions',
    phone: '+1 (555) 987-6543',
    avatar_url: null,
    is_vip: true,
    is_blocked: false,
    tags: ['vip', 'priority'],
    lifetime_value: 45000,
    created_at: '2023-03-20T09:15:00Z',
    last_contact_at: '2024-01-14T16:45:00Z',
    total_tickets: 28,
    open_tickets: 1,
    city: 'San Francisco',
    country: 'USA',
  },
  {
    id: '3',
    name: 'Bob Wilson',
    email: 'bob@startup.io',
    company: 'Startup Inc',
    phone: '+1 (555) 246-8135',
    avatar_url: null,
    is_vip: false,
    is_blocked: false,
    tags: ['startup', 'growth'],
    lifetime_value: 8500,
    created_at: '2023-09-10T11:20:00Z',
    last_contact_at: '2024-01-13T10:15:00Z',
    total_tickets: 6,
    open_tickets: 0,
    city: 'Austin',
    country: 'USA',
  },
];

function CustomerRow({ customer }: any) {
  return (
    <TableRow className="cursor-pointer hover:bg-accent/50">
      <TableCell>
        <div className="flex items-center gap-3">
          <Avatar className="h-9 w-9">
            <AvatarImage src={customer.avatar_url} />
            <AvatarFallback>{customer.name.split(' ').map((n: string) => n[0]).join('')}</AvatarFallback>
          </Avatar>
          <div>
            <div className="flex items-center gap-2">
              <p className="font-medium">{customer.name}</p>
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
          <p className="text-sm">{customer.company}</p>
          <p className="text-xs text-muted-foreground">{customer.city}, {customer.country}</p>
        </div>
      </TableCell>
      <TableCell>
        <div className="flex flex-wrap gap-1">
          {customer.tags.map((tag: string) => (
            <Badge key={tag} variant="secondary" className="text-xs">
              {tag}
            </Badge>
          ))}
        </div>
      </TableCell>
      <TableCell>
        <div className="space-y-1">
          <div className="flex items-center gap-1">
            <Ticket className="h-3 w-3 text-muted-foreground" />
            <span className="text-sm">{customer.total_tickets} total</span>
          </div>
          {customer.open_tickets > 0 && (
            <Badge variant="outline" className="text-xs">
              {customer.open_tickets} open
            </Badge>
          )}
        </div>
      </TableCell>
      <TableCell>
        <div className="text-sm">
          ${customer.lifetime_value.toLocaleString()}
        </div>
      </TableCell>
      <TableCell>
        <div className="text-sm">
          <p>{format(new Date(customer.last_contact_at), 'MMM d, yyyy')}</p>
          <p className="text-xs text-muted-foreground">
            {format(new Date(customer.last_contact_at), 'h:mm a')}
          </p>
        </div>
      </TableCell>
      <TableCell className="text-right">
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" size="sm">
              <MoreHorizontal className="h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuLabel>Actions</DropdownMenuLabel>
            <DropdownMenuItem>View Profile</DropdownMenuItem>
            <DropdownMenuItem>View Tickets</DropdownMenuItem>
            <DropdownMenuItem>Send Email</DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem>Add Note</DropdownMenuItem>
            <DropdownMenuItem>Edit Customer</DropdownMenuItem>
            {customer.is_vip ? (
              <DropdownMenuItem>Remove VIP Status</DropdownMenuItem>
            ) : (
              <DropdownMenuItem>Mark as VIP</DropdownMenuItem>
            )}
            {customer.is_blocked ? (
              <DropdownMenuItem>Unblock Customer</DropdownMenuItem>
            ) : (
              <DropdownMenuItem className="text-red-600">Block Customer</DropdownMenuItem>
            )}
          </DropdownMenuContent>
        </DropdownMenu>
      </TableCell>
    </TableRow>
  );
}

export default function CustomersPage() {
  const [searchQuery, setSearchQuery] = useState('');
  const [filterVIP, setFilterVIP] = useState('all');
  const [isCreateDialogOpen, setIsCreateDialogOpen] = useState(false);

  const { data: customers, isLoading } = useQuery({
    queryKey: ['customers', searchQuery, filterVIP],
    queryFn: async () => {
      // For now, return mock data
      let filtered = [...mockCustomers];

      if (filterVIP === 'vip') {
        filtered = filtered.filter(c => c.is_vip);
      } else if (filterVIP === 'regular') {
        filtered = filtered.filter(c => !c.is_vip);
      }

      if (searchQuery) {
        filtered = filtered.filter(c =>
          c.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
          c.email.toLowerCase().includes(searchQuery.toLowerCase()) ||
          c.company.toLowerCase().includes(searchQuery.toLowerCase())
        );
      }

      return {
        data: filtered,
        meta: {
          total: filtered.length,
        },
      };
    },
  });

  const stats = {
    total: customers?.meta?.total || 0,
    vip: mockCustomers.filter(c => c.is_vip).length,
    active: mockCustomers.filter(c => c.open_tickets > 0).length,
    new_this_month: 12,
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
        <Dialog open={isCreateDialogOpen} onOpenChange={setIsCreateDialogOpen}>
          <DialogTrigger asChild>
            <Button>
              <UserPlus className="mr-2 h-4 w-4" />
              Add Customer
            </Button>
          </DialogTrigger>
          <DialogContent className="sm:max-w-[525px]">
            <DialogHeader>
              <DialogTitle>Add New Customer</DialogTitle>
              <DialogDescription>
                Create a new customer profile to track their support history
              </DialogDescription>
            </DialogHeader>
            <div className="grid gap-4 py-4">
              <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-2">
                  <Label htmlFor="name">Name</Label>
                  <Input id="name" placeholder="John Doe" />
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="email">Email</Label>
                  <Input id="email" type="email" placeholder="john@example.com" />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-2">
                  <Label htmlFor="company">Company</Label>
                  <Input id="company" placeholder="Acme Corp" />
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="phone">Phone</Label>
                  <Input id="phone" type="tel" placeholder="+1 (555) 123-4567" />
                </div>
              </div>
              <div className="grid gap-2">
                <Label htmlFor="tags">Tags</Label>
                <Input id="tags" placeholder="enterprise, priority (comma separated)" />
              </div>
            </div>
            <DialogFooter>
              <Button variant="outline" onClick={() => setIsCreateDialogOpen(false)}>
                Cancel
              </Button>
              <Button onClick={() => setIsCreateDialogOpen(false)}>
                Add Customer
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
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
            <CardTitle className="text-sm font-medium">Avg. Lifetime Value</CardTitle>
            <DollarSign className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">$22.8k</div>
            <p className="text-xs text-muted-foreground">
              <TrendingUp className="inline h-3 w-3 text-green-500" /> +12% from last month
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
                <TableHead>Tags</TableHead>
                <TableHead>Tickets</TableHead>
                <TableHead>Lifetime Value</TableHead>
                <TableHead>Last Contact</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading ? (
                <TableRow>
                  <TableCell colSpan={7} className="text-center py-8">
                    <div className="flex justify-center">
                      <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                    </div>
                  </TableCell>
                </TableRow>
              ) : customers?.data?.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={7} className="text-center py-8">
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
        </CardContent>
      </Card>
    </div>
  );
}