<?php

namespace App\Services\Outreach;

use App\Models\EmployerCampaign;
use Carbon\Carbon;
use PDO;
use RuntimeException;

class AcademicEmployerLeads
{
    public function import(EmployerCampaign $campaign): int
    {
        if (config('outreach.academic_jobs_feed')) {
            return $this->importFeed($campaign);
        }
        $path = config('outreach.academic_jobs_database');
        if (! $path || ! is_file($path)) {
            throw new RuntimeException('Configure ACADEMIC_JOBS_DATABASE with the local Academic Ecology Jobs SQLite database path.');
        }
        // Open the scraper database read-only. This app never changes its jobs.
        $db = new PDO('sqlite:'.$path, null, null, [PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $columns = array_column($db->query('PRAGMA table_info(job_listings)')->fetchAll(PDO::FETCH_ASSOC), 'name');
        foreach (['id', 'institution', 'subject_area', 'url', 'source', 'created_at'] as $required) {
            if (! in_array($required, $columns)) {
                throw new RuntimeException('The configured database does not contain the expected scraper job fields.');
            }
        }
        $where = "created_at >= :recent AND url IS NOT NULL AND url != ''";
        if (in_array('source_closed', $columns)) {
            $where .= ' AND (source_closed IS NULL OR source_closed = 0)';
        }
        if (in_array('valid_through', $columns)) {
            $where .= ' AND (valid_through IS NULL OR valid_through >= :today)';
        }
        $query = $db->prepare("SELECT * FROM job_listings WHERE $where ORDER BY CASE WHEN source = 'jobsacuk' THEN 0 ELSE 1 END, created_at DESC LIMIT 200");
        $params = ['recent' => now()->subDays(60)->toDateTimeString()];
        if (in_array('valid_through', $columns)) {
            $params['today'] = now()->toDateTimeString();
        }
        $query->execute($params);
        $count = 0;
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (! PublicPage::validUrl($row['url']) || ! trim($row['institution']) || strtolower(trim($row['institution'])) === 'unknown') {
                continue;
            }
            $paid = $row['source'] === 'jobsacuk';
            $lead = $campaign->leads()->firstOrCreate(['source_job_id' => hash('sha256', $row['url'])], ['employer' => mb_substr($row['institution'], 0, 255), 'job_title' => mb_substr($row['subject_area'], 0, 255), 'job_url' => $row['url'], 'source' => $row['source'], 'country' => $row['country'] ?? null, 'deadline' => $row['valid_through'] ?? null, 'first_seen' => $row['created_at'], 'signal' => $paid ? 'Paid advertising channel — purchase unconfirmed' : 'Hiring activity — advertising spend unknown', 'priority' => $paid ? 100 : 10, 'rate_url' => $paid ? 'https://www.jobs.ac.uk/recruiters/products/higher-and-further-education' : null]);
            if ($lead->wasRecentlyCreated) {
                $count++;
            }
        }

        return $count;
    }

    private function importFeed(EmployerCampaign $campaign): int
    {
        $data = json_decode(app(PublicPage::class)->fetch(config('outreach.academic_jobs_feed')), true);
        if (! is_array($data['items'] ?? null)) {
            throw new RuntimeException('The academic job feed returned an invalid format. No leads were imported.');
        }
        $count = 0;
        foreach (array_slice($data['items'], 0, 200) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $url = $item['inReplyTo']['href'] ?? '';
            $employer = trim($item['attributedTo']['name'] ?? '');
            $title = trim($item['name'] ?? '');
            if (! PublicPage::validUrl($url) || ! $employer || strtolower($employer) === 'unknown' || ! $title) {
                continue;
            }
            try {
                $seen = Carbon::parse($item['published'] ?? 'invalid');
                $deadline = empty($item['expiresAt']) ? null : Carbon::parse($item['expiresAt']);
            } catch (\Throwable) {
                continue;
            }
            if ($seen->lt(now()->subDays(60)) || $seen->gt(now()->addDay()) || ($deadline && $deadline->isPast())) {
                continue;
            }
            $source = preg_replace('/^View on /', '', $item['inReplyTo']['name'] ?? parse_url($url, PHP_URL_HOST));
            $paid = in_array(strtolower(parse_url($url, PHP_URL_HOST)), ['jobs.ac.uk', 'www.jobs.ac.uk']);
            // The item id is the job's own page on the board, shown to employers as "already listed".
            $listing = is_string($item['id'] ?? null) && PublicPage::validUrl($item['id']) ? mb_substr($item['id'], 0, 255) : null;
            $lead = $campaign->leads()->firstOrCreate(['source_job_id' => hash('sha256', $url)], [
                'employer' => mb_substr($employer, 0, 255), 'job_title' => mb_substr($title, 0, 255), 'job_url' => $url, 'listing_url' => $listing,
                'source' => $paid ? 'jobsacuk' : mb_substr($source, 0, 255),
                'country' => mb_substr($item['attributedTo']['location'] ?? '', 0, 255), 'deadline' => $deadline, 'first_seen' => $seen,
                'signal' => $paid ? 'Paid advertising channel — purchase unconfirmed' : 'Hiring activity — advertising spend unknown',
                'priority' => $paid ? 100 : 10,
                'rate_url' => $paid ? 'https://www.jobs.ac.uk/recruiters/products/higher-and-further-education' : null,
            ]);
            if ($lead->wasRecentlyCreated) {
                $count++;
            } elseif ($listing && ! $lead->listing_url) {
                $lead->update(['listing_url' => $listing]);
            }
        }

        return $count;
    }
}
