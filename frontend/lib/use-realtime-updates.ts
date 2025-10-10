import { useEffect, useRef, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';

interface RealtimeOptions {
  onNewTicket?: (ticket: any) => void;
  onTicketUpdate?: (ticket: any) => void;
  onNewNotification?: (notification: any) => void;
  enableBrowserNotifications?: boolean;
  enableSound?: boolean;
}

export function useRealtimeUpdates(options: RealtimeOptions = {}) {
  const queryClient = useQueryClient();
  const [hasPermission, setHasPermission] = useState(false);
  const previousTicketCount = useRef<number | null>(null);
  const previousNotificationCount = useRef<number | null>(null);
  const audioRef = useRef<HTMLAudioElement | null>(null);

  // Request browser notification permission
  useEffect(() => {
    if (options.enableBrowserNotifications && 'Notification' in window) {
      if (Notification.permission === 'granted') {
        setHasPermission(true);
      } else if (Notification.permission !== 'denied') {
        Notification.requestPermission().then((permission) => {
          setHasPermission(permission === 'granted');
        });
      }
    }
  }, [options.enableBrowserNotifications]);

  // Initialize notification sound
  useEffect(() => {
    if (options.enableSound && typeof window !== 'undefined') {
      // Create a simple notification sound using Web Audio API
      audioRef.current = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSuBzvLZiTYIG2m98OScTgwOUKXh8LJnHgU2jdXvyHkpBSp+zPLaizsKGGS56+qmWhQJS5vi8bllHAU2jdXvyHkpBSp+zPLaizsKGGS56+qmWhQJS5vi8bllHAU=');
    }
  }, [options.enableSound]);

  // Monitor for new tickets
  const checkForNewTickets = () => {
    const ticketsData = queryClient.getQueryData(['tickets']) as any;
    if (ticketsData?.meta?.total && previousTicketCount.current !== null) {
      const newCount = ticketsData.meta.total;
      if (newCount > previousTicketCount.current) {
        const diff = newCount - previousTicketCount.current;

        // Show toast notification
        toast.success(`${diff} new ticket${diff > 1 ? 's' : ''} received!`, {
          duration: 5000,
        });

        // Play sound
        if (options.enableSound && audioRef.current) {
          audioRef.current.play().catch(() => {
            // Ignore autoplay errors
          });
        }

        // Show browser notification
        if (hasPermission && options.enableBrowserNotifications) {
          new Notification('New Ticket', {
            body: `${diff} new ticket${diff > 1 ? 's' : ''} received`,
            icon: '/favicon.ico',
            tag: 'new-ticket',
          });
        }

        if (options.onNewTicket && ticketsData.data?.[0]) {
          options.onNewTicket(ticketsData.data[0]);
        }
      }
    }
    previousTicketCount.current = ticketsData?.meta?.total || 0;
  };

  // Monitor for new notifications
  const checkForNewNotifications = () => {
    const notificationsData = queryClient.getQueryData(['notifications']) as any;
    if (notificationsData?.unread_count !== undefined && previousNotificationCount.current !== null) {
      const newCount = notificationsData.unread_count;
      if (newCount > previousNotificationCount.current) {
        const diff = newCount - previousNotificationCount.current;

        // Show toast notification
        toast.info(`${diff} new notification${diff > 1 ? 's' : ''}`, {
          duration: 4000,
        });

        // Play sound
        if (options.enableSound && audioRef.current) {
          audioRef.current.play().catch(() => {
            // Ignore autoplay errors
          });
        }

        if (options.onNewNotification) {
          options.onNewNotification(notificationsData);
        }
      }
    }
    previousNotificationCount.current = notificationsData?.unread_count || 0;
  };

  return {
    checkForNewTickets,
    checkForNewNotifications,
    hasPermission,
  };
}
