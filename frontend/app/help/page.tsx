'use client';

import React from 'react';
import Link from 'next/link';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Button } from '@/components/ui/button';
import {
  Ticket,
  LayoutDashboard,
  Users,
  Bell,
  Settings,
  Sparkles,
  CheckCircle,
  Clock,
  AlertTriangle,
  MessageSquare,
  Search,
  Filter,
  Star,
  TrendingUp,
  Handshake,
  Mail,
  UserCheck,
  FileText,
  Lightbulb,
  Zap,
  Target,
  Shield,
  Activity,
  HelpCircle,
  ArrowLeft,
  Headphones,
} from 'lucide-react';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useAuth } from '@/lib/auth';

const FeatureCard = ({ icon: Icon, title, description, tip }: any) => (
  <Card className="hover:shadow-lg transition-shadow">
    <CardHeader>
      <div className="flex items-start gap-4">
        <div className="p-3 bg-primary/10 rounded-lg">
          <Icon className="h-6 w-6 text-primary" />
        </div>
        <div className="flex-1">
          <CardTitle className="text-lg mb-2">{title}</CardTitle>
          <CardDescription className="text-sm">{description}</CardDescription>
        </div>
      </div>
    </CardHeader>
    {tip && (
      <CardContent>
        <div className="flex items-start gap-2 p-3 bg-amber-50 dark:bg-amber-950/20 rounded-lg border border-amber-200 dark:border-amber-800">
          <Lightbulb className="h-4 w-4 text-amber-600 dark:text-amber-400 mt-0.5 flex-shrink-0" />
          <p className="text-sm text-amber-900 dark:text-amber-100">
            <span className="font-semibold">Tip:</span> {tip}
          </p>
        </div>
      </CardContent>
    )}
  </Card>
);

const QuickTip = ({ children }: { children: React.ReactNode }) => (
  <div className="flex items-start gap-3 p-4 bg-blue-50 dark:bg-blue-950/20 rounded-lg border border-blue-200 dark:border-blue-800">
    <Zap className="h-5 w-5 text-blue-600 dark:text-blue-400 mt-0.5 flex-shrink-0" />
    <div className="text-sm text-blue-900 dark:text-blue-100">{children}</div>
  </div>
);

const StatusBadgeExample = ({ label, color }: any) => (
  <div className="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border bg-card">
    <span className={`h-2 w-2 rounded-full ${color}`} />
    <span className="text-sm font-medium">{label}</span>
  </div>
);

const PriorityBadgeExample = ({ label, color }: any) => (
  <div className="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border bg-card">
    <span className={`h-2 w-2 rounded-full ${color}`} />
    <span className="text-sm font-medium">{label}</span>
  </div>
);

