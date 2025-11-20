<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ChatCatalog;

class ChatWidgetController extends Controller
{
    public function bootstrap(Request $request, ChatCatalog $catalog)
    {
        $useCases = $catalog->useCases($request->user());
        $personas = $catalog->personas();

        return response()->json([
            'useCases' => $useCases,
            'personas' => $personas,
        ]);
    }
}