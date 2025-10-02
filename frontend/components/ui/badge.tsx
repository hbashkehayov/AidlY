import * as React from "react"
import { cva, type VariantProps } from "class-variance-authority"

import { cn } from "@/lib/utils"

const badgeVariants = cva(
  "inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2",
  {
    variants: {
      variant: {
        default:
          "border-transparent bg-primary text-primary-foreground",
        secondary:
          "border-transparent bg-secondary text-secondary-foreground",
        destructive:
          "border-transparent bg-destructive text-destructive-foreground",
        outline: "text-foreground",
        success:
          "border-transparent bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100",
        warning:
          "border-transparent bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-100",
        // Priority variants (with transparency)
        "priority-low":
          "border-green-300/50 dark:border-green-700/50 bg-green-100/70 dark:bg-green-900/30 text-green-800 dark:text-green-200",
        "priority-medium":
          "border-yellow-300/50 dark:border-yellow-700/50 bg-yellow-100/70 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-200",
        "priority-high":
          "border-orange-300/50 dark:border-orange-700/50 bg-orange-100/70 dark:bg-orange-900/30 text-orange-800 dark:text-orange-200",
        "priority-urgent":
          "border-red-300/50 dark:border-red-700/50 bg-red-100/70 dark:bg-red-900/30 text-red-800 dark:text-red-200",
        // Status variants (with transparency)
        "status-open":
          "border-blue-300/50 dark:border-blue-700/50 bg-blue-100/70 dark:bg-blue-900/30 text-blue-800 dark:text-blue-200",
        "status-pending":
          "border-amber-300/50 dark:border-amber-700/50 bg-amber-100/70 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200",
        "status-resolved":
          "border-green-300/50 dark:border-green-700/50 bg-green-100/70 dark:bg-green-900/30 text-green-800 dark:text-green-200",
        "status-closed":
          "border-gray-300/50 dark:border-gray-600/50 bg-gray-100/70 dark:bg-gray-800/30 text-gray-800 dark:text-gray-300",
        "status-new":
          "border-purple-300/50 dark:border-purple-700/50 bg-purple-100/70 dark:bg-purple-900/30 text-purple-800 dark:text-purple-200",
        "status-cancelled":
          "border-rose-300/50 dark:border-rose-700/50 bg-rose-100/70 dark:bg-rose-900/30 text-rose-800 dark:text-rose-200",
        // Role variants (with transparency)
        "role-admin":
          "border-purple-400/50 dark:border-purple-600/50 bg-purple-500/70 dark:bg-purple-600/70 text-white dark:text-white",
        "role-agent":
          "border-gray-300/50 dark:border-gray-600/50 bg-gray-100/70 dark:bg-gray-800/30 text-gray-800 dark:text-gray-300",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  }
)

export interface BadgeProps
  extends React.HTMLAttributes<HTMLDivElement>,
    VariantProps<typeof badgeVariants> {}

function Badge({ className, variant, ...props }: BadgeProps) {
  return (
    <div className={cn(badgeVariants({ variant }), className)} {...props} />
  )
}

export { Badge, badgeVariants }