export default function HelpPage() {
  const { user } = useAuth();
  const isAdmin = user?.role === 'admin';
  const isAgent = user?.role === 'agent';

  return (
    <div className="min-h-screen bg-background">
      {/* Simple Navigation Header */}
      <div className="sticky top-0 z-50 w-full border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
        <div className="container flex h-16 items-center justify-between px-4 md:px-8">
          <div className="flex items-center gap-4">
            <Link href={user?.role === 'agent' ? '/dashboard/agent' : '/dashboard'}>
              <Button variant="ghost" size="sm" className="gap-2">
                <ArrowLeft className="h-4 w-4" />
                Back to Dashboard
              </Button>
            </Link>
            <Separator orientation="vertical" className="h-6" />
            <div className="flex items-center gap-2">
              <div className="w-8 h-8 bg-primary rounded-lg flex items-center justify-center">
                <Headphones className="h-5 w-5 text-primary-foreground" />
              </div>
              <span className="text-lg font-semibold hidden sm:inline">AidlY Help Center</span>
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="container mx-auto px-4 md:px-8 py-8 space-y-6">
        {/* Header */}
        <div className="space-y-2">
          <h1 className="text-4xl font-bold tracking-tight flex items-center gap-3">
            <div className="p-2 bg-primary/10 rounded-xl">
              <Lightbulb className="h-8 w-8 text-primary" />
            </div>
            Help Center
          </h1>
          <p className="text-lg text-muted-foreground">
            Everything you need to know about using AidlY - Your Customer Support Platform
          </p>
        </div>

      <Tabs defaultValue="getting-started" className="w-full">
        <TabsList className="grid w-full grid-cols-2 lg:grid-cols-5 mb-6">
          <TabsTrigger value="getting-started">Getting Started</TabsTrigger>
          <TabsTrigger value="tickets">Tickets</TabsTrigger>
          <TabsTrigger value="dashboard">Dashboard</TabsTrigger>
          <TabsTrigger value="features">Features</TabsTrigger>
          <TabsTrigger value="tips">Tips & Tricks</TabsTrigger>
        </TabsList>

        {/* Getting Started Tab */}
        <TabsContent value="getting-started" className="space-y-6">
          <Card className="border-primary/20 bg-gradient-to-br from-primary/5 to-transparent">
            <CardHeader>
              <CardTitle className="text-2xl">Welcome to AidlY!</CardTitle>
              <CardDescription className="text-base">
                AidlY is a comprehensive customer support platform designed to help teams deliver exceptional support experiences.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid gap-4 md:grid-cols-2">
                <div className="flex items-start gap-3">
                  <CheckCircle className="h-5 w-5 text-green-600 mt-1" />
                  <div>
                    <h4 className="font-semibold mb-1">Smart Ticketing</h4>
                    <p className="text-sm text-muted-foreground">
                      Manage all customer inquiries in one place with intelligent ticket management
                    </p>
                  </div>
                </div>
                <div className="flex items-start gap-3">
                  <CheckCircle className="h-5 w-5 text-green-600 mt-1" />
                  <div>
                    <h4 className="font-semibold mb-1">Real-time Notifications</h4>
                    <p className="text-sm text-muted-foreground">
                      Stay updated with browser notifications for new tickets and messages
                    </p>
                  </div>
                </div>
                <div className="flex items-start gap-3">
                  <CheckCircle className="h-5 w-5 text-green-600 mt-1" />
                  <div>
                    <h4 className="font-semibold mb-1">Team Collaboration</h4>
                    <p className="text-sm text-muted-foreground">
                      Work together with internal notes visible only to your team
                    </p>
                  </div>
                </div>
                <div className="flex items-start gap-3">
                  <CheckCircle className="h-5 w-5 text-green-600 mt-1" />
                  <div>
                    <h4 className="font-semibold mb-1">Analytics & Reports</h4>
                    <p className="text-sm text-muted-foreground">
                      Track performance with detailed insights and metrics
                    </p>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          <div className="grid gap-6 md:grid-cols-2">
            <FeatureCard
              icon={LayoutDashboard}
              title="Your Dashboard"
              description="Get a bird's-eye view of your support operations. Monitor open tickets, response times, and team performance in real-time."
              tip="Agents have a dedicated dashboard showing only their assigned tickets and personal metrics."
            />
            <FeatureCard
              icon={Ticket}
              title="Ticket Management"
              description="View, filter, and manage all customer tickets. Use tabs to switch between your assigned tickets, available tickets, and closed tickets."
              tip="Click on any ticket to view full details and respond to customer inquiries."
            />
            <FeatureCard
              icon={Users}
              title="Customer Profiles"
              description="Access complete customer information including contact details, ticket history, and custom notes."
              tip="Add notes to customer profiles to keep important information accessible to your team."
            />
            <FeatureCard
              icon={Handshake}
              title="Team Management"
              description="View team members, assign tickets, and track individual performance metrics."
              tip={isAdmin ? "Admins can create new team members and manage user roles." : "View your teammates and see who's working on what."}
            />
          </div>

          <QuickTip>
            <strong>Quick Start:</strong> Start by exploring your dashboard, then check the Tickets page to see all active support requests. Click on any ticket to view details and respond to customers.
          </QuickTip>
        </TabsContent>

        {/* Tickets Tab */}
        <TabsContent value="tickets" className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle className="text-2xl flex items-center gap-2">
                <Ticket className="h-6 w-6" />
                Understanding Tickets
              </CardTitle>
              <CardDescription>
                Tickets are the core of your support workflow. Here's everything you need to know.
              </CardDescription>
            </CardHeader>
          </Card>

          <div className="grid gap-6">
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Activity className="h-5 w-5" />
                  Ticket Status
                </CardTitle>
                <CardDescription>Each ticket has a status that indicates its current state</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="grid gap-3">
                  <div className="flex items-center justify-between p-3 rounded-lg border">
                    <div className="flex items-center gap-3">
                      <StatusBadgeExample label="Open" color="bg-blue-500" />
                      <span className="text-sm text-muted-foreground">New tickets waiting to be addressed</span>
                    </div>
                  </div>
                  <div className="flex items-center justify-between p-3 rounded-lg border">
                    <div className="flex items-center gap-3">
                      <StatusBadgeExample label="Pending" color="bg-yellow-500" />
                      <span className="text-sm text-muted-foreground">Waiting for customer response or additional info</span>
                    </div>
                  </div>
                  <div className="flex items-center justify-between p-3 rounded-lg border">
                    <div className="flex items-center gap-3">
                      <StatusBadgeExample label="Resolved" color="bg-green-500" />
                      <span className="text-sm text-muted-foreground">Issue has been solved</span>
                    </div>
                  </div>
                  <div className="flex items-center justify-between p-3 rounded-lg border">
                    <div className="flex items-center gap-3">
                      <StatusBadgeExample label="Closed" color="bg-gray-500" />
                      <span className="text-sm text-muted-foreground">Ticket is complete and archived</span>
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <AlertTriangle className="h-5 w-5" />
                  Priority Levels
                </CardTitle>
                <CardDescription>Prioritize tickets based on urgency and impact</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="grid gap-3">
                  <div className="flex items-center justify-between p-3 rounded-lg border">
                    <div className="flex items-center gap-3">
                      <PriorityBadgeExample label="Urgent" color="bg-red-500" />
                      <span className="text-sm text-muted-foreground">Critical issues requiring immediate attention</span>
                    </div>
                  </div>
                  <div className="flex items-center justify-between p-3 rounded-lg border">
                    <div className="flex items-center gap-3">
                      <PriorityBadgeExample label="High" color="bg-orange-500" />
                      <span className="text-sm text-muted-foreground">Important issues that need quick resolution</span>
                    </div>
                  </div>
                  <div className="flex items-center justify-between p-3 rounded-lg border">
                    <div className="flex items-center gap-3">
                      <PriorityBadgeExample label="Medium" color="bg-yellow-500" />
                      <span className="text-sm text-muted-foreground">Standard priority tickets</span>
                    </div>
                  </div>
                  <div className="flex items-center justify-between p-3 rounded-lg border">
                    <div className="flex items-center gap-3">
                      <PriorityBadgeExample label="Low" color="bg-blue-500" />
                      <span className="text-sm text-muted-foreground">Non-urgent inquiries and requests</span>
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Filter className="h-5 w-5" />
                  Ticket Tabs & Filters
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="space-y-3">
                  {isAdmin && (
                    <div className="p-4 rounded-lg border">
                      <h4 className="font-semibold mb-2 flex items-center gap-2">
                        <Badge>All Tickets</Badge>
                      </h4>
                      <p className="text-sm text-muted-foreground">
                        View all tickets in the system (Admin only)
                      </p>
                    </div>
                  )}
                  <div className="p-4 rounded-lg border">
                    <h4 className="font-semibold mb-2 flex items-center gap-2">
                      <Badge>My Tickets</Badge>
                    </h4>
                    <p className="text-sm text-muted-foreground">
                      Tickets assigned specifically to you
                    </p>
                  </div>
                  <div className="p-4 rounded-lg border">
                    <h4 className="font-semibold mb-2 flex items-center gap-2">
                      <Badge>Available</Badge>
                    </h4>
                    <p className="text-sm text-muted-foreground">
                      Unassigned tickets that you can claim
                    </p>
                  </div>
                  <div className="p-4 rounded-lg border">
                    <h4 className="font-semibold mb-2 flex items-center gap-2">
                      <Badge>Closed</Badge>
                    </h4>
                    <p className="text-sm text-muted-foreground">
                      Resolved and closed tickets for reference
                    </p>
                  </div>
                  {isAdmin && (
                    <div className="p-4 rounded-lg border">
                      <h4 className="font-semibold mb-2 flex items-center gap-2">
                        <Badge>Archived</Badge>
                      </h4>
                      <p className="text-sm text-muted-foreground">
                        Archived tickets (Admin only - can be restored)
                      </p>
                    </div>
                  )}
                </div>

                <Separator />

                <div className="space-y-3">
                  <h4 className="font-semibold">Available Filters</h4>
                  <div className="grid gap-2 md:grid-cols-2">
                    <div className="flex items-start gap-2 p-3 rounded-lg bg-muted">
                      <Search className="h-4 w-4 mt-1" />
                      <div>
                        <p className="font-medium text-sm">Search</p>
                        <p className="text-xs text-muted-foreground">Find tickets by number, subject, or customer</p>
                      </div>
                    </div>
                    <div className="flex items-start gap-2 p-3 rounded-lg bg-muted">
                      <Filter className="h-4 w-4 mt-1" />
                      <div>
                        <p className="font-medium text-sm">Status Filter</p>
                        <p className="text-xs text-muted-foreground">Filter by Open, Pending, Resolved, etc.</p>
                      </div>
                    </div>
                    <div className="flex items-start gap-2 p-3 rounded-lg bg-muted">
                      <AlertTriangle className="h-4 w-4 mt-1" />
                      <div>
                        <p className="font-medium text-sm">Priority Filter</p>
                        <p className="text-xs text-muted-foreground">Filter by Urgent, High, Medium, or Low</p>
                      </div>
                    </div>
                    <div className="flex items-start gap-2 p-3 rounded-lg bg-muted">
                      <Clock className="h-4 w-4 mt-1" />
                      <div>
                        <p className="font-medium text-sm">Sort Options</p>
                        <p className="text-xs text-muted-foreground">Sort by date, priority, or status</p>
                      </div>
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <MessageSquare className="h-5 w-5" />
                  Responding to Tickets
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="space-y-3">
                  <div className="p-4 rounded-lg border">
                    <h4 className="font-semibold mb-2">Public Comments</h4>
                    <p className="text-sm text-muted-foreground mb-3">
                      Regular comments are visible to the customer and sent via email. Use these for customer-facing communication.
                    </p>
                    <div className="flex items-center gap-2">
                      <div className="h-5 min-w-5 px-1.5 bg-blue-500 text-white text-xs font-semibold rounded-full flex items-center justify-center">
                        3
                      </div>
                      <span className="text-xs text-muted-foreground">Blue badges show unread public comments</span>
                    </div>
                  </div>
                  <div className="p-4 rounded-lg border">
                    <h4 className="font-semibold mb-2">Internal Notes</h4>
                    <p className="text-sm text-muted-foreground mb-3">
                      Internal notes are only visible to your team. Use these for collaboration, notes, and internal discussion.
                    </p>
                    <div className="flex items-center gap-2">
                      <div className="h-5 min-w-5 px-1.5 bg-yellow-500 text-white text-xs font-semibold rounded-full flex items-center justify-center">
                        2
                      </div>
                      <span className="text-xs text-muted-foreground">Yellow badges show unread internal notes</span>
                    </div>
                  </div>
                  <div className="p-4 rounded-lg border">
                    <h4 className="font-semibold mb-2">Attachments</h4>
                    <p className="text-sm text-muted-foreground">
                      You can attach files to your responses. Supported formats include images, PDFs, and documents.
                    </p>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>

          <QuickTip>
            <strong>Pro Tip:</strong> Use the search bar to quickly find specific tickets. You can search by ticket number, customer name, or keywords from the ticket subject. Combine with filters for even more precise results!
          </QuickTip>
        </TabsContent>

        {/* Dashboard Tab */}
        <TabsContent value="dashboard" className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle className="text-2xl flex items-center gap-2">
                <LayoutDashboard className="h-6 w-6" />
                Dashboard Overview
              </CardTitle>
              <CardDescription>
                {isAgent
                  ? "Your personal agent dashboard shows your assigned tickets and performance metrics."
                  : "Monitor your support team's performance and key metrics at a glance."
                }
              </CardDescription>
            </CardHeader>
          </Card>

          <div className="grid gap-6 md:grid-cols-2">
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <TrendingUp className="h-5 w-5" />
                  Key Metrics
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                <div className="p-3 rounded-lg border">
                  <div className="flex items-center gap-2 mb-1">
                    <Ticket className="h-4 w-4 text-primary" />
                    <span className="font-semibold">Open Tickets</span>
                  </div>
                  <p className="text-sm text-muted-foreground">
                    Total number of tickets currently open and awaiting response
                  </p>
                </div>
                <div className="p-3 rounded-lg border">
                  <div className="flex items-center gap-2 mb-1">
                    <Clock className="h-4 w-4 text-primary" />
                    <span className="font-semibold">Avg. Response Time</span>
                  </div>
                  <p className="text-sm text-muted-foreground">
                    Average time taken to respond to customer inquiries
                  </p>
                </div>
                <div className="p-3 rounded-lg border">
                  <div className="flex items-center gap-2 mb-1">
                    <AlertTriangle className="h-4 w-4 text-primary" />
                    <span className="font-semibold">Pending Tickets</span>
                  </div>
                  <p className="text-sm text-muted-foreground">
                    Tickets waiting for customer response or additional information
                  </p>
                </div>
                <div className="p-3 rounded-lg border">
                  <div className="flex items-center gap-2 mb-1">
                    <CheckCircle className="h-4 w-4 text-primary" />
                    <span className="font-semibold">Resolved Tickets</span>
                  </div>
                  <p className="text-sm text-muted-foreground">
                    Successfully resolved tickets during the current period
                  </p>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Activity className="h-5 w-5" />
                  Charts & Visualizations
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                <div className="p-3 rounded-lg border">
                  <span className="font-semibold block mb-1">Ticket Overview Chart</span>
                  <p className="text-sm text-muted-foreground">
                    Bar chart showing daily ticket volume, open tickets, and resolved tickets over the past week
                  </p>
                </div>
                <div className="p-3 rounded-lg border">
                  <span className="font-semibold block mb-1">Priority Distribution</span>
                  <p className="text-sm text-muted-foreground">
                    Pie chart displaying the breakdown of tickets by priority level (Urgent, High, Medium, Low)
                  </p>
                </div>
                <div className="p-3 rounded-lg border">
                  <span className="font-semibold block mb-1">Recent Tickets</span>
                  <p className="text-sm text-muted-foreground">
                    Quick access to the 5 most recent tickets requiring attention
                  </p>
                </div>
              </CardContent>
            </Card>
          </div>

          {isAgent && (
            <Card className="border-primary/20 bg-gradient-to-br from-primary/5 to-transparent">
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Target className="h-5 w-5" />
                  Agent Dashboard Features
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                <div className="grid gap-3 md:grid-cols-2">
                  <div className="p-3 rounded-lg border bg-background">
                    <span className="font-semibold block mb-1">Your Queue</span>
                    <p className="text-sm text-muted-foreground">
                      See all tickets assigned to you in one place
                    </p>
                  </div>
                  <div className="p-3 rounded-lg border bg-background">
                    <span className="font-semibold block mb-1">Personal Stats</span>
                    <p className="text-sm text-muted-foreground">
                      Track your individual performance metrics
                    </p>
                  </div>
                  <div className="p-3 rounded-lg border bg-background">
                    <span className="font-semibold block mb-1">Recent Activity</span>
                    <p className="text-sm text-muted-foreground">
                      View your recent ticket responses and actions
                    </p>
                  </div>
                  <div className="p-3 rounded-lg border bg-background">
                    <span className="font-semibold block mb-1">Productivity Metrics</span>
                    <p className="text-sm text-muted-foreground">
                      Monitor your resolution rate and response times
                    </p>
                  </div>
                </div>
              </CardContent>
            </Card>
          )}

          <QuickTip>
            <strong>Dashboard Tip:</strong> The dashboard updates automatically every few seconds to show real-time data. Check the trend arrows next to metrics to see if performance is improving or needs attention.
          </QuickTip>
        </TabsContent>

        {/* Features Tab */}
        <TabsContent value="features" className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle className="text-2xl flex items-center gap-2">
                <Sparkles className="h-6 w-6" />
                Platform Features
              </CardTitle>
              <CardDescription>
                Explore all the powerful features AidlY offers to enhance your support workflow
              </CardDescription>
            </CardHeader>
          </Card>

          <div className="grid gap-6 md:grid-cols-2">
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Bell className="h-5 w-5" />
                  Real-time Notifications
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                <p className="text-sm text-muted-foreground">
                  Stay on top of important updates with AidlY's notification system:
                </p>
                <div className="space-y-2">
                  <div className="flex items-start gap-2">
                    <CheckCircle className="h-4 w-4 text-green-600 mt-0.5" />
                    <span className="text-sm">Browser notifications for new tickets and messages</span>
                  </div>
                  <div className="flex items-start gap-2">
                    <CheckCircle className="h-4 w-4 text-green-600 mt-0.5" />
                    <span className="text-sm">Sound alerts when enabled in settings</span>
                  </div>
                  <div className="flex items-start gap-2">
                    <CheckCircle className="h-4 w-4 text-green-600 mt-0.5" />
                    <span className="text-sm">Badge indicators showing unread counts</span>
                  </div>
                  <div className="flex items-start gap-2">
                    <CheckCircle className="h-4 w-4 text-green-600 mt-0.5" />
                    <span className="text-sm">Notification center with history</span>
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Users className="h-5 w-5" />
                  Customer Management
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                <p className="text-sm text-muted-foreground">
                  Comprehensive customer profiles with:
                </p>
                <div className="space-y-2">
                  <div className="flex items-start gap-2">
                    <CheckCircle className="h-4 w-4 text-green-600 mt-0.5" />
                    <span className="text-sm">Complete contact information and history</span>
                  </div>
                  <div className="flex items-start gap-2">
                    <CheckCircle className="h-4 w-4 text-green-600 mt-0.5" />
                    <span className="text-sm">All tickets from the customer in one view</span>
                  </div>
                  <div className="flex items-start gap-2">
                    <CheckCircle className="h-4 w-4 text-green-600 mt-0.5" />
                    <span className="text-sm">Custom notes visible to your team</span>
                  </div>
                  <div className="flex items-start gap-2">
                    <CheckCircle className="h-4 w-4 text-green-600 mt-0.5" />
                    <span className="text-sm">Customer merge capability to prevent duplicates</span>
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Mail className="h-5 w-5" />
                  Email Integration
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                <p className="text-sm text-muted-foreground">
                  Seamless email integration allows you to:
                </p>
                <div className="space-y-2">
                  <div className="flex items-start gap-2">
                    <CheckCircle className="h-4 w-4 text-green-600 mt-0.5" />
                    <span className="text-sm">Convert incoming emails to tickets automatically</span>
                  </div>
                  <div className="flex items-start gap-2">
                    <CheckCircle className="h-4 w-4 text-green-600 mt-0.5" />
                    <span className="text-sm">Reply to customers directly from the platform</span>
                  </div>
                  <div className="flex items-start gap-2">
                    <CheckCircle className="h-4 w-4 text-green-600 mt-0.5" />
                    <span className="text-sm">Use email templates for common responses</span>
                  </div>
                  <div className="flex items-start gap-2">
                    <CheckCircle className="h-4 w-4 text-green-600 mt-0.5" />
                    <span className="text-sm">Track email delivery and read status</span>
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Handshake className="h-5 w-5" />
                  Team Collaboration
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                <p className="text-sm text-muted-foreground">
                  Work better together with:
                </p>
                <div className="space-y-2">
                  <div className="flex items-start gap-2">
                    <CheckCircle className="h-4 w-4 text-green-600 mt-0.5" />
                    <span className="text-sm">Internal notes for team communication</span>
                  </div>
                  <div className="flex items-start gap-2">
                    <CheckCircle className="h-4 w-4 text-green-600 mt-0.5" />
                    <span className="text-sm">Ticket assignment and claiming</span>
                  </div>
                  <div className="flex items-start gap-2">
                    <CheckCircle className="h-4 w-4 text-green-600 mt-0.5" />
                    <span className="text-sm">Team member visibility and status</span>
                  </div>
                  <div className="flex items-start gap-2">
                    <CheckCircle className="h-4 w-4 text-green-600 mt-0.5" />
                    <span className="text-sm">Shared customer notes and history</span>
                  </div>
                </div>
              </CardContent>
            </Card>

            {isAdmin && (
              <>
                <Card>
                  <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                      <FileText className="h-5 w-5" />
                      Reports & Analytics
                    </CardTitle>
                  </CardHeader>
                  <CardContent className="space-y-3">
                    <p className="text-sm text-muted-foreground">
                      Advanced reporting capabilities:
                    </p>
                    <div className="space-y-2">
                      <div className="flex items-start gap-2">
                        <CheckCircle className="h-4 w-4 text-green-600 mt-0.5" />
                        <span className="text-sm">Team performance metrics and trends</span>
                      </div>
                      <div className="flex items-start gap-2">
                        <CheckCircle className="h-4 w-4 text-green-600 mt-0.5" />
                        <span className="text-sm">SLA compliance tracking</span>
                      </div>
                      <div className="flex items-start gap-2">
                        <CheckCircle className="h-4 w-4 text-green-600 mt-0.5" />
                        <span className="text-sm">Customer satisfaction insights</span>
                      </div>
                      <div className="flex items-start gap-2">
                        <CheckCircle className="h-4 w-4 text-green-600 mt-0.5" />
                        <span className="text-sm">Export data to Excel or PDF</span>
                      </div>
                    </div>
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                      <Shield className="h-5 w-5" />
                      Admin Features
                    </CardTitle>
                  </CardHeader>
                  <CardContent className="space-y-3">
                    <p className="text-sm text-muted-foreground">
                      As an admin, you have access to:
                    </p>
                    <div className="space-y-2">
                      <div className="flex items-start gap-2">
                        <CheckCircle className="h-4 w-4 text-green-600 mt-0.5" />
                        <span className="text-sm">User management and role assignment</span>
                      </div>
                      <div className="flex items-start gap-2">
                        <CheckCircle className="h-4 w-4 text-green-600 mt-0.5" />
                        <span className="text-sm">Ticket archiving and restoration</span>
                      </div>
                      <div className="flex items-start gap-2">
                        <CheckCircle className="h-4 w-4 text-green-600 mt-0.5" />
                        <span className="text-sm">System-wide settings and configuration</span>
                      </div>
                      <div className="flex items-start gap-2">
                        <CheckCircle className="h-4 w-4 text-green-600 mt-0.5" />
                        <span className="text-sm">View all tickets across the organization</span>
                      </div>
                    </div>
                  </CardContent>
                </Card>
              </>
            )}

            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Settings className="h-5 w-5" />
                  Customization
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                <p className="text-sm text-muted-foreground">
                  Personalize your experience:
                </p>
                <div className="space-y-2">
                  <div className="flex items-start gap-2">
                    <CheckCircle className="h-4 w-4 text-green-600 mt-0.5" />
                    <span className="text-sm">Dark/Light theme toggle</span>
                  </div>
                  <div className="flex items-start gap-2">
                    <CheckCircle className="h-4 w-4 text-green-600 mt-0.5" />
                    <span className="text-sm">Adjustable font sizes for accessibility</span>
                  </div>
                  <div className="flex items-start gap-2">
                    <CheckCircle className="h-4 w-4 text-green-600 mt-0.5" />
                    <span className="text-sm">Notification sound preferences</span>
                  </div>
                  <div className="flex items-start gap-2">
                    <CheckCircle className="h-4 w-4 text-green-600 mt-0.5" />
                    <span className="text-sm">Profile customization</span>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>
        </TabsContent>

        {/* Tips & Tricks Tab */}
        <TabsContent value="tips" className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle className="text-2xl flex items-center gap-2">
                <Star className="h-6 w-6" />
                Tips & Best Practices
              </CardTitle>
              <CardDescription>
                Pro tips to help you become more efficient and provide better support
              </CardDescription>
            </CardHeader>
          </Card>

          <div className="grid gap-6">
            <Card className="border-amber-200 bg-amber-50/50 dark:bg-amber-950/20 dark:border-amber-800">
              <CardHeader>
                <CardTitle className="flex items-center gap-2 text-amber-900 dark:text-amber-100">
                  <Zap className="h-5 w-5" />
                  Productivity Tips
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="space-y-3">
                  <div className="flex items-start gap-3 p-3 bg-background rounded-lg border">
                    <div className="p-2 bg-amber-100 dark:bg-amber-900 rounded-lg">
                      <span className="font-bold text-amber-900 dark:text-amber-100">1</span>
                    </div>
                    <div>
                      <h4 className="font-semibold mb-1">Use Keyboard Shortcuts</h4>
                      <p className="text-sm text-muted-foreground">
                        Navigate faster by learning keyboard shortcuts. Press <kbd className="px-2 py-1 bg-muted rounded">Ctrl+K</kbd> to open command palette (coming soon).
                      </p>
                    </div>
                  </div>

                  <div className="flex items-start gap-3 p-3 bg-background rounded-lg border">
                    <div className="p-2 bg-amber-100 dark:bg-amber-900 rounded-lg">
                      <span className="font-bold text-amber-900 dark:text-amber-100">2</span>
                    </div>
                    <div>
                      <h4 className="font-semibold mb-1">Claim Available Tickets</h4>
                      <p className="text-sm text-muted-foreground">
                        Check the "Available" tab regularly for unassigned tickets you can claim and resolve.
                      </p>
                    </div>
                  </div>

                  <div className="flex items-start gap-3 p-3 bg-background rounded-lg border">
                    <div className="p-2 bg-amber-100 dark:bg-amber-900 rounded-lg">
                      <span className="font-bold text-amber-900 dark:text-amber-100">3</span>
                    </div>
                    <div>
                      <h4 className="font-semibold mb-1">Use Internal Notes</h4>
                      <p className="text-sm text-muted-foreground">
                        Document important information using internal notes. They're perfect for tracking customer preferences or technical details.
                      </p>
                    </div>
                  </div>

                  <div className="flex items-start gap-3 p-3 bg-background rounded-lg border">
                    <div className="p-2 bg-amber-100 dark:bg-amber-900 rounded-lg">
                      <span className="font-bold text-amber-900 dark:text-amber-100">4</span>
                    </div>
                    <div>
                      <h4 className="font-semibold mb-1">Filter and Sort Wisely</h4>
                      <p className="text-sm text-muted-foreground">
                        Combine filters to focus on high-priority tickets. Try sorting by "Default" to see unread tickets first.
                      </p>
                    </div>
                  </div>

                  <div className="flex items-start gap-3 p-3 bg-background rounded-lg border">
                    <div className="p-2 bg-amber-100 dark:bg-amber-900 rounded-lg">
                      <span className="font-bold text-amber-900 dark:text-amber-100">5</span>
                    </div>
                    <div>
                      <h4 className="font-semibold mb-1">Check Your Dashboard Daily</h4>
                      <p className="text-sm text-muted-foreground">
                        Start each day by reviewing your dashboard to understand your workload and prioritize accordingly.
                      </p>
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card className="border-blue-200 bg-blue-50/50 dark:bg-blue-950/20 dark:border-blue-800">
              <CardHeader>
                <CardTitle className="flex items-center gap-2 text-blue-900 dark:text-blue-100">
                  <UserCheck className="h-5 w-5" />
                  Customer Service Best Practices
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="space-y-3">
                  <div className="flex items-start gap-3 p-3 bg-background rounded-lg border">
                    <CheckCircle className="h-5 w-5 text-green-600 mt-1 flex-shrink-0" />
                    <div>
                      <h4 className="font-semibold mb-1">Respond Promptly</h4>
                      <p className="text-sm text-muted-foreground">
                        Aim to respond to tickets within your SLA timeframes. Even a quick acknowledgment helps build trust.
                      </p>
                    </div>
                  </div>

                  <div className="flex items-start gap-3 p-3 bg-background rounded-lg border">
                    <CheckCircle className="h-5 w-5 text-green-600 mt-1 flex-shrink-0" />
                    <div>
                      <h4 className="font-semibold mb-1">Be Clear and Concise</h4>
                      <p className="text-sm text-muted-foreground">
                        Write responses that are easy to understand. Avoid jargon and provide step-by-step instructions when needed.
                      </p>
                    </div>
                  </div>

                  <div className="flex items-start gap-3 p-3 bg-background rounded-lg border">
                    <CheckCircle className="h-5 w-5 text-green-600 mt-1 flex-shrink-0" />
                    <div>
                      <h4 className="font-semibold mb-1">Review Customer History</h4>
                      <p className="text-sm text-muted-foreground">
                        Check the customer's profile and previous tickets to understand context before responding.
                      </p>
                    </div>
                  </div>

                  <div className="flex items-start gap-3 p-3 bg-background rounded-lg border">
                    <CheckCircle className="h-5 w-5 text-green-600 mt-1 flex-shrink-0" />
                    <div>
                      <h4 className="font-semibold mb-1">Update Ticket Status</h4>
                      <p className="text-sm text-muted-foreground">
                        Keep ticket statuses current. Move to "Pending" when waiting for customer, "Resolved" when fixed.
                      </p>
                    </div>
                  </div>

                  <div className="flex items-start gap-3 p-3 bg-background rounded-lg border">
                    <CheckCircle className="h-5 w-5 text-green-600 mt-1 flex-shrink-0" />
                    <div>
                      <h4 className="font-semibold mb-1">Follow Up</h4>
                      <p className="text-sm text-muted-foreground">
                        Check back on pending tickets and follow up with customers who haven't responded in a while.
                      </p>
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card className="border-green-200 bg-green-50/50 dark:bg-green-950/20 dark:border-green-800">
              <CardHeader>
                <CardTitle className="flex items-center gap-2 text-green-900 dark:text-green-100">
                  <Target className="h-5 w-5" />
                  Performance Optimization
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                <div className="space-y-2">
                  <div className="flex items-start gap-2">
                    <div className="p-1 bg-green-200 dark:bg-green-800 rounded">
                      <TrendingUp className="h-4 w-4 text-green-900 dark:text-green-100" />
                    </div>
                    <div>
                      <p className="text-sm">
                        <span className="font-semibold">Monitor your metrics:</span> Keep an eye on your response time and resolution rate in your agent dashboard.
                      </p>
                    </div>
                  </div>
                  <div className="flex items-start gap-2">
                    <div className="p-1 bg-green-200 dark:bg-green-800 rounded">
                      <Clock className="h-4 w-4 text-green-900 dark:text-green-100" />
                    </div>
                    <div>
                      <p className="text-sm">
                        <span className="font-semibold">Batch similar tickets:</span> If you have multiple tickets about the same issue, address them together.
                      </p>
                    </div>
                  </div>
                  <div className="flex items-start gap-2">
                    <div className="p-1 bg-green-200 dark:bg-green-800 rounded">
                      <Star className="h-4 w-4 text-green-900 dark:text-green-100" />
                    </div>
                    <div>
                      <p className="text-sm">
                        <span className="font-semibold">Prioritize urgent tickets:</span> Use the priority filter to focus on high and urgent tickets first.
                      </p>
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>

          <QuickTip>
            <strong>Remember:</strong> The goal is to provide excellent customer support while maintaining efficiency. Use these tools and features to help you achieve both. If you need help, don't hesitate to ask your team or check back here anytime!
          </QuickTip>
        </TabsContent>
      </Tabs>

      {/* Bottom CTA */}
      <Card className="border-primary/20 bg-gradient-to-br from-primary/10 to-transparent">
        <CardContent className="p-6">
          <div className="flex flex-col md:flex-row items-center justify-between gap-4">
            <div className="flex items-center gap-4">
              <div className="p-3 bg-primary/20 rounded-xl">
                <HelpCircle className="h-8 w-8 text-primary" />
              </div>
              <div>
                <h3 className="font-semibold text-lg">Need More Help?</h3>
                <p className="text-sm text-muted-foreground">
                  Contact your administrator or check with your team lead for additional support.
                </p>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
      </div>
    </div>
  );
}
