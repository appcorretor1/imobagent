<?php

use App\Http\Controllers\WhatsappWebhookController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserCheckController;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\DashboardGestorController;
use App\Http\Controllers\IncorporadoraController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\EmpreendimentoUnidadeController;
use App\Http\Controllers\CompanySettingsController;
use App\Http\Controllers\EmpreendimentoFotoController;


use App\Http\Controllers\{
    ProfileController,
    EmpreendimentoController,
    AssetController,
    QaController
};


Route::get('/debug/proposta/{emp}/{unidade}/{grupo}', [\App\Http\Controllers\WppController::class, 'debugProposta'])
    ->name('debug.proposta');




/*
|--------------------------------------------------------------------------
| Página inicial / Dashboard
|--------------------------------------------------------------------------
*/
Route::get('/', fn () => view('welcome'));

/* VERIFICA NUMERO USUARIO */
Route::get('/usuarios/check', [UserCheckController::class, 'check']);


//dashboard filtrado | gestor e corretor
  Route::get('/dashboard', [DashboardGestorController::class, 'index'])
            ->name('admin.dashboard');
Route::middleware(['auth', 'verified', 'role:diretor'])
    ->prefix('admin')
    ->group(function () {

      

        Route::get('/dashboard/pdf', [DashboardGestorController::class, 'exportPdf'])
            ->name('admin.dashboard.pdf');

        Route::get('/company', [CompanySettingsController::class, 'edit'])
            ->name('admin.company.edit');

        Route::post('/company', [CompanySettingsController::class, 'update'])
            ->name('admin.company.update');
    });

// Gestão de usuários
Route::middleware(['auth', 'role:diretor,corretor'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('users', AdminUserController::class);

        Route::post('users/{user}/toggle-status', [AdminUserController::class, 'toggleStatus'])
            ->name('users.toggle-status');

        Route::post('users/{user}/resend-access', [AdminUserController::class, 'resendAccess'])
            ->name('users.resend-access');
    });

/*
|--------------------------------------------------------------------------
| Área autenticada (perfil do usuário)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/profile',  [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/*
|--------------------------------------------------------------------------
| Área administrativa (requer auth + tenant)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'tenant'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Empreendimentos e incorporadoras/contrutoras
        |--------------------------------------------------------------------------
        */
        Route::resource('empreendimentos', EmpreendimentoController::class)
            ->parameters(['empreendimentos' => 'e'])
            ->whereNumber('e');

         Route::delete('/empreendimentos/{empreendimento}', [EmpreendimentoController::class, 'destroy'])->name('empreendimentos.destroy');

           // 🔹 INCORPORADORAS (sem prefix extra, sem "admin." duplicado)
        Route::get('incorporadoras', [IncorporadoraController::class, 'index'])
            ->name('incorporadoras.index');

        Route::post('incorporadoras', [IncorporadoraController::class, 'store'])
            ->name('incorporadoras.store');

        Route::get('incorporadoras/{id}/edit', [IncorporadoraController::class, 'edit'])
            ->name('incorporadoras.edit');

        Route::post('incorporadoras/{id}', [IncorporadoraController::class, 'update'])
            ->name('incorporadoras.update');

        /*
        |--------------------------------------------------------------------------
        | Texto corrido (base de conhecimento da IA)
        |--------------------------------------------------------------------------
        */
        Route::get('empreendimentos/{e}/texto', [EmpreendimentoController::class, 'editTexto'])
            ->name('empreendimentos.texto.edit')
            ->whereNumber('e');

        Route::post('empreendimentos/{e}/texto', [EmpreendimentoController::class, 'updateTexto'])
            ->name('empreendimentos.texto.update')
            ->whereNumber('e');

        /*
        |--------------------------------------------------------------------------
        | Knowledge Assets (arquivos do empreendimento)
        |--------------------------------------------------------------------------
        */
        Route::get('empreendimentos/{e}/assets', [AssetController::class, 'index'])
            ->name('assets.index')
            ->whereNumber('e');

        Route::post('empreendimentos/{e}/assets', [AssetController::class, 'store'])
            ->name('assets.store')
            ->whereNumber('e');

        Route::get('empreendimentos/{e}/assets/{asset}', [AssetController::class, 'show'])
            ->name('assets.show')
            ->whereNumber('e')
            ->whereNumber('asset');

        Route::get('empreendimentos/{e}/assets/{asset}/download', [AssetController::class, 'download'])
            ->name('assets.download')
            ->whereNumber('e')
            ->whereNumber('asset');

        Route::delete('empreendimentos/{e}/assets/{asset}', [AssetController::class, 'destroy'])
            ->name('assets.destroy')
            ->whereNumber('e')
            ->whereNumber('asset');

        /*
        |--------------------------------------------------------------------------
        | QA (Chat sobre o empreendimento)
        |--------------------------------------------------------------------------
        */
        Route::get('empreendimentos/{e}/perguntar', [QaController::class, 'form'])
            ->name('qa.form')
            ->whereNumber('e');

        Route::post('empreendimentos/{e}/perguntar', [QaController::class, 'ask'])
            ->name('qa.ask')
            ->whereNumber('e');

        Route::post('empreendimentos/{e}/perguntar/reset', [QaController::class, 'reset'])
            ->name('qa.reset')
            ->whereNumber('e');

        // Upload direto para o Vector Store (sem banco)
        Route::post('empreendimentos/{e}/vs/files', [QaController::class, 'attachVsFile'])
            ->name('qa.vs.attach')
            ->whereNumber('e');

        // Remover arquivo do VS
        Route::delete('empreendimentos/{e}/vs/files/{fileId}', [QaController::class, 'deleteVsFile'])
            ->name('qa.vs.delete')
            ->whereNumber('e');

        // Listar arquivos do VS (para montar a UI)
        Route::get('empreendimentos/{e}/vs/files', [QaController::class, 'listVsFiles'])
            ->name('qa.vs.list')
            ->whereNumber('e');

        /*
        |--------------------------------------------------------------------------
        | Unidades do empreendimento
        |--------------------------------------------------------------------------
        |
        | Aqui usamos {empreendimento} separado de {e} do resource.
        | O controller EmpreendimentoUnidadeController está tipado com
        | Empreendimento $empreendimento, então esse binding fica redondo.
        */
        Route::prefix('empreendimentos/{empreendimento}')
            ->name('empreendimentos.')
            ->whereNumber('empreendimento')
            ->group(function () {
                Route::get('unidades', [EmpreendimentoUnidadeController::class, 'index'])
                    ->name('unidades.index');

                Route::post('unidades', [EmpreendimentoUnidadeController::class, 'store'])
                    ->name('unidades.store');

                Route::put('unidades/{unidade}', [EmpreendimentoUnidadeController::class, 'update'])
                    ->name('unidades.update')
                    ->whereNumber('unidade');

                Route::delete('unidades/{unidade}', [EmpreendimentoUnidadeController::class, 'destroy'])
                    ->name('unidades.destroy')
                    ->whereNumber('unidade');

                Route::put('unidades-bulk-status', [EmpreendimentoUnidadeController::class, 'bulkUpdateStatus'])
                    ->name('unidades.bulk-status');

                Route::post('unidades/import', [EmpreendimentoUnidadeController::class, 'import'])
                    ->name('unidades.import');

                     // 🔹 NOVO: modelo de planilha
                    Route::get('unidades/modelo', [EmpreendimentoUnidadeController::class, 'downloadTemplate'])
    ->name('unidades.template');
            });
    });

