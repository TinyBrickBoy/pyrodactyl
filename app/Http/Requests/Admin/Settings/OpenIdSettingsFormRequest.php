<?php

namespace Pterodactyl\Http\Requests\Admin\Settings;

use Illuminate\Validation\Rule;
use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class OpenIdSettingsFormRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'openid:enabled' => ['required', Rule::in(['true', 'false'])],
            'openid:display_name' => ['nullable', 'string', 'max:191'],
            'openid:issuer' => ['nullable', 'string', 'max:191', 'url', 'required_if:openid:enabled,true'],
            'openid:client_id' => ['nullable', 'string', 'max:191', 'required_if:openid:enabled,true'],
            'openid:client_secret' => ['nullable', 'string', 'max:1000', 'required_if:openid:enabled,true'],
            'openid:scopes' => ['nullable', 'string', 'max:191'],
            'openid:auto_create' => ['required', Rule::in(['true', 'false'])],
            'openid:create_as_admin' => ['required', Rule::in(['true', 'false'])],
            'openid:reauth_window' => ['required', 'integer', 'min:30', 'max:3600'],
        ];
    }

    public function attributes(): array
    {
        return [
            'openid:enabled' => 'SSO Enabled',
            'openid:display_name' => 'Login Button Label',
            'openid:issuer' => 'Issuer URL',
            'openid:client_id' => 'Client ID',
            'openid:client_secret' => 'Client Secret',
            'openid:scopes' => 'Scopes',
            'openid:auto_create' => 'Auto-create Users',
            'openid:create_as_admin' => 'Create Users as Admin',
            'openid:reauth_window' => 'Re-authentication Window',
        ];
    }

    public function normalize(?array $only = null): array
    {
        $data = $this->validated();

        // When SSO is disabled, keep the provider credentials as entered but
        // ensure a sensible default scope/label remain populated.
        if (empty($data['openid:display_name'])) {
            $data['openid:display_name'] = 'Single Sign-On';
        }

        if (empty($data['openid:scopes'])) {
            $data['openid:scopes'] = 'openid profile email';
        }

        if ($only !== null) {
            return array_intersect_key($data, array_flip($only));
        }

        return $data;
    }
}
