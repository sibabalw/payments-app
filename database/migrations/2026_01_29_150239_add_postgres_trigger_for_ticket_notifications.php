<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds PostgreSQL triggers to send notifications when tickets or messages are created/updated.
     */
    public function up(): void
    {
        $dbDriver = DB::getDriverName();

        if ($dbDriver === 'pgsql') {
            // Function to notify on ticket changes
            DB::unprepared("
                CREATE OR REPLACE FUNCTION notify_ticket_changes()
                RETURNS TRIGGER AS \$\$
                BEGIN
                    PERFORM pg_notify('tickets', json_build_object(
                        'type', TG_OP,
                        'ticket_id', NEW.id,
                        'user_id', NEW.user_id,
                        'status', NEW.status,
                        'priority', NEW.priority,
                        'subject', NEW.subject,
                        'timestamp', NOW()
                    )::text);
                    RETURN NEW;
                END;
                \$\$ LANGUAGE plpgsql;
            ");

            // Trigger for tickets table
            DB::unprepared('
                DROP TRIGGER IF EXISTS trg_notify_ticket_changes ON tickets;
                CREATE TRIGGER trg_notify_ticket_changes
                AFTER INSERT OR UPDATE ON tickets
                FOR EACH ROW
                EXECUTE FUNCTION notify_ticket_changes();
            ');

            // Function to notify on ticket message changes
            DB::unprepared("
                CREATE OR REPLACE FUNCTION notify_ticket_message_changes()
                RETURNS TRIGGER AS \$\$
                BEGIN
                    PERFORM pg_notify('tickets', json_build_object(
                        'type', 'ticket_message_' || TG_OP,
                        'ticket_id', NEW.ticket_id,
                        'message_id', NEW.id,
                        'user_id', NEW.user_id,
                        'is_admin', NEW.is_admin,
                        'timestamp', NOW()
                    )::text);
                    RETURN NEW;
                END;
                \$\$ LANGUAGE plpgsql;
            ");

            // Trigger for ticket_messages table
            DB::unprepared('
                DROP TRIGGER IF EXISTS trg_notify_ticket_message_changes ON ticket_messages;
                CREATE TRIGGER trg_notify_ticket_message_changes
                AFTER INSERT ON ticket_messages
                FOR EACH ROW
                EXECUTE FUNCTION notify_ticket_message_changes();
            ');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $dbDriver = DB::getDriverName();

        if ($dbDriver === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_notify_ticket_changes ON tickets;');
            DB::unprepared('DROP FUNCTION IF EXISTS notify_ticket_changes();');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_notify_ticket_message_changes ON ticket_messages;');
            DB::unprepared('DROP FUNCTION IF EXISTS notify_ticket_message_changes();');
        }
    }
};
