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
        console.log(`No saved theme for user ${user.id}, using light default`);
        setTheme('light');
      }
    } else {
      // Use light theme for non-authenticated users
      console.log('Not logged in, using light theme');
      setTheme('light');
    }
  }, [user?.id, isAuthenticated, setTheme]);

  return null;
}
