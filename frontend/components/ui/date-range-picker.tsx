'use client'

import * as React from 'react'
import { format } from 'date-fns'
import { Calendar as CalendarIcon, ChevronLeft, ChevronRight } from 'lucide-react'
import { DateRange } from 'react-day-picker'

import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Calendar } from '@/components/ui/calendar'
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover'

interface DateRangePickerProps {
  date?: DateRange
  onDateChange?: (date: DateRange | undefined) => void
  placeholder?: string
  className?: string
  disabled?: boolean
  presets?: Array<{
    label: string
    value: () => DateRange
  }>
}

// Custom Caption Component
function CustomCaption({ month, onMonthChange }: { month: Date; onMonthChange: (date: Date) => void }) {
  const [isOpen, setIsOpen] = React.useState(false)
  const [selectedYear, setSelectedYear] = React.useState(month.getFullYear())

  const months = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
  ]

  const currentYear = new Date().getFullYear()

  const handlePrevMonth = () => {
    const newDate = new Date(month)
    newDate.setMonth(newDate.getMonth() - 1)
    onMonthChange(newDate)
  }

  const handleNextMonth = () => {
    const newDate = new Date(month)
    newDate.setMonth(newDate.getMonth() + 1)
    onMonthChange(newDate)
  }

  const handleMonthSelect = (monthIndex: number) => {
    const newDate = new Date(selectedYear, monthIndex, 1)
    onMonthChange(newDate)
    setIsOpen(false)
  }

  const handleYearChange = (year: number) => {
    setSelectedYear(year)
  }

  return (
    <div className="flex items-center justify-between w-full px-2 pb-3">
      <Button
        variant="ghost"
        size="icon"
        className="h-7 w-7"
        onClick={handlePrevMonth}
        type="button"
      >
        <ChevronLeft className="h-4 w-4" />
      </Button>

      <Popover
        open={isOpen}
        onOpenChange={(open) => {
          setIsOpen(open)
          if (open) {
            setSelectedYear(month.getFullYear())
          }
        }}
      >
        <PopoverTrigger asChild>
          <Button
            variant="ghost"
            className="h-8 px-3 font-medium hover:bg-gray-100 dark:hover:bg-gray-800"
            type="button"
          >
            {months[month.getMonth()]} {month.getFullYear()}
          </Button>
        </PopoverTrigger>
        <PopoverContent className="w-[320px] p-0" align="center">
          <div className="flex flex-col">
            {/* Year selector at the top */}
            <div className="border-b px-4 py-2">
              <div className="flex items-center justify-between">
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => handleYearChange(selectedYear - 1)}
                  type="button"
                  className="h-8 w-8 p-0"
                >
                  <ChevronLeft className="h-4 w-4" />
                </Button>
                <span className="text-sm font-semibold">{selectedYear}</span>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => handleYearChange(selectedYear + 1)}
                  type="button"
                  className="h-8 w-8 p-0"
                >
                  <ChevronRight className="h-4 w-4" />
                </Button>
              </div>
            </div>

            {/* Month grid */}
            <div className="grid grid-cols-3 gap-1 p-3">
              {months.map((monthName, monthIndex) => {
                const isCurrentMonth = monthIndex === new Date().getMonth() && selectedYear === currentYear
                const isSelectedMonth = monthIndex === month.getMonth() && selectedYear === month.getFullYear()

                return (
                  <Button
                    key={monthIndex}
                    variant={isSelectedMonth ? "default" : "ghost"}
                    className={cn(
                      "h-10 text-sm font-normal",
                      isCurrentMonth && !isSelectedMonth && "text-primary font-medium"
                    )}
                    onClick={() => handleMonthSelect(monthIndex)}
                    type="button"
                  >
                    {monthName.slice(0, 3)}
                  </Button>
                )
              })}
            </div>

            {/* Quick year navigation */}
            <div className="border-t px-3 pb-3 pt-2">
              <div className="flex gap-1 justify-center">
                <Button
                  variant="ghost"
                  size="sm"
                  className="text-xs h-7 px-2"
                  onClick={() => {
                    const today = new Date()
                    onMonthChange(today)
                    setIsOpen(false)
                  }}
                  type="button"
                >
                  Today
                </Button>
                {[currentYear - 1, currentYear, currentYear + 1].map(year => (
                  <Button
                    key={year}
                    variant={selectedYear === year ? "outline" : "ghost"}
                    size="sm"
                    className="text-xs h-7 px-2"
                    onClick={() => handleYearChange(year)}
                    type="button"
                  >
                    {year}
                  </Button>
                ))}
              </div>
            </div>
          </div>
        </PopoverContent>
      </Popover>

      <Button
        variant="ghost"
        size="icon"
        className="h-7 w-7"
        onClick={handleNextMonth}
        type="button"
      >
        <ChevronRight className="h-4 w-4" />
      </Button>
    </div>
  )
}

