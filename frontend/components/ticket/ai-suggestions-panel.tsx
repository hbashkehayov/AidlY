'use client';

import React from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Progress } from '@/components/ui/progress';
import {
  Brain,
  Tag,
  Clock,
  ThumbsUp,
  ThumbsDown,
  Sparkles,
  Languages,
  Heart,
  AlertCircle,
  CheckCircle,
  RefreshCw,
} from 'lucide-react';
import { cn } from '@/lib/utils';

interface AISuggestion {
  categories?: Array<{
    id: string;
    name: string;
    confidence: number;
  }>;
  tags?: Array<{
    name: string;
    confidence: number;
  }>;
  responses?: Array<{
    text: string;
    confidence: number;
    type: 'template' | 'generated';
  }>;
  priority?: {
    suggested: 'low' | 'medium' | 'high' | 'urgent';
    confidence: number;
    reasoning?: string;
  };
  sentiment?: {
    score: 'positive' | 'negative' | 'neutral';
    confidence: number;
  };
  language?: {
    detected: string;
    confidence: number;
  };
  estimatedResolutionTime?: number; // in minutes
  processingStatus?: 'pending' | 'processing' | 'completed' | 'failed';
  confidenceLevel?: 'high' | 'medium' | 'low' | 'none';
}

interface AISuggestionsPanelProps {
  suggestions?: AISuggestion;
  isEnabled?: boolean;
  isLoading?: boolean;
  onApplySuggestion?: (type: string, value: any) => void;
  onRefreshSuggestions?: () => void;
  className?: string;
}

