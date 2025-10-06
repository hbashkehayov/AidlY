'use client';

import { useState, useEffect } from 'react';
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
  ChevronDown,
  ChevronUp,
  Download,
  FileText,
  FileImage,
  File,
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

const formatFileSize = (bytes: number): string => {
  if (bytes === 0) return '0 Bytes';
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

const cleanEmailThreadMetadata = (content: string): string => {
  if (!content) return content;

  // Remove HTML tags first
  let cleaned = content.replace(/<[^>]*>/g, '');

  // Split by common footer/signature patterns and take only the first part
  const footerPatterns = [
    /On\s+.+?(wrote|said):/i,
    /Ticket Update:/i,
    /From:/i,
    /View Full Ticket/i,
    /This is an automated message/i,
    /Best regards/i,
    /Sincerely/i,
    /Thanks/i,
    /Regards/i,
    /Sent from/i,
    /Get Outlook/i,
    /Ticket\s*#[A-Z]+-\d+/i,
    /[-_]{2,}/,  // Signature separator
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

  // Remove quoted text that starts with >
  cleaned = cleaned.replace(/^>.*$/gm, '');

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

  // Mutations
  const updateTicketMutation = useMutation({
    mutationFn: async (data: any) => {
      const response = await api.tickets.update(ticketId as string, data);
      return response.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['ticket', ticketId] });
      toast.success('Ticket updated successfully');
    },
    onError: (error: any) => {
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
      queryClient.invalidateQueries({ queryKey: ['ticket', ticketId] });
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
        <Button onClick={() => router.push('/tickets')} variant="outline">
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

  return (
    <div className="min-h-screen bg-gray-50 overflow-x-hidden">
      <TooltipProvider>
        {/* Streamlined Header */}
        <div className="bg-white border-b border-gray-200 sticky top-0 z-50 shadow-sm">
          <div className="w-full px-3 md:px-6">
            <div className="flex items-center justify-between h-16">
              <div className="flex items-center gap-4">
                <Button
                  onClick={() => router.push('/tickets')}
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

              <div className="flex items-center gap-2">
                {!isAuthenticated && (
                  <Button
                    asChild
                    variant="outline"
                    size="sm"
                  >
                    <Link href="/auth/login">
                      <User className="h-4 w-4 mr-1" />
                      Login
                    </Link>
                  </Button>
                )}
                {isAuthenticated && (
                  <div className="flex items-center gap-2 text-sm text-gray-600">
                    <User className="h-4 w-4" />
                    <span>{user?.name}</span>
                    <span className="text-gray-400">|</span>
                  </div>
                )}
                <Button
                  onClick={handleToggleReply}
                  variant={showComposeReply ? "default" : "outline"}
                  size="sm"
                  className={showComposeReply ? "bg-gray-900 hover:bg-gray-800 text-white" : ""}
                >
                  <Reply className="h-4 w-4 mr-1" />
                  Reply
                </Button>

                <Button
                  onClick={() => setIsForwardDialogOpen(true)}
                  variant="outline"
                  size="sm"
                >
                  <Forward className="h-4 w-4 mr-1" />
                  Forward
                </Button>
              </div>
            </div>
          </div>
        </div>

        {/* Three-panel layout - Responsive */}
        <div className="flex flex-col lg:flex-row h-auto lg:h-[calc(100vh-4rem)]">
          {/* Client Panel - Hidden on mobile, shown on desktop */}
          <div className="hidden lg:flex w-64 xl:w-80 bg-white border-r border-gray-200 flex-col flex-shrink-0">
            <div className="p-6 border-b border-gray-200">
              <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                <User className="h-5 w-5" />
                Client
              </h3>
              <div className="space-y-4">
                <div className="flex items-center gap-3">
                  <Avatar className="h-12 w-12">
                    <AvatarFallback className="bg-gray-100 text-gray-900 text-lg font-semibold">
                      {clientInfo.name.charAt(0).toUpperCase()}
                    </AvatarFallback>
                  </Avatar>
                  <div className="flex-1 min-w-0">
                    <h4 className="font-semibold text-gray-900">{clientInfo.name}</h4>
                    <p className="text-sm text-gray-600 truncate">{clientInfo.email}</p>
                  </div>
                </div>

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
              </div>
            </div>
          </div>

          {/* Conversation Panel - Main content */}
          <div className="flex-1 flex flex-col bg-white min-w-0 overflow-hidden">
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
            <div className="flex-1 overflow-y-auto p-4 lg:p-6">
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
                  {hasExternalImages(ticket.description) && !showExternalImages['ticket-main'] && (
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

                  {/* Render full HTML email with iframe for security */}
                  {ticket.description && (ticket.description.includes('<html') || ticket.description.includes('<body') || ticket.description.includes('<table') || ticket.description.includes('<div')) ? (
                    ticket.description.includes('<html') ? (
                      <iframe
                        srcDoc={showExternalImages['ticket-main'] ? ticket.description : blockExternalImages(ticket.description)}
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
                    ) : (
                      <div className="prose prose-sm max-w-none">
                        <div
                          className="email-content"
                          dangerouslySetInnerHTML={{
                            __html: showExternalImages['ticket-main']
                              ? ticket.description || ''
                              : blockExternalImages(ticket.description || '')
                          }}
                          style={{ wordBreak: 'break-word', overflowWrap: 'break-word' }}
                        />
                      </div>
                    )
                  ) : (
                    <pre className="whitespace-pre-wrap font-sans text-sm text-gray-700">
                      {ticket.description}
                    </pre>
                  )}
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
                          const FileIcon = getFileIcon(attachment.mime_type, attachment.filename);
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
                                  {attachment.filename}
                                </p>
                                <p className="text-xs text-gray-500">
                                  {formatFileSize(attachment.size)}
                                </p>
                              </div>
                              <Button
                                variant="ghost"
                                size="sm"
                                className="opacity-0 group-hover:opacity-100 transition-opacity"
                                onClick={() => handleDownloadAttachment(attachment.id, attachment.filename)}
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
                <div className="px-4 pb-4 flex items-center gap-2">
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setShowComposeReply(true)}
                    className="gap-2"
                  >
                    <Reply className="h-4 w-4" />
                    Reply
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setIsForwardDialogOpen(true)}
                    className="gap-2"
                  >
                    <Forward className="h-4 w-4" />
                    Forward
                  </Button>
                </div>
              </div>

              {/* Comments - Chat-Style Message Thread */}
              {ticket.comments && ticket.comments.length > 0 && (
                <div className="space-y-6">
                  {ticket.comments
                    .filter((comment: TicketComment) =>
                      // Filter out forward messages (they're shown above)
                      !(comment.is_internal_note && comment.content?.startsWith('FORWARD_MESSAGE:'))
                    )
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

                    return (
                      <div
                        key={comment.id}
                        className={cn(
                          "flex gap-3",
                          isFromAgent ? "flex-row-reverse" : "flex-row"
                        )}
                      >
                        {/* Avatar */}
                        <Avatar className={cn(
                          "h-10 w-10 flex-shrink-0",
                          isFromAgent && "ring-2 ring-indigo-100"
                        )}>
                          <AvatarFallback
                            className={cn(
                              "font-semibold text-white",
                              comment.is_internal_note
                                ? "bg-amber-500"
                                : isFromAgent
                                  ? "bg-indigo-600"
                                  : "bg-blue-500"
                            )}
                          >
                            {initials}
                          </AvatarFallback>
                        </Avatar>

                        {/* Message Bubble */}
                        <div className={cn(
                          "flex-1 max-w-[85%]",
                          isFromAgent ? "ml-auto" : "mr-auto"
                        )}>
                          {/* Sender Info Header */}
                          <div className={cn(
                            "flex items-center gap-2 mb-2",
                            isFromAgent ? "flex-row-reverse" : "flex-row"
                          )}>
                            <span className={cn(
                              "font-semibold text-sm",
                              comment.is_internal_note
                                ? "text-amber-700"
                                : isFromAgent
                                  ? "text-indigo-900"
                                  : "text-blue-900"
                            )}>
                              {senderName}
                            </span>
                            <Badge
                              variant="outline"
                              className={cn(
                                "text-xs font-medium",
                                comment.is_internal_note
                                  ? "bg-amber-50 text-amber-700 border-amber-300"
                                  : isFromAgent
                                    ? "bg-indigo-50 text-indigo-700 border-indigo-200"
                                    : "bg-blue-50 text-blue-700 border-blue-200"
                              )}
                            >
                              {comment.is_internal_note ? '🔒 Internal Note' : isFromAgent ? '👤 Agent' : '💬 Client'}
                            </Badge>
                            {senderEmail && (
                              <span className="text-xs text-gray-500">
                                {senderEmail}
                              </span>
                            )}
                            <span className="text-xs text-gray-500">
                              {format(new Date(comment.created_at), 'MMM d, h:mm a')}
                            </span>
                          </div>

                          {/* Message Content Card */}
                          <div
                            className={cn(
                              "rounded-2xl shadow-sm transition-all border",
                              comment.is_internal_note
                                ? "bg-amber-50 border-amber-200"
                                : isFromAgent
                                  ? "bg-white border-gray-200"
                                  : "bg-blue-50 border-blue-200"
                            )}
                          >
                            <div className="p-4">
                              <div className={cn(
                                "flex items-start justify-between gap-2 mb-2",
                                !isExpanded && "mb-0"
                              )}>
                                <div className="flex-1 min-w-0">

                                  {/* Collapsed preview */}
                                  {!isExpanded && (
                                    <p className="text-sm text-gray-700 line-clamp-2">
                                      {cleanEmailThreadMetadata(comment.body_plain || displayContent || '').substring(0, 150) || 'No content'}
                                    </p>
                                  )}
                                </div>

                                {/* Expand/Collapse Button */}
                                <Button
                                  variant="ghost"
                                  size="sm"
                                  className="h-8 w-8 p-0 flex-shrink-0"
                                  onClick={() => setExpandedEmails(prev => ({ ...prev, [comment.id]: !isExpanded }))}
                                >
                                  {isExpanded ? (
                                    <ChevronUp className="h-4 w-4 text-gray-600" />
                                  ) : (
                                    <ChevronDown className="h-4 w-4 text-gray-600" />
                                  )}
                                </Button>
                              </div>

                              {/* Attachments preview when collapsed */}
                              {!isExpanded && comment.attachments && comment.attachments.length > 0 && (
                                <div className="flex items-center gap-1 mt-2 text-xs text-gray-500">
                                  <Paperclip className="h-3 w-3" />
                                  <span>{comment.attachments.length} attachment{comment.attachments.length !== 1 && 's'}</span>
                                </div>
                              )}

                              {/* Expanded Message Body */}
                              {isExpanded && (
                                <div className="mt-3 pt-3 border-t border-gray-200">
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
                                srcDoc={showExternalImages[comment.id] ? displayContent : blockExternalImages(displayContent)}
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
                            ) : (
                              <div className="prose prose-sm max-w-none">
                                <div
                                  className="email-content"
                                  dangerouslySetInnerHTML={{
                                    __html: showExternalImages[comment.id]
                                      ? displayContent
                                      : blockExternalImages(displayContent)
                                  }}
                                  style={{ wordBreak: 'break-word', overflowWrap: 'break-word' }}
                                />
                              </div>
                            )}

                            {comment.attachments && comment.attachments.length > 0 && (
                              <div className="mt-4 pt-4 border-t border-gray-200">
                                <div className="flex items-center gap-2 mb-3">
                                  <Paperclip className="h-4 w-4 text-gray-500" />
                                  <span className="text-sm font-medium text-gray-700">
                                    {comment.attachments.length} attachment{comment.attachments.length !== 1 && 's'}
                                  </span>
                                </div>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                                  {comment.attachments.map((attachment: any) => {
                                    const FileIcon = getFileIcon(attachment.mime_type, attachment.filename);
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
                                            {attachment.filename}
                                          </p>
                                          <p className="text-xs text-gray-500">
                                            {formatFileSize(attachment.size)}
                                          </p>
                                        </div>
                                        <Button
                                          variant="ghost"
                                          size="sm"
                                          className="opacity-0 group-hover:opacity-100 transition-opacity"
                                          onClick={() => handleDownloadAttachment(attachment.id, attachment.filename)}
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
                              )}
                            </div>
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
              <div className={cn(
                "border-t border-gray-200 p-6 bg-gray-50 transition-all duration-200",
                isClosingReply
                  ? "animate-out slide-out-to-bottom-4 fade-out"
                  : "animate-in slide-in-from-bottom-4 fade-in"
              )}>
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
                        Or <Link href="/auth/login" className="text-primary underline">log in</Link> for full features
                      </div>
                    </div>
                  </div>
                )}
              </div>
            )}
          </div>

          {/* Action Panel - Hidden on mobile */}
          <div className="hidden lg:block w-64 xl:w-80 bg-white border-l border-gray-200 p-4 xl:p-6 flex-shrink-0 overflow-y-auto">
            <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
              <Tag className="h-5 w-5" />
              Quick Actions
            </h3>

            <div className="space-y-4">
              {/* Status */}
              <div>
                <Label className="text-sm font-medium text-gray-700">Status</Label>
                <Select
                  value={ticket.status || 'open'}
                  onValueChange={(value) => updateTicketMutation.mutate({ status: value })}
                  disabled={updateTicketMutation.isPending}
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
              </div>

              {/* Priority */}
              <div>
                <Label className="text-sm font-medium text-gray-700">Priority</Label>
                <Select
                  value={ticket.priority || 'medium'}
                  onValueChange={(value) => updateTicketMutation.mutate({ priority: value })}
                  disabled={updateTicketMutation.isPending}
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

              {/* Assigned Agent - Read Only */}
              <div>
                <Label className="text-sm font-medium text-gray-700">Assigned To</Label>
                <div className="mt-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-md text-sm text-gray-700">
                  {ticket.assigned_agent_id ? (
                    <div className="flex items-center gap-2">
                      <User className="h-4 w-4 text-gray-500" />
                      <span>
                        {agents.find((agent: any) => agent.id === ticket.assigned_agent_id)?.name || 'Unknown Agent'}
                      </span>
                    </div>
                  ) : (
                    <span className="text-gray-500 italic">Unassigned</span>
                  )}
                </div>
              </div>

              {/* Quick Status Updates */}
              <div className="pt-4 border-t border-gray-200">
                <h4 className="text-sm font-medium text-gray-700 mb-3">Quick Status</h4>
                <div className="space-y-2">
                  <Button
                    variant="outline"
                    size="sm"
                    className="w-full justify-start text-green-700 border-green-200 hover:bg-green-50"
                    onClick={() => updateTicketMutation.mutate({ status: 'resolved' })}
                    disabled={updateTicketMutation.isPending}
                  >
                    <CheckCircle className="h-4 w-4 mr-2" />
                    Mark as Resolved
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    className="w-full justify-start text-yellow-700 border-yellow-200 hover:bg-yellow-50"
                    onClick={() => updateTicketMutation.mutate({ status: 'pending' })}
                    disabled={updateTicketMutation.isPending}
                  >
                    <Clock className="h-4 w-4 mr-2" />
                    Mark as Pending
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    className="w-full justify-start text-gray-700 border-gray-200 hover:bg-gray-50"
                    onClick={() => updateTicketMutation.mutate({ status: 'closed' })}
                    disabled={updateTicketMutation.isPending}
                  >
                    <XCircle className="h-4 w-4 mr-2" />
                    Close Ticket
                  </Button>
                </div>
              </div>
            </div>
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
      </TooltipProvider>
    </div>
  );
}