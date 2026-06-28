<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\HttpFoundation\Response;

/**
 * Von Cloud Scheduler aufgerufener Endpoint, der die fälligen geplanten
 * Aufgaben (z. B. reservations:purge, accounts:purge) ausführt.
 *
 * Geschützt über ein geheimes Token (SCHEDULER_TOKEN), da Cloud Run keinen
 * eigenen Cron-Daemon hat.
 */
class RunScheduledTasksController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $expected = (string) config('app.scheduler_token');
        $provided = (string) $request->header('X-Scheduler-Token');

        abort_if($expected === '' || ! hash_equals($expected, $provided), 403);

        Artisan::call('schedule:run');

        return response('OK', 200);
    }
}
