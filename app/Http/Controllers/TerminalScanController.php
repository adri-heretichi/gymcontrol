<?php

namespace App\Http\Controllers;

use App\Services\ControlAccesoService;
use Illuminate\Http\Request;

class TerminalScanController extends Controller
{
    public function scan(Request $request, string $token, ScanQrController $scanner, ControlAccesoService $controlAcceso)
    {
        return $scanner->scan($request, $token, $controlAcceso);
    }
}
