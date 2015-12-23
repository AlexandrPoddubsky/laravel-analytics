<?php

namespace Spatie\LaravelAnalytics;

use DateTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LaravelAnalytics
{
    /**
     * @var GoogleApiHelper
     */
    protected $client;

    /**
     * @var string
     */
    protected $siteId;

    /**
     * @param GoogleApiHelper $client
     * @param string          $siteId
     */
    public function __construct(GoogleApiHelper $client, $siteId = '')
    {
        $this->client = $client;
        $this->siteId = $siteId;
    }

    /**
     * Set the siteId.
     *
     * @param string $siteId
     *
     * @return $this
     */
    public function setSiteId($siteId)
    {
        $this->siteId = $siteId;

        return $this;
    }

    /**
     * Get the amount of visitors for given page URL.
     *
     * @param array  $urls
     * @param int    $numberOfDays
     *
     * @return Collection
     */
    public function getMultiplePageVisits($urls = [], $numberOfDays = 365)
    {
        list($startDate, $endDate) = $this->calculateNumberOfDays($numberOfDays);

        $answer = $this->performQuery($startDate, $endDate, 'ga:pageviews', [
            'dimensions' => 'ga:pagePath,ga:date',
            'filters' => "ga:pagePath==" . implode(',ga:pagePath==', $urls),
        ]);

        // Get empty data array
        $visitorData = $this->getEmptyDateRangeData($urls, $startDate, $endDate);

        // No data...sad
        if (is_null($answer->rows)) {
            return new Collection($visitorData);
        }

        // Merge Analytic data with days
        foreach ($answer->rows as $dataRow) {
            $date = Carbon::createFromFormat('Ymd H:i:s', "{$dataRow[1]} 00:00:00")->format('U') * 1000;

            if (isset($visitorData[$dataRow[0]][$date])) {
                $visitorData[$dataRow[0]][$date] = $dataRow[2];
            }
        }

        return new Collection($visitorData);
    }

    /**
     * Get the amount of visitors and pageViews for given page URL.
     *
     * @param string $metrics
     * @param int    $numberOfDays
     * @param int    $limit
     *
     * @return Collection
     */
    public function getSeriesPageViews($metrics, $numberOfDays = 7, $limit = 20)
    {
        list($startDate, $endDate) = $this->calculateNumberOfDays($numberOfDays);


        $collecion = new Collection([
            2 => 13,
            3 => 10,
            4 => 10,
            5 => 8,
            12 => 3,
            13 => 2,
            66 => 2,
        ]);

        return $collecion;



        $visitorData = [];

//        $answer = $this->performQuery($startDate, $endDate, 'ga:visits,ga:pageviews', [
//            'metrics' => 'ga:pageviews',
//            'dimensions' => 'ga:pagePath',
//            'filters' => "ga:pagePath=~^/series/.*/;ga:pagePath!@/episodes/",
//            'sort' => "-ga:pageviews",
//            'max-results' => $limit,
//        ]);

        $answer = $this->performQuery($startDate, $endDate, 'ga:totalEvents', [
            'dimensions' => 'ga:eventLabel',
            'filters' => 'ga:eventCategory==Series;ga:eventAction==view',
            'sort' => '-ga:eventLabel',
            'max-results' => $limit,
        ]);

        if (is_null($answer->rows)) {
            return new Collection($visitorData);
        }
dd($answer->rows);
        foreach ($answer->rows as $pageRow) {
            $visitorData[$pageRow[0]] = $pageRow[1];
        }

        return new Collection($visitorData);
    }

    /**
     * Get the top referrers.
     *
     * @param int $numberOfDays
     * @param int $maxResults
     *
     * @return Collection
     */
    public function getTopReferrers($numberOfDays = 365, $maxResults = 20)
    {
        list($startDate, $endDate) = $this->calculateNumberOfDays($numberOfDays);

        return $this->getTopReferrersForPeriod($startDate, $endDate, $maxResults);
    }

    /**
     * Get the top referrers for the given period.
     *
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @param int      $maxResults
     *
     * @return Collection
     */
    public function getTopReferrersForPeriod(DateTime $startDate, DateTime $endDate, $maxResults)
    {
        $referrerData = [];

        $answer = $this->performQuery($startDate, $endDate, 'ga:pageviews', [
            'dimensions' => 'ga:fullReferrer',
            'sort' => '-ga:pageviews',
            'max-results' => $maxResults
        ]);

        if (is_null($answer->rows)) {
            return new Collection([]);
        }

        foreach ($answer->rows as $pageRow) {
            $referrerData[] = [
                'url' => $pageRow[0],
                'pageViews' => $pageRow[1]
            ];
        }

        return new Collection($referrerData);
    }

    /**
     * Get the most visited pages for the given period.
     *
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @param int      $maxResults
     *
     * @return Collection
     */
    public function getMostVisitedPagesForPeriod(DateTime $startDate, DateTime $endDate, $maxResults = 20)
    {
        $pagesData = [];

        $answer = $this->performQuery($startDate, $endDate, 'ga:pageviews', [
            'dimensions' => 'ga:pagePath',
            'sort' => '-ga:pageviews',
            'max-results' => $maxResults
        ]);

        if (is_null($answer->rows)) {
            return new Collection([]);
        }

        foreach ($answer->rows as $pageRow) {
            $pagesData[] = [
                'url' => $pageRow[0],
                'pageViews' => $pageRow[1]
            ];
        }

        return new Collection($pagesData);
    }

    /**
     * Returns the site id (ga:xxxxxxx) for the given url.
     *
     * @param string $url
     *
     * @throws \Exception
     *
     * @return string
     */
    public function getSiteIdByUrl($url)
    {
        return $this->client->getSiteIdByUrl($url);
    }

    /**
     * Call the query method on the authenticated client.
     *
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @param string   $metrics
     * @param array    $others
     *
     * @return mixed
     */
    public function performQuery(DateTime $startDate, DateTime $endDate, $metrics, $others = [])
    {
        return $this->client->performQuery($this->siteId, $startDate->format('Y-m-d'), $endDate->format('Y-m-d'),
            $metrics, $others);
    }

    /**
     * Call the real time query method on the authenticated client.
     *
     * @param string $metrics
     * @param array  $others
     *
     * @return mixed
     */
    public function performRealTimeQuery($metrics, $others = [])
    {
        return $this->client->performRealTimeQuery($this->siteId, $metrics, $others);
    }

    /**
     * Return true if this site is configured to use Google Analytics.
     *
     * @return bool
     */
    public function isEnabled()
    {
        return $this->siteId != '';
    }

    /**
     * Returns an array with the current date and the date minus the number of days specified.
     *
     * @param array $urls
     * @param Carbon $startDate
     * @param Carbon $endDate
     *
     * @return array
     */
    private function getEmptyDateRangeData($urls, $startDate, $endDate)
    {
        $data = [];

        // Difference in days
        $numberOfDays = $startDate->diffInDays($endDate);

        // Create date range array
        $range = [];
        for ($i = 0; $i < $numberOfDays; $i++) {
            $day = $startDate->addDay()->format('U') * 1000;
            $range[$day] = 0;
        }

        // Add ranges to data
        foreach ($urls as $url) {
            $data[$url] = $range;
        }

        return $data;
    }

    /**
     * Returns an array with the current date and the date minus the number of days specified.
     *
     * @param int $numberOfDays
     *
     * @return array
     */
    private function calculateNumberOfDays($numberOfDays)
    {
        $endDate = Carbon::today()->subDays(1);
        $startDate = Carbon::today()->subDays($numberOfDays + 1);

        return [$startDate, $endDate];
    }
}