export function DateRangePicker({
  date,
  onDateChange,
  placeholder = 'Select date range',
  className,
  disabled = false,
  presets,
}: DateRangePickerProps) {
  const [selectedRange, setSelectedRange] = React.useState<DateRange | undefined>(date)
  const [isOpen, setIsOpen] = React.useState(false)
  const [currentMonth, setCurrentMonth] = React.useState(new Date())

  React.useEffect(() => {
    setSelectedRange(date)
  }, [date])

  const handleSelect = (range: DateRange | undefined) => {
    setSelectedRange(range)
    if (onDateChange) {
      onDateChange(range)
    }
    // Keep popover open - let user close it manually
    // This allows users to adjust their selection if needed
  }

  const handlePresetClick = (preset: () => DateRange) => {
    const range = preset()
    handleSelect(range)
  }

  const formatDateRange = () => {
    if (!selectedRange?.from) {
      return placeholder
    }
    if (!selectedRange.to) {
      return format(selectedRange.from, 'MMM d, yyyy')
    }
    return `${format(selectedRange.from, 'MMM d, yyyy')} - ${format(selectedRange.to, 'MMM d, yyyy')}`
  }

  return (
    <div className={cn('grid gap-2', className)}>
      <Popover open={isOpen} onOpenChange={setIsOpen}>
        <PopoverTrigger asChild>
          <Button
            variant="outline"
            className={cn(
              'justify-start text-left font-normal',
              !selectedRange && 'text-muted-foreground'
            )}
            disabled={disabled}
          >
            <CalendarIcon className="mr-2 h-4 w-4" />
            {formatDateRange()}
          </Button>
        </PopoverTrigger>
        <PopoverContent className="w-auto p-0" align="start">
          <div className="flex flex-col">
            {/* Header with selected date range display */}
            {selectedRange?.from && (
              <div className="p-3 border-b">
                <p className="text-sm text-muted-foreground text-center">
                  {format(selectedRange.from, 'MMM d, yyyy')}
                  {selectedRange.to && ` - ${format(selectedRange.to, 'MMM d, yyyy')}`}
                </p>
              </div>
            )}

            <div className="flex">
              {presets && presets.length > 0 && (
                <div className="flex flex-col gap-1 p-3 border-r">
                  <p className="text-xs font-medium mb-2 text-muted-foreground">Quick select</p>
                  {presets.map((preset, index) => (
                    <Button
                      key={index}
                      variant="ghost"
                      size="sm"
                      className="justify-start text-xs h-7"
                      onClick={() => handlePresetClick(preset.value)}
                    >
                      {preset.label}
                    </Button>
                  ))}
                </div>
              )}
              <div className="flex flex-col">
                <CustomCaption month={currentMonth} onMonthChange={setCurrentMonth} />
                <Calendar
                  mode="range"
                  month={currentMonth}
                  onMonthChange={setCurrentMonth}
                  selected={selectedRange}
                  onSelect={handleSelect}
                  numberOfMonths={1}
                  className="p-3 pt-0 [&_table]:w-full"
                  showOutsideDays={true}
                  fixedWeeks={true}
                  classNames={{
                    months: "w-full",
                    month: "w-full space-y-4",
                    nav: "hidden",
                    caption_label: "hidden",
                    month_caption: "hidden",
                    dropdowns: "hidden",
                    table: "w-full border-collapse",
                    weekdays: "flex w-full",
                    weekday: "text-muted-foreground w-9 font-normal text-[0.8rem]",
                    week: "flex w-full mt-2",
                    day: "h-9 w-9 text-center text-sm",
                  }}
                />
              </div>
            </div>

            <div className="flex items-center justify-between border-t p-3">
              <Button
                variant="ghost"
                size="sm"
                onClick={() => {
                  setSelectedRange(undefined)
                  if (onDateChange) {
                    onDateChange(undefined)
                  }
                }}
                disabled={!selectedRange}
              >
                Clear
              </Button>
              <div className="flex gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setIsOpen(false)}
                >
                  Cancel
                </Button>
                <Button
                  size="sm"
                  onClick={() => setIsOpen(false)}
                  disabled={!selectedRange?.from}
                  className="bg-black hover:bg-gray-800 text-white"
                >
                  Apply
                </Button>
              </div>
            </div>
          </div>
        </PopoverContent>
      </Popover>
    </div>
  )
}

