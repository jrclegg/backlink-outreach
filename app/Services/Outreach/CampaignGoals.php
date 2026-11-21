<?php
namespace App\Services\Outreach;
// The few kinds of outreach the general campaign engine knows how to run. A new segment is a new
// campaign brief (data), not a new goal; add a goal only for a genuinely different kind of ask.
class CampaignGoals {
    const ALL = [
        'sales' => [
            'label' => 'Sell a product or service',
            'research' => 'organisations that could genuinely buy or use the offer',
            'detail' => 'ONE specific, concrete detail from the organisation\'s own website about what they do or who they serve (a niche service, client type, way of working) that makes the offer relevant and gives a natural, personal opening',
            'body' => 'then one or two plain sentences on what the offer is and the single benefit most relevant to them',
            'contact' => 'the owner\'s, a director\'s, a relevant decision maker\'s, or the organisation\'s general inbox',
            'call' => false,
        ],
        'link' => [
            'label' => 'Earn a link to a page',
            'research' => 'websites with a specific existing page (a resource list, guide, article, directory or reading list) where a link to product_url would genuinely help that page\'s readers',
            'detail' => 'the exact page where the link would fit (detail_url must be that page) and what it covers, so the email can name it and say where the link would fit',
            'body' => 'then one or two plain sentences on what product_url offers their readers and why it would be a useful addition to that specific page',
            'contact' => 'the site owner\'s, editor\'s, author\'s or the site\'s general inbox',
            'call' => false,
        ],
        'sponsorship' => [
            'label' => 'Win sponsors or advertisers',
            'research' => 'companies that sell to the audience described in the offer and plausibly market to them',
            'detail' => 'ONE specific detail from the company\'s own website about a product or customer group that overlaps with the audience on offer',
            'body' => 'then one or two plain sentences on who the audience is and what the sponsorship or advertising offers them',
            'contact' => 'a marketing, partnerships or founder address, or the company\'s general inbox',
            'call' => false,
        ],
        'partnership' => [
            'label' => 'Find partners or referrers',
            'research' => 'organisations that serve the same customers without competing, so a referral or partnership would benefit both sides',
            'detail' => 'ONE specific detail from the organisation\'s own website about who they serve that shows the overlap',
            'body' => 'then one or two plain sentences on the partnership idea and what is in it for them',
            'contact' => 'the owner\'s, a director\'s, a partnerships address, or the organisation\'s general inbox',
            'call' => false,
        ],
        'conversation' => [
            'label' => 'Ask for a short conversation',
            'research' => 'people or organisations who match the target and whose experience would answer the questions in the offer',
            'detail' => 'ONE specific detail from their own website about what they do that explains why their view would be useful',
            'body' => 'then one or two plain sentences on what you are trying to learn or offering, and a clear, low-pressure ask for a short conversation',
            'contact' => 'the person\'s own address, a director\'s, or the organisation\'s general inbox',
            'call' => true,
        ],
    ];

    public static function get(?string $goal): array {
        return self::ALL[$goal] ?? self::ALL['sales'];
    }

    public static function labels(): array {
        return array_map(fn ($g) => $g['label'], self::ALL);
    }
}
