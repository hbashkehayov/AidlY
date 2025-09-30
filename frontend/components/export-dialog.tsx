'use client';

import { useState } from 'react';
import { FileSpreadsheet, FileText, Loader2 } from 'lucide-react';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type ExportFormat = 'excel' | 'pdf';

interface ExportDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onExport: (format: ExportFormat) => Promise<void>;
  title?: string;
  description?: string;
}

export function ExportDialog({
  open,
  onOpenChange,
  onExport,
  title = 'Export Report',
  description = 'Choose a format to export your report',
}: ExportDialogProps) {
  const [selectedFormat, setSelectedFormat] = useState<ExportFormat | null>(null);
  const [isExporting, setIsExporting] = useState(false);

  const handleExport = async (format: ExportFormat) => {
    setSelectedFormat(format);
    setIsExporting(true);
    try {
      await onExport(format);
      // Success - close dialog after a short delay
      setTimeout(() => {
        onOpenChange(false);
        setIsExporting(false);
        setSelectedFormat(null);
      }, 500);
    } catch (error) {
      console.error('Export failed:', error);
      setIsExporting(false);
      setSelectedFormat(null);
      // Keep dialog open on error
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
          <DialogDescription>{description}</DialogDescription>
        </DialogHeader>

        <div className="grid grid-cols-2 gap-4 py-4">
          {/* Excel Option */}
          <button
            onClick={() => handleExport('excel')}
            disabled={isExporting}
            className={cn(
              'group relative flex flex-col items-center justify-center gap-4 rounded-lg border-2 border-muted bg-background p-6 transition-all hover:border-primary hover:bg-accent/50',
              'disabled:opacity-50 disabled:cursor-not-allowed',
              selectedFormat === 'excel' && isExporting && 'border-primary bg-accent/50'
            )}
          >
            <div className={cn(
              'rounded-full bg-green-100 dark:bg-green-950 p-4 transition-transform group-hover:scale-110',
              selectedFormat === 'excel' && isExporting && 'animate-pulse'
            )}>
              {isExporting && selectedFormat === 'excel' ? (
                <Loader2 className="h-8 w-8 text-green-600 dark:text-green-400 animate-spin" />
              ) : (
                <FileSpreadsheet className="h-8 w-8 text-green-600 dark:text-green-400" />
              )}
            </div>
            <div className="text-center">
              <h3 className="font-semibold text-sm">Excel</h3>
              <p className="text-xs text-muted-foreground mt-1">
                .xlsx format
              </p>
            </div>
            {isExporting && selectedFormat === 'excel' && (
              <div className="absolute inset-0 flex items-center justify-center bg-background/80 rounded-lg">
                <span className="text-sm font-medium">Exporting...</span>
              </div>
            )}
          </button>

          {/* PDF Option */}
          <button
            onClick={() => handleExport('pdf')}
            disabled={isExporting}
            className={cn(
              'group relative flex flex-col items-center justify-center gap-4 rounded-lg border-2 border-muted bg-background p-6 transition-all hover:border-primary hover:bg-accent/50',
              'disabled:opacity-50 disabled:cursor-not-allowed',
              selectedFormat === 'pdf' && isExporting && 'border-primary bg-accent/50'
            )}
          >
            <div className={cn(
              'rounded-full bg-red-100 dark:bg-red-950 p-4 transition-transform group-hover:scale-110',
              selectedFormat === 'pdf' && isExporting && 'animate-pulse'
            )}>
              {isExporting && selectedFormat === 'pdf' ? (
                <Loader2 className="h-8 w-8 text-red-600 dark:text-red-400 animate-spin" />
              ) : (
                <FileText className="h-8 w-8 text-red-600 dark:text-red-400" />
              )}
            </div>
            <div className="text-center">
              <h3 className="font-semibold text-sm">PDF</h3>
              <p className="text-xs text-muted-foreground mt-1">
                .pdf format
              </p>
            </div>
            {isExporting && selectedFormat === 'pdf' && (
              <div className="absolute inset-0 flex items-center justify-center bg-background/80 rounded-lg">
                <span className="text-sm font-medium">Exporting...</span>
              </div>
            )}
          </button>
        </div>

        <div className="flex justify-end">
          <Button
            variant="outline"
            onClick={() => onOpenChange(false)}
            disabled={isExporting}
          >
            Cancel
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  );
}