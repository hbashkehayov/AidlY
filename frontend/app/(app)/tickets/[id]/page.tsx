'use client';

import { useState, useEffect, useRef } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api';
import { format, formatDistanceToNow } from 'date-fns';
import { Input } from '@/components/ui/input';
import { useAuth } from '@/lib/auth';
import Link from 'next/link';
import {
  ArrowLeft,
  Mail,
  Send,
  AlertCircle,
  XCircle,
  Calendar,
  Tag,
  User,
  Clock,
  Paperclip,
  Forward,
  Reply,
  Edit3,
  Building,
  CheckCircle,
  Check,
  ChevronDown,
  ChevronUp,
  ChevronLeft,
  ChevronRight,
  Download,
  FileText,
  FileImage,
  File,
  Ticket,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { TooltipProvider } from '@/components/ui/tooltip';
import { Textarea } from '@/components/ui/textarea';
import { RichTextEditor } from '@/components/editor/rich-text-editor';
import { toast } from 'sonner';
import { cn } from '@/lib/utils';
import { getStatusColor, getStatusLabel, getPriorityColor, getPriorityLabel } from '@/lib/colors';

const statusConfig = {
  open: { label: 'Open' },
  pending: { label: 'Pending' },
  resolved: { label: 'Resolved' },
  closed: { label: 'Closed' },
  cancelled: { label: 'Cancelled' },
  new: { label: 'New' },
};

interface TicketComment {
  id: string;
  user_id?: string;
  client_id?: string;
  content: string;
  is_internal_note: boolean;
  created_at: string;
  attachments?: any[];
  // Email metadata
  from_address?: string;
  to_addresses?: string[];
  cc_addresses?: string[];
  subject?: string;
  body_html?: string;
  body_plain?: string;
  headers?: Record<string, any>;
  user?: {
    id: string;
    name: string;
    email: string;
    role?: string;
  };
  client?: {
    id: string;
    name: string;
    email: string;
    company?: string;
  };
}

// Helper functions
const getFileIcon = (mimeType?: string, filename?: string) => {
  if (!mimeType && !filename) return File;
  const mime = mimeType?.toLowerCase() || '';
  const ext = filename?.split('.').pop()?.toLowerCase() || '';

  if (mime.startsWith('image/') || ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp'].includes(ext)) {
    return FileImage;
  }
  if (mime.includes('pdf') || mime.includes('document') || mime.includes('text') ||
      ['pdf', 'doc', 'docx', 'txt', 'rtf', 'odt'].includes(ext)) {
    return FileText;
  }
  return File;
};

const formatFileSize = (bytes?: number | null): string => {
  if (!bytes || bytes === 0) return '0 Bytes';
  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
};

const handleDownloadAttachment = async (attachmentId: string, filename: string) => {
  try {
    const response = await api.attachments.download(attachmentId);

    // Create a blob from the response data
    const blob = new Blob([response.data]);

    // Create a temporary URL for the blob
    const url = window.URL.createObjectURL(blob);

    // Create a temporary anchor element and trigger download
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();

    // Cleanup
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);

    toast.success('Download started');
  } catch (error: any) {
    console.error('Failed to download attachment:', error);
    toast.error('Failed to download attachment');
  }
};

const hasExternalImages = (html: string): boolean => {
  if (!html) return false;
  // Check for external images (http/https URLs, not data URIs)
  const imgRegex = /<img[^>]+src=["'](https?:\/\/[^"']+)["']/gi;
  return imgRegex.test(html);
};

const blockExternalImages = (html: string): string => {
  if (!html) return html;
  // Replace external image URLs with placeholder
  return html.replace(
    /<img([^>]+)src=["'](https?:\/\/[^"']+)["']/gi,
    '<img$1src="data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'200\' height=\'100\'%3E%3Crect width=\'200\' height=\'100\' fill=\'%23f3f4f6\'/%3E%3Ctext x=\'50%25\' y=\'50%25\' dominant-baseline=\'middle\' text-anchor=\'middle\' fill=\'%239ca3af\' font-family=\'Arial\' font-size=\'12\'%3EExternal Image Blocked%3C/text%3E%3C/svg%3E"'
  );
};

const cleanEmailThreadMetadata = (content: string, isHtml: boolean = false): string => {
  if (!content) return content;

  let cleaned = content;

  if (isHtml) {
    // Strip out email history/quoted content for HTML
    cleaned = cleaned
      // Remove everything after "On ... wrote:" pattern
      .replace(/On\s+.+?\s+wrote:[\s\S]*/gi, '')
      // Remove blockquote elements (common in email replies)
      .replace(/<blockquote[\s\S]*?<\/blockquote>/gi, '')
      // Remove Gmail quote div
      .replace(/<div class="gmail_quote"[\s\S]*?<\/div>/gi, '')
      // Remove generic quote divs
      .replace(/<div[^>]*class="[^"]*quote[^"]*"[^>]*>[\s\S]*?<\/div>/gi, '')
      // Remove "From:" headers (email forwarding)
      .replace(/From:[\s\S]*?Subject:[\s\S]*?(?=<|$)/gi, '')
      // Remove hr separators often used in email threads
      .replace(/<hr[^>]*>/gi, '');

    return cleaned;
  }

  // Remove HTML tags first for plain text
  cleaned = cleaned.replace(/<[^>]*>/g, '');

  // For plain text, remove quoted lines (lines starting with >)
  const lines = cleaned.split('\n');
  const cleanLines: string[] = [];
  let inQuote = false;

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    const trimmedLine = line.trim();

    // Detect start of email quote section
    if (trimmedLine.match(/^On\s+.+?\s+wrote:/i) ||
        trimmedLine.match(/^From:/i) ||
        trimmedLine.match(/^-{3,}/) ||
        trimmedLine.startsWith('>')) {
      inQuote = true;
      continue;
    }

    // Skip quoted lines
    if (inQuote) {
      // Stop quote section if we hit a non-quoted, non-empty line
      if (trimmedLine && !trimmedLine.startsWith('>')) {
        inQuote = false;
        cleanLines.push(line);
      }
      continue;
    }

    cleanLines.push(line);
  }

  cleaned = cleanLines.join('\n').trim();

  // Split by common footer/signature patterns and take only the first part
  const footerPatterns = [
    /Ticket Update:/i,
    /View Full Ticket/i,
    /This is an automated message/i,
    /Best regards/i,
    /Sincerely/i,
    /Thanks/i,
    /Regards/i,
    /Sent from/i,
    /Get Outlook/i,
    /Ticket\s*#[A-Z]+-\d+/i,
  ];

  // Find the earliest occurrence of any footer pattern
  let cutoffIndex = cleaned.length;
  for (const pattern of footerPatterns) {
    const match = cleaned.match(pattern);
    if (match && match.index !== undefined && match.index < cutoffIndex) {
      cutoffIndex = match.index;
    }
  }

  // Cut the string at the earliest footer pattern
  cleaned = cleaned.substring(0, cutoffIndex);

  // Remove excessive whitespace and newlines
  cleaned = cleaned.replace(/\s+/g, ' ').trim();

  return cleaned;
};

