import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { MessageSquare, Plus, Trash2 } from 'lucide-react';

interface Conversation {
    id: number;
    title: string;
    updated_at: string;
    last_message?: string;
}

interface ConversationListProps {
    conversations: Conversation[];
    activeId?: number;
    onNewConversation?: () => void;
    onDelete?: (id: number) => void;
}

export function ConversationList({ conversations, activeId, onNewConversation, onDelete }: ConversationListProps) {
    return (
        <div className="flex h-full flex-col">
            <div className="border-b p-4">
                <Button onClick={onNewConversation} className="w-full gap-2">
                    <Plus className="h-4 w-4" />
                    New Chat
                </Button>
            </div>
            <div className="flex-1 overflow-y-auto">
                {conversations.length === 0 ? (
                    <div className="text-muted-foreground p-4 text-center text-sm">No conversations yet</div>
                ) : (
                    <div className="space-y-1 p-2">
                        {conversations.map((conv) => (
                            <div key={conv.id} className="group relative">
                                <Link
                                    href={`/chat/${conv.id}`}
                                    className={cn(
                                        'flex items-start gap-3 rounded-lg px-3 py-2 transition-colors',
                                        'hover:bg-accent',
                                        activeId === conv.id && 'bg-accent',
                                    )}
                                >
                                    <MessageSquare className="text-muted-foreground mt-0.5 h-4 w-4 shrink-0" />
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium">{conv.title}</p>
                                        <p className="text-muted-foreground text-xs">
                                            {new Date(conv.updated_at).toLocaleDateString()}
                                        </p>
                                    </div>
                                </Link>
                                {onDelete && (
                                    <button
                                        onClick={(e) => {
                                            e.preventDefault();
                                            onDelete(conv.id);
                                        }}
                                        className="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 opacity-0 transition-opacity hover:bg-destructive/10 group-hover:opacity-100"
                                    >
                                        <Trash2 className="text-destructive h-4 w-4" />
                                    </button>
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
