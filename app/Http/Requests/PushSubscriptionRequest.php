<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PushSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subscription' => ['required', 'array'],
            'subscription.endpoint' => ['required', 'string', 'max:2048'],
            'subscription.keys' => ['required', 'array'],
            'subscription.keys.p256dh' => ['required', 'string', 'max:255'],
            'subscription.keys.auth' => ['required', 'string', 'max:255'],
            'subscription.expirationTime' => ['nullable'],
            'wants_prayer' => ['sometimes', 'boolean'],
            'wants_live' => ['sometimes', 'boolean'],
            'locale' => ['sometimes', 'string', Rule::in(['en', 'tl', 'cbk', 'gly'])],
        ];
    }
}
