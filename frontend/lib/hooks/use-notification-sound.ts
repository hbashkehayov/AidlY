import { useEffect, useRef } from 'react';
import { useAuth } from '@/lib/auth';

/**
 * Hook to play notification sounds when new notifications arrive
 * Respects user's sound preferences from localStorage
 */
export function useNotificationSound(unreadCount: number) {
  const previousCountRef = useRef<number | null>(null);
  const { user } = useAuth();

  // Play sound when unread count increases
  useEffect(() => {
    // Skip on initial mount
    if (previousCountRef.current === null) {
      console.log('[NotificationSound] Initial mount, setting previous count to:', unreadCount);
      previousCountRef.current = unreadCount;
      return;
    }

    // Only proceed if user is logged in
    if (!user?.id) {
      console.log('[NotificationSound] No user logged in, skipping');
      return;
    }

    // Check if sound is enabled
    const soundEnabled = localStorage.getItem(`notifications_sound_${user.id}`) !== 'false';

    if (!soundEnabled) {
      console.log('[NotificationSound] Sound disabled in settings');
      previousCountRef.current = unreadCount;
      return;
    }

    // Check if count increased (new notifications arrived)
    const hasNewNotifications = unreadCount > previousCountRef.current;

    console.log('[NotificationSound] Count check:', {
      previous: previousCountRef.current,
      current: unreadCount,
      hasNew: hasNewNotifications,
      soundEnabled
    });

    if (hasNewNotifications) {
      // Get selected sound type
      const selectedSound = localStorage.getItem(`notification_sound_type_${user.id}`) || 'default';
      const soundFile = `/sounds/notification-${selectedSound}.mp3`;

      console.log('[NotificationSound] 🔊 Playing sound:', soundFile);

      // Create and play audio
      const audio = new Audio(soundFile);
      audio.volume = 0.5;

      audio.play()
        .then(() => {
          console.log('[NotificationSound] ✅ Sound played successfully');
        })
        .catch((error) => {
          console.error('[NotificationSound] ❌ Failed to play sound:', error);
        });
    }

    // Update the previous count
    previousCountRef.current = unreadCount;
  }, [unreadCount, user?.id]);

  return null;
}
