#!/bin/bash
# Setup Laravel Scheduler

echo "Setting up Laravel Scheduler..."

# Option 1: Using Supervisor (recommended for production)
if command -v supervisorctl &> /dev/null; then
    echo "Supervisor detected. Creating supervisor config..."
    sudo cp supervisor/laravel-scheduler.conf /etc/supervisor/conf.d/
    sudo supervisorctl reread
    sudo supervisorctl update
    sudo supervisorctl start laravel-scheduler:*
    echo "Scheduler started via Supervisor. Check status with: sudo supervisorctl status laravel-scheduler"
else
    echo "Supervisor not found. Setting up cron job instead..."
    # Option 2: Using Cron (fallback)
    CRON_ENTRY="* * * * * cd /home/sibabalwentoyi/payments-app && php artisan schedule:run >> /dev/null 2>&1"
    
    # Check if cron entry already exists
    if crontab -l 2>/dev/null | grep -q "schedule:run"; then
        echo "Cron job already exists."
    else
        (crontab -l 2>/dev/null; echo "$CRON_ENTRY") | crontab -
        echo "Cron job added. Scheduler will run every minute."
    fi
fi

echo ""
echo "To test manually, run: php artisan schedule:run"
echo "To see scheduled tasks: php artisan schedule:list"
