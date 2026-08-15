<?php

namespace App\Http\Controllers;

use App\Models\EmailInbox;
use App\Services\Mail\InboxConnectProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

class OAuthInboxController extends Controller
{
    protected InboxConnectProvider $provider;

    public function __construct(InboxConnectProvider $provider)
    {
        $this->provider = $provider;
        $this->middleware('auth');
    }

    public function connect(Request $request, string $provider)
    {
        if (!in_array($provider, ['gmail', 'outlook'])) {
            return Redirect::route('inboxes.create')
                ->with('error', 'Invalid provider');
        }

        // Store the provider and inbox details in session for later retrieval
        session()->put('oauth_provider', $provider);
        session()->put('oauth_inbox_name', $request->query('name', 'My Inbox'));
        session()->put('oauth_inbox_from_name', $request->query('from_name', Auth::user()->name));

        if ($provider === 'gmail') {
            $url = $this->provider->getGoogleAuthUrl();
        } else {
            $url = $this->provider->getOutlookAuthUrl();
        }

        return Redirect::to($url);
    }

    public function callback(Request $request, string $provider)
    {
        if (!in_array($provider, ['gmail', 'outlook'])) {
            return Redirect::route('inboxes.create')
                ->with('error', 'Invalid provider');
        }

        $code = $request->query('code');
        $error = $request->query('error');

        if ($error) {
            return Redirect::route('inboxes.create')
                ->with('error', "OAuth error: {$error}");
        }

        if (!$code) {
            return Redirect::route('inboxes.create')
                ->with('error', 'No authorization code received');
        }

        try {
            $org = Auth::user()->currentOrganization;
            $name = session()->get('oauth_inbox_name', 'My Inbox');
            $fromName = session()->get('oauth_inbox_from_name', Auth::user()->name);

            if ($provider === 'gmail') {
                $inbox = $this->provider->handleGoogleCallback($code, $org->id, $name, $fromName);
            } else {
                $inbox = $this->provider->handleOutlookCallback($code, $org->id, $name, $fromName);
            }

            session()->forget(['oauth_provider', 'oauth_inbox_name', 'oauth_inbox_from_name']);

            return Redirect::route('inboxes.index')
                ->with('success', "Email inbox '{$inbox->name}' connected successfully!");
        } catch (\Exception $e) {
            return Redirect::route('inboxes.create')
                ->with('error', "Failed to connect inbox: {$e->getMessage()}");
        }
    }
}
