<?php 

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Company;



class CompanySettingsController extends Controller
{
    public function edit(Request $request)
    {
        $company = $request->user()->company; // ajusta se a relação tiver outro nome

        return view('admin.company-settings', compact('company'));
    }

   public function update(Request $request)
{
    $company = $request->user()->company; // ajusta se a relação tiver outro nome

    $data = $request->validate([
        'name'             => ['required', 'string', 'max:255'],
        'website_url'      => ['nullable', 'url', 'max:255'],
        'instagram_url'    => ['nullable', 'url', 'max:255'],
        'facebook_url'     => ['nullable', 'url', 'max:255'],
        'linkedin_url'     => ['nullable', 'url', 'max:255'],
        'whatsapp_number'  => ['nullable', 'string', 'max:255'],
        'zapi_instance_id' => ['nullable', 'string', 'max:255'],
        'zapi_token'       => ['nullable', 'string', 'max:255'],
        'zapi_base_url'    => ['nullable', 'string', 'max:255'],
        'logo'             => ['nullable', 'image', 'max:2048'],
    ]);

    // Upload do logo direto na AWS S3, em uma pasta por imobiliária
    if ($request->hasFile('logo')) {
        $logo = $request->file('logo');

        $ext = $logo->getClientOriginalExtension() ?: 'png';
        $filename = 'logo.'.$ext;

        // Ex: companies/1/logo.png
        $path = $logo->storeAs(
            'companies/'.$company->id,
            $filename,
            's3' // disk do S3 configurado no filesystems.php
        );

        $data['logo_path'] = $path;
    }

    $company->update($data);

    return redirect()
        ->route('admin.company.edit')
        ->with('status', 'Dados da empresa atualizados com sucesso.');
}

}
