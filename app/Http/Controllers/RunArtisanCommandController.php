<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\HttpFoundation\Response;

/**
 * Endpoint, der die Ausführung beliebiger Artisan-Befehle über das Web ermöglicht.
 * 
 * Geschützt über ein geheimes Token (ARTISAN_TOKEN).
 */
class RunArtisanCommandController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $expected = (string) config('app.artisan_token');
        $providedHeader = (string) $request->header('X-Artisan-Token');
        $providedQuery = (string) $request->query('token');

        // Token kann entweder per Header (X-Artisan-Token) oder als URL-Parameter (?token=...) übergeben werden
        $provided = $providedHeader !== '' ? $providedHeader : $providedQuery;

        abort_if($expected === '' || ! hash_equals($expected, $provided), 403, 'Unauthorized');

        $command = (string) $request->query('command');

        abort_if($command === '', 400, 'Kein Artisan Befehl angegeben (?command=...)');

        try {
            Artisan::call($command);
            $output = Artisan::output();
            
            return response($output !== '' ? $output : 'Befehl erfolgreich ausgeführt (kein Output).', 200)
                ->header('Content-Type', 'text/plain');
        } catch (\Exception $e) {
            return response('Fehler bei der Ausführung: ' . $e->getMessage(), 500)
                ->header('Content-Type', 'text/plain');
        }
    }
}