// Preset date range helpers
export const dateRangePresets = {
  thisWeek: () => {
    const now = new Date()
    const dayOfWeek = now.getDay()
    const diff = now.getDate() - dayOfWeek + (dayOfWeek === 0 ? -6 : 1)
    const monday = new Date(now.setDate(diff))
    const sunday = new Date(monday)
    sunday.setDate(monday.getDate() + 6)
    return { from: monday, to: sunday }
  },

  thisMonth: () => {
    const now = new Date()
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1)
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0)
    return { from: firstDay, to: lastDay }
  },

  nextMonth: () => {
    const now = new Date()
    const firstDay = new Date(now.getFullYear(), now.getMonth() + 1, 1)
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 2, 0)
    return { from: firstDay, to: lastDay }
  },

  lastMonth: () => {
    const now = new Date()
    const firstDay = new Date(now.getFullYear(), now.getMonth() - 1, 1)
    const lastDay = new Date(now.getFullYear(), now.getMonth(), 0)
    return { from: firstDay, to: lastDay }
  },

  thisYear: () => {
    const year = new Date().getFullYear()
    return {
      from: new Date(year, 0, 1),
      to: new Date(year, 11, 31)
    }
  },

  nextYear: () => {
    const year = new Date().getFullYear() + 1
    return {
      from: new Date(year, 0, 1),
      to: new Date(year, 11, 31)
    }
  },

  last30Days: () => {
    const now = new Date()
    const thirtyDaysAgo = new Date(now)
    thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30)
    return { from: thirtyDaysAgo, to: now }
  },

  next30Days: () => {
    const now = new Date()
    const thirtyDaysFromNow = new Date(now)
    thirtyDaysFromNow.setDate(thirtyDaysFromNow.getDate() + 30)
    return { from: now, to: thirtyDaysFromNow }
  },

  thisQuarter: () => {
    const now = new Date()
    const quarter = Math.floor((now.getMonth() + 3) / 3)
    const firstMonth = (quarter - 1) * 3
    const lastMonth = firstMonth + 2
    return {
      from: new Date(now.getFullYear(), firstMonth, 1),
      to: new Date(now.getFullYear(), lastMonth + 1, 0)
    }
  },

  nextQuarter: () => {
    const now = new Date()
    const quarter = Math.floor((now.getMonth() + 3) / 3)
    const nextQuarter = quarter === 4 ? 1 : quarter + 1
    const firstMonth = (nextQuarter - 1) * 3
    const lastMonth = firstMonth + 2
    const year = nextQuarter === 1 ? now.getFullYear() + 1 : now.getFullYear()
    return {
      from: new Date(year, firstMonth, 1),
      to: new Date(year, lastMonth + 1, 0)
    }
  }
}