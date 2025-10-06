'use client';

import { useState } from 'react';
import { format } from 'date-fns';
import {
  MessageSquare,
  FileText,
  Eye,
  EyeOff,
  Reply,
  ChevronDown,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

// Utility function to format file sizes
function formatFileSize(bytes: number): string {
  if (!bytes || bytes === 0) return '0 B';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`;
}

interface TicketComment {
  id: string;
  user_id?: string;
  client_id?: string;
  content: string;
  is_internal_note: boolean;
  created_at: string;
  attachments?: any[];
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

interface ReplyThreadHistoryProps {
  comments: TicketComment[];
  className?: string;
}

export function ReplyThreadHistory({
  comments = [],
  className,
}: ReplyThreadHistoryProps) {
  const [showInternalNotes, setShowInternalNotes] = useState(true);
  const [isThreadOpen, setIsThreadOpen] = useState(true);

  // Filter comments based on internal notes visibility
  const visibleComments = showInternalNotes
    ? comments
    : comments.filter((c) => !c.is_internal_note);

  return (
    <Card className={cn('h-fit', className)}>
      <CardHeader
        className="cursor-pointer select-none hover:bg-accent/50 transition-colors pb-3"
        onClick={() => setIsThreadOpen(!isThreadOpen)}
      >
        <div className="flex justify-between items-center">
          <CardTitle className="text-lg flex items-center gap-2">
            <MessageSquare className="h-5 w-5" />
            Reply Thread
            {visibleComments.length > 0 && (
              <Badge variant="secondary" className="ml-2">
                {visibleComments.length}
              </Badge>
            )}
          </CardTitle>
          <div className="flex items-center gap-4">
            <div className="flex items-center gap-2">
              <Label htmlFor="show-internal" className="text-sm cursor-pointer">
                {showInternalNotes ? <Eye className="h-4 w-4" /> : <EyeOff className="h-4 w-4" />}
              </Label>
              <Switch
                id="show-internal"
                checked={showInternalNotes}
                onCheckedChange={setShowInternalNotes}
                onClick={(e) => e.stopPropagation()}
              />
              <span className="text-sm text-muted-foreground">Internal notes</span>
            </div>
            <div
              className={cn(
                'transition-transform duration-200',
                isThreadOpen && 'rotate-180'
              )}
            >
              <ChevronDown className="h-5 w-5" />
            </div>
          </div>
        </div>
      </CardHeader>

      <div
        className={cn(
          'transition-all duration-300 ease-in-out',
          isThreadOpen
            ? 'max-h-[2000px] opacity-100'
            : 'max-h-0 opacity-0 overflow-hidden'
        )}
      >
        <CardContent className="pt-0">
          {/* Comments History */}
          {visibleComments.length > 0 && (
            <ScrollArea className="h-[400px] pr-4 mb-6">
              <div className="space-y-4">
                {visibleComments.map((comment) => (
                  <div
                    key={comment.id}
                    className={cn(
                      'p-4 rounded-lg border transition-all',
                      comment.is_internal_note
                        ? 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-800'
                        : comment.user_id
                          ? 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800'
                          : 'bg-background'
                    )}
                  >
                    <div className="flex items-start gap-3">
                      <Avatar className="h-8 w-8">
                        <AvatarFallback className="text-xs">
                          {comment.user?.name?.charAt(0) ||
                            comment.client?.name?.charAt(0) ||
                            '?'}
                        </AvatarFallback>
                      </Avatar>
                      <div className="flex-1 space-y-1">
                        <div className="flex items-center justify-between">
                          <div className="flex items-center gap-2">
                            <p className="text-sm font-semibold">
                              {comment.user?.name ||
                                comment.client?.name ||
                                'Unknown'}
                            </p>
                            {comment.user?.email && (
                              <span className="text-xs text-muted-foreground">
                                ({comment.user.email})
                              </span>
                            )}
                            {comment.is_internal_note && (
                              <Badge variant="outline" className="text-xs">
                                <FileText className="h-3 w-3 mr-1" />
                                Internal
                              </Badge>
                            )}
                            {comment.user_id && !comment.is_internal_note && (
                              <Badge variant="outline" className="text-xs">
                                <Reply className="h-3 w-3 mr-1" />
                                Agent Reply
                              </Badge>
                            )}
                          </div>
                          <time className="text-xs text-muted-foreground">
                            {format(new Date(comment.created_at), 'PPp')}
                          </time>
                        </div>
                        <div
                          className="text-sm text-foreground prose prose-sm max-w-none dark:prose-invert"
                          dangerouslySetInnerHTML={{ __html: comment.content }}
                        />

                        {/* Display attachments for this comment */}
                        {comment.attachments && comment.attachments.length > 0 && (
                          <div className="mt-3 pt-3 border-t space-y-2">
                            <p className="text-xs font-medium text-muted-foreground flex items-center gap-1">
                              <svg className="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                              </svg>
                              {comment.attachments.length} attachment{comment.attachments.length !== 1 ? 's' : ''}
                            </p>
                            <div className="space-y-1">
                              {comment.attachments.map((attachment: any, idx: number) => (
                                <a
                                  key={attachment.id || idx}
                                  href={attachment.url || attachment.download_url || `/api/v1/attachments/${attachment.id}/download`}
                                  download
                                  target="_blank"
                                  rel="noopener noreferrer"
                                  className="flex items-center gap-2 px-2 py-1.5 text-xs bg-muted/50 hover:bg-muted rounded border border-border/50 hover:border-border transition-colors group"
                                >
                                  <svg className="h-3.5 w-3.5 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                  </svg>
                                  <span className="flex-1 truncate font-medium text-foreground group-hover:text-primary">
                                    {attachment.file_name || attachment.filename || 'Attachment'}
                                  </span>
                                  {attachment.file_size && (
                                    <span className="text-muted-foreground">
                                      {formatFileSize(attachment.file_size)}
                                    </span>
                                  )}
                                  <svg className="h-3 w-3 text-muted-foreground opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                  </svg>
                                </a>
                              ))}
                            </div>
                          </div>
                        )}
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </ScrollArea>
          )}

        </CardContent>
      </div>
    </Card>
  );
}