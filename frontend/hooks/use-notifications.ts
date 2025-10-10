'use client';

import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';

export interface NotificationCounts {
  open_tickets: number;
  unread_messages: number;
  pending_approvals: number;
  overdue_tickets: number;
}

export function useNotificationCounts() {
  return useQuery<NotificationCounts>({
    queryKey: ['notification-counts'],
    queryFn: async () => {
      const response = await api.notifications.counts();
      return response.data;
    },
    // Real-time updates for notifications
    refetchInterval: 3000, // Refetch every 3 seconds for near real-time notifications
    refetchOnWindowFocus: true, // Refetch when window regains focus
    refetchIntervalInBackground: true, // Continue polling in background for new notifications
    refetchOnMount: true, // Always refetch on mount
    refetchOnReconnect: true, // Refetch when connection is restored
    staleTime: 1000, // Consider stale after 1 second for faster updates
    gcTime: 5 * 60 * 1000, // Keep in cache for 5 minutes
  });
}