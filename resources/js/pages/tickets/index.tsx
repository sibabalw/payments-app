import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, MessageSquare } from 'lucide-react';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useEffect, useRef } from 'react';
import { disconnectEcho } from '@/echo';

const TICKETS_CHANNEL = 'tickets';
const TICKET_EVENTS = ['.ticket.created', '.ticket.updated'] as const;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Support Tickets', href: '/tickets' },
];

const statusColors: Record<string, string> = {
    open: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    in_progress: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
    closed: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300',
};

const priorityColors: Record<string, string> = {
    low: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300',
    medium: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
    high: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
};

export default function TicketsIndex({ tickets, filters }: any) {
    // Real-time updates via WebSockets (Pusher). Leave channel when user navigates away.
    const channelRef = useRef<{ stopListening: (event: string) => void } | null>(null);

    useEffect(() => {
        let reloadTimeout: NodeJS.Timeout | null = null;
        const reloadDebounceMs = 1000;

        const leaveTicketsChannel = () => {
            if (typeof window === 'undefined' || !window.Echo) return;
            try {
                const ch = channelRef.current;
                if (ch && typeof ch.stopListening === 'function') {
                    TICKET_EVENTS.forEach((ev) => ch.stopListening(ev));
                }
                window.Echo.leave(TICKETS_CHANNEL);
                channelRef.current = null;
            } catch (e) {
                console.error('WebSocket: Error leaving tickets channel', e);
            }
            disconnectEcho();
        };

        const initWebSocket = async () => {
            try {
                const echo = (await import('@/echo')).default();

                const handleUpdate = () => {
                    if (reloadTimeout) clearTimeout(reloadTimeout);
                    reloadTimeout = setTimeout(() => {
                        reloadTimeout = null;
                        router.reload({
                            only: ['tickets'],
                            preserveScroll: true,
                            preserveState: false,
                        });
                    }, reloadDebounceMs);
                };

                const channel = echo
                    .channel(TICKETS_CHANNEL)
                    .listen('.ticket.created', handleUpdate)
                    .listen('.ticket.updated', handleUpdate);
                channelRef.current = channel;
            } catch (e) {
                console.error('Failed to initialize WebSocket:', e);
            }
        };

        initWebSocket();

        return () => {
            if (reloadTimeout) clearTimeout(reloadTimeout);
            leaveTicketsChannel();
        };
    }, []);

    const handleStatusFilter = (status: string) => {
        router.get('/tickets', { status: status || null });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Support Tickets" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Support Tickets</h1>
                    <Link href="/tickets/create">
                        <Button>
                            <Plus className="mr-2 h-4 w-4" />
                            New Ticket
                        </Button>
                    </Link>
                </div>

                <div className="flex gap-4">
                    <Select
                        value={filters?.status || 'all'}
                        onValueChange={handleStatusFilter}
                    >
                        <SelectTrigger className="w-[180px]">
                            <SelectValue placeholder="Filter by status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Statuses</SelectItem>
                            <SelectItem value="open">Open</SelectItem>
                            <SelectItem value="in_progress">In Progress</SelectItem>
                            <SelectItem value="closed">Closed</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                {tickets?.data && tickets.data.length > 0 ? (
                    <div className="grid gap-4">
                        {tickets.data.map((ticket: any) => (
                            <Card key={ticket.id} className="hover:shadow-md transition-shadow">
                                <CardHeader>
                                    <div className="flex items-start justify-between">
                                        <div className="flex-1">
                                            <CardTitle className="mb-2">
                                                <Link
                                                    href={`/tickets/${ticket.id}`}
                                                    className="hover:underline"
                                                >
                                                    {ticket.subject}
                                                </Link>
                                            </CardTitle>
                                            <div className="flex gap-2 flex-wrap">
                                                <Badge className={statusColors[ticket.status]}>
                                                    {ticket.status.replace('_', ' ')}
                                                </Badge>
                                                <Badge className={priorityColors[ticket.priority]}>
                                                    {ticket.priority}
                                                </Badge>
                                            </div>
                                        </div>
                                        <Link href={`/tickets/${ticket.id}`}>
                                            <Button variant="outline" size="sm">
                                                <MessageSquare className="mr-2 h-4 w-4" />
                                                View
                                            </Button>
                                        </Link>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm text-muted-foreground line-clamp-2">
                                        {ticket.description}
                                    </p>
                                    <p className="text-xs text-muted-foreground mt-2">
                                        Created {new Date(ticket.created_at).toLocaleDateString()}
                                    </p>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                ) : (
                    <Card>
                        <CardContent className="py-10 text-center">
                            <p className="text-muted-foreground">No tickets found.</p>
                            <Link href="/tickets/create" className="mt-4 inline-block">
                                <Button>Create your first ticket</Button>
                            </Link>
                        </CardContent>
                    </Card>
                )}

                {tickets?.links && (
                    <div className="flex justify-center gap-2">
                        {tickets.links.map((link: any, index: number) => (
                            <Button
                                key={index}
                                variant={link.active ? 'default' : 'outline'}
                                size="sm"
                                onClick={() => link.url && router.get(link.url)}
                                disabled={!link.url}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
