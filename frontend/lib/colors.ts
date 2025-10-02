/**
 * Centralized color configuration for AidlY
 *
 * Priority colors: Progressive "heat" scale
 * - Low → Green (calm, safe, not pressing)
 * - Medium → Yellow (caution, needs attention)
 * - High → Orange (warning, should be handled soon)
 * - Urgent → Red (critical, top of the pile)
 *
 * Status colors: Workflow-oriented (cooler colors)
 * - Open → Blue (active, in progress, "we're on it")
 * - Pending → Yellow/Amber (waiting, customer action needed)
 * - Resolved → Green (done, looks good, positive closure)
 * - Closed → Gray (inactive, archived, conversation over)
 */

export const priorityColors = {
  low: {
    bg: 'bg-green-100/70',
    bgDark: 'dark:bg-green-900/30',
    text: 'text-green-800',
    textDark: 'dark:text-green-200',
    border: 'border-green-300/50',
    borderDark: 'dark:border-green-700/50',
    dot: 'bg-green-500',
    // For charts
    chart: '#E5BEB5', // Custom light rose/beige
    chartOpacity: 'rgba(229, 190, 181, 0.7)',
  },
  medium: {
    bg: 'bg-yellow-100/70',
    bgDark: 'dark:bg-yellow-900/30',
    text: 'text-yellow-800',
    textDark: 'dark:text-yellow-200',
    border: 'border-yellow-300/50',
    borderDark: 'dark:border-yellow-700/50',
    dot: 'bg-yellow-500',
    // For charts
    chart: '#eab308', // yellow-500
    chartOpacity: 'rgba(234, 179, 8, 0.7)',
  },
  high: {
    bg: 'bg-orange-100/70',
    bgDark: 'dark:bg-orange-900/30',
    text: 'text-orange-800',
    textDark: 'dark:text-orange-200',
    border: 'border-orange-300/50',
    borderDark: 'dark:border-orange-700/50',
    dot: 'bg-orange-500',
    // For charts
    chart: '#f97316', // orange-500
    chartOpacity: 'rgba(249, 115, 22, 0.7)',
  },
  urgent: {
    bg: 'bg-red-100/70',
    bgDark: 'dark:bg-red-900/30',
    text: 'text-red-800',
    textDark: 'dark:text-red-200',
    border: 'border-red-300/50',
    borderDark: 'dark:border-red-700/50',
    dot: 'bg-red-500',
    // For charts
    chart: '#ef4444', // red-500
    chartOpacity: 'rgba(239, 68, 68, 0.7)',
  },
} as const;

export const statusColors = {
  open: {
    bg: 'bg-blue-100/70',
    bgDark: 'dark:bg-blue-900/30',
    text: 'text-blue-800',
    textDark: 'dark:text-blue-200',
    border: 'border-blue-300/50',
    borderDark: 'dark:border-blue-700/50',
    dot: 'bg-blue-500',
    // For charts
    chart: '#3b82f6', // blue-500
    chartOpacity: 'rgba(59, 130, 246, 0.7)',
  },
  pending: {
    bg: 'bg-amber-100/70',
    bgDark: 'dark:bg-amber-900/30',
    text: 'text-amber-800',
    textDark: 'dark:text-amber-200',
    border: 'border-amber-300/50',
    borderDark: 'dark:border-amber-700/50',
    dot: 'bg-amber-500',
    // For charts
    chart: '#f59e0b', // amber-500
    chartOpacity: 'rgba(245, 158, 11, 0.7)',
  },
  resolved: {
    bg: 'bg-green-100/70',
    bgDark: 'dark:bg-green-900/30',
    text: 'text-green-800',
    textDark: 'dark:text-green-200',
    border: 'border-green-300/50',
    borderDark: 'dark:border-green-700/50',
    dot: 'bg-green-500',
    // For charts
    chart: '#10b981', // green-500
    chartOpacity: 'rgba(16, 185, 129, 0.7)',
  },
  closed: {
    bg: 'bg-gray-100/70',
    bgDark: 'dark:bg-gray-800/30',
    text: 'text-gray-800',
    textDark: 'dark:text-gray-300',
    border: 'border-gray-300/50',
    borderDark: 'dark:border-gray-600/50',
    dot: 'bg-gray-500',
    // For charts
    chart: '#6b7280', // gray-500
    chartOpacity: 'rgba(107, 114, 128, 0.7)',
  },
  new: {
    bg: 'bg-purple-100/70',
    bgDark: 'dark:bg-purple-900/30',
    text: 'text-purple-800',
    textDark: 'dark:text-purple-200',
    border: 'border-purple-300/50',
    borderDark: 'dark:border-purple-700/50',
    dot: 'bg-purple-500',
    // For charts
    chart: '#a855f7', // purple-500
    chartOpacity: 'rgba(168, 85, 247, 0.7)',
  },
  cancelled: {
    bg: 'bg-rose-100/70',
    bgDark: 'dark:bg-rose-900/30',
    text: 'text-rose-800',
    textDark: 'dark:text-rose-200',
    border: 'border-rose-300/50',
    borderDark: 'dark:border-rose-700/50',
    dot: 'bg-rose-500',
    // For charts
    chart: '#f43f5e', // rose-500
    chartOpacity: 'rgba(244, 63, 94, 0.7)',
  },
  on_hold: {
    bg: 'bg-indigo-100/70',
    bgDark: 'dark:bg-indigo-900/30',
    text: 'text-indigo-800',
    textDark: 'dark:text-indigo-200',
    border: 'border-indigo-300/50',
    borderDark: 'dark:border-indigo-700/50',
    dot: 'bg-indigo-500',
    // For charts
    chart: '#6366f1', // indigo-500
    chartOpacity: 'rgba(99, 102, 241, 0.7)',
  },
} as const;

export type Priority = keyof typeof priorityColors;
export type Status = keyof typeof statusColors;

/**
 * Get priority color configuration
 */
export function getPriorityColor(priority: string) {
  const key = priority.toLowerCase() as Priority;
  return priorityColors[key] || priorityColors.medium;
}

/**
 * Get status color configuration
 */
export function getStatusColor(status: string) {
  const key = status.toLowerCase() as Status;
  return statusColors[key] || statusColors.open;
}

/**
 * Get priority label
 */
export function getPriorityLabel(priority: string): string {
  return priority.charAt(0).toUpperCase() + priority.slice(1);
}

/**
 * Get status label
 */
export function getStatusLabel(status: string): string {
  // Convert snake_case to Title Case
  return status
    .split('_')
    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
}

/**
 * Get chart color for priority
 */
export function getPriorityChartColor(priority: string): string {
  const key = priority.toLowerCase() as Priority;
  return priorityColors[key]?.chart || priorityColors.medium.chart;
}

/**
 * Get chart color for status
 */
export function getStatusChartColor(status: string): string {
  const key = status.toLowerCase() as Status;
  return statusColors[key]?.chart || statusColors.open.chart;
}

/**
 * Get priority colors for charts (returns array of all priority colors)
 */
export function getAllPriorityChartColors(): string[] {
  return [
    priorityColors.low.chart,
    priorityColors.medium.chart,
    priorityColors.high.chart,
    priorityColors.urgent.chart,
  ];
}

/**
 * Get status colors for charts (returns array of common status colors)
 */
export function getAllStatusChartColors(): string[] {
  return [
    statusColors.open.chart,
    statusColors.pending.chart,
    statusColors.resolved.chart,
    statusColors.closed.chart,
  ];
}
