@extends('layouts.admin')
@include('partials/admin.settings.nav', ['activeTab' => 'sso'])

@section('title')
  SSO Settings
@endsection

@section('content-header')
  <h1>SSO Settings<small>Configure OpenID Connect single sign-on for the login screen.</small></h1>
  <ol class="breadcrumb">
    <li><a href="{{ route('admin.index') }}">Admin</a></li>
    <li class="active">Settings</li>
  </ol>
@endsection

@section('content')
  @yield('settings::nav')
  <div class="row">
    <div class="col-xs-12">
      <form action="{{ route('admin.settings.sso') }}" method="POST">
        <div class="box">
          <div class="box-header with-border">
            <h3 class="box-title">OpenID Connect</h3>
          </div>
          <div class="box-body">
            <div class="row">
              <div class="form-group col-md-4">
                <label class="control-label">Enable SSO</label>
                <div>
                  <select name="openid:enabled" class="form-control">
                    <option value="false" @if(old('openid:enabled', config('openid.enabled') ? 'true' : 'false') === 'false') selected @endif>Disabled</option>
                    <option value="true" @if(old('openid:enabled', config('openid.enabled') ? 'true' : 'false') === 'true') selected @endif>Enabled</option>
                  </select>
                  <p class="text-muted"><small>Adds a "Sign in with ..." button to the login page. Password login stays enabled.</small></p>
                </div>
              </div>
              <div class="form-group col-md-4">
                <label class="control-label">Login Button Label</label>
                <div>
                  <input type="text" class="form-control" name="openid:display_name"
                    value="{{ old('openid:display_name', config('openid.display_name')) }}" />
                  <p class="text-muted"><small>Shown on the button, e.g. "Authentik" &rarr; "Sign in with Authentik".</small></p>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-12">
                <div class="alert alert-info">
                  <strong>Redirect / Callback URL</strong><br>
                  Register this exact URL with your identity provider:
                  <code>{{ url('/auth/login/sso/callback') }}</code>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="box">
          <div class="box-header with-border">
            <h3 class="box-title">Provider Credentials</h3>
          </div>
          <div class="box-body">
            <div class="row">
              <div class="form-group col-md-12">
                <label class="control-label">Issuer URL</label>
                <div>
                  <input type="text" class="form-control" name="openid:issuer"
                    value="{{ old('openid:issuer', config('openid.issuer')) }}"
                    placeholder="https://sso.example.com/application/o/pyrodactyl/" />
                  <p class="text-muted"><small>The provider's base/issuer URL. Discovery is performed at <code>{issuer}/.well-known/openid-configuration</code>.</small></p>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="form-group col-md-6">
                <label class="control-label">Client ID</label>
                <div>
                  <input type="text" class="form-control" name="openid:client_id"
                    value="{{ old('openid:client_id', config('openid.client_id')) }}" />
                </div>
              </div>
              <div class="form-group col-md-6">
                <label class="control-label">Client Secret</label>
                <div>
                  <input type="password" class="form-control" name="openid:client_secret"
                    value="{{ old('openid:client_secret', config('openid.client_secret')) }}" autocomplete="new-password" />
                  <p class="text-muted"><small>Stored encrypted. Leave as-is to keep the current secret.</small></p>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="form-group col-md-12">
                <label class="control-label">Scopes</label>
                <div>
                  <input type="text" class="form-control" name="openid:scopes"
                    value="{{ old('openid:scopes', config('openid.scopes')) }}" />
                  <p class="text-muted"><small>Space-separated. Must include <code>openid</code>; <code>email</code> is required for account matching.</small></p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="box">
          <div class="box-header with-border">
            <h3 class="box-title">Account Provisioning &amp; Security</h3>
          </div>
          <div class="box-body">
            <div class="row">
              <div class="form-group col-md-4">
                <label class="control-label">Auto-create Users</label>
                <div>
                  <select name="openid:auto_create" class="form-control">
                    <option value="true" @if(old('openid:auto_create', config('openid.auto_create') ? 'true' : 'false') === 'true') selected @endif>Yes</option>
                    <option value="false" @if(old('openid:auto_create', config('openid.auto_create') ? 'true' : 'false') === 'false') selected @endif>No</option>
                  </select>
                  <p class="text-muted"><small>Provision new users on first login (requires a verified email from the provider).</small></p>
                </div>
              </div>
              <div class="form-group col-md-4">
                <label class="control-label">Create Users as Admin</label>
                <div>
                  <select name="openid:create_as_admin" class="form-control">
                    <option value="false" @if(old('openid:create_as_admin', config('openid.create_as_admin') ? 'true' : 'false') === 'false') selected @endif>No</option>
                    <option value="true" @if(old('openid:create_as_admin', config('openid.create_as_admin') ? 'true' : 'false') === 'true') selected @endif>Yes</option>
                  </select>
                  <p class="text-muted"><small>Grant admin to newly provisioned SSO users. Leave "No" unless your provider is the sole source of trusted admins.</small></p>
                </div>
              </div>
              <div class="form-group col-md-4">
                <label class="control-label">Re-authentication Window (seconds)</label>
                <div>
                  <input type="number" min="30" max="3600" class="form-control" name="openid:reauth_window"
                    value="{{ old('openid:reauth_window', config('openid.reauth_window', 300)) }}" />
                  <p class="text-muted"><small>How long a fresh SSO re-login stays valid when confirming destructive actions (e.g. deleting backups).</small></p>
                </div>
              </div>
            </div>
          </div>
          <div class="box-footer">
            {{ csrf_field() }}
            <button type="submit" name="_method" value="PATCH" class="btn btn-sm btn-primary pull-right">Save</button>
          </div>
        </div>
      </form>
    </div>
  </div>
@endsection
