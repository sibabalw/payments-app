import * as React from "react"
import { format } from "date-fns"
import { Calendar as CalendarIcon } from "lucide-react"
import { DayPicker } from "react-day-picker"

import { cn } from "@/lib/utils"
import { Button } from "@/components/ui/button"
import { Calendar } from "@/components/ui/calendar"
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover"
import { TimePicker } from "@/components/ui/time-picker"
import { isBusinessDay, isWeekend, isSouthAfricaHoliday, getHolidayName } from "@/lib/sa-holidays"

interface DatePickerProps {
  date?: Date
  onDateChange?: (date: Date | undefined) => void
  time?: string
  onTimeChange?: (time: string) => void
  disabled?: boolean
  className?: string
  showTime?: boolean
  disabledDates?: (date: Date) => boolean
}

export function DatePicker({
  date,
  onDateChange,
  time,
  onTimeChange,
  disabled = false,
  className,
  showTime = true,
  disabledDates,
}: DatePickerProps) {
  const [open, setOpen] = React.useState(false)

  const handleDateSelect = (selectedDate: Date | undefined) => {
    if (selectedDate) {
      // If time is set, preserve it
      if (time) {
        const [hours, minutes] = time.split(':')
        selectedDate.setHours(parseInt(hours, 10))
        selectedDate.setMinutes(parseInt(minutes, 10))
      }
      onDateChange?.(selectedDate)
    } else {
      onDateChange?.(undefined)
    }
    setOpen(false)
  }

  const handleTimeChange = (newTime: string) => {
    onTimeChange?.(newTime)
    
    if (date && newTime) {
      const [hours, minutes] = newTime.split(':')
      const newDate = new Date(date)
      newDate.setHours(parseInt(hours, 10))
      newDate.setMinutes(parseInt(minutes, 10))
      onDateChange?.(newDate)
    }
  }

  const isDateDisabled = (dateToCheck: Date): boolean => {
    // Check if date is disabled by custom function
    if (disabledDates && disabledDates(dateToCheck)) {
      return true
    }
    
    // Always disable weekends and holidays
    return !isBusinessDay(dateToCheck)
  }

  const getDateError = (): string | null => {
    if (!date) return null
    
    if (isWeekend(date)) {
      return "Cannot schedule on weekends"
    }
    
    if (isSouthAfricaHoliday(date)) {
      const holidayName = getHolidayName(date)
      return `Cannot schedule on ${holidayName}`
    }
    
    return null
  }

  const dateError = getDateError()

  return (
    <div className={cn("space-y-2", className)}>
      <div className="flex gap-2">
        <Popover open={open} onOpenChange={setOpen}>
          <PopoverTrigger asChild>
            <Button
              variant="outline"
              className={cn(
                "w-full justify-start text-left font-normal",
                !date && "text-muted-foreground",
                dateError && "border-destructive"
              )}
              disabled={disabled}
            >
              <CalendarIcon className="mr-2 h-4 w-4" />
              {date ? format(date, "PPP") : <span>Pick a date</span>}
            </Button>
          </PopoverTrigger>
          <PopoverContent className="w-auto p-0" align="start">
            <Calendar
              mode="single"
              selected={date}
              onSelect={handleDateSelect}
              disabled={isDateDisabled}
              initialFocus
            />
          </PopoverContent>
        </Popover>
        
        {showTime && (
          <TimePicker
            value={time}
            onChange={handleTimeChange}
            disabled={disabled}
            className="flex-1"
          />
        )}
      </div>
      
      {dateError && (
        <p className="text-sm text-destructive">{dateError}</p>
      )}
    </div>
  )
}
