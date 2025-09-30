'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { cn } from '@/lib/utils';
import {
  LayoutDashboard,
  Ticket,
  Users,
  Settings,
  HelpCircle,
  BarChart3,
  Menu,
  Search,
  Bell,
  User,
  LogOut,
  Handshake,
} from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Input } from '@/components/ui/input';
import { useAuth } from '@/lib/auth';
import { Badge } from '@/components/ui/badge';
import { useSidebar } from '@/lib/sidebar-context';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useNotificationCounts } from '@/hooks/use-notifications';

const getNavigation = (notificationCounts: any, userRole?: string) => [
  { name: 'Dashboard', href: '/dashboard', icon: LayoutDashboard },
  {
    name: 'Tickets',
    href: '/tickets',
    icon: Ticket,
    badge: notificationCounts?.new_tickets > 0 ? notificationCounts.new_tickets : undefined
  },
  { name: 'Customers', href: '/customers', icon: Users },
  {
    name: 'Team',
    href: '/team',
    icon: Handshake,
    badge: undefined
  },
  ...(userRole === 'admin' ? [{ name: 'Reports', href: '/reports', icon: BarChart3 }] : []),
];

const bottomNavigation = [
  { name: 'Settings', href: '/settings', icon: Settings },
];

export function Sidebar() {
  const pathname = usePathname();
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const { user, logout } = useAuth();
  const { isCollapsed, toggle } = useSidebar();
  const { data: notificationCounts } = useNotificationCounts();

  const navigation = getNavigation(notificationCounts, user?.role);

  return (
    <>
      {/* Top Navigation Bar */}
      <div className="fixed top-0 left-0 right-0 z-50 h-16 bg-background border-b">
        <div className="flex h-full items-center justify-between px-4">
          <div className="flex items-center space-x-4">
            {/* Desktop toggle button */}
            <Button
              onClick={toggle}
              variant="ghost"
              size="icon"
              className="hidden lg:flex p-2 hover:bg-accent rounded-md"
            >
              <Menu className="h-5 w-5" />
            </Button>

            {/* Mobile toggle button */}
            <Button
              onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
              variant="ghost"
              size="icon"
              className="lg:hidden p-2 hover:bg-accent rounded-md"
            >
              <Menu className="h-5 w-5" />
            </Button>

            <Link href="/dashboard" className="flex items-center space-x-2">
              <div className="w-8 h-8 bg-primary rounded-lg flex items-center justify-center">
                <span className="text-primary-foreground font-bold text-lg">A</span>
              </div>
              <span className="text-xl font-semibold hidden sm:block">AidlY</span>
            </Link>
          </div>

          {/* Search Bar */}
          <div className="flex-1 max-w-xl mx-4 hidden md:block">
            <div className="relative">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input
                type="search"
                placeholder="Search tickets, customers, or articles..."
                className="pl-10 pr-4"
              />
            </div>
          </div>

          {/* Right Navigation */}
          <div className="flex items-center space-x-4">
            <Button variant="ghost" size="icon" className="relative">
              <Bell className="h-5 w-5" />
              <span className="absolute -top-1 -right-1 h-4 w-4 bg-red-500 rounded-full text-[10px] text-white flex items-center justify-center">
                5
              </span>
            </Button>

            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="ghost" className="relative h-8 w-8 rounded-full">
                  <Avatar className="h-8 w-8">
                    <AvatarImage src={user?.avatar_url} alt={user?.name} />
                    <AvatarFallback>{user?.name?.charAt(0).toUpperCase()}</AvatarFallback>
                  </Avatar>
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent className="w-56" align="end" forceMount>
                <DropdownMenuLabel className="font-normal">
                  <div className="flex flex-col space-y-1">
                    <p className="text-sm font-medium leading-none">{user?.name}</p>
                    <p className="text-xs leading-none text-muted-foreground">
                      {user?.email}
                    </p>
                  </div>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                  <Link href="/profile">
                    <User className="mr-2 h-4 w-4" />
                    Profile
                  </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                  <Link href="/settings">
                    <Settings className="mr-2 h-4 w-4" />
                    Settings
                  </Link>
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem onClick={() => logout()}>
                  <LogOut className="mr-2 h-4 w-4" />
                  Log out
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </div>
      </div>

      {/* Mobile Overlay */}
      {mobileMenuOpen && (
        <div
          className="fixed inset-0 z-30 bg-black/50 lg:hidden"
          onClick={() => setMobileMenuOpen(false)}
        />
      )}

      {/* Sidebar */}
      <aside
        className={cn(
          "fixed left-0 top-16 bottom-0 z-40 bg-background border-r transition-all duration-300",
          // Mobile behavior
          "lg:translate-x-0",
          mobileMenuOpen ? "translate-x-0 w-64" : "-translate-x-full w-64",
          // Desktop behavior
          "lg:w-64",
          isCollapsed && "lg:w-16"
        )}
      >
        <nav className="flex flex-col h-full">
          <TooltipProvider>
            <div className="flex-1 space-y-1 p-2">
              {navigation.map((item) => {
                const isActive = pathname?.startsWith(item.href);
                const linkContent = (
                  <Link
                    key={item.name}
                    href={item.href}
                    onClick={() => setMobileMenuOpen(false)} // Close mobile menu on navigation
                    className={cn(
                      "relative flex items-center justify-between px-3 py-2 text-sm font-medium rounded-lg transition-all",
                      isActive
                        ? "bg-primary text-primary-foreground"
                        : "text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                    )}
                  >
                    <div className="flex items-center">
                      <item.icon className={cn("h-5 w-5", isCollapsed && "lg:mx-auto")} />
                      <span className={cn(
                        "ml-3",
                        isCollapsed && "lg:hidden"
                      )}>
                        {item.name}
                      </span>
                    </div>
                    {item.badge && (
                      <>
                        {/* Badge for expanded state */}
                        <Badge
                          variant={isActive ? "secondary" : "default"}
                          className={cn("ml-auto", isCollapsed && "lg:hidden")}
                        >
                          {item.badge}
                        </Badge>
                        {/* Dot indicator for collapsed state */}
                        {isCollapsed && (
                          <div className="hidden lg:block absolute top-1 right-1 h-2 w-2 bg-red-500 rounded-full"></div>
                        )}
                      </>
                    )}
                  </Link>
                );

                return isCollapsed ? (
                  <Tooltip key={item.name}>
                    <TooltipTrigger asChild className="hidden lg:block">
                      {linkContent}
                    </TooltipTrigger>
                    <TooltipContent side="right" className="hidden lg:block">
                      <p>{item.name}</p>
                      {item.badge && (
                        <Badge variant="outline" className="ml-2">
                          {item.badge}
                        </Badge>
                      )}
                    </TooltipContent>
                    <div className="lg:hidden">{linkContent}</div>
                  </Tooltip>
                ) : (
                  <div key={item.name}>{linkContent}</div>
                );
              })}
            </div>

            <div className="border-t p-2">
              {bottomNavigation.map((item) => {
                const isActive = pathname?.startsWith(item.href);
                const linkContent = (
                  <Link
                    key={item.name}
                    href={item.href}
                    onClick={() => setMobileMenuOpen(false)} // Close mobile menu on navigation
                    className={cn(
                      "flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-all",
                      isActive
                        ? "bg-primary text-primary-foreground"
                        : "text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                    )}
                  >
                    <item.icon className={cn("h-5 w-5", isCollapsed && "lg:mx-auto")} />
                    <span className={cn(
                      "ml-3",
                      isCollapsed && "lg:hidden"
                    )}>
                      {item.name}
                    </span>
                  </Link>
                );

                return isCollapsed ? (
                  <Tooltip key={item.name}>
                    <TooltipTrigger asChild className="hidden lg:block">
                      {linkContent}
                    </TooltipTrigger>
                    <TooltipContent side="right" className="hidden lg:block">
                      <p>{item.name}</p>
                    </TooltipContent>
                    <div className="lg:hidden">{linkContent}</div>
                  </Tooltip>
                ) : (
                  <div key={item.name}>{linkContent}</div>
                );
              })}
            </div>
          </TooltipProvider>
        </nav>
      </aside>
    </>
  );
}