export function AISuggestionsPanel({
  suggestions,
  isEnabled = false,
  isLoading = false,
  onApplySuggestion,
  onRefreshSuggestions,
  className
}: AISuggestionsPanelProps) {
  const getConfidenceColor = (confidence: number) => {
    if (confidence >= 0.8) return 'text-green-600';
    if (confidence >= 0.6) return 'text-yellow-600';
    return 'text-red-600';
  };

  const getConfidenceBadge = (confidence: number) => {
    if (confidence >= 0.8) return { variant: 'success', label: 'High' };
    if (confidence >= 0.6) return { variant: 'warning', label: 'Medium' };
    return { variant: 'destructive', label: 'Low' };
  };

  const getSentimentIcon = (sentiment: string) => {
    switch (sentiment) {
      case 'positive': return <ThumbsUp className="h-4 w-4 text-green-500" />;
      case 'negative': return <ThumbsDown className="h-4 w-4 text-red-500" />;
      default: return <Heart className="h-4 w-4 text-gray-500" />;
    }
  };

  const getPriorityColor = (priority: string) => {
    switch (priority) {
      case 'urgent': return 'text-red-600 bg-red-50';
      case 'high': return 'text-orange-600 bg-orange-50';
      case 'medium': return 'text-blue-600 bg-blue-50';
      case 'low': return 'text-green-600 bg-green-50';
      default: return 'text-gray-600 bg-gray-50';
    }
  };

  if (!isEnabled) {
    return (
      <Card className={cn("border-dashed", className)}>
        <CardContent className="flex flex-col items-center justify-center py-8">
          <Brain className="h-12 w-12 text-muted-foreground/50 mb-4" />
          <h3 className="text-lg font-medium text-muted-foreground mb-2">
            AI Suggestions Disabled
          </h3>
          <p className="text-sm text-muted-foreground text-center">
            Enable AI suggestions in your account settings to get intelligent recommendations.
          </p>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card className={className}>
      <CardHeader className="pb-4">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <Brain className="h-5 w-5 text-primary" />
            <CardTitle className="text-lg">AI Suggestions</CardTitle>
            {suggestions?.confidenceLevel && (
              <Badge variant="outline" className="ml-2">
                {suggestions.confidenceLevel} confidence
              </Badge>
            )}
          </div>
          {onRefreshSuggestions && (
            <Button
              variant="ghost"
              size="sm"
              onClick={onRefreshSuggestions}
              disabled={isLoading}
              className="h-8 w-8 p-0"
            >
              <RefreshCw className={cn("h-4 w-4", isLoading && "animate-spin")} />
            </Button>
          )}
        </div>
        <CardDescription>
          AI-powered suggestions to help resolve this ticket efficiently
        </CardDescription>
      </CardHeader>

      <CardContent className="space-y-6">
        {/* Processing Status */}
        {suggestions?.processingStatus && suggestions.processingStatus !== 'completed' && (
          <div className="flex items-center gap-2 p-3 bg-blue-50 rounded-lg">
            {suggestions.processingStatus === 'processing' && (
              <RefreshCw className="h-4 w-4 text-blue-600 animate-spin" />
            )}
            {suggestions.processingStatus === 'failed' && (
              <AlertCircle className="h-4 w-4 text-red-600" />
            )}
            {suggestions.processingStatus === 'pending' && (
              <Clock className="h-4 w-4 text-yellow-600" />
            )}
            <span className="text-sm font-medium capitalize">
              {suggestions.processingStatus === 'processing' && 'Analyzing ticket...'}
              {suggestions.processingStatus === 'failed' && 'Analysis failed'}
              {suggestions.processingStatus === 'pending' && 'Queued for analysis'}
            </span>
          </div>
        )}

        {/* Language Detection */}
        {suggestions?.language && (
          <div className="space-y-2">
            <div className="flex items-center gap-2">
              <Languages className="h-4 w-4" />
              <span className="text-sm font-medium">Detected Language</span>
            </div>
            <div className="flex items-center gap-2 pl-6">
              <Badge variant="outline">
                {suggestions.language.detected?.toUpperCase()}
              </Badge>
              <Progress
                value={suggestions.language.confidence * 100}
                className="flex-1 max-w-24"
              />
              <span className="text-xs text-muted-foreground">
                {Math.round(suggestions.language.confidence * 100)}%
              </span>
            </div>
          </div>
        )}

        {/* Sentiment Analysis */}
        {suggestions?.sentiment && (
          <div className="space-y-2">
            <div className="flex items-center gap-2">
              {getSentimentIcon(suggestions.sentiment.score)}
              <span className="text-sm font-medium">Sentiment Analysis</span>
            </div>
            <div className="flex items-center gap-2 pl-6">
              <Badge variant="outline" className="capitalize">
                {suggestions.sentiment.score}
              </Badge>
              <Progress
                value={suggestions.sentiment.confidence * 100}
                className="flex-1 max-w-24"
              />
              <span className="text-xs text-muted-foreground">
                {Math.round(suggestions.sentiment.confidence * 100)}%
              </span>
            </div>
          </div>
        )}

        <Separator />

        {/* Priority Suggestion */}
        {suggestions?.priority && (
          <div className="space-y-3">
            <div className="flex items-center justify-between">
              <h4 className="text-sm font-medium flex items-center gap-2">
                <AlertCircle className="h-4 w-4" />
                Suggested Priority
              </h4>
              <Badge
                variant="outline"
                className={getPriorityColor(suggestions.priority.suggested)}
              >
                {suggestions.priority.suggested.toUpperCase()}
              </Badge>
            </div>
            <div className="pl-6 space-y-2">
              <div className="flex items-center justify-between">
                <span className="text-sm">Confidence:</span>
                <span className={cn("text-sm font-medium", getConfidenceColor(suggestions.priority.confidence))}>
                  {Math.round(suggestions.priority.confidence * 100)}%
                </span>
              </div>
              {suggestions.priority.reasoning && (
                <p className="text-xs text-muted-foreground">
                  {suggestions.priority.reasoning}
                </p>
              )}
              {onApplySuggestion && (
                <Button
                  size="sm"
                  variant="outline"
                  onClick={() => onApplySuggestion('priority', suggestions.priority?.suggested)}
                  className="w-full"
                >
                  Apply Priority
                </Button>
              )}
            </div>
          </div>
        )}

        {/* Category Suggestions */}
        {suggestions?.categories && suggestions.categories.length > 0 && (
          <div className="space-y-3">
            <h4 className="text-sm font-medium flex items-center gap-2">
              <Tag className="h-4 w-4" />
              Suggested Categories
            </h4>
            <div className="space-y-2 pl-6">
              {suggestions.categories.slice(0, 3).map((category, index) => (
                <div key={index} className="flex items-center justify-between p-2 rounded-lg border">
                  <div className="flex-1">
                    <span className="text-sm font-medium">{category.name}</span>
                    <div className="flex items-center gap-2 mt-1">
                      <Progress value={category.confidence * 100} className="flex-1 max-w-16" />
                      <span className="text-xs text-muted-foreground">
                        {Math.round(category.confidence * 100)}%
                      </span>
                    </div>
                  </div>
                  {onApplySuggestion && (
                    <Button
                      size="sm"
                      variant="ghost"
                      onClick={() => onApplySuggestion('category', category.id)}
                    >
                      Apply
                    </Button>
                  )}
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Tag Suggestions */}
        {suggestions?.tags && suggestions.tags.length > 0 && (
          <div className="space-y-3">
            <h4 className="text-sm font-medium flex items-center gap-2">
              <Sparkles className="h-4 w-4" />
              Suggested Tags
            </h4>
            <div className="flex flex-wrap gap-2 pl-6">
              {suggestions.tags.slice(0, 5).map((tag, index) => (
                <Badge
                  key={index}
                  variant="outline"
                  className="cursor-pointer hover:bg-accent"
                  onClick={() => onApplySuggestion?.('tags', [tag.name])}
                >
                  {tag.name}
                  <span className="ml-1 text-xs opacity-60">
                    {Math.round(tag.confidence * 100)}%
                  </span>
                </Badge>
              ))}
            </div>
          </div>
        )}

        {/* Estimated Resolution Time */}
        {suggestions?.estimatedResolutionTime && (
          <div className="space-y-2">
            <div className="flex items-center gap-2">
              <Clock className="h-4 w-4" />
              <span className="text-sm font-medium">Estimated Resolution Time</span>
            </div>
            <div className="pl-6">
              <Badge variant="outline" className="text-blue-600 bg-blue-50">
                {suggestions.estimatedResolutionTime > 60
                  ? `${Math.round(suggestions.estimatedResolutionTime / 60)} hours`
                  : `${suggestions.estimatedResolutionTime} minutes`
                }
              </Badge>
            </div>
          </div>
        )}

        {/* Response Suggestions */}
        {suggestions?.responses && suggestions.responses.length > 0 && (
          <div className="space-y-3">
            <h4 className="text-sm font-medium flex items-center gap-2">
              <CheckCircle className="h-4 w-4" />
              Suggested Responses
            </h4>
            <div className="space-y-3 pl-6">
              {suggestions.responses.slice(0, 2).map((response, index) => (
                <div key={index} className="p-3 rounded-lg border">
                  <div className="flex items-center justify-between mb-2">
                    <Badge variant={response.type === 'template' ? 'secondary' : 'outline'}>
                      {response.type}
                    </Badge>
                    <span className="text-xs text-muted-foreground">
                      {Math.round(response.confidence * 100)}% match
                    </span>
                  </div>
                  <p className="text-sm text-muted-foreground line-clamp-3">
                    {response.text}
                  </p>
                  {onApplySuggestion && (
                    <Button
                      size="sm"
                      variant="outline"
                      className="w-full mt-2"
                      onClick={() => onApplySuggestion('response', response.text)}
                    >
                      Use Response
                    </Button>
                  )}
                </div>
              ))}
            </div>
          </div>
        )}

        {/* No Suggestions State */}
        {suggestions?.processingStatus === 'completed' &&
         !suggestions.categories?.length &&
         !suggestions.tags?.length &&
         !suggestions.responses?.length &&
         !suggestions.priority && (
          <div className="text-center py-4">
            <Brain className="h-8 w-8 text-muted-foreground/50 mx-auto mb-2" />
            <p className="text-sm text-muted-foreground">
              No AI suggestions available for this ticket
            </p>
          </div>
        )}
      </CardContent>
    </Card>
  );
}