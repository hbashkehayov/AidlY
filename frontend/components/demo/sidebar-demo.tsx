'use client';

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { useSidebar } from '@/lib/sidebar-context';
import { cn } from '@/lib/utils';

export function SidebarDemo() {
  const { isCollapsed, toggle } = useSidebar();

  return (
    <div className="p-6 space-y-6">
      <Card>
        <CardHeader>
          <CardTitle>Responsive Sidebar Demo</CardTitle>
          <CardDescription>
            Test the sidebar toggle functionality and see how the main content adapts.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex items-center gap-4">
            <Button onClick={toggle} variant="outline">
              {isCollapsed ? 'Expand' : 'Collapse'} Sidebar
            </Button>
            <div className="text-sm text-muted-foreground">
              Sidebar is currently <strong>{isCollapsed ? 'collapsed' : 'expanded'}</strong>
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {Array.from({ length: 6 }).map((_, i) => (
              <Card key={i} className="p-4">
                <div className="space-y-2">
                  <div className="h-4 bg-muted rounded w-3/4"></div>
                  <div className="h-3 bg-muted rounded w-1/2"></div>
                  <div className="h-3 bg-muted rounded w-2/3"></div>
                </div>
              </Card>
            ))}
          </div>
        </CardContent>
      </Card>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <Card>
          <CardHeader>
            <CardTitle>Responsive Behavior</CardTitle>
          </CardHeader>
          <CardContent>
            <ul className="space-y-2 text-sm">
              <li className="flex items-center gap-2">
                <span className="w-2 h-2 bg-green-500 rounded-full"></span>
                Desktop: Toggle between 256px (expanded) and 64px (collapsed)
              </li>
              <li className="flex items-center gap-2">
                <span className="w-2 h-2 bg-blue-500 rounded-full"></span>
                Mobile: Overlay sidebar that can be opened/closed
              </li>
              <li className="flex items-center gap-2">
                <span className="w-2 h-2 bg-purple-500 rounded-full"></span>
                Smooth transitions with proper main content adjustment
              </li>
              <li className="flex items-center gap-2">
                <span className="w-2 h-2 bg-orange-500 rounded-full"></span>
                Tooltips show in collapsed desktop mode
              </li>
            </ul>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Features Implemented</CardTitle>
          </CardHeader>
          <CardContent>
            <ul className="space-y-2 text-sm">
              <li className="flex items-center gap-2">
                <span className="w-2 h-2 bg-emerald-500 rounded-full"></span>
                Context-based state management
              </li>
              <li className="flex items-center gap-2">
                <span className="w-2 h-2 bg-cyan-500 rounded-full"></span>
                Mobile-first responsive design
              </li>
              <li className="flex items-center gap-2">
                <span className="w-2 h-2 bg-pink-500 rounded-full"></span>
                Automatic mobile menu close on navigation
              </li>
              <li className="flex items-center gap-2">
                <span className="w-2 h-2 bg-yellow-500 rounded-full"></span>
                Badge indicators for both collapsed and expanded states
              </li>
            </ul>
          </CardContent>
        </Card>
      </div>

      <Card className={cn(
        "transition-all duration-300 border-2",
        isCollapsed ? "border-green-200 bg-green-50" : "border-blue-200 bg-blue-50"
      )}>
        <CardContent className="p-6">
          <p className="text-center font-medium">
            This card changes color based on sidebar state to demonstrate reactivity!
            {isCollapsed ? " 🟢 Collapsed Mode" : " 🔵 Expanded Mode"}
          </p>
        </CardContent>
      </Card>
    </div>
  );
}