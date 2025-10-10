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
import { format } from 'date-fns';
import { toZonedTime } from 'date-fns-tz';
import Link from 'next/link';
import { useNotificationSound } from '@/lib/hooks/use-notification-sound';

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

/**
 * Sanitize notification text by removing HTML tags and unprocessed template variables
 */
const sanitizeNotificationText = (text: string): string => {
  if (!text) return 'No message';

  // Strip HTML tags
  let sanitized = text.replace(/<[^>]*>/g, '');

  // Remove unprocessed template variables like {{variable}}, {variable}, {Variable}
  sanitized = sanitized.replace(/\{\{?[^}]+\}\}?/g, '');

  // Remove extra whitespace and trim
  sanitized = sanitized.replace(/\s+/g, ' ').trim();

  // If empty after sanitization, return fallback
  return sanitized || 'New notification';
};

/**
 * Format UTC timestamp to local timezone
 */
const formatLocalTime = (utcTimestamp: string): string => {
  try {
    // Parse the UTC timestamp
    const utcDate = new Date(utcTimestamp + 'Z'); // Add 'Z' to ensure it's treated as UTC

    // Get the user's timezone
    const userTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

    // Convert to user's local timezone
    const localDate = toZonedTime(utcDate, userTimezone);

    // Format the date
    return format(localDate, 'MMM d, yyyy • h:mm a');
  } catch (error) {
    console.error('Error formatting time:', error);
    return 'Invalid date';
  }
};

