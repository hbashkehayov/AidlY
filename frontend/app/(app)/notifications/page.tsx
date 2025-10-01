'use client';

import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Bell, Check, Trash2, Ticket, MessageSquare, CheckCheck, Trash, Filter } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cn } from '@/lib/utils';
import api from '@/lib/api';
import { format } from 'date-fns';
import Link from 'next/link';

interface Notification {
  id: string;
  type: string;
  channel: string;
  notifiable_id: string;
  notifiable_type: string;
  ticket_id?: string;
  comment_id?: string;
  triggered_by?: string;
  title: string;
  message: string;
  data?: any;
  action_url?: string;
  action_text?: string;
  status: string;
  priority: string;
  read_at?: string;
  created_at: string;
}

const getNotificationIcon = (type: string) => {
  if (type.includes('ticket')) return Ticket;
  if (type.includes('comment') || type.includes('reply')) return MessageSquare;
  return Bell;
};

const getPriorityColor = (priority: string) => {
  switch (priority) {
    case 'urgent':
      return 'text-red-500';
    case 'high':
      return 'text-orange-500';
    case 'normal':
      return 'text-blue-500';
    case 'low':
      return 'text-gray-500';
    default:
      return 'text-gray-500';
  }
};

export default function NotificationsPage() {
  const [activeTab, setActiveTab] = useState('all');
  const [filterType, setFilterType] = useState<string>('all');
  const queryClient = useQueryClient();

  // Fetch all notifications for current user only
  const { data: notificationsData, isLoading } = useQuery({
    queryKey: ['notifications', activeTab, filterType],
    queryFn: async () => {
      const params: any = { limit: 100 };
      if (activeTab === 'unread') {
        params.unread_only = true;
      }
      if (filterType !== 'all') {
        params.type = filterType;
      }
      const response = await api.notifications.list(params);
      return response.data;
    },
    refetchInterval: 30000, // Refetch every 30 seconds
  });

  // Fetch stats for current user only
  const { data: statsData } = useQuery({
    queryKey: ['notification-stats'],
    queryFn: async () => {
      const response = await api.notifications.stats(false);
      return response.data;
    },
    refetchInterval: 10000,
  });

  const notifications: Notification[] = notificationsData?.data || [];
  const stats = statsData?.data || { total: 0, unread: 0, read: 0 };

  // Get unique notification types for filter
  const notificationTypes = ['all', ...Array.from(new Set(notifications.map(n => n.type)))];

  // Mark as read mutation (only for unread tab)
  const markAsReadMutation = useMutation({
    mutationFn: (notificationId: string) => api.notifications.markAsRead(notificationId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
      queryClient.invalidateQueries({ queryKey: ['notification-stats'] });
      queryClient.invalidateQueries({ queryKey: ['notifications-preview'] });
    },
  });

  // Delete notification mutation
  const deleteNotificationMutation = useMutation({
    mutationFn: (notificationId: string) => api.notifications.delete(notificationId),
    onSuccess: () => {
      toast.success('Notification deleted');
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
      queryClient.invalidateQueries({ queryKey: ['notification-stats'] });
      queryClient.invalidateQueries({ queryKey: ['notifications-preview'] });
    },
    onError: () => {
      toast.error('Failed to delete notification');
    },
  });

  // Mark all as read mutation (for Unread tab)
  const markAllAsReadMutation = useMutation({
    mutationFn: async () => {
      const unreadIds = notifications
        .filter(n => !n.read_at)
        .map(n => n.id);
      if (unreadIds.length === 0) return Promise.resolve({ data: null });
      return api.notifications.markMultipleAsRead(unreadIds);
    },
    onSuccess: () => {
      const count = notifications.filter(n => !n.read_at).length;
      if (count > 0) {
        toast.success(`${count} notification${count !== 1 ? 's' : ''} marked as read`);
      }
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
      queryClient.invalidateQueries({ queryKey: ['notification-stats'] });
      queryClient.invalidateQueries({ queryKey: ['notifications-preview'] });
    },
  });

  // Clear all notifications mutation
  const clearAllNotificationsMutation = useMutation({
    mutationFn: async () => {
      const deletePromises = notifications.map(n => api.notifications.delete(n.id));
      return Promise.all(deletePromises);
    },
    onSuccess: () => {
      const count = notifications.length;
      toast.success(`${count} notification${count !== 1 ? 's' : ''} cleared`);
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
      queryClient.invalidateQueries({ queryKey: ['notification-stats'] });
      queryClient.invalidateQueries({ queryKey: ['notifications-preview'] });
    },
    onError: () => {
      toast.error('Failed to clear notifications');
    },
  });

  const handleMarkAsRead = (notificationId: string) => {
    markAsReadMutation.mutate(notificationId);
  };

  const handleDelete = (notificationId: string) => {
    deleteNotificationMutation.mutate(notificationId);
  };

  const handleMarkAllAsRead = () => {
    markAllAsReadMutation.mutate();
  };

  const handleClearAll = () => {
    if (confirm('Are you sure you want to clear all notifications? This action cannot be undone.')) {
      clearAllNotificationsMutation.mutate();
    }
  };

  return (
    <div className="container max-w-5xl mx-auto py-8 px-4">
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">Notifications</h1>
            <p className="text-muted-foreground mt-1">
              Stay updated with your tickets and messages
            </p>
          </div>

          <div className="flex items-center gap-2">
            {/* Only show "Mark all read" button on Unread tab */}
            {activeTab === 'unread' && notifications.filter(n => !n.read_at).length > 0 && (
              <Button
                variant="outline"
                size="sm"
                onClick={handleMarkAllAsRead}
                disabled={markAllAsReadMutation.isPending}
              >
                <CheckCheck className="h-4 w-4 mr-2" />
                Mark all read
              </Button>
            )}
            {notifications.length > 0 && (
              <Button
                variant="outline"
                size="sm"
                onClick={handleClearAll}
                disabled={clearAllNotificationsMutation.isPending}
                className="text-destructive hover:text-destructive"
              >
                <Trash className="h-4 w-4 mr-2" />
                Clear all
              </Button>
            )}
          </div>
        </div>

        {/* Stats Cards */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <Card>
            <CardHeader className="pb-3">
              <CardDescription>Total</CardDescription>
              <CardTitle className="text-3xl">{stats.total}</CardTitle>
            </CardHeader>
          </Card>
          <Card>
            <CardHeader className="pb-3">
              <CardDescription>Unread</CardDescription>
              <CardTitle className="text-3xl text-blue-600">{stats.unread}</CardTitle>
            </CardHeader>
          </Card>
          <Card>
            <CardHeader className="pb-3">
              <CardDescription>Read</CardDescription>
              <CardTitle className="text-3xl text-gray-500">{stats.read}</CardTitle>
            </CardHeader>
          </Card>
        </div>

        {/* Tabs and Filters */}
        <div className="space-y-4">
          <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
            <TabsList className="grid w-full max-w-md grid-cols-2">
              <TabsTrigger value="all">
                All Notifications
                <Badge variant="secondary" className="ml-2">
                  {stats.total}
                </Badge>
              </TabsTrigger>
              <TabsTrigger value="unread">
                Unread
                <Badge variant="secondary" className="ml-2">
                  {stats.unread}
                </Badge>
              </TabsTrigger>
            </TabsList>
          </Tabs>

          {/* Type Filter */}
          {notificationTypes.length > 1 && (
            <div className="flex items-center gap-2">
              <Filter className="h-4 w-4 text-muted-foreground" />
              <span className="text-sm text-muted-foreground">Filter by type:</span>
              <div className="flex flex-wrap gap-2">
                {notificationTypes.map((type) => (
                  <Button
                    key={type}
                    variant={filterType === type ? 'default' : 'outline'}
                    size="sm"
                    onClick={() => setFilterType(type)}
                    className="h-7 text-xs"
                  >
                    {type === 'all' ? 'All Types' : type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}
                  </Button>
                ))}
              </div>
            </div>
          )}
        </div>

          <div className="mt-6">
            {isLoading ? (
              <Card>
                <CardContent className="p-12 text-center text-muted-foreground">
                  Loading notifications...
                </CardContent>
              </Card>
            ) : notifications.length === 0 ? (
              <Card>
                <CardContent className="p-12 text-center">
                  <Bell className="h-16 w-16 mx-auto mb-4 opacity-20" />
                  <h3 className="text-lg font-semibold mb-2">
                    {activeTab === 'unread' ? 'No unread notifications' : 'No notifications yet'}
                  </h3>
                  <p className="text-muted-foreground">
                    {activeTab === 'unread'
                      ? "You're all caught up! Check back later for new updates."
                      : "When you receive notifications, they'll appear here."}
                  </p>
                </CardContent>
              </Card>
            ) : (
              <div className="space-y-2">
                {notifications.map((notification) => {
                  const Icon = getNotificationIcon(notification.type);
                  const isUnread = !notification.read_at;

                  return (
                    <Card
                      key={notification.id}
                      className={cn(
                        "hover:shadow-md transition-all cursor-pointer",
                        isUnread && "border-l-4 border-l-blue-500 bg-blue-50/50 dark:bg-blue-950/20"
                      )}
                    >
                      <CardContent className="p-4">
                        <div className="flex gap-4">
                          <div className={cn(
                            "flex-shrink-0 mt-1",
                            getPriorityColor(notification.priority)
                          )}>
                            <Icon className="h-6 w-6" />
                          </div>

                          <div className="flex-1 min-w-0">
                            <div className="flex items-start justify-between gap-2 mb-2">
                              <div className="flex items-center gap-2">
                                <h3 className={cn(
                                  "text-base font-medium",
                                  isUnread && "font-semibold"
                                )}>
                                  {notification.title}
                                </h3>
                                {isUnread && (
                                  <div className="h-2 w-2 bg-blue-500 rounded-full flex-shrink-0" />
                                )}
                              </div>

                              <span className="text-xs text-muted-foreground whitespace-nowrap">
                                {format(new Date(notification.created_at), 'MMM d, yyyy • h:mm a')}
                              </span>
                            </div>

                            <p className="text-sm text-muted-foreground mb-3">
                              {notification.message}
                            </p>

                            <div className="flex items-center justify-between">
                              <div className="flex items-center gap-2">
                                {notification.action_url && (
                                  <Link href={notification.action_url}>
                                    <Button variant="outline" size="sm">
                                      {notification.action_text || 'View'}
                                    </Button>
                                  </Link>
                                )}

                                <Badge variant="outline" className="text-xs">
                                  {notification.type.replace(/_/g, ' ')}
                                </Badge>

                                {notification.priority !== 'normal' && (
                                  <Badge
                                    variant={notification.priority === 'urgent' ? 'destructive' : 'secondary'}
                                    className="text-xs"
                                  >
                                    {notification.priority}
                                  </Badge>
                                )}
                              </div>

                              <div className="flex items-center gap-1">
                                {/* Only show mark as read on Unread tab */}
                                {activeTab === 'unread' && isUnread && (
                                  <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => handleMarkAsRead(notification.id)}
                                    disabled={markAsReadMutation.isPending}
                                    title="Mark as read"
                                    className="text-blue-600 hover:text-blue-700"
                                  >
                                    <Check className="h-4 w-4" />
                                  </Button>
                                )}

                                <Button
                                  variant="ghost"
                                  size="sm"
                                  onClick={() => handleDelete(notification.id)}
                                  disabled={deleteNotificationMutation.isPending}
                                  className="text-muted-foreground hover:text-destructive"
                                  title="Delete notification"
                                >
                                  <Trash2 className="h-4 w-4" />
                                </Button>
                              </div>
                            </div>
                          </div>
                        </div>
                      </CardContent>
                    </Card>
                  );
                })}
              </div>
            )}
          </div>
      </div>
    </div>
  );
}
