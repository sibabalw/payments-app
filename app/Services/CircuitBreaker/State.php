<?php

namespace App\Services\CircuitBreaker;

enum State: string
{
    case CLOSED = 'closed'; // Normal operation - requests pass through
    case OPEN = 'open'; // Circuit is open - requests fail fast
    case HALF_OPEN = 'half_open'; // Testing if service recovered - allow limited requests
}
