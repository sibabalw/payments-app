<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     */
    public function toResponse($request): Response
    {
        $user = $request->user();

        // Admins go straight to admin dashboard, skipping onboarding
        if ($user->is_admin) {
            return $request->wantsJson()
                ? new JsonResponse(['two_factor' => false, 'redirect' => route('admin.dashboard')], 200)
                : redirect()->intended(route('admin.dashboard'));
        }

        // Regular users go to dashboard (which handles onboarding check)
        return $request->wantsJson()
            ? new JsonResponse(['two_factor' => false], 200)
            : redirect()->intended(config('fortify.home'));
    }
}
