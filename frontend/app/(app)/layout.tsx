'use client';

import { Sidebar } from '@/components/layout/sidebar';
import { useAuth } from '@/lib/auth';
import { useRouter } from 'next/navigation';
import { useEffect } from 'react';
import { SidebarProvider, useSidebar } from '@/lib/sidebar-context';
import { cn } from '@/lib/utils';

function AppLayoutContent({ children }: { children: React.ReactNode }) {
  const { isCollapsed } = useSidebar();

  return (
    <div className="h-screen bg-background flex flex-col">
      <Sidebar />
      <div className={cn(
        "flex-1 pt-16 overflow-hidden transition-all duration-300",
        "lg:pl-64", // Default padding for expanded sidebar
        isCollapsed && "lg:pl-16" // Reduced padding for collapsed sidebar
      )}>
        <main className="h-full overflow-auto">
          {children}
        </main>
      </div>
    </div>
  );
}

export default function AppLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const { isAuthenticated, isLoading } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (!isLoading && !isAuthenticated) {
      router.push('/login');
    }
  }, [isAuthenticated, isLoading, router]);

  if (isLoading) {
    return (
      <div className="flex h-screen items-center justify-center">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
      </div>
    );
  }

  if (!isAuthenticated) {
    return null;
  }

  return (
    <SidebarProvider>
      <AppLayoutContent>{children}</AppLayoutContent>
    </SidebarProvider>
  );
}