export function NotificationBell() {
  const [open, setOpen] = useState(false);
  const queryClient = useQueryClient();

  // Fetch all notifications for current user (no limit)
  // Everyone sees only their own notifications
  const { data: notificationsData, isLoading } = useQuery({
    queryKey: ['notifications-preview'],
    queryFn: async () => {
      const response = await api.notifications.list({ limit: 10000, unread_only: false });
      return response.data;
    },
    // Real-time updates for notifications
    refetchInterval: 3000, // Refetch every 3 seconds for real-time updates
    refetchOnWindowFocus: true,
    refetchIntervalInBackground: true,
    refetchOnMount: true,
    refetchOnReconnect: true,
  });

  // Fetch unread count - only for current user
  const { data: statsData } = useQuery({
    queryKey: ['notification-stats'],
    queryFn: async () => {
      const response = await api.notifications.stats(false);
      return response.data;
    },
    // Real-time updates for unread count
    refetchInterval: 3000, // Check for new notifications every 3 seconds
    refetchOnWindowFocus: true,
    refetchIntervalInBackground: true,
    refetchOnMount: true,
    refetchOnReconnect: true,
  });

  const notifications: Notification[] = notificationsData?.data || [];
  const unreadCount = statsData?.data?.unread || 0;

  // Play sound when new notifications arrive
  useNotificationSound(unreadCount);

  // Delete notification mutation with optimistic updates
  const deleteNotificationMutation = useMutation({
    mutationFn: (notificationId: string) => api.notifications.delete(notificationId),
    // Optimistic update: Remove from UI immediately
    onMutate: async (notificationId) => {
      // Cancel outgoing refetches
      await queryClient.cancelQueries({ queryKey: ['notifications-preview'] });
      await queryClient.cancelQueries({ queryKey: ['notification-stats'] });

      // Snapshot previous values
      const previousNotifications = queryClient.getQueryData(['notifications-preview']);
      const previousStats = queryClient.getQueryData(['notification-stats']);

      // Optimistically remove the notification
      queryClient.setQueryData(['notifications-preview'], (old: any) => {
        if (!old?.data) return old;

        const deletedNotification = old.data.find((n: Notification) => n.id === notificationId);
        const isUnread = deletedNotification && !deletedNotification.read_at;

        return {
          ...old,
          data: old.data.filter((n: Notification) => n.id !== notificationId),
          meta: {
            ...old.meta,
            total: (old.meta?.total || 0) - 1,
            unread: isUnread ? (old.meta?.unread || 0) - 1 : old.meta?.unread
          }
        };
      });

      // Update stats
      queryClient.setQueryData(['notification-stats'], (old: any) => {
        if (!old?.data) return old;

        const previousNotificationsData: any = previousNotifications;
        const deletedNotification = previousNotificationsData?.data?.find((n: Notification) => n.id === notificationId);
        const isUnread = deletedNotification && !deletedNotification.read_at;

        return {
          ...old,
          data: {
            ...old.data,
            total: Math.max(0, (old.data?.total || 0) - 1),
            unread: isUnread ? Math.max(0, (old.data?.unread || 0) - 1) : old.data?.unread,
            read: !isUnread ? Math.max(0, (old.data?.read || 0) - 1) : old.data?.read
          }
        };
      });

      return { previousNotifications, previousStats };
    },
    onSuccess: () => {
      toast.success('Notification deleted');
    },
    onError: (_error, _variables, context) => {
      // Rollback on error
      if (context?.previousNotifications) {
        queryClient.setQueryData(['notifications-preview'], context.previousNotifications);
      }
      if (context?.previousStats) {
        queryClient.setQueryData(['notification-stats'], context.previousStats);
      }
      toast.error('Failed to delete notification');
    },
    // Always refetch to ensure data consistency
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: ['notifications-preview'] });
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
      queryClient.invalidateQueries({ queryKey: ['notification-stats'] });
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

  // Clear all notifications mutation with optimistic updates
  const clearAllNotificationsMutation = useMutation({
    mutationFn: async () => {
      const user = JSON.parse(localStorage.getItem('user') || '{}');
      const userId = user?.id;

      if (!userId) {
        throw new Error('User ID not found');
      }

      // Fetch ALL notification IDs from backend and delete them
      const allNotificationsResponse = await api.notifications.list({ limit: 10000, unread_only: false });
      const allNotifications = allNotificationsResponse.data?.data || [];

      // Delete all notifications
      const deletePromises = allNotifications.map((n: Notification) => api.notifications.delete(n.id));
      await Promise.all(deletePromises);

      return allNotifications.length;
    },
    // Optimistic update: Clear UI immediately
    onMutate: async () => {
      // Cancel outgoing refetches
      await queryClient.cancelQueries({ queryKey: ['notifications-preview'] });
      await queryClient.cancelQueries({ queryKey: ['notification-stats'] });

      // Snapshot previous values
      const previousNotifications = queryClient.getQueryData(['notifications-preview']);
      const previousStats = queryClient.getQueryData(['notification-stats']);

      // Optimistically update to empty state
      queryClient.setQueryData(['notifications-preview'], (old: any) => ({
        ...old,
        data: [],
        meta: { ...old?.meta, total: 0, unread: 0 }
      }));

      queryClient.setQueryData(['notification-stats'], (old: any) => ({
        ...old,
        data: { ...old?.data, total: 0, unread: 0, read: 0 }
      }));

      // Return snapshot for rollback
      return { previousNotifications, previousStats };
    },
    onError: (_error, _variables, context) => {
      // Rollback on error
      if (context?.previousNotifications) {
        queryClient.setQueryData(['notifications-preview'], context.previousNotifications);
      }
      if (context?.previousStats) {
        queryClient.setQueryData(['notification-stats'], context.previousStats);
      }
      toast.error('Failed to clear notifications');
    },
    // Always refetch to ensure data consistency
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: ['notifications-preview'] });
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
      queryClient.invalidateQueries({ queryKey: ['notification-stats'] });
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

  // Mark individual notification as read when clicked
  const handleNotificationClick = async (notificationId: string) => {
    try {
      // Mark as read when clicking on the notification
      await api.notifications.markAsRead(notificationId);

      // Refetch to update UI
      queryClient.invalidateQueries({ queryKey: ['notifications-preview'] });
      queryClient.invalidateQueries({ queryKey: ['notification-stats'] });

      // Close dropdown
      setOpen(false);
    } catch (error) {
      console.error('Failed to mark notification as read:', error);
      // Still close the dropdown and navigate
      setOpen(false);
    }
  };

  // Just toggle open state without marking as read
  const handleOpenChange = (newOpen: boolean) => {
    setOpen(newOpen);
  };

  return (
    <DropdownMenu open={open} onOpenChange={handleOpenChange}>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" size="icon" className="relative">
          <Bell className="h-5 w-5" />
          {unreadCount > 0 && (
            <span className="absolute -top-1 -right-1 h-5 w-5 bg-red-500 rounded-full text-[10px] text-white flex items-center justify-center font-semibold">
              {unreadCount > 9 ? '9+' : unreadCount}
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
                    onClick={() => handleNotificationClick(notification.id)}
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
                              {sanitizeNotificationText(notification.title)}
                            </p>
                          </div>
                          {isUnread && (
                            <div className="h-2 w-2 bg-blue-500 rounded-full flex-shrink-0 mt-1.5" />
                          )}
                        </div>

                        <p className="text-sm text-muted-foreground line-clamp-2 mb-2">
                          {sanitizeNotificationText(notification.message)}
                        </p>

                        <div className="flex items-center justify-between">
                          <span className="text-xs text-muted-foreground">
                            {formatLocalTime(notification.created_at)}
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
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
