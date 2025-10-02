'use client';

import { useEditor, EditorContent } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Placeholder from '@tiptap/extension-placeholder';
import Underline from '@tiptap/extension-underline';
import Link from '@tiptap/extension-link';
import TextAlign from '@tiptap/extension-text-align';
import {
  Bold,
  Italic,
  Underline as UnderlineIcon,
  List,
  ListOrdered,
  Quote,
  Undo,
  Redo,
  Link as LinkIcon,
  AlignLeft,
  AlignCenter,
  AlignRight,
  Maximize2,
  Minimize2,
  Sparkles,
  Loader2,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Toggle } from '@/components/ui/toggle';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';
import { useState } from 'react';
import { toast } from 'sonner';
import api from '@/lib/api';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';

interface RichTextEditorProps {
  content?: string;
  onChange?: (content: string) => void;
  placeholder?: string;
  className?: string;
  expandable?: boolean;
  minHeight?: string;
  maxHeight?: string;
  ticketId?: string;
  enableAI?: boolean;
}

export function RichTextEditor({
  content = '',
  onChange,
  placeholder = 'Write your response...',
  className,
  expandable = true,
  minHeight = '200px',
  maxHeight = '500px',
  ticketId,
  enableAI = true,
}: RichTextEditorProps) {
  const [isExpanded, setIsExpanded] = useState(false);
  const [isGenerating, setIsGenerating] = useState(false);
  const [showAIDialog, setShowAIDialog] = useState(false);
  const [editedSuggestion, setEditedSuggestion] = useState('');

  const editor = useEditor({
    extensions: [
      StarterKit.configure({
        heading: false,
      }),
      Placeholder.configure({
        placeholder,
      }),
      Underline,
      Link.configure({
        openOnClick: false,
        HTMLAttributes: {
          class: 'text-primary underline',
        },
      }),
      TextAlign.configure({
        types: ['paragraph'],
      }),
    ],
    content,
    onUpdate: ({ editor }) => {
      onChange?.(editor.getHTML());
    },
    editorProps: {
      attributes: {
        class: cn(
          'prose prose-sm dark:prose-invert max-w-none p-4 focus:outline-none',
          'overflow-y-auto transition-all duration-300 ease-in-out',
          className
        ),
        style: `min-height: ${isExpanded ? '400px' : minHeight}; max-height: ${isExpanded ? '80vh' : maxHeight};`,
      },
    },
    immediatelyRender: false, // Prevent SSR hydration issues
  });

  if (!editor) {
    return null;
  }

  const addLink = () => {
    const url = window.prompt('Enter URL:');
    if (url) {
      editor.chain().focus().setLink({ href: url }).run();
    }
  };

  const handleAIAutoWrite = async () => {
    if (!ticketId) {
      toast.error('Ticket ID is required for AI auto-write');
      return;
    }

    setIsGenerating(true);

    try {
      // Call the AI service auto-write endpoint using the API client
      const response = await api.ai.autoWrite({
        ticket_id: ticketId,
        context: editor.getText(),
        tone: 'professional',
        length: 'medium',
      });

      if (response.data?.success && response.data?.data?.text) {
        const aiText = response.data.data.text;
        const source = response.data.data.source;

        // Set the AI suggestion and show dialog for review
        setEditedSuggestion(aiText);
        setShowAIDialog(true);

        // Show appropriate toast based on source
        if (source === 'fallback') {
          toast.info('AI is not enabled. Using example text. Add your AI API key to get real AI suggestions.', {
            duration: 5000,
          });
        } else {
          toast.success('AI text generated successfully!');
        }
      } else {
        toast.error('Failed to generate AI text');
      }
    } catch (error: any) {
      console.error('AI auto-write error:', error);
      const errorMessage = error.response?.data?.error || error.message || 'Failed to generate AI text';
      toast.error(errorMessage);
    } finally {
      setIsGenerating(false);
    }
  };

  const handleConfirmAISuggestion = () => {
    // Insert the edited suggestion into the editor
    if (editedSuggestion.trim()) {
      editor.chain().focus().insertContent(`<p>${editedSuggestion}</p>`).run();
      toast.success('AI suggestion inserted successfully!');
    }
    setShowAIDialog(false);
    setEditedSuggestion('');
  };

  const handleCancelAISuggestion = () => {
    setShowAIDialog(false);
    setEditedSuggestion('');
  };

  return (
    <>
      {/* AI Suggestion Dialog */}
      <Dialog open={showAIDialog} onOpenChange={setShowAIDialog}>
        <DialogContent className="sm:max-w-[600px] max-h-[80vh] flex flex-col">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <Sparkles className="h-5 w-5 text-purple-600" />
              AI Suggestion Preview
            </DialogTitle>
            <DialogDescription>
              Review and edit the AI-generated text before inserting it into your response.
            </DialogDescription>
          </DialogHeader>

          <div className="flex-1 py-4 overflow-hidden">
            <Textarea
              value={editedSuggestion}
              onChange={(e) => setEditedSuggestion(e.target.value)}
              className="min-h-[200px] max-h-[400px] resize-none focus-visible:ring-purple-600"
              placeholder="AI-generated text will appear here..."
            />
          </div>

          <DialogFooter className="gap-2">
            <Button
              variant="outline"
              onClick={handleCancelAISuggestion}
              className="sm:w-auto w-full"
            >
              Cancel
            </Button>
            <Button
              onClick={handleConfirmAISuggestion}
              disabled={!editedSuggestion.trim()}
              className="sm:w-auto w-full bg-purple-600 hover:bg-purple-700 text-white"
            >
              <Sparkles className="h-4 w-4 mr-2" />
              Insert Text
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <div className={cn(
        'border rounded-lg transition-all duration-300 ease-in-out',
        isExpanded && 'fixed inset-4 z-50 bg-background shadow-2xl animate-in fade-in zoom-in-95 duration-200'
      )}>
        {/* Toolbar */}
      <div className="border-b p-2 flex items-center justify-between flex-wrap gap-2 bg-muted/50">
        <div className="flex items-center gap-1">
          <Toggle
            size="sm"
            pressed={editor.isActive('bold')}
            onPressedChange={() => editor.chain().focus().toggleBold().run()}
          >
            <Bold className="h-4 w-4" />
          </Toggle>
          <Toggle
            size="sm"
            pressed={editor.isActive('italic')}
            onPressedChange={() => editor.chain().focus().toggleItalic().run()}
          >
            <Italic className="h-4 w-4" />
          </Toggle>
          <Toggle
            size="sm"
            pressed={editor.isActive('underline')}
            onPressedChange={() => editor.chain().focus().toggleUnderline().run()}
          >
            <UnderlineIcon className="h-4 w-4" />
          </Toggle>

          <Separator orientation="vertical" className="h-6 mx-1" />

          <Toggle
            size="sm"
            pressed={editor.isActive('bulletList')}
            onPressedChange={() => editor.chain().focus().toggleBulletList().run()}
          >
            <List className="h-4 w-4" />
          </Toggle>
          <Toggle
            size="sm"
            pressed={editor.isActive('orderedList')}
            onPressedChange={() => editor.chain().focus().toggleOrderedList().run()}
          >
            <ListOrdered className="h-4 w-4" />
          </Toggle>
          <Toggle
            size="sm"
            pressed={editor.isActive('blockquote')}
            onPressedChange={() => editor.chain().focus().toggleBlockquote().run()}
          >
            <Quote className="h-4 w-4" />
          </Toggle>

          <Separator orientation="vertical" className="h-6 mx-1" />

          <Toggle
            size="sm"
            pressed={editor.isActive({ textAlign: 'left' })}
            onPressedChange={() => editor.chain().focus().setTextAlign('left').run()}
          >
            <AlignLeft className="h-4 w-4" />
          </Toggle>
          <Toggle
            size="sm"
            pressed={editor.isActive({ textAlign: 'center' })}
            onPressedChange={() => editor.chain().focus().setTextAlign('center').run()}
          >
            <AlignCenter className="h-4 w-4" />
          </Toggle>
          <Toggle
            size="sm"
            pressed={editor.isActive({ textAlign: 'right' })}
            onPressedChange={() => editor.chain().focus().setTextAlign('right').run()}
          >
            <AlignRight className="h-4 w-4" />
          </Toggle>

          <Separator orientation="vertical" className="h-6 mx-1" />

          <Button
            size="sm"
            variant="ghost"
            onClick={addLink}
            className="h-8"
          >
            <LinkIcon className="h-4 w-4" />
          </Button>

          <Separator orientation="vertical" className="h-6 mx-1" />

          {enableAI && ticketId && (
            <>
              <Button
                size="sm"
                variant="ghost"
                onClick={handleAIAutoWrite}
                disabled={isGenerating}
                className="h-8 gap-1.5 text-purple-600 hover:text-purple-700 hover:bg-purple-50"
                title="AI Auto-Write"
              >
                {isGenerating ? (
                  <Loader2 className="h-4 w-4 animate-spin" />
                ) : (
                  <Sparkles className="h-4 w-4" />
                )}
                <span className="text-xs font-medium">AI Write</span>
              </Button>

              <Separator orientation="vertical" className="h-6 mx-1" />
            </>
          )}

          <Button
            size="sm"
            variant="ghost"
            onClick={() => editor.chain().focus().undo().run()}
            disabled={!editor.can().undo()}
            className="h-8"
          >
            <Undo className="h-4 w-4" />
          </Button>
          <Button
            size="sm"
            variant="ghost"
            onClick={() => editor.chain().focus().redo().run()}
            disabled={!editor.can().redo()}
            className="h-8"
          >
            <Redo className="h-4 w-4" />
          </Button>
        </div>

        {expandable && (
          <Button
            size="sm"
            variant="ghost"
            onClick={() => setIsExpanded(!isExpanded)}
            className="h-8"
          >
            {isExpanded ? (
              <Minimize2 className="h-4 w-4" />
            ) : (
              <Maximize2 className="h-4 w-4" />
            )}
          </Button>
        )}
      </div>

        {/* Editor */}
        <EditorContent editor={editor} />
      </div>
    </>
  );
}