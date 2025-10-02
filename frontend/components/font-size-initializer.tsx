'use client';

import { useEffect } from 'react';
import { useAuth } from '@/lib/auth';

export function FontSizeInitializer() {
  const { user, isAuthenticated } = useAuth();

  useEffect(() => {
    // This runs after React hydration to ensure font size is applied
    const applyFontSize = () => {
      try {
        let savedFontSize = 'medium'; // Default for non-authenticated users

        // Use user-specific font size if logged in
        if (isAuthenticated && user?.id) {
          savedFontSize = localStorage.getItem(`fontSize_${user.id}`) || 'medium';
          console.log(`Font size for user ${user.id}:`, savedFontSize);
        } else {
          console.log('Using default font size (not logged in)');
        }

        const currentSize = document.documentElement.getAttribute('data-font-size');

        // Only update if different to avoid unnecessary DOM updates
        if (currentSize !== savedFontSize) {
          document.documentElement.setAttribute('data-font-size', savedFontSize);
          console.log('Font size applied:', savedFontSize);
        }
      } catch (e) {
        console.error('Failed to apply font size:', e);
        document.documentElement.setAttribute('data-font-size', 'medium');
      }
    };

    // Apply font size when component mounts or user changes
    applyFontSize();

    // Also apply on storage events (when changed in another tab)
    const handleStorageChange = (e: StorageEvent) => {
      if (user?.id && e.key === `fontSize_${user.id}`) {
        applyFontSize();
      }
    };

    window.addEventListener('storage', handleStorageChange);

    return () => {
      window.removeEventListener('storage', handleStorageChange);
    };
  }, [user?.id, isAuthenticated]);

  return null;
}
