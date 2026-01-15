<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_deals', function (Blueprint $table) {
            $table->string('empreendimento_nome')->nullable()->after('empreendimento_id');
            $table->string('empreendimento_nome_normalizado')->nullable()->after('empreendimento_nome');
            $table->index('empreendimento_nome_normalizado');
        });
    }

    public function down(): void
    {
        Schema::table('crm_deals', function (Blueprint $table) {
            $table->dropIndex(['empreendimento_nome_normalizado']);
            $table->dropColumn(['empreendimento_nome', 'empreendimento_nome_normalizado']);
        });
    }
};
