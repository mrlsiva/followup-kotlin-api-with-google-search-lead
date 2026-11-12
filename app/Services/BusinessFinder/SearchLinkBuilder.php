<?php

namespace App\Services\BusinessFinder;

use App\Support\Categories;

/**
 * Builds free, manual-research links (Google Maps / Google Search). Nothing is fetched or
 * scraped: the user opens these links in the browser or the Maps app and researches by hand.
 */
class SearchLinkBuilder
{
    /**
     * @param  array{business_name?: ?string, match_type?: ?string, keywords?: ?string, category?: ?string,
     *               district?: ?string, city?: ?string, area?: ?string, pincode?: ?string, quantity?: ?int}  $p
     */
    public function build(array $p): array
    {
        $what = $this->what($p);
        $where = $this->where($p);
        $query = trim("$what in $where");
        $quantity = (int) ($p['quantity'] ?? 20);

        $search = fn (string $q, int $start = 0) => 'https://www.google.com/search?'
            .http_build_query(array_filter(['q' => $q, 'start' => $start ?: null]));

        $contactQuery = "$query contact number";
        $pages = [];
        for ($i = 0, $n = (int) ceil($quantity / 10); $i < $n; $i++) {
            $pages[] = ['page' => $i + 1, 'url' => $search($contactQuery, $i * 10)];
        }

        return [
            'query' => $query,
            'target_count' => $quantity,
            'google_maps_url' => 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($query),
            'google_maps_app_uri' => 'geo:0,0?q='.rawurlencode($query),
            'google_search_url' => $search($contactQuery),
            'links' => [
                ['type' => 'maps', 'label' => 'Google Maps', 'url' => 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($query)],
                ['type' => 'contact', 'label' => 'Contact numbers', 'url' => $search($contactQuery)],
                ['type' => 'website', 'label' => 'Official websites', 'url' => $search("$query official website")],
                ['type' => 'directory', 'label' => 'Address and phone', 'url' => $search("$query address phone")],
            ],
            'result_pages' => $pages,
            'instructions' => 'Open the links, research each business manually, then use "Add Business to CRM". '
                .'Google may show fewer than the target count; the number is only a research goal.',
        ];
    }

    /** "Hospitals", "\"Apollo Hospital\"", "Bakery cake shop" … */
    private function what(array $p): string
    {
        $name = trim((string) ($p['business_name'] ?? ''));
        $keywords = trim((string) ($p['keywords'] ?? ''));
        $category = trim((string) ($p['category'] ?? ''));

        if ($name !== '') {
            $name = ($p['match_type'] ?? 'partial') === 'exact' ? '"'.str_replace('"', '', $name).'"' : $name;

            return trim("$name $keywords");
        }
        if ($category !== '' && ! in_array($category, [Categories::ALL, Categories::OTHER], true)) {
            return trim(Categories::searchTerm($category).' '.$keywords);
        }

        return $keywords !== '' ? $keywords : 'Businesses';
    }

    /** "Chennai", "Anna Nagar, Madurai 625020", "Theni district, Tamil Nadu", "Tamil Nadu". */
    private function where(array $p): string
    {
        $state = config('business_finder.state');
        $area = trim((string) ($p['area'] ?? ''));
        $city = trim((string) ($p['city'] ?? ''));
        $district = trim((string) ($p['district'] ?? ''));
        $pincode = trim((string) ($p['pincode'] ?? ''));

        $place = implode(', ', array_filter([$area, $city !== '' ? $city : ($district !== '' ? "$district district" : '')]));
        if ($place === '' && $pincode === '') {
            return $state;
        }
        $place = trim("$place $pincode");

        return $city === '' ? "$place, $state" : $place;
    }
}
