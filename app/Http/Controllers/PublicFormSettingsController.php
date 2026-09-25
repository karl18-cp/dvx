<?php

namespace App\Http\Controllers;

use App\Models\PublicFormSetting;
use App\Services\PublicFormService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PublicFormSettingsController extends Controller
{
    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === 'admin' && $request->user()->status === 'active', 403);
    }

    public function index(Request $request, PublicFormService $forms)
    {
        $this->authorizeAdmin($request);

        return Inertia::render('public-form-settings', ['definitions' => ['application' => $forms->definition('application'), 'business' => $forms->definition('business')], 'statusMessage' => $request->session()->get('status')]);
    }

    public function update(Request $request, string $kind, PublicFormService $forms)
    {
        $this->authorizeAdmin($request);
        abort_unless(in_array($kind, ['application', 'business'], true), 404);
        $definition = $forms->validateDefinition($request, $kind);
        PublicFormSetting::updateOrCreate(['kind' => $kind], ['definition' => $definition, 'updated_by' => $request->user()->id]);

        return back()->with('status', 'Form settings saved. New visitors will see the updated form.');
    }
}
