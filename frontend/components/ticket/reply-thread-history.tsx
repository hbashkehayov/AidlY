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