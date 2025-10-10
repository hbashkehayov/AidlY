/**
 * Available notification sound options
 */
export interface NotificationSound {
  id: string;
  name: string;
  description: string;
  file: string;
}

export const NOTIFICATION_SOUNDS: NotificationSound[] = [
  {
    id: 'ding',
    name: 'Ding',
    description: 'Simple ding sound',
    file: '/sounds/notification-ding.mp3',
  },
  {
    id: 'chime',
    name: 'Chime',
    description: 'Soft chime sound',
    file: '/sounds/notification-chime.mp3',
  },
  {
    id: 'ping',
    name: 'Meow',
    description: 'Quick ping notification',
    file: '/sounds/notification-ping.mp3',
  },
];

/**
 * Play a preview of a notification sound
 */
export function playNotificationPreview(soundId: string, volume: number = 0.5): void {
  const sound = NOTIFICATION_SOUNDS.find(s => s.id === soundId);
  if (sound) {
    const audio = new Audio(sound.file);
    audio.volume = volume;
    audio.play().catch(error => {
      console.warn('Failed to play preview sound:', error);
    });
  }
}

/**
 * Get the current user's selected notification sound
 */
export function getSelectedNotificationSound(userId: string): string {
  if (typeof window === 'undefined') return 'ding';
  return localStorage.getItem(`notification_sound_type_${userId}`) || 'ding';
}

/**
 * Save the user's notification sound preference
 */
export function saveNotificationSound(userId: string, soundId: string): void {
  if (typeof window !== 'undefined') {
    localStorage.setItem(`notification_sound_type_${userId}`, soundId);
  }
}
