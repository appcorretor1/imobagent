<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{
    WppController,
    WppSessionController,
    WppBotController, // se ainda quiser manter legado
};
use App\Http\Controllers\WppFileController;

// Health
Route::get('/health', fn () => response()->json(['ok' => true, 'ts' => now()->toIso8601String()]));
Route::get('/ping',   fn () => response()->json(['ok' => true, 'time' => now()]))->name('api.ping');

// Session (se você usa)
Route::get ('/wpp/session',       [WppSessionController::class, 'get'])->name('api.wpp.session.get');
Route::post('/wpp/session',       [WppSessionController::class, 'set'])->name('api.wpp.session.set');
Route::post('/wpp/session/touch', [WppSessionController::class, 'touch'])->name('api.wpp.session.touch');
Route::post('/wpp/session/reset', [WppSessionController::class, 'reset'])->name('api.wpp.session.reset');

// WhatsApp (NOVA – principal)
Route::middleware('throttle:60,1')->post('/wpp/inbound', [WppController::class, 'inbound'])->name('api.wpp.inbound');
Route::post('/wpp/empreendimentos/select', [WppController::class, 'select'])->name('api.wpp.empreendimentos.select');

// (Opcional) legado: se algum integrador ainda chama
//Route::post('/wpp/handle', [WppBotController::class, 'handle'])->name('legacy.wpp.handle');


//enviar doc para usuario
Route::post('/wpp/send-file', [WppFileController::class, 'sendFile']);



//recebe conversa para enviar whatsapp
Route::post('/make/wpp/send', [\App\Http\Controllers\WppProxyController::class, 'sendFromMake']);
