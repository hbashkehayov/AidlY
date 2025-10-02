'use client';

import { useEffect } from 'react';
import { useTheme } from 'next-themes';
import { useAuth } from '@/lib/auth';

export function ThemeInitializer() {
  const { user, isAuthenticated } = useAuth();
  const { setTheme } = useTheme();

  useEffect(() => {
    // Apply user-specific theme when logged in
    if (isAuthenticated && user?.id) {
      const savedTheme = localStorage.getItem(`theme_${user.id}`);
      if (savedTheme) {
        console.log(`Loading theme for user ${user.id}:`, savedTheme);
        setTheme(savedTheme);
      } else {
        console.log(`No saved theme for user ${user.id}, using system default`);
        setTheme('system');
      }
    } else {
      // Use system theme for non-authenticated users
      console.log('Not logged in, using system theme');
      setTheme('system');
    }
  }, [user?.id, isAuthenticated, setTheme]);

  return null;
}
