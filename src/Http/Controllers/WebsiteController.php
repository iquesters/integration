<?php

namespace Iquesters\Integration\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller; 
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;

class WebsiteController extends Controller
{
    public function fetchWebsite(Request $request)
{
    $validator = Validator::make($request->all(), [
        'url' => 'required|url|max:500'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Invalid URL provided'
        ], 400);
    }

    $url = $request->input('url');

    try {
        $response = Http::timeout(15)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; WebsiteVerifier/1.0)'
            ])
            ->get($url);

        if ($response->successful()) {
            $html = $response->body();
            
            // Extract metadata
            $metadata = $this->extractMetadata($html, $url);

            return response()->json([
                'success' => true,
                'title' => $metadata['title'],
                'description' => $metadata['description'],
                'favicon' => $metadata['favicon'],
                'image' => $metadata['image'],
                'message' => 'Website fetched successfully'
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Website returned status code: ' . $response->status()
            ], 400);
        }

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error fetching website: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * Extract metadata from HTML
 */
private function extractMetadata($html, $url)
{
    $metadata = [
        'title' => '',
        'description' => '',
        'favicon' => '',
        'image' => ''
    ];

    // Parse URL for base path
    $parsedUrl = parse_url($url);
    $baseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];

    // Extract title
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
        $metadata['title'] = trim(strip_tags($matches[1]));
    }

    // Extract meta description
    if (preg_match('/<meta[^>]*name=["\']description["\'][^>]*content=["\'](.*?)["\']/is', $html, $matches)) {
        $metadata['description'] = trim(strip_tags($matches[1]));
    } elseif (preg_match('/<meta[^>]*property=["\']og:description["\'][^>]*content=["\'](.*?)["\']/is', $html, $matches)) {
        $metadata['description'] = trim(strip_tags($matches[1]));
    }

    // Extract favicon
    if (preg_match('/<link[^>]*rel=["\'][^"\']*icon[^"\']*["\'][^>]*href=["\'](.*?)["\']/is', $html, $matches)) {
        $favicon = $matches[1];
        $metadata['favicon'] = $this->makeAbsoluteUrl($favicon, $baseUrl);
    } else {
        // Default favicon location
        $metadata['favicon'] = $baseUrl . '/favicon.ico';
    }

    // Extract Open Graph image or first image
    if (preg_match('/<meta[^>]*property=["\']og:image["\'][^>]*content=["\'](.*?)["\']/is', $html, $matches)) {
        $metadata['image'] = $this->makeAbsoluteUrl($matches[1], $baseUrl);
    } elseif (preg_match('/<img[^>]*src=["\'](.*?)["\']/is', $html, $matches)) {
        $metadata['image'] = $this->makeAbsoluteUrl($matches[1], $baseUrl);
    }

    return $metadata;
}

/**
 * Convert relative URL to absolute
 */
private function makeAbsoluteUrl($url, $baseUrl)
{
    // Already absolute
    if (preg_match('/^https?:\/\//i', $url)) {
        return $url;
    }

    // Protocol-relative URL
    if (strpos($url, '//') === 0) {
        return 'https:' . $url;
    }

    // Absolute path
    if (strpos($url, '/') === 0) {
        return $baseUrl . $url;
    }

    // Relative path
    return $baseUrl . '/' . $url;
}
}