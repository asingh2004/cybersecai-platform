<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/



Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});



Route::get('/siemref/{id}', function($id) {
    $row = DB::table('siem_refs')->where('id', $id)->first();
    if(!$row) return response()->json(['error'=>'Not found'],404);
    return response()->json([
        'format' => $row->format,
        'template_field_map' => json_decode($row->template_field_map, true)
    ]);
});