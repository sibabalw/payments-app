import { ConversationList } from '@/components/chat/conversation-list';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { Bot, MessageSquarePlus } from 'lucide-react';

interface Conversation {
    id: number;
    title: string;
    updated_at: string;
    last_message?: string;
}

interface Business {
    id: number;
    name: string;
}

interface Props {
    conversations: Conversation[];
    business: Business | null;
}

export default function ChatIndex({ conversations, business }: Props) {
    const handleNewConversation = () => {
        router.post('/chat');
    };

    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this conversation?')) {
            router.delete(`/chat/${id}`);
        }
    };

    if (!business) {
        return (
            <AppLayout>
                <Head title="AI Assistant" />
                <div className="flex h-[calc(100vh-8rem)] items-center justify-center">
                    <Card className="max-w-md">
                        <CardHeader className="text-center">
                            <Bot className="mx-auto h-12 w-12 text-muted-foreground" />
                            <CardTitle>No Business Selected</CardTitle>
                            <CardDescription>Please select a business to start chatting with the AI assistant.</CardDescription>
                        </CardHeader>
                    </Card>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout>
            <Head title="AI Assistant" />
            <div className="flex h-[calc(100vh-8rem)] flex-col">
                <div className="border-b px-6 py-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-semibold">AI Assistant</h1>
                            <p className="text-muted-foreground text-sm">
                                Ask questions about {business.name}'s data
                            </p>
                        </div>
                        <Button onClick={handleNewConversation} className="gap-2">
                            <MessageSquarePlus className="h-4 w-4" />
                            New Chat
                        </Button>
                    </div>
                </div>

                <div className="flex flex-1 overflow-hidden">
                    {/* Conversation List */}
                    <div className="w-80 border-r">
                        <ConversationList
                            conversations={conversations}
                            onNewConversation={handleNewConversation}
                            onDelete={handleDelete}
                        />
                    </div>

                    {/* Empty State */}
                    <div className="flex flex-1 items-center justify-center">
                        <Card className="max-w-md text-center">
                            <CardHeader>
                                <Bot className="text-muted-foreground mx-auto h-16 w-16" />
                                <CardTitle>Start a Conversation</CardTitle>
                                <CardDescription>
                                    Ask me anything about your business data. I can help you with:
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <ul className="text-muted-foreground space-y-2 text-left text-sm">
                                    <li>- Employee counts and department breakdowns</li>
                                    <li>- Upcoming payments and payment schedules</li>
                                    <li>- Payroll summaries and schedules</li>
                                    <li>- Escrow account balance</li>
                                    <li>- Tax compliance status (UIF, EMP201, IRP5)</li>
                                </ul>
                                <Button onClick={handleNewConversation} className="mt-6 w-full gap-2">
                                    <MessageSquarePlus className="h-4 w-4" />
                                    Start New Chat
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
