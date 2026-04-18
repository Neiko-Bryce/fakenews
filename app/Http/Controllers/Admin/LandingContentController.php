<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateLandingCopyFormRequest;
use App\Services\LandingContentService;
use App\Services\LandingFormMapper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LandingContentController extends Controller
{
    public function edit(LandingContentService $service, LandingFormMapper $mapper): Response
    {
        return Inertia::render('admin/landing', [
            'landing' => $mapper->formFromResolved($service->resolve()),
            'has_custom_content' => $service->hasStoredContent(),
        ]);
    }

    public function update(UpdateLandingCopyFormRequest $request, LandingContentService $service, LandingFormMapper $mapper): RedirectResponse
    {
        $merged = $mapper->mergeIntoCurrent(
            $service->resolve(),
            $request->validated('landing'),
        );

        try {
            $service->save($merged);
        } catch (ValidationException $e) {
            return Redirect::back()->withErrors($e->errors());
        }

        return Redirect::route('admin.landing.edit')->with('status', 'landing-saved');
    }

    public function reset(LandingContentService $service): RedirectResponse
    {
        $service->reset();

        return Redirect::route('admin.landing.edit')->with('status', 'landing-reset');
    }
}
