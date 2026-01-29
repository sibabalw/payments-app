/**
 * Simple WebSocket client for Laravel Broadcasting
 * 
 * This is a lightweight WebSocket client that works with Laravel's broadcasting system.
 * For production, consider using Laravel Echo with pusher-js for better features.
 */

export interface WebSocketMessage {
    type: string;
    [key: string]: any;
}

export type WebSocketCallback = (data: WebSocketMessage) => void;

export class LaravelWebSocket {
    private ws: WebSocket | null = null;
    private url: string;
    private reconnectAttempts = 0;
    private maxReconnectAttempts = 5;
    private reconnectDelay = 3000;
    private reconnectTimeout: NodeJS.Timeout | null = null;
    private isConnecting = false;
    private isConnected = false;
    private channels: Map<string, Set<WebSocketCallback>> = new Map();
    private heartbeatInterval: NodeJS.Timeout | null = null;

    constructor(url: string) {
        // Convert HTTP/HTTPS URL to WebSocket URL
        this.url = url.replace(/^http/, 'ws');
    }

    connect(): void {
        if (this.isConnecting || this.isConnected) {
            return;
        }

        this.isConnecting = true;

        try {
            this.ws = new WebSocket(this.url);

            this.ws.onopen = () => {
                this.isConnecting = false;
                this.isConnected = true;
                this.reconnectAttempts = 0;
                this.startHeartbeat();
                this.subscribeToChannels();
            };

            this.ws.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    this.handleMessage(data);
                } catch (e) {
                    console.error('Error parsing WebSocket message:', e);
                }
            };

            this.ws.onerror = (error) => {
                console.error('WebSocket error:', error);
                this.isConnecting = false;
            };

            this.ws.onclose = () => {
                this.isConnected = false;
                this.isConnecting = false;
                this.stopHeartbeat();
                this.attemptReconnect();
            };
        } catch (e) {
            console.error('Failed to create WebSocket:', e);
            this.isConnecting = false;
            this.attemptReconnect();
        }
    }

    private subscribeToChannels(): void {
        // Subscribe to all registered channels
        for (const channel of this.channels.keys()) {
            this.send({
                event: 'subscribe',
                channel: channel,
            });
        }
    }

    private handleMessage(data: any): void {
        // Handle different message types
        if (data.event && data.channel) {
            const callbacks = this.channels.get(data.channel);
            if (callbacks) {
                callbacks.forEach((callback) => {
                    try {
                        callback(data.data || data);
                    } catch (e) {
                        console.error('Error in WebSocket callback:', e);
                    }
                });
            }
        }
    }

    subscribe(channel: string, callback: WebSocketCallback): () => void {
        if (!this.channels.has(channel)) {
            this.channels.set(channel, new Set());
        }

        this.channels.get(channel)!.add(callback);

        // If already connected, subscribe immediately
        if (this.isConnected) {
            this.send({
                event: 'subscribe',
                channel: channel,
            });
        }

        // Return unsubscribe function
        return () => {
            const callbacks = this.channels.get(channel);
            if (callbacks) {
                callbacks.delete(callback);
                if (callbacks.size === 0) {
                    this.channels.delete(channel);
                    if (this.isConnected) {
                        this.send({
                            event: 'unsubscribe',
                            channel: channel,
                        });
                    }
                }
            }
        };
    }

    private send(data: any): void {
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            this.ws.send(JSON.stringify(data));
        }
    }

    private startHeartbeat(): void {
        this.heartbeatInterval = setInterval(() => {
            if (this.isConnected) {
                this.send({ event: 'ping' });
            }
        }, 30000); // Every 30 seconds
    }

    private stopHeartbeat(): void {
        if (this.heartbeatInterval) {
            clearInterval(this.heartbeatInterval);
            this.heartbeatInterval = null;
        }
    }

    private attemptReconnect(): void {
        if (this.reconnectAttempts >= this.maxReconnectAttempts) {
            console.warn('Max WebSocket reconnection attempts reached');
            return;
        }

        this.reconnectAttempts++;
        this.reconnectTimeout = setTimeout(() => {
            this.connect();
        }, this.reconnectDelay);
    }

    disconnect(): void {
        if (this.reconnectTimeout) {
            clearTimeout(this.reconnectTimeout);
            this.reconnectTimeout = null;
        }

        this.stopHeartbeat();

        if (this.ws) {
            this.ws.close();
            this.ws = null;
        }

        this.isConnected = false;
        this.isConnecting = false;
        this.channels.clear();
    }
}

// Simple polling fallback when WebSockets are not available
export class PollingClient {
    private interval: NodeJS.Timeout | null = null;
    private channels: Map<string, Set<WebSocketCallback>> = new Map();
    private lastCheck: number = Math.floor(Date.now() / 1000); // Unix timestamp in seconds

    constructor(private pollUrl: string, private pollInterval: number = 5000) {}

    subscribe(channel: string, callback: WebSocketCallback): () => void {
        if (!this.channels.has(channel)) {
            this.channels.set(channel, new Set());
        }

        this.channels.get(channel)!.add(callback);

        if (!this.interval) {
            this.startPolling();
        }

        return () => {
            const callbacks = this.channels.get(channel);
            if (callbacks) {
                callbacks.delete(callback);
                if (callbacks.size === 0) {
                    this.channels.delete(channel);
                }
            }

            if (this.channels.size === 0) {
                this.stopPolling();
            }
        };
    }

    private startPolling(): void {
        this.interval = setInterval(async () => {
            try {
                // Get all ticket IDs from subscribed channels
                const ticketIds: number[] = [];
                for (const channelName of this.channels.keys()) {
                    const ticketIdMatch = channelName.match(/^ticket\.(\d+)$/);
                    if (ticketIdMatch) {
                        ticketIds.push(parseInt(ticketIdMatch[1]));
                    }
                }

                const url = new URL(this.pollUrl, window.location.origin);
                url.searchParams.set('since', this.lastCheck.toString());
                
                // If we have a single ticket channel, use it
                if (ticketIds.length === 1) {
                    url.searchParams.set('ticket_id', ticketIds[0].toString());
                }

                const response = await fetch(url.toString());
                if (response.ok) {
                    const data = await response.json();
                    this.lastCheck = data.timestamp || Math.floor(Date.now() / 1000);

                    if (data.events) {
                        data.events.forEach((event: any) => {
                            // Check all channels that match
                            for (const [channelName, callbacks] of this.channels.entries()) {
                                if (event.channel === channelName || 
                                    (event.channel === 'tickets' && channelName === 'tickets') ||
                                    (event.channel === 'tickets' && channelName.startsWith('ticket.'))) {
                                    callbacks.forEach((callback) => {
                                        callback(event.data);
                                    });
                                }
                            }
                        });
                    }
                }
            } catch (e) {
                console.error('Polling error:', e);
            }
        }, this.pollInterval);
    }

    private stopPolling(): void {
        if (this.interval) {
            clearInterval(this.interval);
            this.interval = null;
        }
    }

    disconnect(): void {
        this.stopPolling();
        this.channels.clear();
    }
}