export default function TicketPage() {
  const { id: ticketId } = useParams();
  const router = useRouter();
  const queryClient = useQueryClient();
  const { user, isAuthenticated } = useAuth();

  // State
  const [showComposeReply, setShowComposeReply] = useState(false);
  const [replyContent, setReplyContent] = useState('');
  const [isInternalNote, setIsInternalNote] = useState(false);
  const [replyEmail, setReplyEmail] = useState('');
  const [replyName, setReplyName] = useState('');
  const [replyAttachments, setReplyAttachments] = useState<File[]>([]);
  const [isForwardDialogOpen, setIsForwardDialogOpen] = useState(false);
  const [selectedAgent, setSelectedAgent] = useState('');
  const [forwardMessage, setForwardMessage] = useState('');
  const [isEditDialogOpen, setIsEditDialogOpen] = useState(false);
  const [isDeleteDialogOpen, setIsDeleteDialogOpen] = useState(false);
  const [editedTicket, setEditedTicket] = useState<any>(null);
  const [expandedEmails, setExpandedEmails] = useState<Record<string, boolean>>({});
  const [showExternalImages, setShowExternalImages] = useState<Record<string, boolean>>({});
  const [dismissedForwardMessages, setDismissedForwardMessages] = useState<string[]>([]);
  const [isClosingReply, setIsClosingReply] = useState(false);
  const [isClientSidebarOpen, setIsClientSidebarOpen] = useState(true);
  const [isActionsSidebarOpen, setIsActionsSidebarOpen] = useState(true);
  const [isOpenTicketsExpanded, setIsOpenTicketsExpanded] = useState(true);
  const [showAddNoteDialog, setShowAddNoteDialog] = useState(false);
  const [noteContent, setNoteContent] = useState('');
  const [editingNoteId, setEditingNoteId] = useState<string | null>(null);
  const [editingNoteContent, setEditingNoteContent] = useState('');
  const [hiddenNoteIds, setHiddenNoteIds] = useState<string[]>([]);
  const [deleteNoteId, setDeleteNoteId] = useState<string | null>(null);
  const [isCloseConfirmDialogOpen, setIsCloseConfirmDialogOpen] = useState(false);
  const [pendingStatusChange, setPendingStatusChange] = useState<string | null>(null);

  // Data fetching
  const { data: ticket, isLoading, error } = useQuery({
    queryKey: ['ticket', ticketId],
    queryFn: async () => {
      try {
        const response = await api.tickets.get(ticketId as string);
        // The API returns { success: true, data: ticketObject }
        if (response.data?.success && response.data?.data) {
          return response.data.data;
        }
        // Fallback to direct data if structure is different
        return response.data;
      } catch (err: any) {
        console.error('Error fetching ticket:', err);
        throw err;
      }
    },
    enabled: !!ticketId,
    // Real-time updates - more aggressive polling
    refetchInterval: 2000, // Poll every 2 seconds for real-time updates on individual ticket
    refetchOnWindowFocus: true, // Refetch when window regains focus
    refetchIntervalInBackground: true, // Continue polling in background for updates
    refetchOnMount: true, // Always refetch on mount
    refetchOnReconnect: true, // Refetch when connection is restored
  });

  // Set initial edit form data when ticket loads
  useEffect(() => {
    if (ticket && !editedTicket) {
      setEditedTicket({
        subject: ticket.subject || '',
        description: ticket.description || '',
        priority: ticket.priority || 'medium',
        status: ticket.status || 'open',
        category_id: ticket.category_id || '',
      });
    }
  }, [ticket, ticketId, editedTicket]);

  // Track previous assigned agent to detect reassignment
  const previousAssignedAgentId = useRef<string | null>(null);

  // Ref for reply area to enable auto-scroll
  const replyAreaRef = useRef<HTMLDivElement>(null);

  // Redirect user if ticket is reassigned away from them
  useEffect(() => {
    if (ticket && user) {
      // If this is the first load, just store the current assignment
      if (previousAssignedAgentId.current === null) {
        previousAssignedAgentId.current = ticket.assigned_agent_id || null;
        return;
      }

      // Check if ticket was assigned to current user but is now assigned to someone else (or unassigned)
      const wasAssignedToMe = previousAssignedAgentId.current === user.id;
      const isNowAssignedToSomeoneElse = ticket.assigned_agent_id !== user.id;

      if (wasAssignedToMe && isNowAssignedToSomeoneElse) {
        // Ticket was reassigned away from current user - redirect to tickets list
        toast.info('This ticket has been reassigned');
        router.push('/tickets');
      }

      // Update the ref with current assignment
      previousAssignedAgentId.current = ticket.assigned_agent_id || null;
    }
  }, [ticket?.assigned_agent_id, user, router]);

  // Auto-scroll to reply area when it opens
  useEffect(() => {
    if (showComposeReply && replyAreaRef.current) {
      // Use a slight delay to ensure the animation has started
      setTimeout(() => {
        replyAreaRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }, 100);
    }
  }, [showComposeReply]);

  // Extract forward messages from internal notes
  const forwardMessages = ticket?.comments
    ?.filter((comment: TicketComment) =>
      comment.is_internal_note &&
      comment.content?.startsWith('FORWARD_MESSAGE:')
    )
    .map((comment: TicketComment) => ({
      message: comment.content.replace('FORWARD_MESSAGE:', '').trim(),
      created_at: comment.created_at,
      id: comment.id,
      sender: comment.user || { name: 'Unknown User', email: '' }
    }))
    .filter((msg: any) => !dismissedForwardMessages.includes(msg.id)) || [];

  // Fetch all users for assignment (not just agents)
  const { data: agents = [] } = useQuery({
    queryKey: ['users-for-assignment'],
    queryFn: () => api.users.listAssignable().then(res => res.data?.data || []),
  });

  const { data: categories = [] } = useQuery({
    queryKey: ['categories'],
    queryFn: () => api.categories.list().then(res => res.data?.data || res.data || []),
  });

  // Fetch other tickets from this client (open tickets only)
  const { data: clientTicketsData, isLoading: isLoadingClientTickets } = useQuery({
    queryKey: ['client-tickets', ticket?.client?.id],
    queryFn: async () => {
      if (!ticket?.client?.id) return [];
      try {
        const response = await api.tickets.list({
          client_id: ticket.client.id,
          limit: 50
        });
        console.log('Client tickets response:', response.data);
        let tickets = [];
        if (response.data?.success && response.data?.data) {
          tickets = response.data.data;
        } else {
          tickets = response.data?.data || response.data || [];
        }
        // Filter for open statuses on client side
        const openTickets = tickets.filter((t: any) =>
          ['open', 'new', 'pending'].includes(t.status?.toLowerCase())
        );
        console.log('Filtered open tickets:', openTickets);
        return openTickets;
      } catch (err) {
        console.error('Error fetching client tickets:', err);
        return [];
      }
    },
    enabled: !!ticket?.client?.id,
  });

  // Mutations
  const updateTicketMutation = useMutation({
    mutationFn: async (data: any) => {
      const response = await api.tickets.update(ticketId as string, data);
      return response.data;
    },
    onMutate: async (updatedData) => {
      // Cancel any outgoing refetches to avoid overwriting our optimistic update
      await queryClient.cancelQueries({ queryKey: ['ticket', ticketId] });

      // Snapshot the previous value
      const previousTicket = queryClient.getQueryData(['ticket', ticketId]);

      // Optimistically update the ticket
      queryClient.setQueryData(['ticket', ticketId], (old: any) => {
        if (!old) return old;
        return {
          ...old,
          data: {
            ...old.data,
            ...updatedData,
          },
        };
      });

      // Return a context with the previous ticket
      return { previousTicket };
    },
    onSuccess: () => {
      // Invalidate single ticket query
      queryClient.invalidateQueries({ queryKey: ['ticket', ticketId] });
      // Invalidate tickets list to update in real-time
      queryClient.invalidateQueries({ queryKey: ['tickets'] });
      toast.success('Ticket updated successfully');
    },
    onError: (error: any, updatedData, context: any) => {
      // Rollback to the previous value on error
      if (context?.previousTicket) {
        queryClient.setQueryData(['ticket', ticketId], context.previousTicket);
      }
      console.error('Update error:', error);
      const message = error.response?.data?.error?.message || 'Failed to update ticket';
      toast.error(message);
    },
  });

  const replyMutation = useMutation({
    mutationFn: (data: { content: string; isInternal: boolean; email?: string; attachments?: File[] }) =>
      api.tickets.addComment(ticketId as string, data.content, data.isInternal, data.email, data.attachments),
    onSuccess: () => {
      setReplyContent('');
      setShowComposeReply(false);
      setIsInternalNote(false);
      setReplyEmail('');
      setReplyName('');
      setReplyAttachments([]);
      // Invalidate both ticket and tickets list queries
      queryClient.invalidateQueries({ queryKey: ['ticket', ticketId] });
      queryClient.invalidateQueries({ queryKey: ['tickets'] });
      toast.success('Reply sent successfully');
    },
    onError: (error: any) => {
      const message = error.response?.data?.error?.message || 'Failed to send reply';
      toast.error(message);
    },
  });

  // Remove forwardMutation as we're using updateTicketMutation for assignments

  const deleteTicketMutation = useMutation({
    mutationFn: () => api.tickets.delete(ticketId as string),
    onSuccess: () => {
      toast.success('Ticket deleted successfully');
      router.push('/tickets');
    },
    onError: () => {
      toast.error('Failed to delete ticket');
    },
  });

  const editTicketMutation = useMutation({
    mutationFn: (data: any) => api.tickets.update(ticketId as string, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['ticket', ticketId] });
      setIsEditDialogOpen(false);
      toast.success('Ticket updated successfully');
    },
    onError: () => {
      toast.error('Failed to update ticket');
    },
  });

  const handleCloseReply = () => {
    setIsClosingReply(true);
    setTimeout(() => {
      setShowComposeReply(false);
      setIsClosingReply(false);
      setReplyEmail('');
      setReplyName('');
      setReplyContent('');
    }, 200); // Match animation duration
  };

  const handleToggleReply = () => {
    if (showComposeReply) {
      // If closing, animate out
      handleCloseReply();
    } else {
      // If opening, just toggle
      setShowComposeReply(true);
    }
  };

  const handleReply = () => {
    if (!replyContent.trim()) return;

    // For non-authenticated users, include email in the reply
    if (!isAuthenticated) {
      // For guests, format the content with their info if provided
      if (replyEmail) {
        const formattedContent = replyName
          ? `${replyName} (${replyEmail}):\n\n${replyContent}`
          : `${replyEmail}:\n\n${replyContent}`;

        replyMutation.mutate({
          content: formattedContent,
          isInternal: false,
          email: replyEmail,
          attachments: replyAttachments
        });
      } else {
        // Allow reply without email too
        replyMutation.mutate({
          content: replyContent,
          isInternal: false,
          attachments: replyAttachments
        });
      }
    } else {
      // For authenticated users
      replyMutation.mutate({
        content: replyContent,
        isInternal: isInternalNote,
        attachments: replyAttachments
      });
    }
  };

  const handleForwardTicket = async () => {
    if (!selectedAgent) return;

    try {
      const selectedAgentData = agents.find((agent: any) => agent.id === selectedAgent);
      const selectedAgentName = selectedAgentData?.name || 'Unknown Agent';

      // Add internal note if message provided
      if (forwardMessage.trim()) {
        await api.tickets.addComment(
          ticketId as string,
          `FORWARD_MESSAGE: ${forwardMessage}`,
          true
        );
      }

      // Update the ticket with new agent assignment
      await api.tickets.update(ticketId as string, { assigned_agent_id: selectedAgent });

      // Send notification to the assigned agent
      try {
        const notifResponse = await api.notifications.notifyTicketAssigned({
          ticket_id: ticketId as string,
          ticket_number: ticket.ticket_number || 'N/A',
          subject: ticket.subject || 'No Subject',
          priority: ticket.priority || 'medium',
          customer_name: ticket.client?.name || 'Unknown',
          assigned_to_id: selectedAgent,
          assigned_to_name: selectedAgentData?.name || 'Unknown Agent',
          assigned_to_email: selectedAgentData?.email || '',
          assigned_by: user?.name || 'System'
        });
        console.log('Notification sent successfully:', notifResponse.data);
      } catch (notifError: any) {
        console.error('Failed to send notification:', notifError);
        console.error('Notification error response:', notifError.response?.data);
        console.error('Notification error status:', notifError.response?.status);
        // Don't fail the whole operation if notification fails
      }

      // Refresh ticket data
      queryClient.invalidateQueries({ queryKey: ['ticket', ticketId] });

      setIsForwardDialogOpen(false);
      setSelectedAgent('');
      setForwardMessage('');
      toast.success(`Ticket assigned to ${selectedAgentName}`);
    } catch (error) {
      console.error('Forward error:', error);
      toast.error('Failed to forward ticket');
    }
  };

  const handleDeleteTicket = () => {
    deleteTicketMutation.mutate();
  };

  const handleEditTicket = () => {
    if (!editedTicket) return;
    editTicketMutation.mutate(editedTicket);
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-screen">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
      </div>
    );
  }

  if (error || (!isLoading && !ticket)) {
    return (
      <div className="flex flex-col items-center justify-center h-screen gap-4">
        <AlertCircle className="h-12 w-12 text-muted-foreground" />
        <p className="text-lg text-muted-foreground">Ticket not found</p>
        <Button onClick={() => router.back()} variant="outline">
          <ArrowLeft className="mr-2 h-4 w-4" />
          Back to Tickets
        </Button>
      </div>
    );
  }

  // Return loading if still loading or no ticket yet
  if (isLoading || !ticket) {
    return (
      <div className="flex items-center justify-center h-screen">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
      </div>
    );
  }

  const clientInfo = ticket.client || {
    name: 'Unknown',
    email: 'no-email@example.com',
    company: null,
  };

  const clientTickets = (clientTicketsData || []).filter((t: any) => t.id !== ticketId);

  // Check if ticket is closed
  const isTicketClosed = ticket?.status?.toLowerCase() === 'closed';

  // Check if ticket is NOT assigned to current user
  // If it's unassigned OR assigned to someone else, it's read-only
  // Only editable if assigned to current user
  const isNotAssignedToCurrentUser = !ticket?.assigned_agent_id || ticket?.assigned_agent_id !== user?.id;

  // Handler for status changes that checks for "closed" status
  const handleStatusChange = (newStatus: string) => {
    if (newStatus === 'closed' && ticket?.status?.toLowerCase() !== 'closed') {
      // Show confirmation dialog
      setPendingStatusChange(newStatus);
      setIsCloseConfirmDialogOpen(true);
    } else {
      // Update immediately for other statuses
      updateTicketMutation.mutate({ status: newStatus });
    }
  };

  // Handler for confirming close action
  const handleConfirmClose = () => {
    if (pendingStatusChange) {
      updateTicketMutation.mutate({ status: pendingStatusChange });
      setIsCloseConfirmDialogOpen(false);
      setPendingStatusChange(null);
    }
  };

  return (
    <div className="min-h-screen bg-gray-50 overflow-x-hidden">
      <TooltipProvider>
        {/* Streamlined Header */}
        <div className="bg-white border-b border-gray-200 sticky top-0 z-50 shadow-sm">
          <div className="w-full px-3 md:px-6">
            <div className="flex items-center justify-between h-16">
              <div className="flex items-center gap-4">
                <Button
                  onClick={() => router.back()}
                  variant="ghost"
                  size="sm"
                  className="text-gray-600 hover:text-gray-900"
                >
                  <ArrowLeft className="h-4 w-4 mr-1" />
                  Back
                </Button>
                <div className="flex items-center gap-3">
                  <span className="text-lg font-semibold text-gray-900">
                    #{ticket.ticket_number || 'N/A'}
                  </span>
                  {ticket.status && (
                    <Badge variant={`status-${ticket.status.toLowerCase()}` as any} className="gap-1.5">
                      <span className={cn('h-2 w-2 rounded-full', getStatusColor(ticket.status).dot)} />
                      {statusConfig[ticket.status as keyof typeof statusConfig]?.label || getStatusLabel(ticket.status)}
                    </Badge>
                  )}
                  {ticket.priority && (
                    <Badge variant={`priority-${ticket.priority.toLowerCase()}` as any} className="gap-1.5">
                      <span className={cn('h-2 w-2 rounded-full', getPriorityColor(ticket.priority).dot)} />
                      {getPriorityLabel(ticket.priority)}
                    </Badge>
                  )}
                </div>
              </div>

              <div className="flex items-center gap-3">
                {/* Claim Ticket Button */}
                {isAuthenticated && (() => {
                  const isAssignedToCurrentUser = ticket.assigned_agent?.id === user?.id;
                  const isAssignedToSomeoneElse = ticket.assigned_agent && !isAssignedToCurrentUser;

                  // Button is disabled only when assigned to current user
                  const isDisabled = isAssignedToCurrentUser;

                  return (
                    <Button
                      variant={isDisabled ? "outline" : "default"}
                      size="sm"
                      disabled={isDisabled}
                      onClick={async () => {
                        if (isDisabled) return;
                        try {
                          await api.tickets.claim(ticket.id);
                          // Refresh ticket data and tickets list
                          queryClient.invalidateQueries({ queryKey: ['ticket', ticketId] });
                          queryClient.invalidateQueries({ queryKey: ['tickets'] });
                          toast.success(isAssignedToSomeoneElse ? 'Ticket re-claimed successfully' : 'Ticket claimed successfully');
                        } catch (error) {
                          console.error('Failed to claim ticket:', error);
                          toast.error('Failed to claim ticket. Please try again.');
                        }
                      }}
                      className={cn(
                        isDisabled
                          ? "bg-gray-100 text-gray-400 cursor-not-allowed hover:bg-gray-100"
                          : "bg-blue-600 hover:bg-blue-700 text-white"
                      )}
                    >
                      {isDisabled ? (
                        <>
                          <CheckCircle className="h-4 w-4 mr-1" />
                          Claimed
                        </>
                      ) : isAssignedToSomeoneElse ? (
                        <>
                          <User className="h-4 w-4 mr-1" />
                          Re-Claim Ticket
                        </>
                      ) : (
                        <>
                          <User className="h-4 w-4 mr-1" />
                          Claim Ticket
                        </>
                      )}
                    </Button>
                  );
                })()}

                {!isAuthenticated && (
                  <Button
                    asChild
                    variant="outline"
                    size="sm"
                  >
                    <Link href="/login">
                      <User className="h-4 w-4 mr-1" />
                      Login
                    </Link>
                  </Button>
                )}
                {isAuthenticated && (
                  <div className="flex items-center gap-2 text-sm text-gray-600">
                    <User className="h-4 w-4" />
                    <span>{user?.name}</span>
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>

        {/* Three-panel layout - Responsive */}
        <div className="flex flex-col lg:flex-row min-h-[calc(100vh-4rem)]">
          {/* Client Panel - Hidden on mobile, collapsible on desktop */}
          <div className={cn(
            "hidden lg:flex bg-white border-r border-gray-200 flex-col flex-shrink-0 transition-all duration-300",
            isClientSidebarOpen ? "w-64 xl:w-80" : "w-12"
          )}>
            <div className="p-6 border-b border-gray-200 relative">
              <Button
                variant="ghost"
                size="sm"
                className="absolute top-2 right-2 h-8 w-8 p-0"
                onClick={() => setIsClientSidebarOpen(!isClientSidebarOpen)}
              >
                {isClientSidebarOpen ? (
                  <ChevronLeft className="h-4 w-4" />
                ) : (
                  <ChevronRight className="h-4 w-4" />
                )}
              </Button>
              {isClientSidebarOpen && (
                <>
                  <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                    <User className="h-5 w-5" />
                    Client
                  </h3>
                  <div className="space-y-4">
                    {/* Clickable Client Info */}
                    <Link
                      href={ticket.client?.id ? `/customers/${ticket.client.id}` : '#'}
                      className="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 transition-colors cursor-pointer"
                    >
                      <Avatar className="h-12 w-12">
                        <AvatarFallback className="bg-gray-100 text-gray-900 text-lg font-semibold">
                          {clientInfo.name.charAt(0).toUpperCase()}
                        </AvatarFallback>
                      </Avatar>
                      <div className="flex-1 min-w-0">
                        <h4 className="font-semibold text-gray-900 hover:text-blue-600">{clientInfo.name}</h4>
                        <p className="text-sm text-gray-600 truncate">{clientInfo.email}</p>
                      </div>
                    </Link>

                    <div className="space-y-3 pt-4 border-t border-gray-200">
                      <div className="flex items-center gap-3 text-sm">
                        <Mail className="h-4 w-4 text-gray-400" />
                        <span className="text-gray-600">{clientInfo.email}</span>
                      </div>
                      {clientInfo.company && (
                        <div className="flex items-center gap-3 text-sm">
                          <Building className="h-4 w-4 text-gray-400" />
                          <span className="text-gray-600">{clientInfo.company}</span>
                        </div>
                      )}
                      <div className="flex items-center gap-3 text-sm">
                        <Calendar className="h-4 w-4 text-gray-400" />
                        <span className="text-gray-600">
                          Client since {ticket.created_at ? format(new Date(ticket.created_at), 'MMM yyyy') : 'Unknown'}
                        </span>
                      </div>
                    </div>

                    {/* Open Tickets from this Client - Collapsible */}
                    <div className="pt-4 border-t border-gray-200">
                      <button
                        onClick={() => setIsOpenTicketsExpanded(!isOpenTicketsExpanded)}
                        className="w-full flex items-center justify-between text-sm font-semibold text-gray-900 mb-3 hover:text-gray-700 transition-colors"
                      >
                        <div className="flex items-center gap-2">
                          <Ticket className="h-4 w-4" />
                          Open Tickets ({clientTickets.length})
                        </div>
                        {isOpenTicketsExpanded ? (
                          <ChevronUp className="h-4 w-4" />
                        ) : (
                          <ChevronDown className="h-4 w-4" />
                        )}
                      </button>
                      {isOpenTicketsExpanded && (
                        <>
                          {isLoadingClientTickets ? (
                            <div className="text-xs text-gray-500 py-2">Loading tickets...</div>
                          ) : clientTickets.length > 0 ? (
                            <div className="max-h-60 overflow-y-auto space-y-2 pr-2 scrollbar-thin scrollbar-thumb-gray-300 scrollbar-track-gray-100">
                              {clientTickets.map((ticket: any) => (
                                <Link
                                  key={ticket.id}
                                  href={`/tickets/${ticket.id}`}
                                  className="block p-2 rounded-lg hover:bg-gray-50 transition-colors border border-gray-200"
                                >
                                  <p className="text-sm font-medium text-gray-900 truncate hover:text-blue-600">
                                    {ticket.subject || 'No Subject'}
                                  </p>
                                  <div className="flex items-center gap-2 mt-1">
                                    <span className="text-xs text-gray-500">
                                      #{ticket.ticket_number}
                                    </span>
                                    <span className="text-xs text-gray-400">•</span>
                                    <Badge variant="outline" className="text-xs">
                                      {ticket.status}
                                    </Badge>
                                  </div>
                                </Link>
                              ))}
                            </div>
                          ) : (
                            <p className="text-xs text-gray-500 py-2">No other open tickets from this client</p>
                          )}
                        </>
                      )}
                    </div>
                  </div>
                </>
              )}
            </div>
          </div>

          {/* Conversation Panel - Main content */}
          <div className="flex-1 flex flex-col bg-white min-w-0">
            {/* Read-Only Banner for Closed Tickets */}
            {isTicketClosed && (
              <div className="bg-red-50 border-b border-red-200 px-6 py-3">
                <div className="flex items-center gap-3">
                  <XCircle className="h-5 w-5 text-red-600 flex-shrink-0" />
                  <div className="flex-1">
                    <p className="text-sm font-semibold text-red-800">
                      This ticket is closed and in read-only mode
                    </p>
                    <p className="text-xs text-red-700">
                      No modifications can be made to closed tickets. All editing features are disabled.
                    </p>
                  </div>
                </div>
              </div>
            )}

            {/* Ticket Header */}
            <div className="p-6 border-b border-gray-200">
              <h1 className="text-xl font-semibold text-gray-900 mb-2">
                {ticket.subject || 'No Subject'}
              </h1>
              <div className="flex items-center gap-4 text-sm text-gray-500">
                <div className="flex items-center gap-1">
                  <Calendar className="h-4 w-4" />
                  <span>{ticket.created_at ? format(new Date(ticket.created_at), 'MMM d, yyyy • h:mm a') : 'Unknown'}</span>
                </div>
                <div className="flex items-center gap-1">
                  <Clock className="h-4 w-4" />
                  <span>Updated {ticket.updated_at ? formatDistanceToNow(new Date(ticket.updated_at)) : 'Unknown'} ago</span>
                </div>
              </div>

              {/* Forward Messages - Right below heading */}
              {forwardMessages.length > 0 && (
                <div className="mt-4 space-y-2">
                  {forwardMessages.map((fwdMsg: any) => (
                    <div
                      key={fwdMsg.id}
                      className="p-4 bg-yellow-50 border border-yellow-200 rounded-lg"
                    >
                      <div className="flex items-start gap-3">
                        <Forward className="h-5 w-5 text-yellow-600 mt-0.5 flex-shrink-0" />
                        <div className="flex-1 min-w-0">
                          <div className="flex items-start justify-between gap-2 mb-2">
                            <div className="flex-1">
                              <div className="text-sm font-medium text-yellow-800">
                                Forwarded by {fwdMsg.sender.name}
                              </div>
                              <div className="text-xs text-yellow-600">
                                {formatDistanceToNow(new Date(fwdMsg.created_at), { addSuffix: true })}
                              </div>
                            </div>
                            <Button
                              variant="ghost"
                              size="sm"
                              className="h-6 w-6 p-0 text-yellow-700 hover:text-yellow-900 hover:bg-yellow-100"
                              onClick={() => setDismissedForwardMessages(prev => [...prev, fwdMsg.id])}
                            >
                              <XCircle className="h-4 w-4" />
                            </Button>
                          </div>
                          <div className="text-sm text-yellow-700 whitespace-pre-wrap">
                            {fwdMsg.message}
                          </div>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>

            {/* Conversation Area */}
            <div className="flex-1 p-4 lg:p-6">
              <div className="max-w-4xl mx-auto space-y-4">
              {/* Original Message - Gmail Style */}
              <div className="border rounded-lg bg-white">
                <div className="p-4">
                  <div className="flex items-start gap-3">
                    <Avatar className="h-10 w-10 flex-shrink-0">
                      <AvatarFallback className="bg-gray-700 text-white font-medium">
                        {clientInfo.name.charAt(0).toUpperCase()}
                      </AvatarFallback>
                    </Avatar>
                    <div className="flex-1 min-w-0">
                      <div className="flex items-start justify-between gap-2">
                        <div className="flex-1 min-w-0">
                          <div className="flex items-center gap-2 mb-1">
                            <span className="font-semibold text-gray-900">{clientInfo.name}</span>
                            <Badge variant="outline" className="text-xs bg-gray-100 text-gray-700">
                              Client
                            </Badge>
                          </div>
                          <div className="text-sm space-y-1">
                            <div className="flex items-center gap-2 text-gray-600">
                              <span className="text-gray-500">to</span>
                              <span>me</span>
                            </div>
                            <div className="flex items-center gap-2">
                              <span className="text-gray-500 text-xs">
                                {ticket.created_at ? format(new Date(ticket.created_at), 'MMM d, yyyy, h:mm a') : 'Unknown'}
                              </span>
                            </div>
                          </div>
                        </div>
                        <div className="flex items-center gap-1 flex-shrink-0">
                          <span className="text-xs text-gray-500">
                            {ticket.created_at ? format(new Date(ticket.created_at), 'h:mm a') : ''}
                          </span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div className="px-4 pb-4">
                  {/* External images warning banner */}
                  {hasExternalImages(ticket.description_html || ticket.description) && !showExternalImages['ticket-main'] && (
                    <div className="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg flex items-center justify-between">
                      <div className="flex items-center gap-2 text-sm text-blue-800">
                        <AlertCircle className="h-4 w-4" />
                        <span>External images are blocked for your privacy</span>
                      </div>
                      <Button
                        size="sm"
                        variant="outline"
                        onClick={() => setShowExternalImages(prev => ({ ...prev, 'ticket-main': true }))}
                        className="text-blue-700 border-blue-300 hover:bg-blue-100"
                      >
                        Show Images
                      </Button>
                    </div>
                  )}

                  {/* Render full HTML email with iframe for security - prefer description_html over description */}
                  {(() => {
                    const displayContent = ticket.description_html || ticket.description;
                    const isHtml = displayContent && (displayContent.includes('<html') || displayContent.includes('<body') || displayContent.includes('<table') || displayContent.includes('<div'));

                    if (isHtml) {
                      if (displayContent.includes('<html')) {
                        return (
                          <iframe
                            srcDoc={showExternalImages['ticket-main'] ? displayContent : blockExternalImages(displayContent)}
                            sandbox="allow-same-origin"
                            className="w-full border-0 min-h-[400px]"
                            style={{ height: 'auto' }}
                            onLoad={(e) => {
                              const iframe = e.target as HTMLIFrameElement;
                              if (iframe.contentWindow) {
                                const height = iframe.contentWindow.document.body.scrollHeight;
                                iframe.style.height = height + 'px';
                              }
                            }}
                          />
                        );
                      } else {
                        return (
                          <div className="prose prose-sm max-w-none">
                            <div
                              className="email-content"
                              dangerouslySetInnerHTML={{
                                __html: showExternalImages['ticket-main']
                                  ? displayContent || ''
                                  : blockExternalImages(displayContent || '')
                              }}
                              style={{ wordBreak: 'break-word', overflowWrap: 'break-word' }}
                            />
                          </div>
                        );
                      }
                    } else {
                      return (
                        <pre className="whitespace-pre-wrap font-sans text-sm text-gray-700">
                          {ticket.description}
                        </pre>
                      );
                    }
                  })()}
                  {ticket.attachments && ticket.attachments.length > 0 && (
                    <div className="mt-4 pt-4 border-t border-gray-200">
                      <div className="flex items-center gap-2 mb-3">
                        <Paperclip className="h-4 w-4 text-gray-500" />
                        <span className="text-sm font-medium text-gray-700">
                          {ticket.attachments.length} attachment{ticket.attachments.length !== 1 && 's'}
                        </span>
                      </div>
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                        {ticket.attachments.map((attachment: any) => {
                          const filename = attachment.filename || attachment.file_name || 'Unknown file';
                          const fileSize = attachment.size ?? attachment.file_size;
                          const FileIcon = getFileIcon(attachment.mime_type, filename);
                          return (
                            <div
                              key={attachment.id}
                              className="flex items-center gap-3 p-3 border border-gray-200 rounded-lg hover:border-gray-300 hover:bg-gray-50 transition-colors group"
                            >
                              <div className="flex-shrink-0">
                                <div className="h-10 w-10 rounded bg-gray-100 flex items-center justify-center">
                                  <FileIcon className="h-5 w-5 text-gray-600" />
                                </div>
                              </div>
                              <div className="flex-1 min-w-0">
                                <p className="text-sm font-medium text-gray-900 truncate">
                                  {filename}
                                </p>
                                <p className="text-xs text-gray-500">
                                  {formatFileSize(fileSize)}
                                </p>
                              </div>
                              <Button
                                variant="ghost"
                                size="sm"
                                className="opacity-0 group-hover:opacity-100 transition-opacity"
                                onClick={() => handleDownloadAttachment(attachment.id, filename)}
                              >
                                <Download className="h-4 w-4" />
                              </Button>
                            </div>
                          );
                        })}
                      </div>
                    </div>
                  )}
                </div>
                <div className="px-4 pb-4 flex items-center justify-end gap-2">
                  <Button
                    size="sm"
                    onClick={() => setShowComposeReply(true)}
                    className="gap-2 bg-black hover:bg-gray-800 text-white"
                    disabled={isTicketClosed || isNotAssignedToCurrentUser}
                  >
                    <Reply className="h-4 w-4" />
                    {isTicketClosed ? 'Ticket Closed' : isNotAssignedToCurrentUser ? 'Not Assigned to You' : 'Reply'}
                  </Button>
                </div>
              </div>

              {/* Comments - Chat-Style Message Thread */}
              {ticket.comments && ticket.comments.length > 0 && (
                <div className="space-y-6">
                  {ticket.comments
                    .filter((comment: TicketComment) =>
                      // Filter out ALL internal notes (they're shown in sidebar only)
                      !comment.is_internal_note
                    )
                    .sort((a: TicketComment, b: TicketComment) => {
                      // Sort by created_at descending (newest first)
                      const dateA = new Date(a.created_at).getTime();
                      const dateB = new Date(b.created_at).getTime();
                      return dateB - dateA;
                    })
                    .map((comment: TicketComment) => {
                    const isExpanded = expandedEmails[comment.id] === true;

                    // Determine sender info based on comment type
                    // A comment is from an agent if it has a user_id (and user object)
                    const isFromAgent = !!(comment.user_id && comment.user);

                    // Get the sender's name
                    let senderName = 'Unknown';
                    let senderEmail = '';

                    if (isFromAgent && comment.user) {
                      // This is from an agent/user
                      senderName = comment.user.name || 'Agent';
                      senderEmail = comment.user.email || '';
                    } else if (comment.client) {
                      // This is from a client (has client object)
                      senderName = comment.client.name || 'Client';
                      senderEmail = comment.client.email || '';
                    } else if (comment.from_address) {
                      // Fallback to email metadata if available
                      senderName = comment.from_address.split('@')[0] || 'Client';
                      senderEmail = comment.from_address;
                    } else {
                      // Last resort - use ticket's client info
                      senderName = clientInfo.name || 'Client';
                      senderEmail = clientInfo.email || '';
                    }

                    const initials = senderName.split(' ').map((n: any) => n[0]).join('').toUpperCase().slice(0, 2);
                    const displayContent = comment.body_html || comment.content;

                    // Auto-expand short messages (less than 300 chars and no attachments)
                    const isShortMessage = (comment.body_plain || displayContent || '').length < 300;
                    const hasAttachments = comment.attachments && comment.attachments.length > 0;
                    const shouldAutoExpand = isShortMessage && !hasAttachments;
                    const isExpandedFinal = expandedEmails[comment.id] !== undefined
                      ? expandedEmails[comment.id]
                      : shouldAutoExpand;

                    return (
                      <div key={comment.id} className="mb-4">
                        {/* Message Card */}
                        <div
                          className={cn(
                            "rounded border p-4",
                            comment.is_internal_note
                              ? "bg-amber-50 border-amber-200"
                              : isFromAgent
                                ? "bg-gray-50 border-gray-200"
                                : "bg-white border-gray-200"
                          )}
                        >
                          {/* Sender Info Header */}
                          <div className="flex items-center justify-between mb-2">
                            {isFromAgent ? (
                              <>
                                <span className="text-xs text-gray-500">
                                  {format(new Date(comment.created_at), 'MMM d, h:mm a')}
                                </span>
                                <span className="font-medium text-sm text-foreground">
                                  {senderName}
                                </span>
                              </>
                            ) : (
                              <>
                                <span className="font-medium text-sm text-foreground">
                                  {senderName}
                                </span>
                                <span className="text-xs text-gray-500">
                                  {format(new Date(comment.created_at), 'MMM d, h:mm a')}
                                </span>
                              </>
                            )}
                          </div>

                          {/* Message Content */}
                          <div>
                            {/* Show collapse button only for long messages or those with attachments */}
                            {(!shouldAutoExpand) && (
                              <div className={cn(
                                "flex items-start justify-between gap-2 mb-2",
                                !isExpandedFinal && "mb-0"
                              )}>
                                <div className="flex-1 min-w-0">
                                  {/* Collapsed preview */}
                                  {!isExpandedFinal && (
                                    <p className={cn("text-sm text-gray-700 line-clamp-2", isFromAgent && "text-right")}>
                                      {cleanEmailThreadMetadata(comment.body_plain || displayContent || '', false).substring(0, 150) || 'No content'}
                                    </p>
                                  )}
                                </div>

                                {/* Expand/Collapse Button */}
                                <Button
                                  variant="ghost"
                                  size="sm"
                                  className="h-8 w-8 p-0 flex-shrink-0"
                                  onClick={() => setExpandedEmails(prev => ({ ...prev, [comment.id]: !isExpandedFinal }))}
                                >
                                  {isExpandedFinal ? (
                                    <ChevronUp className="h-4 w-4 text-gray-600" />
                                  ) : (
                                    <ChevronDown className="h-4 w-4 text-gray-600" />
                                  )}
                                </Button>
                              </div>
                            )}

                            {/* Attachments preview when collapsed */}
                            {!isExpandedFinal && hasAttachments && (
                              <div className="flex items-center gap-1 mt-2 text-xs text-gray-500">
                                <Paperclip className="h-3 w-3" />
                                <span>{comment.attachments.length} attachment{comment.attachments.length !== 1 && 's'}</span>
                              </div>
                              )}

                              {/* Message Body (Always shown for short messages, expandable for long ones) */}
                              {isExpandedFinal && (
                                <div className={cn(!shouldAutoExpand && "mt-3 pt-3 border-t border-gray-200")}>
                            {/* External images warning banner */}
                            {hasExternalImages(displayContent) && !showExternalImages[comment.id] && (
                              <div className="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg flex items-center justify-between">
                                <div className="flex items-center gap-2 text-sm text-blue-800">
                                  <AlertCircle className="h-4 w-4" />
                                  <span>External images are blocked for your privacy</span>
                                </div>
                                <Button
                                  size="sm"
                                  variant="outline"
                                  onClick={() => setShowExternalImages(prev => ({ ...prev, [comment.id]: true }))}
                                  className="text-blue-700 border-blue-300 hover:bg-blue-100"
                                >
                                  Show Images
                                </Button>
                              </div>
                            )}

                            {/* Render full HTML email with iframe for security if it's a complete HTML doc */}
                            {displayContent && displayContent.includes('<html') ? (
                              <iframe
                                srcDoc={showExternalImages[comment.id] ? cleanEmailThreadMetadata(displayContent, true) : blockExternalImages(cleanEmailThreadMetadata(displayContent, true))}
                                sandbox="allow-same-origin"
                                className="w-full border-0 min-h-[400px]"
                                style={{ height: 'auto' }}
                                onLoad={(e) => {
                                  const iframe = e.target as HTMLIFrameElement;
                                  if (iframe.contentWindow) {
                                    const height = iframe.contentWindow.document.body.scrollHeight;
                                    iframe.style.height = height + 'px';
                                  }
                                }}
                              />
                            ) : displayContent && displayContent.includes('<') ? (
                              // HTML content without full document
                              <div className={cn("prose prose-sm max-w-none text-gray-700", isFromAgent && "text-right")}>
                                <div
                                  className="email-content"
                                  dangerouslySetInnerHTML={{
                                    __html: showExternalImages[comment.id]
                                      ? cleanEmailThreadMetadata(displayContent, true)
                                      : blockExternalImages(cleanEmailThreadMetadata(displayContent, true))
                                  }}
                                  style={{ wordBreak: 'break-word', overflowWrap: 'break-word' }}
                                />
                              </div>
                            ) : (
                              // Plain text content - preserve line breaks and paragraphs
                              <div className={cn("text-sm text-gray-700 whitespace-pre-wrap leading-relaxed", isFromAgent && "text-right")}>
                                {cleanEmailThreadMetadata(comment.body_plain || displayContent || '', false)}
                              </div>
                            )}

                            {(() => {
                              // Filter attachments to only show those belonging to this specific comment
                              const commentAttachments = comment.attachments?.filter(
                                (attachment: any) => {
                                  const attachmentCommentId = attachment.comment_id || attachment.ticket_comment_id;
                                  return attachmentCommentId && attachmentCommentId === comment.id;
                                }
                              ) || [];

                              if (commentAttachments.length === 0) return null;

                              return (
                                <div className="mt-4 pt-4 border-t border-gray-200">
                                  <div className="flex items-center gap-2 mb-3">
                                    <Paperclip className="h-4 w-4 text-gray-500" />
                                    <span className="text-sm font-medium text-gray-700">
                                      {commentAttachments.length} attachment{commentAttachments.length !== 1 && 's'}
                                    </span>
                                  </div>
                                  <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                                    {commentAttachments.map((attachment: any) => {
                                    const filename = attachment.filename || attachment.file_name || 'Unknown file';
                                    const fileSize = attachment.size ?? attachment.file_size;
                                    const FileIcon = getFileIcon(attachment.mime_type, filename);
                                    return (
                                      <div
                                        key={attachment.id}
                                        className="flex items-center gap-3 p-3 border border-gray-200 rounded-lg hover:border-gray-300 hover:bg-gray-50 transition-colors group"
                                      >
                                        <div className="flex-shrink-0">
                                          <div className="h-10 w-10 rounded bg-gray-100 flex items-center justify-center">
                                            <FileIcon className="h-5 w-5 text-gray-600" />
                                          </div>
                                        </div>
                                        <div className="flex-1 min-w-0">
                                          <p className="text-sm font-medium text-gray-900 truncate">
                                            {filename}
                                          </p>
                                          <p className="text-xs text-gray-500">
                                            {formatFileSize(fileSize)}
                                          </p>
                                        </div>
                                        <Button
                                          variant="ghost"
                                          size="sm"
                                          className="opacity-0 group-hover:opacity-100 transition-opacity"
                                          onClick={() => handleDownloadAttachment(attachment.id, filename)}
                                        >
                                          <Download className="h-4 w-4" />
                                        </Button>
                                      </div>
                                    );
                                  })}
                                </div>
                              </div>
                              );
                            })()}
                          </div>
                        )}
                          </div>
                        </div>
                      </div>
                    );
                  })}
                </div>
              )}
              </div>
            </div>

            {/* Reply Area */}
            {showComposeReply && (
              <div
                ref={replyAreaRef}
                className={cn(
                  "border-t border-gray-200 p-6 bg-gray-50 transition-all duration-200",
                  isClosingReply
                    ? "animate-out slide-out-to-bottom-4 fade-out"
                    : "animate-in slide-in-from-bottom-4 fade-in"
                )}
              >
                {isAuthenticated ? (
                  <div className="space-y-4">
                    <div className="flex items-center justify-between">
                      <h4 className="font-medium text-gray-900">Compose Reply</h4>
                    </div>
                    <RichTextEditor
                      content={replyContent}
                      onChange={setReplyContent}
                      ticketId={ticketId as string}
                      enableAI={true}
                      onAttachmentsChange={setReplyAttachments}
                    />
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-2">
                        <Button
                          onClick={handleReply}
                          disabled={!replyContent.trim() || replyMutation.isPending}
                          className="bg-gray-900 hover:bg-gray-800"
                        >
                          <Send className="h-4 w-4 mr-2" />
                          Send Reply
                        </Button>
                        <Button
                          variant="outline"
                          onClick={handleCloseReply}
                        >
                          Cancel
                        </Button>
                      </div>
                      <div className="text-sm text-gray-500">
                        Replying as: <span className="font-medium">{user?.name}</span>
                      </div>
                    </div>
                  </div>
                ) : (
                  <div className="space-y-4">
                    <div className="flex items-center justify-between">
                      <h4 className="font-medium text-gray-900">Reply to Ticket</h4>
                      <div className="text-sm text-gray-500">
                        Replying as guest
                      </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <div>
                        <Label htmlFor="reply-name" className="text-sm">
                          Your Name (optional)
                        </Label>
                        <Input
                          id="reply-name"
                          type="text"
                          placeholder="John Doe"
                          value={replyName}
                          onChange={(e) => setReplyName(e.target.value)}
                          className="mt-1"
                        />
                      </div>
                      <div>
                        <Label htmlFor="reply-email" className="text-sm">
                          Your Email (optional)
                        </Label>
                        <Input
                          id="reply-email"
                          type="email"
                          placeholder="your@email.com"
                          value={replyEmail}
                          onChange={(e) => setReplyEmail(e.target.value)}
                          className="mt-1"
                        />
                      </div>
                    </div>

                    <RichTextEditor
                      content={replyContent}
                      onChange={setReplyContent}
                      ticketId={ticketId as string}
                      enableAI={true}
                      onAttachmentsChange={setReplyAttachments}
                    />

                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-2">
                        <Button
                          onClick={handleReply}
                          disabled={!replyContent.trim() || replyMutation.isPending}
                          className="bg-gray-900 hover:bg-gray-800"
                        >
                          <Send className="h-4 w-4 mr-2" />
                          Send Reply
                        </Button>
                        <Button
                          variant="outline"
                          onClick={handleCloseReply}
                        >
                          Cancel
                        </Button>
                      </div>
                      <div className="text-sm text-gray-500">
                        Or <Link href="/login" className="text-primary underline">log in</Link> for full features
                      </div>
                    </div>
                  </div>
                )}
              </div>
            )}
          </div>

          {/* Action Panel - Hidden on mobile, collapsible on desktop */}
          <div className={cn(
            "hidden lg:block bg-white border-l border-gray-200 flex-shrink-0 overflow-y-auto transition-all duration-300",
            isActionsSidebarOpen ? "w-64 xl:w-80 p-4 xl:p-6" : "w-12 p-2"
          )}>
            <div className="relative">
              <Button
                variant="ghost"
                size="sm"
                className="absolute top-0 left-0 h-8 w-8 p-0"
                onClick={() => setIsActionsSidebarOpen(!isActionsSidebarOpen)}
              >
                {isActionsSidebarOpen ? (
                  <ChevronRight className="h-4 w-4" />
                ) : (
                  <ChevronLeft className="h-4 w-4" />
                )}
              </Button>
            </div>
            {isActionsSidebarOpen && (
              <>
                <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2 mt-8">
                  <Tag className="h-5 w-5" />
                  Quick Actions
                </h3>
                <div className="space-y-4">
              {/* Status */}
              <div>
                <Label className="text-sm font-medium text-gray-700">Status</Label>
                <Select
                  value={ticket.status || 'open'}
                  onValueChange={handleStatusChange}
                  disabled={updateTicketMutation.isPending || isTicketClosed || isNotAssignedToCurrentUser}
                >
                  <SelectTrigger className="w-full mt-1">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="open">Open</SelectItem>
                    <SelectItem value="pending">Pending</SelectItem>
                    <SelectItem value="resolved">Resolved</SelectItem>
                    <SelectItem value="closed">Closed</SelectItem>
                  </SelectContent>
                </Select>
                {isTicketClosed && (
                  <p className="text-xs text-red-600 mt-1">Ticket is closed and read-only</p>
                )}
              </div>

              {/* Priority */}
              <div>
                <Label className="text-sm font-medium text-gray-700">Priority</Label>
                <Select
                  value={ticket.priority || 'medium'}
                  onValueChange={(value) => updateTicketMutation.mutate({ priority: value })}
                  disabled={updateTicketMutation.isPending || isTicketClosed || isNotAssignedToCurrentUser}
                >
                  <SelectTrigger className="w-full mt-1">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="low">
                      <div className="flex items-center gap-2">
                        <div className={cn("h-2 w-2 rounded-full", getPriorityColor('low').dot)}></div>
                        Low
                      </div>
                    </SelectItem>
                    <SelectItem value="medium">
                      <div className="flex items-center gap-2">
                        <div className={cn("h-2 w-2 rounded-full", getPriorityColor('medium').dot)}></div>
                        Medium
                      </div>
                    </SelectItem>
                    <SelectItem value="high">
                      <div className="flex items-center gap-2">
                        <div className={cn("h-2 w-2 rounded-full", getPriorityColor('high').dot)}></div>
                        High
                      </div>
                    </SelectItem>
                    <SelectItem value="urgent">
                      <div className="flex items-center gap-2">
                        <div className={cn("h-2 w-2 rounded-full", getPriorityColor('urgent').dot)}></div>
                        Urgent
                      </div>
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>

              {/* Assigned Agent - Editable Dropdown */}
              <div>
                <Label className="text-sm font-medium text-gray-700">Assigned To</Label>
                <Select
                  value={ticket.assigned_agent_id || 'unassigned'}
                  onValueChange={(value) => {
                    const newAgentId = value === 'unassigned' ? null : value;
                    updateTicketMutation.mutate({ assigned_agent_id: newAgentId });
                  }}
                  disabled={
                    updateTicketMutation.isPending ||
                    isTicketClosed ||
                    (user?.role !== 'admin' && isNotAssignedToCurrentUser)
                  }
                >
                  <SelectTrigger className="mt-1 w-full [&>span]:line-clamp-none">
                    <SelectValue>
                      {ticket.assigned_agent_id ? (
                        <div className="flex items-center gap-2">
                          <User className="h-4 w-4 text-gray-500" />
                          <span>
                            {agents.find((agent: any) => agent.id === ticket.assigned_agent_id)?.name || 'Unknown Agent'}
                          </span>
                        </div>
                      ) : (
                        <span className={cn(
                          "italic",
                          (user?.role === 'admin' && !isTicketClosed) ? "text-gray-900" : "text-gray-500"
                        )}>
                          Unassigned
                        </span>
                      )}
                    </SelectValue>
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="unassigned">
                      <span className="text-gray-500 italic">Unassigned</span>
                    </SelectItem>
                    {agents.map((agent: any) => (
                      <SelectItem key={agent.id} value={agent.id}>
                        <div className="flex items-center gap-2">
                          <User className="h-4 w-4 text-gray-500" />
                          <span>{agent.name}</span>
                        </div>
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              {/* Quick Status Updates */}
              <div className="pt-4 border-t border-gray-200">
                <h4 className="text-sm font-medium text-gray-700 mb-3">Quick Status</h4>
                <div className="space-y-2">
                  <Button
                    variant="outline"
                    size="sm"
                    className="w-full justify-start text-green-700 border-green-200 hover:bg-green-50"
                    onClick={() => handleStatusChange('resolved')}
                    disabled={updateTicketMutation.isPending || isTicketClosed || isNotAssignedToCurrentUser}
                  >
                    <CheckCircle className="h-4 w-4 mr-2" />
                    Mark as Resolved
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    className="w-full justify-start text-gray-700 border-gray-200 hover:bg-gray-50"
                    onClick={() => handleStatusChange('closed')}
                    disabled={updateTicketMutation.isPending || isTicketClosed || isNotAssignedToCurrentUser}
                  >
                    <XCircle className="h-4 w-4 mr-2" />
                    Close Ticket
                  </Button>
                </div>
              </div>

              {/* Internal Notes */}
              <div className="pt-4 border-t border-gray-200">
                <div className="flex items-center justify-between mb-3">
                  <h4 className="text-sm font-medium text-gray-700">Internal Notes</h4>
                  <Button
                    size="sm"
                    variant="outline"
                    onClick={() => setShowAddNoteDialog(true)}
                    className="h-7 text-xs"
                  >
                    + Add Note
                  </Button>
                </div>
                <div className="space-y-2 max-h-64 overflow-y-auto pr-2">
                  {ticket?.comments
                    ?.filter((comment: TicketComment) => comment.is_internal_note && !comment.content?.startsWith('FORWARD_MESSAGE:'))
                    .length === 0 ? (
                    <p className="text-xs text-gray-500 italic">No internal notes yet</p>
                  ) : (
                    ticket?.comments
                      ?.filter((comment: TicketComment) => comment.is_internal_note && !comment.content?.startsWith('FORWARD_MESSAGE:'))
                      .sort((a: TicketComment, b: TicketComment) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime())
                      .map((note: TicketComment) => {
                        const isHidden = hiddenNoteIds.includes(note.id);
                        const isEditing = editingNoteId === note.id;

                        return (
                          <div
                            key={note.id}
                            className="bg-amber-50 border border-amber-200 rounded-lg text-sm overflow-hidden"
                          >
                            {/* Header - Always visible */}
                            <div className="flex items-center justify-between gap-2 p-2 bg-amber-100/50">
                              <div className="flex items-center gap-2 flex-1 min-w-0">
                                <User className="h-3 w-3 text-amber-700 flex-shrink-0" />
                                <span className="font-medium text-amber-900 text-xs truncate">
                                  {note.user?.name || 'Unknown User'}
                                </span>
                                <span className="text-xs text-amber-600">
                                  {format(new Date(note.created_at), 'MMM d, h:mm a')}
                                </span>
                              </div>
                              <div className="flex items-center gap-1 flex-shrink-0">
                                <Button
                                  variant="ghost"
                                  size="sm"
                                  className="h-6 w-6 p-0 text-amber-700 hover:text-amber-900 hover:bg-amber-200"
                                  onClick={() => setHiddenNoteIds(prev =>
                                    isHidden ? prev.filter(id => id !== note.id) : [...prev, note.id]
                                  )}
                                >
                                  {isHidden ? (
                                    <ChevronDown className="h-3 w-3" />
                                  ) : (
                                    <ChevronUp className="h-3 w-3" />
                                  )}
                                </Button>
                              </div>
                            </div>

                            {/* Content - Collapsible */}
                            {!isHidden && (
                              <div className="p-3">
                                {isEditing ? (
                                  <div className="space-y-2">
                                    <textarea
                                      value={editingNoteContent}
                                      onChange={(e) => setEditingNoteContent(e.target.value)}
                                      className="w-full min-h-[80px] px-2 py-1.5 text-xs border border-amber-300 rounded focus:outline-none focus:ring-2 focus:ring-amber-500 bg-white"
                                    />
                                    <div className="flex items-center gap-2">
                                      <Button
                                        size="sm"
                                        className="h-6 text-xs bg-amber-600 hover:bg-amber-700"
                                        onClick={async () => {
                                          try {
                                            await api.messages.update(note.id, {
                                              content: editingNoteContent,
                                              is_internal_note: true
                                            });
                                            queryClient.invalidateQueries({ queryKey: ['ticket', ticketId] });
                                            setEditingNoteId(null);
                                            setEditingNoteContent('');
                                            toast.success('Note updated');
                                          } catch (error) {
                                            console.error('Failed to update note:', error);
                                            toast.error('Failed to update note');
                                          }
                                        }}
                                      >
                                        Save
                                      </Button>
                                      <Button
                                        size="sm"
                                        variant="outline"
                                        className="h-6 text-xs"
                                        onClick={() => {
                                          setEditingNoteId(null);
                                          setEditingNoteContent('');
                                        }}
                                      >
                                        Cancel
                                      </Button>
                                    </div>
                                  </div>
                                ) : (
                                  <>
                                    <p className="text-amber-900 whitespace-pre-wrap text-xs leading-relaxed mb-2">
                                      {note.content}
                                    </p>
                                    {/* Only show edit/delete buttons if current user is the author */}
                                    {note.user_id === user?.id && (
                                      <div className="flex items-center gap-1 pt-2 border-t border-amber-200">
                                        <Button
                                          variant="ghost"
                                          size="sm"
                                          className="h-6 px-2 text-xs text-amber-700 hover:text-amber-900 hover:bg-amber-100"
                                          onClick={() => {
                                            setEditingNoteId(note.id);
                                            setEditingNoteContent(note.content);
                                          }}
                                        >
                                          <Edit3 className="h-3 w-3 mr-1" />
                                          Edit
                                        </Button>
                                        <Button
                                          variant="ghost"
                                          size="sm"
                                          className="h-6 px-2 text-xs text-red-700 hover:text-red-900 hover:bg-red-100"
                                          onClick={() => setDeleteNoteId(note.id)}
                                        >
                                          <XCircle className="h-3 w-3 mr-1" />
                                          Delete
                                        </Button>
                                      </div>
                                    )}
                                  </>
                                )}
                              </div>
                            )}
                          </div>
                        );
                      })
                  )}
                </div>
              </div>
                </div>
              </>
            )}
          </div>
        </div>

        {/* Edit Dialog */}
        <Dialog open={isEditDialogOpen} onOpenChange={setIsEditDialogOpen}>
          <DialogContent className="sm:max-w-[600px]">
            <DialogHeader>
              <DialogTitle>Edit Ticket</DialogTitle>
              <DialogDescription>
                Modify the ticket details below.
              </DialogDescription>
            </DialogHeader>
            {editedTicket && (
              <div className="space-y-4">
                <div>
                  <Label htmlFor="edit-subject">Subject</Label>
                  <Input
                    id="edit-subject"
                    value={editedTicket.subject}
                    onChange={(e) => setEditedTicket({ ...editedTicket, subject: e.target.value })}
                    placeholder="Enter ticket subject"
                  />
                </div>
                <div>
                  <Label htmlFor="edit-description">Description</Label>
                  <Textarea
                    id="edit-description"
                    value={editedTicket.description}
                    onChange={(e) => setEditedTicket({ ...editedTicket, description: e.target.value })}
                    placeholder="Enter ticket description"
                    className="min-h-[150px]"
                  />
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <Label>Priority</Label>
                    <Select
                      value={editedTicket.priority}
                      onValueChange={(value) => setEditedTicket({ ...editedTicket, priority: value })}
                    >
                      <SelectTrigger>
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="low">Low</SelectItem>
                        <SelectItem value="medium">Medium</SelectItem>
                        <SelectItem value="high">High</SelectItem>
                        <SelectItem value="urgent">Urgent</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div>
                    <Label>Status</Label>
                    <Select
                      value={editedTicket.status}
                      onValueChange={(value) => setEditedTicket({ ...editedTicket, status: value })}
                    >
                      <SelectTrigger>
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="open">Open</SelectItem>
                        <SelectItem value="pending">Pending</SelectItem>
                        <SelectItem value="resolved">Resolved</SelectItem>
                        <SelectItem value="closed">Closed</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                </div>
                <div>
                  <Label>Category</Label>
                  <Select
                    value={editedTicket.category_id || "uncategorized"}
                    onValueChange={(value) => setEditedTicket({ ...editedTicket, category_id: value === "uncategorized" ? null : value })}
                  >
                    <SelectTrigger>
                      <SelectValue placeholder="Select a category" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="uncategorized">No Category</SelectItem>
                      {categories.map((category: any) => (
                        <SelectItem key={category.id} value={category.id}>
                          {category.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              </div>
            )}
            <DialogFooter>
              <Button variant="outline" onClick={() => setIsEditDialogOpen(false)}>
                Cancel
              </Button>
              <Button
                onClick={handleEditTicket}
                disabled={editTicketMutation.isPending}
              >
                <Edit3 className="h-4 w-4 mr-2" />
                Save Changes
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        {/* Delete Confirmation Dialog */}
        <Dialog open={isDeleteDialogOpen} onOpenChange={setIsDeleteDialogOpen}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Delete Ticket</DialogTitle>
              <DialogDescription>
                Are you sure you want to delete this ticket? This action cannot be undone.
              </DialogDescription>
            </DialogHeader>
            <div className="bg-red-50 border border-red-200 rounded-lg p-4">
              <div className="flex items-start gap-3">
                <AlertCircle className="h-5 w-5 text-red-600 flex-shrink-0 mt-0.5" />
                <div className="space-y-1">
                  <p className="text-sm font-medium text-red-800">
                    Ticket #{ticket?.ticket_number || 'N/A'}
                  </p>
                  <p className="text-sm text-red-700">
                    {ticket?.subject || 'No Subject'}
                  </p>
                  <p className="text-xs text-red-600">
                    All comments and attachments will be permanently deleted.
                  </p>
                </div>
              </div>
            </div>
            <DialogFooter>
              <Button
                variant="outline"
                onClick={() => setIsDeleteDialogOpen(false)}
              >
                Cancel
              </Button>
              <Button
                variant="destructive"
                onClick={handleDeleteTicket}
                disabled={deleteTicketMutation.isPending}
              >
                <XCircle className="h-4 w-4 mr-2" />
                Delete Ticket
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        {/* Forward Dialog */}
        <Dialog open={isForwardDialogOpen} onOpenChange={setIsForwardDialogOpen}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Forward Ticket</DialogTitle>
              <DialogDescription>
                Assign this ticket to a user and optionally add a message that will appear above the ticket.
              </DialogDescription>
            </DialogHeader>
            <div className="space-y-4">
              <div>
                <Label>Assign To</Label>
                <Select value={selectedAgent} onValueChange={setSelectedAgent}>
                  <SelectTrigger>
                    <SelectValue placeholder="Select a user to assign" />
                  </SelectTrigger>
                  <SelectContent>
                    {agents
                      .filter((agent: any) => agent.id !== ticket.assigned_agent_id)
                      .map((agent: any) => (
                        <SelectItem key={agent.id} value={agent.id}>
                          {agent.name} ({agent.email}) - {agent.role}
                        </SelectItem>
                      ))}
                  </SelectContent>
                </Select>
                {ticket.assigned_agent_id && (
                  <p className="text-xs text-gray-500 mt-1">
                    Currently assigned to: {agents.find((agent: any) => agent.id === ticket.assigned_agent_id)?.name}
                  </p>
                )}
              </div>
              <div>
                <Label>Message (Optional)</Label>
                <Textarea
                  value={forwardMessage}
                  onChange={(e) => setForwardMessage(e.target.value)}
                  placeholder="Add a message that will appear in a yellow box above the ticket..."
                  rows={4}
                />
              </div>
            </div>
            <DialogFooter>
              <Button variant="outline" onClick={() => setIsForwardDialogOpen(false)}>
                Cancel
              </Button>
              <Button
                onClick={handleForwardTicket}
                disabled={!selectedAgent || updateTicketMutation.isPending}
              >
                <Forward className="h-4 w-4 mr-2" />
                Forward Ticket
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        {/* Delete Note Confirmation Dialog */}
        <Dialog open={!!deleteNoteId} onOpenChange={(open) => !open && setDeleteNoteId(null)}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Delete Internal Note</DialogTitle>
              <DialogDescription>
                Are you sure you want to delete this internal note? This action cannot be undone.
              </DialogDescription>
            </DialogHeader>
            <div className="bg-red-50 border border-red-200 rounded-lg p-4">
              <div className="flex items-start gap-3">
                <AlertCircle className="h-5 w-5 text-red-600 flex-shrink-0 mt-0.5" />
                <div className="text-sm text-red-800">
                  This will permanently delete the internal note and it will no longer be visible to any team members.
                </div>
              </div>
            </div>
            <DialogFooter>
              <Button
                variant="outline"
                onClick={() => setDeleteNoteId(null)}
              >
                Cancel
              </Button>
              <Button
                variant="destructive"
                onClick={async () => {
                  try {
                    await api.messages.delete(deleteNoteId!);
                    queryClient.invalidateQueries({ queryKey: ['ticket', ticketId] });
                    setDeleteNoteId(null);
                    toast.success('Internal note deleted');
                  } catch (error) {
                    console.error('Failed to delete note:', error);
                    toast.error('Failed to delete note');
                  }
                }}
              >
                <XCircle className="h-4 w-4 mr-2" />
                Delete Note
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        {/* Close Ticket Confirmation Dialog */}
        <Dialog open={isCloseConfirmDialogOpen} onOpenChange={setIsCloseConfirmDialogOpen}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Close Ticket</DialogTitle>
              <DialogDescription>
                Are you sure you want to close this ticket?
              </DialogDescription>
            </DialogHeader>
            <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
              <div className="flex items-start gap-3">
                <AlertCircle className="h-5 w-5 text-yellow-600 flex-shrink-0 mt-0.5" />
                <div className="space-y-1">
                  <p className="text-sm font-medium text-yellow-800">
                    Important: This action will make the ticket read-only
                  </p>
                  <p className="text-sm text-yellow-700">
                    Once closed, you will not be able to:
                  </p>
                  <ul className="text-sm text-yellow-700 list-disc list-inside space-y-1 ml-2">
                    <li>Change the status, priority, or assignment</li>
                    <li>Add new replies or comments</li>
                    <li>Modify any ticket details</li>
                  </ul>
                  <p className="text-xs text-yellow-600 mt-2">
                    Closed tickets can only be viewed, not edited.
                  </p>
                </div>
              </div>
            </div>
            <DialogFooter>
              <Button
                variant="outline"
                onClick={() => {
                  setIsCloseConfirmDialogOpen(false);
                  setPendingStatusChange(null);
                }}
              >
                Cancel
              </Button>
              <Button
                variant="destructive"
                onClick={handleConfirmClose}
                disabled={updateTicketMutation.isPending}
              >
                <XCircle className="h-4 w-4 mr-2" />
                Yes, Close Ticket
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        {/* Add Internal Note Dialog */}
        <Dialog open={showAddNoteDialog} onOpenChange={setShowAddNoteDialog}>
          <DialogContent className="sm:max-w-[500px]">
            <DialogHeader>
              <DialogTitle>Add Internal Note</DialogTitle>
              <DialogDescription>
                Create a private note visible to agents assigned to this ticket
              </DialogDescription>
            </DialogHeader>
            <div className="space-y-4 py-4">
              <div>
                <Label htmlFor="note-content">Note Content</Label>
                <textarea
                  id="note-content"
                  className="w-full min-h-[120px] px-3 py-2 mt-1 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="Enter your internal note..."
                  value={noteContent}
                  onChange={(e) => setNoteContent(e.target.value)}
                />
              </div>
            </div>
            <DialogFooter>
              <Button variant="outline" onClick={() => {
                setShowAddNoteDialog(false);
                setNoteContent('');
              }}>
                Cancel
              </Button>
              <Button
                onClick={async () => {
                  try {
                    // Create internal note via API
                    await api.tickets.addComment(
                      ticketId as string,
                      noteContent,
                      true, // isInternal
                      undefined, // clientEmail
                      undefined // attachments
                      // No visibleToAgents - visible to assigned agents by default
                    );

                    // Refresh ticket data to show new note
                    queryClient.invalidateQueries({ queryKey: ['ticket', ticketId] });

                    // Close dialog and reset
                    setShowAddNoteDialog(false);
                    setNoteContent('');

                    toast.success('Internal note added successfully');
                  } catch (error) {
                    console.error('Failed to add internal note:', error);
                    toast.error('Failed to add internal note');
                  }
                }}
                disabled={!noteContent.trim()}
              >
                Add Note
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </TooltipProvider>
    </div>
  );
}