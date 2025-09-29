'use client';

import { useState, useCallback, useEffect } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Textarea } from '@/components/ui/textarea';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import {
  Search,
  Filter,
  MoreHorizontal,
  MessageSquare,
  Bot,
  ChevronLeft,
  ChevronRight,
  Send,
  Paperclip,
  Eye,
  Reply,
  AlertCircle,
  CheckCircle,
} from 'lucide-react';
import api from '@/lib/api';
import { format } from 'date-fns';
import { cn } from '@/lib/utils';

const messageTypeConfig = {
  comment: { label: 'Comment', color: 'bg-blue-500', icon: MessageSquare },
  internal_note: { label: 'Internal Note', color: 'bg-yellow-500', icon: AlertCircle },
  ai_generated: { label: 'AI Generated', color: 'bg-purple-500', icon: Bot },
};

export default function MessagesPage() {
  const [selectedFilter, setSelectedFilter] = useState('all');
  const [selectedTicket, setSelectedTicket] = useState('all');
  const [searchQuery, setSearchQuery] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const [isReplyDialogOpen, setIsReplyDialogOpen] = useState(false);
  const [selectedMessageId, setSelectedMessageId] = useState<string | null>(null);
  const [replyContent, setReplyContent] = useState('');
  const [isInternalNote, setIsInternalNote] = useState(false);
  const [readMessages, setReadMessages] = useState<Set<string>>(new Set());
  const [hoverTimers, setHoverTimers] = useState<Map<string, NodeJS.Timeout>>(new Map());

  const queryClient = useQueryClient();

  // Fetch messages (ticket comments)
  const { data: messages, isLoading } = useQuery({
    queryKey: ['messages', selectedFilter, selectedTicket, searchQuery, currentPage],
    queryFn: async () => {
      const params = new URLSearchParams();
      if (selectedFilter !== 'all') {
        if (selectedFilter === 'internal') params.append('internal_only', 'true');
        if (selectedFilter === 'public') params.append('public_only', 'true');
        if (selectedFilter === 'ai') params.append('ai_generated', 'true');
      }
      if (selectedTicket !== 'all') params.append('ticket_id', selectedTicket);
      if (searchQuery) params.append('search', searchQuery);
      params.append('page', currentPage.toString());
      params.append('limit', '20');
      params.append('include', 'ticket,user,client');

      const response = await api.messages.list(Object.fromEntries(params));
      return response.data;
    },
  });

  // Fetch tickets for filter dropdown
  const { data: tickets } = useQuery({
    queryKey: ['tickets-for-filter'],
    queryFn: async () => {
      const response = await api.tickets.list({ limit: 100 });
      return response.data.data;
    },
  });

  const getMessageTypeBadge = (message: any) => {
    if (message.is_ai_generated) {
      const config = messageTypeConfig.ai_generated;
      return (
        <Badge variant="outline" className="gap-1">
          <config.icon className="h-3 w-3" />
          {config.label}
        </Badge>
      );
    } else if (message.is_internal_note) {
      const config = messageTypeConfig.internal_note;
      return (
        <Badge variant="outline" className="gap-1">
          <config.icon className="h-3 w-3" />
          {config.label}
        </Badge>
      );
    } else {
      const config = messageTypeConfig.comment;
      return (
        <Badge variant="outline" className="gap-1">
          <config.icon className="h-3 w-3" />
          {config.label}
        </Badge>
      );
    }
  };

  // Mark message as read on hover - immediate visual feedback
  const handleMessageHover = useCallback((messageId: string, isRead: boolean) => {
    if (isRead || readMessages.has(messageId)) return; // Already read, no need to mark again

    // Clear any existing timer for this message
    const existingTimer = hoverTimers.get(messageId);
    if (existingTimer) {
      clearTimeout(existingTimer);
    }

    // Immediately mark as read visually
    setReadMessages(prev => new Set([...prev, messageId]));

    // Optimistically update the notification count immediately
    queryClient.setQueryData(['notification-counts'], (oldData: any) => {
      if (!oldData) return oldData;
      return {
        ...oldData,
        unread_messages: Math.max(0, (oldData.unread_messages || 0) - 1)
      };
    });

    // Set a timer to actually call the API after 500ms
    const timer = setTimeout(async () => {
      try {
        await api.messages.markRead(messageId);
        // Force immediate refetch to get accurate counts from server
        await queryClient.refetchQueries({ queryKey: ['notification-counts'] });
        queryClient.invalidateQueries({ queryKey: ['messages'] });
      } catch (error) {
        console.error('Failed to mark message as read:', error);
        // If API call fails, remove from read messages and revert optimistic update
        setReadMessages(prev => {
          const newSet = new Set(prev);
          newSet.delete(messageId);
          return newSet;
        });
        // Revert the optimistic update
        queryClient.refetchQueries({ queryKey: ['notification-counts'] });
      }

      // Clean up timer
      setHoverTimers(prev => {
        const newMap = new Map(prev);
        newMap.delete(messageId);
        return newMap;
      });
    }, 500);

    // Store the timer
    setHoverTimers(prev => new Map([...prev, [messageId, timer]]));
  }, [readMessages, hoverTimers, queryClient]);

  const handleMessageLeave = useCallback(() => {
    // Mouse left the message - timers continue running to mark as read
  }, []);

  // Cleanup timers on unmount
  useEffect(() => {
    return () => {
      hoverTimers.forEach((timer) => {
        clearTimeout(timer);
      });
    };
  }, [hoverTimers]);

  const handleReply = async () => {
    if (!selectedMessageId || !replyContent.trim()) return;

    try {
      // Find the message to get ticket info
      const message = messages?.data?.find((m: any) => m.id === selectedMessageId);
      if (!message) return;

      await api.messages.reply(message.ticket_id, replyContent, isInternalNote);

      // Reset form
      setReplyContent('');
      setIsInternalNote(false);
      setIsReplyDialogOpen(false);
      setSelectedMessageId(null);

      // Refresh messages list
      // queryClient.invalidateQueries(['messages']);
    } catch (error) {
      console.error('Failed to send reply:', error);
    }
  };

  return (
    <div className="flex-1 space-y-4 p-8 pt-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-3xl font-bold tracking-tight">Messages</h2>
          <p className="text-muted-foreground">
            View and manage all ticket messages and comments
          </p>
        </div>
        <Dialog open={isReplyDialogOpen} onOpenChange={setIsReplyDialogOpen}>
          <DialogTrigger asChild>
            <Button disabled={!selectedMessageId}>
              <Reply className="mr-2 h-4 w-4" />
              Reply to Selected
            </Button>
          </DialogTrigger>
          <DialogContent className="sm:max-w-[625px]">
            <DialogHeader>
              <DialogTitle>Reply to Message</DialogTitle>
              <DialogDescription>
                Send a reply to this ticket conversation
              </DialogDescription>
            </DialogHeader>
            <div className="grid gap-4 py-4">
              <div className="grid gap-2">
                <Label htmlFor="reply-content">Reply Content</Label>
                <Textarea
                  id="reply-content"
                  placeholder="Type your reply here..."
                  value={replyContent}
                  onChange={(e) => setReplyContent(e.target.value)}
                  rows={4}
                />
              </div>
              <div className="flex items-center space-x-2">
                <input
                  type="checkbox"
                  id="internal-note"
                  checked={isInternalNote}
                  onChange={(e) => setIsInternalNote(e.target.checked)}
                  className="rounded border-gray-300"
                />
                <Label htmlFor="internal-note" className="text-sm">
                  Internal note (visible to agents only)
                </Label>
              </div>
            </div>
            <DialogFooter>
              <Button variant="outline" onClick={() => setIsReplyDialogOpen(false)}>
                Cancel
              </Button>
              <Button onClick={handleReply} disabled={!replyContent.trim()}>
                <Send className="mr-2 h-4 w-4" />
                Send Reply
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>

      {/* Filters and Search */}
      <Card>
        <CardContent className="p-4">
          <div className="flex flex-col sm:flex-row gap-4">
            <div className="relative flex-1">
              <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                placeholder="Search messages..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="pl-9"
              />
            </div>
            <Select value={selectedFilter} onValueChange={setSelectedFilter}>
              <SelectTrigger className="w-full sm:w-[180px]">
                <SelectValue placeholder="Message Type" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Messages</SelectItem>
                <SelectItem value="public">Public Comments</SelectItem>
                <SelectItem value="internal">Internal Notes</SelectItem>
                <SelectItem value="ai">AI Generated</SelectItem>
              </SelectContent>
            </Select>
            <Select value={selectedTicket} onValueChange={setSelectedTicket}>
              <SelectTrigger className="w-full sm:w-[200px]">
                <SelectValue placeholder="Filter by Ticket" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Tickets</SelectItem>
                {tickets?.map((ticket: any) => (
                  <SelectItem key={ticket.id} value={ticket.id}>
                    {ticket.ticket_number} - {ticket.subject.substring(0, 30)}...
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Button variant="outline" size="icon">
              <Filter className="h-4 w-4" />
            </Button>
          </div>
        </CardContent>
      </Card>

      {/* Messages Table */}
      <Card>
        <CardContent className="p-0">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Message</TableHead>
                <TableHead>Ticket</TableHead>
                <TableHead>Author</TableHead>
                <TableHead>Type</TableHead>
                <TableHead>Created</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading ? (
                <TableRow>
                  <TableCell colSpan={6} className="text-center py-8">
                    <div className="flex justify-center">
                      <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                    </div>
                  </TableCell>
                </TableRow>
              ) : messages?.data?.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={6} className="text-center py-8">
                    No messages found
                  </TableCell>
                </TableRow>
              ) : (
                messages?.data?.map((message: any) => {
                  const isMessageRead = message.is_read || readMessages.has(message.id);
                  return (
                  <TableRow
                    key={message.id}
                    className={cn(
                      "cursor-pointer hover:bg-accent/50 transition-all",
                      selectedMessageId === message.id && "bg-accent",
                      !isMessageRead && "bg-blue-50 dark:bg-blue-950/20 border-l-4 border-l-blue-500"
                    )}
                    onClick={() => setSelectedMessageId(
                      selectedMessageId === message.id ? null : message.id
                    )}
                    onMouseEnter={() => handleMessageHover(message.id, message.is_read)}
                    onMouseLeave={handleMessageLeave}
                  >
                    <TableCell>
                      <div className="space-y-1 max-w-md">
                        <div className="flex items-center gap-2">
                          {!isMessageRead && (
                            <div className="h-2 w-2 bg-blue-500 rounded-full flex-shrink-0" />
                          )}
                          <p className={cn(
                            "text-sm line-clamp-2",
                            !isMessageRead && "font-medium"
                          )}>
                            {message.content}
                          </p>
                        </div>
                        {message.attachments && message.attachments.length > 0 && (
                          <div className="flex items-center gap-1 text-xs text-muted-foreground">
                            <Paperclip className="h-3 w-3" />
                            {message.attachments.length} attachment(s)
                          </div>
                        )}
                        {message.ai_suggestion_used && (
                          <div className="flex items-center gap-1 text-xs text-purple-600">
                            <Bot className="h-3 w-3" />
                            Used AI suggestion
                          </div>
                        )}
                      </div>
                    </TableCell>
                    <TableCell>
                      <div className="space-y-1">
                        <p className="text-sm font-medium">{message.ticket?.ticket_number}</p>
                        <p className="text-xs text-muted-foreground line-clamp-1">
                          {message.ticket?.subject}
                        </p>
                      </div>
                    </TableCell>
                    <TableCell>
                      <div className="flex items-center gap-2">
                        <Avatar className="h-8 w-8">
                          <AvatarFallback>
                            {message.user_id ? (
                              message.user?.name?.charAt(0)?.toUpperCase() || 'A'
                            ) : (
                              'C'
                            )}
                          </AvatarFallback>
                        </Avatar>
                        <div>
                          <p className="text-sm font-medium">
                            {message.user_id ? (
                              message.user?.name || `Agent ${message.user_id.slice(-8)}`
                            ) : (
                              message.client?.name || `Client ${message.client_id?.slice(-8) || 'Unknown'}`
                            )}
                          </p>
                          <p className="text-xs text-muted-foreground">
                            {message.user_id ? 'Agent' : 'Customer'}
                          </p>
                        </div>
                      </div>
                    </TableCell>
                    <TableCell>
                      {getMessageTypeBadge(message)}
                    </TableCell>
                    <TableCell>
                      <div className="text-sm">
                        <p>{format(new Date(message.created_at), 'MMM d, yyyy')}</p>
                        <p className="text-xs text-muted-foreground">
                          {format(new Date(message.created_at), 'h:mm a')}
                        </p>
                      </div>
                    </TableCell>
                    <TableCell className="text-right">
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="ghost" size="sm">
                            <MoreHorizontal className="h-4 w-4" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                          <DropdownMenuLabel>Actions</DropdownMenuLabel>
                          <DropdownMenuItem
                            onClick={(e) => {
                              e.stopPropagation();
                              setSelectedMessageId(message.id);
                              setIsReplyDialogOpen(true);
                            }}
                          >
                            <Reply className="mr-2 h-4 w-4" />
                            Reply
                          </DropdownMenuItem>
                          <DropdownMenuItem>
                            <Eye className="mr-2 h-4 w-4" />
                            View Ticket
                          </DropdownMenuItem>
                          {message.is_internal_note && (
                            <DropdownMenuItem>
                              <CheckCircle className="mr-2 h-4 w-4" />
                              Mark as Read
                            </DropdownMenuItem>
                          )}
                          <DropdownMenuSeparator />
                          <DropdownMenuItem className="text-red-600">
                            Delete Message
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </TableCell>
                  </TableRow>
                  );
                })
              )}
            </TableBody>
          </Table>

          {/* Pagination */}
          {messages?.meta && messages.meta.pages > 1 && (
            <div className="flex items-center justify-between px-6 py-4 border-t">
              <p className="text-sm text-muted-foreground">
                Showing {((currentPage - 1) * (messages.meta.limit || 20)) + 1} to{' '}
                {Math.min(currentPage * (messages.meta.limit || 20), messages.meta.total)} of{' '}
                {messages.meta.total} messages
              </p>
              <div className="flex items-center gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setCurrentPage(p => Math.max(1, p - 1))}
                  disabled={currentPage === 1}
                >
                  <ChevronLeft className="h-4 w-4" />
                  Previous
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setCurrentPage(p => Math.min(messages.meta.pages, p + 1))}
                  disabled={currentPage === messages.meta.pages}
                >
                  Next
                  <ChevronRight className="h-4 w-4" />
                </Button>
              </div>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}