/* verifica se usuario existe no banco de dados */
Route::post('/whatsapp/webhook', [WhatsappWebhookController::class, 'handle']);


//fotos separada para ver arquivos
Route::get('/empreendimentos/{company}/{empreend}/fotos', [EmpreendimentoFotoController::class, 'index'])
    ->name('empreendimentos.fotos');

/*
|--------------------------------------------------------------------------
| Rota de teste do S3 (pode ser removida em produção)
|--------------------------------------------------------------------------
*/
Route::get('/test-s3', function () {
    try {
        // envia o arquivo de teste dentro da pasta "documentos"
        Storage::disk('s3')->put('documentos/teste.txt', 'Upload S3 funcionando!');
        return '✅ OK — arquivo "documentos/teste.txt" enviado para o S3 com sucesso!';
    } catch (Throwable $e) {
        return '❌ Erro no upload: ' . $e->getMessage();
    }
});

Route::get('/debug-s3', function () {
    try {
        $disk = Storage::disk('s3');
        $path = 'documentos/_ping_laravel.txt';
        $disk->put($path, 'ok');

        return response()->json([
            'put'    => 'ok',
            'exists' => $disk->exists($path),
            'url'    => $disk->temporaryUrl($path, now()->addMinutes(5)),
            'bucket' => env('AWS_BUCKET'),
            'region' => env('AWS_DEFAULT_REGION'),
        ]);
    } catch (Throwable $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

Route::get('/debug/vs/{empreendimento}', function (\App\Models\Empreendimento $e) {
    $svc    = app(\App\Services\VectorStoreService::class);
    $vs     = $svc->ensureVectorStoreForEmpreendimento($e->id);
    $client = $svc->client();

    $list = $client->vectorStores()->files()->list($vs, ['limit' => 50]);
    $rows = [];
    foreach ($list->data as $f) {
        $arr   = json_decode(json_encode($f), true);
        $rows[] = [
            'file_id' => $arr['id'] ?? null,
            'status'  => $arr['status'] ?? null,
            'error'   => $arr['last_error']['message'] ?? null,
            'created' => $arr['created_at'] ?? null,
        ];
    }

    return response()->json([
        'vs_id' => $vs,
        'files' => $rows,
    ]);
})->middleware('auth');

require __DIR__ . '/auth.php';
