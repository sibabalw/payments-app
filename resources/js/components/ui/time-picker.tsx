import * as React from "react"
import { Clock } from "lucide-react"
import { Input } from "@/components/ui/input"
import { cn } from "@/lib/utils"

interface TimePickerProps {
  value?: string
  onChange?: (time: string) => void
  disabled?: boolean
  className?: string
  placeholder?: string
}

export function TimePicker({
  value,
  onChange,
  disabled = false,
  className,
  placeholder = "Select time",
}: TimePickerProps) {
  const [timeValue, setTimeValue] = React.useState(value || "")

  React.useEffect(() => {
    setTimeValue(value || "")
  }, [value])

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const newTime = e.target.value
    setTimeValue(newTime)
    onChange?.(newTime)
  }

  return (
    <div className={cn("relative", className)}>
      <Clock className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground pointer-events-none" />
      <Input
        type="time"
        value={timeValue}
        onChange={handleChange}
        disabled={disabled}
        className="pl-10"
        placeholder={placeholder}
      />
    </div>
  )
}
