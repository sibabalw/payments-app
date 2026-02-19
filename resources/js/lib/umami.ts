import type { ActiveVisit } from '@inertiajs/core';
import { router } from '@inertiajs/react';

declare global {
    interface Window {
        umami?: {
            /** Page view (no args or with custom url/title) */
            track(): void;
            track(pageViewData: { url?: string; title?: string }): void;
            /** Custom event */
            track(eventName: string, eventData?: object): void;
        };
    }
}

/** Umami event name limit (chars). */
const EVENT_NAME_MAX_LENGTH = 50;
/** Max properties per event (arrays count as one). */
const EVENT_DATA_MAX_KEYS = 50;
/** Max length for string values; stringified arrays are truncated to this. */
const EVENT_DATA_STRING_MAX_LENGTH = 500;
/** Max decimal places for numbers. */
const EVENT_DATA_NUMBER_DECIMAL_PLACES = 4;

/** Event data accepted by trackEvent; sanitized to Umami limits before send. */
export type UmamiEventData = Record<
    string,
    string | number | boolean | readonly unknown[] | Record<string, unknown> | null | undefined
>;

/**
 * Returns true if Umami is loaded and available for tracking.
 * @returns True when window.umami.track is a function.
 */
function isAvailable(): boolean {
    if (typeof window === 'undefined' || typeof window.umami?.track !== 'function') {
        return false;
    }
    return true;
}

/** Sanitizes event data to Umami limits (50 keys, 500 chars, 4 decimals); booleans kept as-is; objects/arrays stringified and truncated. */
function sanitizeEventData(data: UmamiEventData): Record<string, string | number | boolean> {
    const entries = Object.entries(data);
    if (entries.length > EVENT_DATA_MAX_KEYS) {
        if (import.meta.env.DEV) {
            console.warn(
                `Umami: event data truncated from ${entries.length} to ${EVENT_DATA_MAX_KEYS} keys`,
            );
        }
        entries.length = EVENT_DATA_MAX_KEYS;
    }

    const result: Record<string, string | number | boolean> = {};
    for (const [key, value] of entries) {
        // Explicitly skip null and undefined for clear, future-proof behavior.
        if (value === null || value === undefined) {
            continue;
        }
        if (typeof value === 'string') {
            const truncated =
                value.length > EVENT_DATA_STRING_MAX_LENGTH
                    ? value.slice(0, EVENT_DATA_STRING_MAX_LENGTH)
                    : value;
            if (value.length > EVENT_DATA_STRING_MAX_LENGTH && import.meta.env.DEV) {
                console.warn(
                    `Umami: event data value for "${key}" truncated from ${value.length} to ${EVENT_DATA_STRING_MAX_LENGTH} chars`,
                );
            }
            result[key] = truncated;
        } else if (typeof value === 'number') {
            const rounded =
                Number.isInteger(value) || !Number.isFinite(value)
                    ? value
                    : Number(
                          value.toFixed(EVENT_DATA_NUMBER_DECIMAL_PLACES),
                      );
            result[key] = rounded;
        } else if (typeof value === 'boolean') {
            result[key] = value;
        } else if (Array.isArray(value)) {
            const str = JSON.stringify(value);
            const truncated =
                str.length > EVENT_DATA_STRING_MAX_LENGTH
                    ? str.slice(0, EVENT_DATA_STRING_MAX_LENGTH)
                    : str;
            if (str.length > EVENT_DATA_STRING_MAX_LENGTH && import.meta.env.DEV) {
                console.warn(
                    `Umami: event data array for "${key}" stringified and truncated from ${str.length} to ${EVENT_DATA_STRING_MAX_LENGTH} chars`,
                );
            }
            result[key] = truncated;
        } else if (typeof value === 'object' && !Array.isArray(value)) {
            const str = JSON.stringify(value);
            const truncated =
                str.length > EVENT_DATA_STRING_MAX_LENGTH
                    ? str.slice(0, EVENT_DATA_STRING_MAX_LENGTH)
                    : str;
            if (str.length > EVENT_DATA_STRING_MAX_LENGTH && import.meta.env.DEV) {
                console.warn(
                    `Umami: event data object for "${key}" stringified and truncated from ${str.length} to ${EVENT_DATA_STRING_MAX_LENGTH} chars`,
                );
            }
            result[key] = truncated;
        }
    }
    return result;
}

/**
 * Track a custom event. Use sparingly for key interactions (e.g. signup, checkout).
 * Event names are truncated to 50 characters per Umami's limit.
 * Event data is sanitized to Umami limits (50 keys, 500 chars per string, 4 decimal places).
 */
export function trackEvent(eventName: string, eventData?: UmamiEventData): void {
    if (!isAvailable()) return;
    const name = eventName.slice(0, EVENT_NAME_MAX_LENGTH);
    if (import.meta.env.DEV && eventName.length > EVENT_NAME_MAX_LENGTH) {
        console.warn(`Umami: event name truncated from ${eventName.length} to ${EVENT_NAME_MAX_LENGTH} chars`);
    }
    try {
        if (eventData) {
            window.umami!.track(name, sanitizeEventData(eventData));
        } else {
            window.umami!.track(name);
        }
    } catch (e) {
        if (import.meta.env.DEV) {
            console.warn('Umami track failed', e);
        }
    }
}

/**
 * Normalize URL (string or URL) to pathname + search for Umami.
 * @param url - Full URL or path; strings are resolved against window.location.origin.
 * @returns pathname + search (e.g. "/contact?ref=nav").
 */
function getPathAndSearch(url: URL | string): string {
    const u = typeof url === 'string' ? new URL(url, window.location.origin) : url;
    return u.pathname + u.search;
}

/**
 * Track a page view. Called automatically on Inertia navigation.
 * Can also be called manually when URL or title changes outside Inertia.
 * visit.url is normalized when it's a string (e.g. different Inertia version or manual call).
 */
export function trackPageView(visit?: { url?: URL | string } | null): void {
    if (!isAvailable()) return;
    try {
        if (visit?.url) {
            const url = getPathAndSearch(visit.url);
            window.umami!.track({ url, title: document.title });
        } else {
            window.umami!.track();
        }
    } catch (e) {
        if (import.meta.env.DEV) {
            console.warn('Umami track failed', e);
        }
    }
}

/**
 * Initialize Umami tracking. Call once when the app mounts.
 * The Umami script sends a page view on initial load; we only track after the first
 * client-side navigation. On the first router 'finish' we do not call trackPageView
 * and set a flag; on subsequent 'finish' events we call trackPageView(visit).
 */
export function initializeUmami(): void {
    let hasSeenFirstFinish = false;
    router.on('finish', (event: { detail: { visit: ActiveVisit } }) => {
        if (!hasSeenFirstFinish) {
            hasSeenFirstFinish = true;
            return;
        }
        trackPageView(event.detail.visit);
    });
}
