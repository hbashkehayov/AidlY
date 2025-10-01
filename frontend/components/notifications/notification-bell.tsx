'use client';

import { useState } from 'react';
import { Bell, Trash2, Ticket, MessageSquare, ExternalLink } from 'lucide-react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ScrollArea } from '@/components/ui/scroll-area';
import { cn } from '@/lib/utils';
import api from '@/lib/api';
import { formatDistanceToNow } from 'date-fns';
import Link from 'next/link';

export interface Notification {
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

export function NotificationBell() {
  const [open, setOpen] = useState(false);
  const queryClient = useQueryClient();

  // Fetch notifications (limit to 10 for preview)
  // Everyone sees only their own notifications
  const { data: notificationsData, isLoading } = useQuery({
    queryKey: ['notifications-preview'],
    queryFn: async () => {
      const response = await api.notifications.list({ limit: 10, unread_only: false });
      return response.data;
    },
    refetchInterval: 30000, // Refetch every 30 seconds
  });

  // Fetch unread count - only for current user
  const { data: statsData } = useQuery({
    queryKey: ['notification-stats'],
    queryFn: async () => {
      const response = await api.notifications.stats(false);
      return response.data;
    },
    refetchInterval: 10000, // Check for new notifications every 10 seconds
  });

  const notifications: Notification[] = notificationsData?.data || [];
  const unreadCount = statsData?.data?.unread || 0;

  // Delete notification mutation
  const deleteNotificationMutation = useMutation({
    mutationFn: (notificationId: string) => api.notifications.delete(notificationId),
    onSuccess: () => {
      toast.success('Notification deleted');
      queryClient.invalidateQueries({ queryKey: ['notifications-preview'] });
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
      queryClient.invalidateQueries({ queryKey: ['notification-stats'] });
    },
    onError: () => {
      toast.error('Failed to delete notification');
    },
  });

  // Mark all as read mutation
  const markAllAsReadMutation = useMutation({
    mutationFn: async () => {
      // Mark ALL unread notifications for current user (no IDs needed)
      return api.notifications.markMultipleAsRead([]);
    },
    onSuccess: async () => {
      // Immediately refetch all queries to update the UI
      await Promise.all([
        queryClient.refetchQueries({ queryKey: ['notifications-preview'] }),
        queryClient.refetchQueries({ queryKey: ['notifications'] }),
        queryClient.refetchQueries({ queryKey: ['notification-stats'] }),
      ]);
    },
  });

  // Clear all notifications mutation
  const clearAllNotificationsMutation = useMutation({
    mutationFn: async () => {
      // Delete all notifications for the current user
      const deletePromises = notifications.map(n => api.notifications.delete(n.id));
      return Promise.all(deletePromises);
    },
    onSuccess: () => {
      const count = notifications.length;
      toast.success(`${count} notification${count !== 1 ? 's' : ''} cleared`);
      queryClient.invalidateQueries({ queryKey: ['notifications-preview'] });
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
      queryClient.invalidateQueries({ queryKey: ['notification-stats'] });
    },
    onError: () => {
      toast.error('Failed to clear notifications');
    },
  });

  const handleDelete = (notificationId: string, event: React.MouseEvent) => {
    event.preventDefault();
    event.stopPropagation();
    deleteNotificationMutation.mutate(notificationId);
  };

  const handleMarkAllAsRead = () => {
    markAllAsReadMutation.mutate();
  };

  const handleClearAll = () => {
    clearAllNotificationsMutation.mutate();
  };

  // Mark all as read when opening the bell
  const handleOpenChange = (newOpen: boolean) => {
    setOpen(newOpen);

    if (newOpen) {
      // Mark all as read when bell is clicked
      handleMarkAllAsRead();
    }
  };

  return (
    <DropdownMenu open={open} onOpenChange={handleOpenChange}>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" size="icon" className="relative">
          <Bell className="h-5 w-5" />
          {unreadCount > 0 && (
            <span className="absolute -top-1 -right-1 h-5 w-5 bg-red-500 rounded-full text-[10px] text-white flex items-center justify-center font-semibold">
              {unreadCount > 99 ? '99+' : unreadCount}
            </span>
          )}
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent className="w-[420px] p-0" align="end">
        <div className="flex items-center justify-between p-4 border-b">
          <h3 className="font-semibold text-sm">Notifications</h3>
          <div className="flex items-center gap-2">
            {notifications.length > 0 && (
              <Button
                variant="ghost"
                size="sm"
                className="h-7 text-xs text-destructive hover:text-destructive"
                onClick={handleClearAll}
                disabled={clearAllNotificationsMutation.isPending}
              >
                Clear all
              </Button>
            )}
          </div>
        </div>

        <ScrollArea className="h-[400px]">
          {isLoading ? (
            <div className="p-8 text-center text-sm text-muted-foreground">
              Loading notifications...
            </div>
          ) : notifications.length === 0 ? (
            <div className="p-8 text-center text-sm text-muted-foreground">
              <Bell className="h-12 w-12 mx-auto mb-2 opacity-20" />
              <p>No notifications yet</p>
            </div>
          ) : (
            <div className="divide-y">
              {notifications.map((notification) => {
                const Icon = getNotificationIcon(notification.type);
                const isUnread = !notification.read_at;

                return (
                  <Link
                    key={notification.id}
                    href={notification.action_url || `/tickets/${notification.ticket_id}`}
                    onClick={() => setOpen(false)}
                    className={cn(
                      "block p-4 hover:bg-accent transition-colors relative",
                      isUnread && "bg-blue-50/50 dark:bg-blue-950/20"
                    )}
                  >
                    <div className="flex gap-3">
                      <div className={cn(
                        "flex-shrink-0 mt-0.5",
                        getPriorityColor(notification.priority)
                      )}>
                        <Icon className="h-5 w-5" />
                      </div>

                      <div className="flex-1 min-w-0">
                        <div className="flex items-start justify-between gap-2 mb-1">
                          <div className="flex-1">
                            <p className={cn(
                              "text-sm font-medium",
                              isUnread && "font-semibold"
                            )}>
                              {notification.title}
                            </p>
                          </div>
                          {isUnread && (
                            <div className="h-2 w-2 bg-blue-500 rounded-full flex-shrink-0 mt-1.5" />
                          )}
                        </div>

                        <p className="text-sm text-muted-foreground line-clamp-2 mb-2">
                          {notification.message}
                        </p>

                        <div className="flex items-center justify-between">
                          <span className="text-xs text-muted-foreground">
                            {formatDistanceToNow(new Date(notification.created_at), { addSuffix: true })}
                          </span>

                          <div className="flex items-center gap-1">
                            {notification.action_url && (
                              <ExternalLink className="h-3 w-3 text-muted-foreground" />
                            )}

                            <Button
                              variant="ghost"
                              size="sm"
                              className="h-6 w-6 p-0 text-muted-foreground hover:text-destructive"
                              onClick={(e) => handleDelete(notification.id, e)}
                              title="Delete notification"
                            >
                              <Trash2 className="h-3 w-3" />
                            </Button>
                          </div>
                        </div>
                      </div>
                    </div>
                  </Link>
                );
              })}
            </div>
          )}
        </ScrollArea>

        <div className="p-2 border-t bg-muted/30">
          <Link
            href="/notifications"
            className="block w-full text-center text-sm text-primary hover:underline py-2 font-medium"
            onClick={() => setOpen(false)}
          >
            {notifications.length > 0 ? 'View all notifications' : 'View notification center'}
          </Link>
        </div>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
