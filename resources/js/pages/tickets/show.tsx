import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Textarea } from '@/components/ui/textarea';
import { formatRelativeTime } from '@/lib/format-relative-time';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ChevronDown, ChevronUp } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const TICKET_EVENTS = ['.ticket.updated', '.ticket.message.created'] as const;

function stopListeningAll(ch: { stopListening?: (event: string) => void } | null) {
    if (ch && typeof ch.stopListening === 'function') {
        TICKET_EVENTS.forEach((ev) => ch.stopListening!(ev));
    }
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Support Tickets', href: '/tickets' },
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

/** Backend returns newest first (DESC). Display order: oldest at top, newest at bottom (WhatsApp). */
function toDisplayOrder(data: any[]): any[] {
    return data ? [...data].reverse() : [];
}

export default function TicketsShow({ ticket: initialTicket, messages: initialMessages }: any) {
    const [ticket, setTicket] = useState(initialTicket);
    const [messages, setMessages] = useState<any[]>(() => toDisplayOrder(initialMessages?.data ?? []));
    const [messagesPage, setMessagesPage] = useState(initialMessages?.current_page ?? 1);
    const [messagesLastPage, setMessagesLastPage] = useState(initialMessages?.last_page ?? 1);
    const [loadingMore, setLoadingMore] = useState(false);
    const [showDescription, setShowDescription] = useState(false);

    const messagesScrollRef = useRef<HTMLDivElement>(null);
    const restoreScrollRef = useRef<{ prevHeight: number; prevTop: number } | null>(null);
    const justPreparedRef = useRef(false);
    const scrollToBottomNextRef = useRef(false);
    const didInitialScrollRef = useRef(false);

    const hasMoreMessages = messagesPage < messagesLastPage;

    useEffect(() => {
        setTicket(initialTicket);
        setMessages(toDisplayOrder(initialMessages?.data ?? []));
        setMessagesPage(initialMessages?.current_page ?? 1);
        setMessagesLastPage(initialMessages?.last_page ?? 1);
    }, [initialTicket.id, initialTicket.updated_at, initialMessages?.current_page]);

    const [replyMessage, setReplyMessage] = useState('');
    const [replyErrors, setReplyErrors] = useState<Record<string, string>>({});
    const [replyProcessing, setReplyProcessing] = useState(false);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        setReplyErrors({});
        setReplyProcessing(true);
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        fetch(`/tickets/${ticket.id}/reply`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ message: replyMessage }),
        })
            .then(async (res) => {
                const data = await res.json().catch(() => ({}));
                if (res.ok && res.status === 201 && data.message) {
                    setReplyMessage('');
                    scrollToBottomNextRef.current = true;
                    setMessages((prev: any) => {
                        if (prev.find((m: any) => m.id === data.message.id)) return prev;
                        return [...prev, data.message];
                    });
                } else if (res.status === 422 && data.errors) {
                    setReplyErrors(data.errors);
                }
            })
            .finally(() => setReplyProcessing(false));
    };

    const loadMoreMessages = () => {
        if (loadingMore || !hasMoreMessages) return;
        const el = messagesScrollRef.current;
        const prevHeight = el?.scrollHeight ?? 0;
        const prevTop = el?.scrollTop ?? 0;
        setLoadingMore(true);
        fetch(`/tickets/${ticket.id}/messages?page=${messagesPage + 1}`, {
            headers: { Accept: 'application/json' },
        })
            .then((res) => res.json())
            .then((data: any) => {
                if (data.data?.length) {
                    const olderReversed = toDisplayOrder(data.data);
                    justPreparedRef.current = true;
                    restoreScrollRef.current = { prevHeight, prevTop };
                    setMessages((prev) => [...olderReversed, ...prev]);
                    setMessagesPage(data.current_page);
                    setMessagesLastPage(data.last_page);
                }
            })
            .finally(() => setLoadingMore(false));
    };

    const privateChannelRef = useRef<{ stopListening?: (event: string) => void } | null>(null);
    const subscribedTicketIdRef = useRef<number | null>(null);

    useEffect(() => {
        if (ticket.status === 'closed') {
            return () => {
                // When ticket is closed we never subscribed in this run; no cleanup needed.
            };
        }

        const ticketId = ticket.id;
        let cancelled = false;

        const leaveChannels = () => {
            if (typeof window === 'undefined' || !window.Echo) return;
            try {
                stopListeningAll(privateChannelRef.current);
                const id = subscribedTicketIdRef.current;
                if (id != null) {
                    window.Echo.leaveChannel(`ticket.${id}`);
                    subscribedTicketIdRef.current = null;
                }
                privateChannelRef.current = null;
            } catch (e) {
                console.error('WebSocket: Error cleaning up channels', e);
            }
        };

        (async () => {
            try {
                const echo = (await import('@/echo')).default();
                if (cancelled || typeof window === 'undefined') return;

                const handleMessageCreated = (data: any) => {
                    if (data.message) {
                        scrollToBottomNextRef.current = true;
                        setMessages((prev: any) => {
                            if (prev.find((m: any) => m.id === data.message.id)) return prev;
                            return [...prev, data.message];
                        });
                    }
                };
                const handleTicketUpdated = (data: any) => {
                    setTicket((prev: any) => ({ ...prev, status: data.status || prev.status }));
                };

                const privateCh = echo
                    .private(`ticket.${ticketId}`)
                    .listen('.ticket.updated', handleTicketUpdated)
                    .listen('.ticket.message.created', handleMessageCreated);
                if (!cancelled) {
                    privateChannelRef.current = privateCh;
                    subscribedTicketIdRef.current = ticketId;
                } else {
                    echo.leaveChannel(`ticket.${ticketId}`);
                }
            } catch (e) {
                console.error('Failed to initialize WebSocket:', e);
            }
        })();

        return () => {
            cancelled = true;
            leaveChannels();
        };
    }, [ticket.id, ticket.status]);

    // Scroll: restore position after prepending older messages, or scroll to bottom after new message / initial load
    useEffect(() => {
        const el = messagesScrollRef.current;
        if (!el) return;
        if (justPreparedRef.current && restoreScrollRef.current) {
            justPreparedRef.current = false;
            const { prevHeight, prevTop } = restoreScrollRef.current;
            restoreScrollRef.current = null;
            requestAnimationFrame(() => {
                el.scrollTop = el.scrollHeight - prevHeight + prevTop;
            });
            return;
        }
        if (scrollToBottomNextRef.current) {
            scrollToBottomNextRef.current = false;
            requestAnimationFrame(() => {
                el.scrollTop = el.scrollHeight;
            });
            return;
        }
        if (messages.length > 0 && !didInitialScrollRef.current) {
            didInitialScrollRef.current = true;
            requestAnimationFrame(() => {
                el.scrollTop = el.scrollHeight;
            });
        }
    }, [messages]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Ticket: ${ticket.subject}`} />
            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-2 overflow-hidden rounded-lg md:gap-3">
                <div className="flex h-[calc(100vh-8rem)] min-h-[400px] flex-col overflow-hidden rounded-lg md:h-[calc(100vh-9rem)] md:min-h-[480px] md:border md:border-border md:bg-card md:shadow-sm">
                    {/* Header: compact on mobile, more space on desktop */}
                    <div className="flex shrink-0 items-center gap-2 border-b bg-muted/30 px-3 py-2 dark:border-border md:gap-3 md:px-4 md:py-3">
                        <Link href="/tickets" className="shrink-0 text-muted-foreground hover:text-foreground">
                            <ArrowLeft className="h-4 w-4 md:h-5 md:w-5" />
                        </Link>
                        <div className="min-w-0 flex-1">
                            <h1 className="truncate text-sm font-semibold md:text-base">{ticket.subject}</h1>
                            <div className="flex items-center gap-2 text-xs text-muted-foreground md:gap-3 md:text-sm">
                                <Badge className={`shrink-0 text-[10px] md:text-xs ${statusColors[ticket.status]}`}>
                                    {ticket.status.replace('_', ' ')}
                                </Badge>
                                <Badge className={`shrink-0 text-[10px] md:text-xs ${priorityColors[ticket.priority]}`}>
                                    {ticket.priority}
                                </Badge>
                                <span className="shrink-0">
                                    {new Date(ticket.created_at).toLocaleDateString()} · {ticket.user?.name}
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* Collapsible description */}
                    <div className="shrink-0 border-b border-border/50 px-3 py-1 md:px-4 md:py-2">
                        <button
                            type="button"
                            onClick={() => setShowDescription((v) => !v)}
                            className="flex w-full items-center justify-between gap-2 py-1 text-left text-xs text-muted-foreground hover:text-foreground md:text-sm"
                        >
                            <span className="truncate">
                                {showDescription ? 'Hide description' : ticket.description?.slice(0, 60)}
                                {!showDescription && (ticket.description?.length ?? 0) > 60 ? '…' : ''}
                            </span>
                            {showDescription ? <ChevronUp className="h-3 w-3 shrink-0" /> : <ChevronDown className="h-3 w-3 shrink-0" />}
                        </button>
                        {showDescription && (
                            <p className="whitespace-pre-wrap py-2 text-xs text-muted-foreground md:text-sm md:leading-relaxed">
                                {ticket.description}
                            </p>
                        )}
                    </div>

                    {/* Messages: Load more above; oldest at top, newest at bottom; load more fetches older from above. */}
                    <div className="flex min-h-0 flex-1 flex-col">
                        {hasMoreMessages && (
                            <div className="shrink-0 flex justify-center border-b border-border/50 py-2 md:py-3">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="h-7 text-xs md:h-8 md:text-sm"
                                    onClick={loadMoreMessages}
                                    disabled={loadingMore}
                                >
                                    {loadingMore ? 'Loading…' : 'Load messages from above'}
                                </Button>
                            </div>
                        )}
                        <div
                            ref={messagesScrollRef}
                            className="min-h-0 flex-1 overflow-y-auto px-2 py-2 md:px-4 md:py-4"
                        >
                            <div className="flex flex-col gap-1.5 md:gap-3">
                                {messages && messages.length > 0 ? (
                                messages.map((message: any) => (
                                    <div
                                        key={message.id}
                                        className={`rounded-lg px-2.5 py-1.5 md:rounded-xl md:px-4 md:py-3 ${
                                            message.is_admin
                                                ? 'bg-primary/10 dark:bg-primary/20'
                                                : 'bg-muted/60 dark:bg-muted/40'
                                        }`}
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="text-xs font-medium md:text-sm">
                                                {message.user?.name}
                                                {message.is_admin && (
                                                    <Badge variant="outline" className="ml-1.5 text-[10px] md:ml-2 md:text-xs">
                                                        Admin
                                                    </Badge>
                                                )}
                                            </span>
                                            <span
                                                className="text-[10px] text-muted-foreground md:text-xs"
                                            title={new Date(message.created_at).toLocaleString()}
                                        >
                                            {formatRelativeTime(message.created_at)}
                                        </span>
                                        </div>
                                        <p className="mt-0.5 whitespace-pre-wrap text-xs leading-snug md:mt-1 md:text-sm md:leading-relaxed">
                                            {message.message}
                                        </p>
                                    </div>
                                ))
                                ) : (
                                    <p className="py-6 text-center text-xs text-muted-foreground md:py-8 md:text-sm">
                                        No messages yet.
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Reply - compact on mobile, more room on desktop */}
                    {ticket.status !== 'closed' && (
                        <form onSubmit={submit} className="shrink-0 border-t border-border bg-background px-2 py-2 md:px-4 md:py-3">
                            <div className="flex gap-2 md:gap-3">
                                <Textarea
                                    id="message"
                                    value={replyMessage}
                                    onChange={(e) => setReplyMessage(e.target.value)}
                                    required
                                    rows={2}
                                    placeholder="Type your reply…"
                                    className="min-h-[52px] resize-none text-sm md:min-h-[72px] md:rows-3 md:text-base"
                                />
                                <Button
                                    type="submit"
                                    disabled={replyProcessing}
                                    className="shrink-0 self-end"
                                >
                                    {replyProcessing ? 'Sending…' : 'Send'}
                                </Button>
                            </div>
                            <InputError
                                message={
                                    Array.isArray(replyErrors.message) ? replyErrors.message[0] : replyErrors.message
                                }
                            />
                        </form>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}