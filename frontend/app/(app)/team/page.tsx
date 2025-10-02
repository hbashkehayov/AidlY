'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import { format } from 'date-fns';
import { useAuth } from '@/lib/auth';
import {
  Users,
  Search,
  Shield,
  User as UserIcon,
  Ticket,
  CheckCircle,
  Clock,
  MoreVertical,
  Mail,
  Calendar,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Input } from '@/components/ui/input';
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
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

const roleConfig = {
  admin: { label: 'Administrator', color: 'destructive', icon: Shield },
  agent: { label: 'Agent', color: 'default', icon: UserIcon },
};

export default function TeamPage() {
  const router = useRouter();
  const { user } = useAuth();
  const [searchQuery, setSearchQuery] = useState('');
  const [roleFilter, setRoleFilter] = useState('all');

  // Check if current user is admin
  const isAdmin = user?.role === 'admin';

  // Fetch all users
  const { data: usersData, isLoading } = useQuery({
    queryKey: ['users', roleFilter],
    queryFn: async () => {
      const params: any = {};
      if (roleFilter !== 'all') {
        params.role = roleFilter;
      }
      const response = await api.users.list(params);
      return response.data;
    },
  });

  // Fetch tickets to calculate stats for each user (only for admins)
  const { data: ticketsData } = useQuery({
    queryKey: ['all-tickets-for-stats'],
    queryFn: async () => {
      const response = await api.tickets.list({ limit: 1000 });
      return response.data?.data || response.data || [];
    },
    enabled: isAdmin, // Only fetch ticket data for admins
  });

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

  const calculateUserStats = (userId: string) => {
    if (!ticketsData) return { total: 0, open: 0, closed: 0 };

    const userTickets = ticketsData.filter((ticket: any) => ticket.assigned_agent_id === userId);
    const openTickets = userTickets.filter((t: any) =>
      ['open', 'pending'].includes(t.status)
    );
    const closedTickets = userTickets.filter((t: any) =>
      ['resolved', 'closed'].includes(t.status)
    );

    return {
      total: userTickets.length,
      open: openTickets.length,
      closed: closedTickets.length,
    };
  };

  // Filter users based on search query
  const users = usersData?.data || usersData || [];
  const filteredUsers = users.filter((user: any) => {
    const matchesSearch = !searchQuery ||
      user.name?.toLowerCase().includes(searchQuery.toLowerCase()) ||
      user.email?.toLowerCase().includes(searchQuery.toLowerCase());
    return matchesSearch;
  });

  const getInitials = (name: string) => {
    return name
      ? name.split(' ').map((n: string) => n[0]).join('').toUpperCase()
      : 'U';
  };

  return (
    <div className="flex-1 space-y-4 p-8 pt-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-3xl font-bold tracking-tight">Team</h2>
          <p className="text-muted-foreground">
            {isAdmin
              ? 'View and manage your support team members'
              : 'View your support team members'}
          </p>
        </div>
        {isAdmin && (
          <Button onClick={() => router.push('/settings?tab=users')}>
            <Users className="mr-2 h-4 w-4" />
            Add Team Member
          </Button>
        )}
      </div>

      {/* Stats Overview */}
      <div className="grid gap-4 md:grid-cols-3">
        <Card>
          <CardContent className="pt-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-muted-foreground">Total Members</p>
                <p className="text-2xl font-bold">{users.length}</p>
              </div>
              <Users className="h-8 w-8 text-muted-foreground" />
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="pt-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-muted-foreground">Active Agents</p>
                <p className="text-2xl font-bold">
                  {users.filter((u: any) => u.is_active && u.role === 'agent').length}
                </p>
              </div>
              <UserIcon className="h-8 w-8 text-muted-foreground" />
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="pt-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-muted-foreground">Administrators</p>
                <p className="text-2xl font-bold">
                  {users.filter((u: any) => u.role === 'admin').length}
                </p>
              </div>
              <Shield className="h-8 w-8 text-muted-foreground" />
            </div>
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
                placeholder="Search team members..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="pl-9"
              />
            </div>
            <Select value={roleFilter} onValueChange={setRoleFilter}>
              <SelectTrigger className="w-full sm:w-[180px]">
                <SelectValue placeholder="Filter by Role" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Roles</SelectItem>
                <SelectItem value="admin">Administrators</SelectItem>
                <SelectItem value="agent">Agents</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </CardContent>
      </Card>

      {/* Team Members Table */}
      <Card>
        <CardContent className="p-0">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Team Member</TableHead>
                <TableHead>Role</TableHead>
                <TableHead>Status</TableHead>
                {isAdmin && (
                  <>
                    <TableHead className="text-center">Total Tickets</TableHead>
                    <TableHead className="text-center">Open</TableHead>
                    <TableHead className="text-center">Resolved</TableHead>
                  </>
                )}
                <TableHead>Joined</TableHead>
                {isAdmin && <TableHead className="text-right">Actions</TableHead>}
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading ? (
                <TableRow>
                  <TableCell colSpan={isAdmin ? 8 : 4} className="text-center py-8">
                    <div className="flex justify-center">
                      <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                    </div>
                  </TableCell>
                </TableRow>
              ) : filteredUsers.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={isAdmin ? 8 : 4} className="text-center py-8">
                    No team members found
                  </TableCell>
                </TableRow>
              ) : (
                filteredUsers.map((user: any) => {
                  const stats = calculateUserStats(user.id);
                  const initials = getInitials(user.name);

                  return (
                    <TableRow
                      key={user.id}
                      className={isAdmin ? "cursor-pointer hover:bg-accent/50" : ""}
                      onClick={isAdmin ? () => router.push(`/team/${user.id}`) : undefined}
                    >
                      <TableCell>
                        <div className="flex items-center gap-3">
                          <Avatar className="h-10 w-10">
                            <AvatarImage src={user.avatar_url} />
                            <AvatarFallback>{initials}</AvatarFallback>
                          </Avatar>
                          <div>
                            <p className="font-medium">{user.name}</p>
                            <p className="text-sm text-muted-foreground">{user.email}</p>
                          </div>
                        </div>
                      </TableCell>
                      <TableCell>
                        <div onClick={(e) => e.stopPropagation()}>
                          {getRoleBadge(user.role)}
                        </div>
                      </TableCell>
                      <TableCell>
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
                      </TableCell>
                      {isAdmin && (
                        <>
                          <TableCell className="text-center">
                            <div className="flex items-center justify-center gap-1">
                              <Ticket className="h-4 w-4 text-muted-foreground" />
                              <span className="font-medium">{stats.total}</span>
                            </div>
                          </TableCell>
                          <TableCell className="text-center">
                            <div className="flex items-center justify-center gap-1">
                              <Clock className="h-4 w-4 text-yellow-500" />
                              <span className="font-medium">{stats.open}</span>
                            </div>
                          </TableCell>
                          <TableCell className="text-center">
                            <div className="flex items-center justify-center gap-1">
                              <CheckCircle className="h-4 w-4 text-green-500" />
                              <span className="font-medium">{stats.closed}</span>
                            </div>
                          </TableCell>
                        </>
                      )}
                      <TableCell>
                        <div className="flex items-center gap-1 text-sm text-muted-foreground">
                          <Calendar className="h-3 w-3" />
                          {user.created_at ? format(new Date(user.created_at), 'MMM yyyy') : '-'}
                        </div>
                      </TableCell>
                      {isAdmin && (
                        <TableCell className="text-right">
                          <DropdownMenu>
                            <DropdownMenuTrigger asChild onClick={(e) => e.stopPropagation()}>
                              <Button variant="ghost" size="sm">
                                <MoreVertical className="h-4 w-4" />
                              </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                              <DropdownMenuLabel>Actions</DropdownMenuLabel>
                              <DropdownMenuItem onClick={() => router.push(`/team/${user.id}`)}>
                                <UserIcon className="mr-2 h-4 w-4" />
                                View Profile
                              </DropdownMenuItem>
                              <DropdownMenuItem>
                                <Mail className="mr-2 h-4 w-4" />
                                Send Email
                              </DropdownMenuItem>
                              <DropdownMenuSeparator />
                              <DropdownMenuItem>
                                Edit Member
                              </DropdownMenuItem>
                              {user.is_active ? (
                                <DropdownMenuItem className="text-red-600">
                                  Deactivate
                                </DropdownMenuItem>
                              ) : (
                                <DropdownMenuItem>
                                  Activate
                                </DropdownMenuItem>
                              )}
                            </DropdownMenuContent>
                          </DropdownMenu>
                        </TableCell>
                      )}
                    </TableRow>
                  );
                })
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  );
}