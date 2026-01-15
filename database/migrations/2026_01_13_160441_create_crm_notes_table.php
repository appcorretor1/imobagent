<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('corretor_id');
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('empreendimento_id')->nullable();
            $table->text('conteudo');
            $table->string('origem')->default('whatsapp'); // whatsapp, dashboard, api
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'corretor_id']);
            $table->index(['company_id', 'created_at']);
            $table->index('lead_id');
            $table->index('empreendimento_id');

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('corretor_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('lead_id')->references('id')->on('crm_leads')->onDelete('set null');
            $table->foreign('empreendimento_id')->references('id')->on('empreendimentos')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_notes');
    }
};
