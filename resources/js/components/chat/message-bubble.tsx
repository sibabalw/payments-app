import { cn } from '@/lib/utils';
import { Bot, User } from 'lucide-react';

interface MessageBubbleProps {
    role: 'user' | 'assistant';
    content: string;
    createdAt?: string;
}

export function MessageBubble({ role, content, createdAt }: MessageBubbleProps) {
    const isUser = role === 'user';

    return (
        <div className={cn('flex gap-3', isUser ? 'flex-row-reverse' : 'flex-row')}>
            <div
                className={cn(
                    'flex h-8 w-8 shrink-0 items-center justify-center rounded-full',
                    isUser ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground',
                )}
            >
                {isUser ? <User className="h-4 w-4" /> : <Bot className="h-4 w-4" />}
            </div>
            <div className={cn('flex max-w-[80%] flex-col gap-1', isUser ? 'items-end' : 'items-start')}>
                <div
                    className={cn(
                        'rounded-2xl px-4 py-2',
                        isUser ? 'bg-primary text-primary-foreground' : 'bg-muted text-foreground',
                    )}
                >
                    <p className="whitespace-pre-wrap text-sm">{content}</p>
                </div>
                {createdAt && (
                    <span className="text-muted-foreground text-xs">
                        {new Date(createdAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                    </span>
                )}
            </div>
        </div>
    );
}
