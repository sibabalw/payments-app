import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { ArrowLeft, CheckCircle, XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import axios from 'axios';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Tickets', href: '/admin/tickets' },
    { title: 'Ticket Details', href: '#' },
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

export default function AdminTicketsShow({ ticket: initialTicket, users }: any) {
    // Use state to manage ticket and messages for real-time updates
    const [ticket, setTicket] = useState(initialTicket);
    const [messages, setMessages] = useState(initialTicket.messages || []);

    // Sync state when props change (e.g., after form submission)
    useEffect(() => {
        setTicket(initialTicket);
        setMessages(initialTicket.messages || []);
    }, [initialTicket.id, initialTicket.updated_at]);

    const replyForm = useForm({
        message: '',
    });

    const statusForm = useForm({
        status: ticket.status,
    });

    const assignForm = useForm({
        assigned_to: ticket.assigned_to?.id || '',
    });

    const handleReply = (e: React.FormEvent) => {
        e.preventDefault();
        replyForm.post(`/admin/tickets/${ticket.id}/reply`, {
            preserveState: true,
            preserveScroll: true,
            only: [],
            replace: false,
            onSuccess: () => {
                replyForm.reset();
                // Message will be added via WebSocket, no need to reload
            },
            onError: () => {
                // Handle errors if needed
            },
            onFinish: () => {
                // Ensure form processing state is reset
            },
        });
    };

    const handleStatusChange = (status: string) => {
        router.patch(`/admin/tickets/${ticket.id}/status`, { status }, {
            preserveState: true,
            preserveScroll: true,
            only: [],
            replace: false,
            onSuccess: () => {
                // Status will be updated via WebSocket, but update immediately for better UX
                setTicket((prev: any) => ({ ...prev, status }));
            },
        });
    };

    const handleAssign = (userId: string) => {
        const assignedToId = userId === 'unassigned' ? null : userId;
        router.patch(`/admin/tickets/${ticket.id}/assign`, { 
            assigned_to: assignedToId 
        }, {
            preserveState: true,
            preserveScroll: true,
            only: [],
            replace: false,
            onSuccess: () => {
                // Assignment will be updated via WebSocket if needed
                setTicket((prev) => ({
                    ...prev,
                    assigned_to: assignedToId ? { id: assignedToId } : null,
                }));
            },
        });
    };

    // Real-time updates via WebSockets (Pusher)
    useEffect(() => {
        console.log('WebSocket: useEffect triggered, ticket status:', ticket.status, 'ticket.id:', ticket.id);
        if (ticket.status === 'closed') {
            console.log('WebSocket: Skipping WebSocket setup - ticket is closed');
            return;
        }

        const initWebSocket = () => {
            try {
                // Use global Echo instance (initialized in app.tsx)
                if (!window.Echo) {
                    console.error('WebSocket: Echo not available on window object');
                    return;
                }
                const echo = window.Echo;
                console.log('WebSocket: Using global Echo instance');

                const handleMessageCreated = (data: any) => {
                    console.log('WebSocket: Received new message', data);
                    if (data.message) {
                        setMessages((prev) => {
                            // Check if message already exists to avoid duplicates
                            if (prev.find((m: any) => m.id === data.message.id)) {
                                return prev;
                            }
                            return [...prev, data.message];
                        });
                    }
                };

                const handleTicketUpdated = (data: any) => {
                    console.log('WebSocket: Received ticket update', data);
                    setTicket((prev) => ({
                        ...prev,
                        status: data.status || prev.status,
                        updated_at: data.timestamp || prev.updated_at,
                    }));
                };

                console.log('WebSocket: Initializing for ticket', ticket.id);

                // Listen to public tickets channel for this specific ticket
                const publicChannel = echo.channel('tickets');
                console.log('WebSocket: Subscribing to public channel "tickets"');
                
                publicChannel
                    .subscribed(() => {
                        console.log('WebSocket: Successfully subscribed to public channel "tickets"');
                    })
                    .error((error: any) => {
                        console.error('WebSocket: Error subscribing to public channel "tickets"', error);
                    })
                    .listen('.ticket.updated', (data: any) => {
                        console.log('WebSocket: Received ticket.updated on public channel', data);
                        if (data.ticket_id === ticket.id) {
                            handleTicketUpdated(data);
                        }
                    })
                    .listen('.ticket.message.created', (data: any) => {
                        console.log('WebSocket: Received ticket.message.created on public channel', data);
                        if (data.ticket_id === ticket.id) {
                            handleMessageCreated(data);
                        }
                    });

                // Also listen to private ticket channel
                const privateChannel = echo.private(`ticket.${ticket.id}`);
                console.log(`WebSocket: Subscribing to private channel "ticket.${ticket.id}"`);
                
                privateChannel
                    .subscribed(() => {
                        console.log(`WebSocket: Successfully subscribed to private channel "ticket.${ticket.id}"`);
                    })
                    .error((error: any) => {
                        console.error(`WebSocket: Error subscribing to private channel "ticket.${ticket.id}"`, error);
                    })
                    .listen('.ticket.updated', (data: any) => {
                        console.log('WebSocket: Received ticket.updated on private channel', data);
                        handleTicketUpdated(data);
                    })
                    .listen('.ticket.message.created', (data: any) => {
                        console.log('WebSocket: Received ticket.message.created on private channel', data);
                        handleMessageCreated(data);
                    });

                // Add connection status listeners
                echo.connector.pusher.connection.bind('connected', () => {
                    console.log('WebSocket: Pusher connected');
                });

                echo.connector.pusher.connection.bind('error', (err: any) => {
                    console.error('WebSocket: Pusher connection error', err);
                });
            } catch (e) {
                console.error('Failed to initialize WebSocket:', e);
            }
        };

        initWebSocket();

        return () => {
            if (window.Echo) {
                try {
                    window.Echo.leave('tickets');
                    window.Echo.leaveChannel(`private-ticket.${ticket.id}`);
                } catch (e) {
                    console.error('WebSocket: Error cleaning up channels', e);
                }
            }
        };
    }, [ticket.id, ticket.status]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Ticket: ${ticket.subject} - Admin`} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <Link href="/admin/tickets">
                        <Button variant="outline" size="sm">
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to Tickets
                        </Button>
                    </Link>
                </div>

                <Card>
                    <CardHeader>
                        <div className="flex items-start justify-between">
                            <div className="flex-1">
                                <CardTitle className="mb-2">{ticket.subject}</CardTitle>
                                <div className="flex gap-2 flex-wrap mb-2">
                                    <Badge className={statusColors[ticket.status]}>
                                        {ticket.status.replace('_', ' ')}
                                    </Badge>
                                    <Badge className={priorityColors[ticket.priority]}>
                                        {ticket.priority}
                                    </Badge>
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    From: {ticket.user?.name} ({ticket.user?.email})
                                </p>
                                {ticket.assigned_to && (
                                    <p className="text-sm text-muted-foreground">
                                        Assigned to: {ticket.assigned_to?.name}
                                    </p>
                                )}
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            <div>
                                <p className="text-sm font-medium mb-1">Description</p>
                                <p className="text-sm text-muted-foreground whitespace-pre-wrap">
                                    {ticket.description}
                                </p>
                            </div>
                            <div className="text-xs text-muted-foreground">
                                Created on {new Date(ticket.created_at).toLocaleString()}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Ticket Actions</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <Label htmlFor="status">Status</Label>
                                <Select
                                    value={ticket.status}
                                    onValueChange={handleStatusChange}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="open">Open</SelectItem>
                                        <SelectItem value="in_progress">In Progress</SelectItem>
                                        <SelectItem value="closed">Closed</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label htmlFor="assigned_to">Assign To</Label>
                                <Select
                                    value={ticket.assigned_to?.id ? String(ticket.assigned_to.id) : 'unassigned'}
                                    onValueChange={handleAssign}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Unassigned" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="unassigned">Unassigned</SelectItem>
                                        {users?.map((user: any) => (
                                            <SelectItem key={user.id} value={String(user.id)}>
                                                {user.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Messages</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {messages && messages.length > 0 ? (
                                messages.map((message: any) => (
                                    <div
                                        key={message.id}
                                        className={`p-4 rounded-lg ${
                                            message.is_admin
                                                ? 'bg-blue-50 dark:bg-blue-900/20'
                                                : 'bg-gray-50 dark:bg-gray-900/20'
                                        }`}
                                    >
                                        <div className="flex items-center justify-between mb-2">
                                            <span className="text-sm font-medium">
                                                {message.user?.name}
                                                {message.is_admin && (
                                                    <Badge className="ml-2" variant="outline">
                                                        Admin
                                                    </Badge>
                                                )}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {new Date(message.created_at).toLocaleString()}
                                            </span>
                                        </div>
                                        <p className="text-sm whitespace-pre-wrap">
                                            {message.message}
                                        </p>
                                    </div>
                                ))
                            ) : (
                                <p className="text-sm text-muted-foreground text-center py-4">
                                    No messages yet.
                                </p>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {ticket.status !== 'closed' && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Add Reply</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleReply} className="space-y-4">
                                <div>
                                    <Label htmlFor="message">Message</Label>
                                    <Textarea
                                        id="message"
                                        value={replyForm.data.message}
                                        onChange={(e) => replyForm.setData('message', e.target.value)}
                                        required
                                        rows={4}
                                        placeholder="Type your reply..."
                                    />
                                    <InputError message={replyForm.errors.message} />
                                </div>
                                <Button type="submit" disabled={replyForm.processing}>
                                    {replyForm.processing ? 'Sending...' : 'Send Reply'}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
