<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DatabaseController extends Controller
{
    /**
     * Display database management page.
     */
    public function index(): Response
    {
        $dbConfig = [
            'default' => config('database.default'),
            'driver' => config('database.connections.'.config('database.default').'.driver'),
            'database' => config('database.connections.'.config('database.default').'.database'),
            'host' => config('database.connections.'.config('database.default').'.host'),
        ];

        // Get database size
        $dbSize = null;
        try {
            if (in_array($dbConfig['driver'], ['mysql', 'mariadb'])) {
                $result = DB::selectOne('
                    SELECT 
                        ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                    FROM information_schema.tables 
                    WHERE table_schema = ?
                ', [$dbConfig['database']]);

                $dbSize = $result ? round($result->size_mb, 2).' MB' : 'Unknown';
            } elseif ($dbConfig['driver'] === 'pgsql') {
                $result = DB::selectOne('
                    SELECT 
                        pg_size_pretty(pg_database_size(?)) AS size
                ', [$dbConfig['database']]);

                $dbSize = $result ? $result->size : 'Unknown';
            }
        } catch (\Exception $e) {
            $dbSize = 'Unable to calculate';
        }

        // Get table count
        $tableCount = 0;
        try {
            if (in_array($dbConfig['driver'], ['mysql', 'mariadb'])) {
                $tables = DB::select('SHOW TABLES');
                $tableCount = count($tables);
            } elseif ($dbConfig['driver'] === 'pgsql') {
                $result = DB::selectOne('
                    SELECT COUNT(*) as count
                    FROM information_schema.tables 
                    WHERE table_schema = \'public\'
                ');
                $tableCount = $result ? (int) $result->count : 0;
            }
        } catch (\Exception $e) {
            // Not supported or error
        }

        return Inertia::render('admin/database/index', [
            'dbConfig' => $dbConfig,
            'dbSize' => $dbSize,
            'tableCount' => $tableCount,
        ]);
    }

    /**
     * Run database migrations.
     */
    public function migrate(): RedirectResponse
    {
        Artisan::call('migrate', ['--force' => true]);

        return back()->with('success', 'Database migrations completed successfully.');
    }

    /**
     * Optimize database.
     */
    public function optimize(): RedirectResponse
    {
        Artisan::call('optimize:clear');
        Artisan::call('config:cache');
        Artisan::call('route:cache');
        Artisan::call('view:cache');

        return back()->with('success', 'Database optimization completed successfully.');
    }
}
