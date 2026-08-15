<?php

namespace App\Http\Controllers;

use App\Models\CampaignClick;
use App\Models\CampaignRecipient;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    public function open(string $token)
    {
        $recipient = CampaignRecipient::where('token', $token)->first();

        if ($recipient && !$recipient->opened_at) {
            $recipient->update([
                'opened_at' => now(),
                'open_count' => $recipient->open_count + 1,
            ]);
        }

        // Always return a 1x1 GIF regardless of match status
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        return response($gif)
            ->header('Content-Type', 'image/gif')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
    }

    public function click(Request $request, string $token)
    {
        $recipient = CampaignRecipient::where('token', $token)->first();

        if ($recipient) {
            CampaignClick::create([
                'campaign_recipient_id' => $recipient->id,
                'url' => $request->input('u'),
                'clicked_at' => now(),
                'ip' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            if (!$recipient->first_clicked_at) {
                $recipient->update([
                    'first_clicked_at' => now(),
                    'click_count' => $recipient->click_count + 1,
                ]);
            } else {
                $recipient->increment('click_count');
            }

            $url = $request->input('u');
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                return redirect($url);
            }
        }

        return redirect('/');
    }
}
