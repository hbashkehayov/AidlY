'use client';

import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';

export interface NotificationCounts {
  new_tickets: number;
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
    refetchInterval: 30000, // Refetch every 30 seconds
    staleTime: 1000, // Consider stale after 1 second for faster updates
    gcTime: 5 * 60 * 1000, // Keep in cache for 5 minutes
  });
}