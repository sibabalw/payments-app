import { ConversationList } from '@/components/chat/conversation-list';
import { MessageBubble } from '@/components/chat/message-bubble';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Loader2, Send } from 'lucide-react';
import { useEffect, useRef } from 'react';

interface Message {
    id: number;
    role: 'user' | 'assistant';
    content: string;
    created_at: string;
}

interface Conversation {
    id: number;
    title: string;
    updated_at?: string;
}

interface Business {
    id: number;
    name: string;
}

interface Props {
    conversation: Conversation;
    messages: Message[];
    conversations: Conversation[];
    business: Business | null;
}

export default function ChatConversation({ conversation, messages, conversations, business }: Props) {
    const messagesEndRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);

    const { data, setData, post, processing, reset } = useForm({
        message: '',
    });

    const scrollToBottom = () => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    };

    useEffect(() => {
        scrollToBottom();
    }, [messages]);

    useEffect(() => {
        if (!processing) {
            inputRef.current?.focus();
        }
    }, [processing]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!data.message.trim() || processing) return;

        post(`/chat/${conversation.id}/message`, {
            preserveScroll: true,
            onSuccess: () => {
                reset('message');
            },
        });
    };

    const handleNewConversation = () => {
        router.post('/chat');
    };

    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this conversation?')) {
            router.delete(`/chat/${id}`);
        }
    };

    return (
        <AppLayout>
            <Head title={`${conversation.title} - AI Assistant`} />
            <div className="flex h-[calc(100vh-8rem)]">
                {/* Sidebar */}
                <div className="hidden w-72 border-r lg:block">
                    <ConversationList
                        conversations={conversations}
                        activeId={conversation.id}
                        onNewConversation={handleNewConversation}
                        onDelete={handleDelete}
                    />
                </div>

                {/* Chat Area */}
                <div className="flex flex-1 flex-col">
                    {/* Header */}
                    <div className="flex items-center gap-4 border-b px-4 py-3">
                        <Button variant="ghost" size="icon" className="lg:hidden" onClick={() => router.visit('/chat')}>
                            <ArrowLeft className="h-4 w-4" />
                        </Button>
                        <div className="min-w-0 flex-1">
                            <h1 className="truncate font-semibold">{conversation.title}</h1>
                            {business && <p className="text-muted-foreground text-xs">{business.name}</p>}
                        </div>
                    </div>

                    {/* Messages */}
                    <div className="flex-1 overflow-y-auto p-4">
                        {messages.length === 0 ? (
                            <div className="text-muted-foreground flex h-full items-center justify-center text-center">
                                <div>
                                    <p className="text-lg font-medium">Start the conversation</p>
                                    <p className="text-sm">Ask a question about your business data</p>
                                </div>
                            </div>
                        ) : (
                            <div className="mx-auto max-w-3xl">
                                {messages.map((message) => (
                                    <div key={message.id} style={{ paddingTop: '24px', paddingBottom: '24px' }}>
                                        <MessageBubble
                                            role={message.role}
                                            content={message.content}
                                            createdAt={message.created_at}
                                        />
                                    </div>
                                ))}
                                <div ref={messagesEndRef} />
                            </div>
                        )}
                    </div>

                    {/* Input */}
                    <div className="border-t p-4">
                        <form onSubmit={handleSubmit} className="mx-auto flex max-w-3xl gap-2">
                            <Input
                                ref={inputRef}
                                value={data.message}
                                onChange={(e) => setData('message', e.target.value)}
                                placeholder="Ask a question about your business..."
                                disabled={processing}
                                className="flex-1"
                            />
                            <Button type="submit" disabled={processing || !data.message.trim()}>
                                {processing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
                            </Button>
                        </form>
                        <p className="text-muted-foreground mx-auto mt-2 max-w-3xl text-center text-xs">
                            AI can make mistakes. Verify important information.
                        </p>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
