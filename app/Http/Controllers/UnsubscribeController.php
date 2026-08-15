<?php

namespace App\Http\Controllers;

use App\Models\CampaignRecipient;
use App\Models\Suppression;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UnsubscribeController extends Controller
{
    /**
     * Show unsubscribe confirmation page
     */
    public function show(string $token)
    {
        $recipient = CampaignRecipient::where('token', $token)->first();

        if (!$recipient) {
            return view('unsubscribe-confirm', [
                'error' => 'Invalid or expired unsubscribe link',
                'valid' => false,
            ]);
        }

        return view('unsubscribe-confirm', [
            'recipient' => $recipient,
            'email' => $recipient->prospect->contact_email,
            'valid' => true,
        ]);
    }

    /**
     * Process unsubscribe request
     */
    public function store(Request $request, string $token)
    {
        $recipient = CampaignRecipient::where('token', $token)->first();

        if (!$recipient) {
            return redirect('/')->with('error', 'Invalid or expired unsubscribe link');
        }

        try {
            // Create suppression record
            Suppression::updateOrCreate(
                [
                    'organization_id' => $recipient->campaign->project->organization_id,
                    'email' => $recipient->prospect->contact_email,
                ],
                [
                    'reason' => 'unsubscribe_link',
                    'campaign_recipient_id' => $recipient->id,
                ]
            );

            Log::info('Email unsubscribed', [
                'email' => $recipient->prospect->contact_email,
                'campaign_id' => $recipient->campaign_id,
                'organization_id' => $recipient->campaign->project->organization_id,
            ]);

            return redirect('/')->with('success', 'You have been unsubscribed successfully');
        } catch (\Exception $e) {
            Log::error('Unsubscribe failed', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);

            return redirect('/')->with('error', 'Failed to unsubscribe. Please try again later.');
        }
    }
}
