<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\PageVisit;
use Illuminate\Support\Str;
use Jenssegers\Agent\Agent;

class TrackPageVisits
{
    /**
     * Routes to exclude from tracking
     */
    protected array $excludedRoutes = [
        'api.*',
        'debugbar.*',
        'telescope.*',
        'horizon.*',
        'livewire.*',
        '_ignition.*',
    ];

    /**
     * URL patterns to exclude
     */
    protected array $excludedPatterns = [
        '/favicon.ico',
        '/_debugbar',
        '/css/',
        '/js/',
        '/images/',
        '/fonts/',
        '/storage/',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        
        $response = $next($request);
        
        // Only track GET requests and HTML responses
        if ($request->method() !== 'GET') {
            return $response;
        }

        // Skip excluded routes
        if ($this->shouldExclude($request)) {
            return $response;
        }

        // Skip AJAX requests
        if ($request->ajax() || $request->wantsJson()) {
            return $response;
        }

        $responseTime = (int) ((microtime(true) - $startTime) * 1000);

        // Track the visit asynchronously or immediately based on preference
        $this->trackVisit($request, $responseTime);

        return $response;
    }

    /**
     * Check if request should be excluded from tracking
     */
    protected function shouldExclude(Request $request): bool
    {
        // Check route name exclusions
        $routeName = $request->route()?->getName();
        if ($routeName) {
            foreach ($this->excludedRoutes as $pattern) {
                if (Str::is($pattern, $routeName)) {
                    return true;
                }
            }
        }

        // Check URL pattern exclusions
        $path = $request->path();
        foreach ($this->excludedPatterns as $pattern) {
            if (Str::contains('/' . $path, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Track the page visit
     */
    protected function trackVisit(Request $request, int $responseTime): void
    {
        try {
            $agent = new Agent();
            $agent->setUserAgent($request->userAgent());

            // Generate or retrieve visitor ID from cookie
            $visitorId = $request->cookie('visitor_id');
            if (!$visitorId) {
                $visitorId = Str::uuid()->toString();
                // Cookie will be set after response
            }

            // Get location data from IP (you can integrate with a geolocation service)
            $locationData = $this->getLocationFromIp($request->ip());

            PageVisit::create([
                'session_id' => $request->session()->getId(),
                'visitor_id' => $visitorId,
                'user_id' => auth()->id(),
                'ip_address' => $this->anonymizeIp($request->ip()),
                'url' => Str::limit($request->fullUrl(), 2048),
                'route_name' => $request->route()?->getName(),
                'method' => $request->method(),
                'referrer' => $request->header('referer') ? Str::limit($request->header('referer'), 2048) : null,
                'user_agent' => Str::limit($request->userAgent(), 500),
                'device_type' => $this->getDeviceType($agent),
                'browser' => $agent->browser(),
                'browser_version' => $agent->version($agent->browser()),
                'platform' => $agent->platform(),
                'country' => $locationData['country'] ?? null,
                'country_code' => $locationData['country_code'] ?? null,
                'city' => $locationData['city'] ?? null,
                'region' => $locationData['region'] ?? null,
                'latitude' => $locationData['latitude'] ?? null,
                'longitude' => $locationData['longitude'] ?? null,
                'is_bot' => $agent->isRobot(),
                'response_time_ms' => $responseTime,
            ]);
        } catch (\Exception $e) {
            // Silently fail - don't break the application for analytics
            \Log::warning('Failed to track page visit: ' . $e->getMessage());
        }
    }

    /**
     * Get device type from user agent
     */
    protected function getDeviceType(Agent $agent): string
    {
        if ($agent->isTablet()) {
            return 'tablet';
        }
        if ($agent->isMobile()) {
            return 'mobile';
        }
        return 'desktop';
    }

    /**
     * Anonymize IP address for privacy (remove last octet)
     */
    protected function anonymizeIp(?string $ip): ?string
    {
        if (!$ip) {
            return null;
        }

        // For IPv4
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return preg_replace('/\.\d+$/', '.0', $ip);
        }

        // For IPv6
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return preg_replace('/:[^:]+$/', ':0', $ip);
        }

        return $ip;
    }

    /**
     * Get location data from IP address
     * This is a placeholder - integrate with a geolocation service like MaxMind, ip-api.com, etc.
     */
    protected function getLocationFromIp(?string $ip): array
    {
        // Skip for local/private IPs
        if (!$ip || $this->isPrivateIp($ip)) {
            return [];
        }

        // You can integrate with services like:
        // - MaxMind GeoIP2
        // - ip-api.com (free tier available)
        // - ipinfo.io
        // - ipstack.com

        // For now, try free ip-api.com service (limited to 45 requests per minute)
        try {
            $response = @file_get_contents("http://ip-api.com/json/{$ip}?fields=status,country,countryCode,regionName,city,lat,lon");
            if ($response) {
                $data = json_decode($response, true);
                if ($data && ($data['status'] ?? '') === 'success') {
                    return [
                        'country' => $data['country'] ?? null,
                        'country_code' => $data['countryCode'] ?? null,
                        'region' => $data['regionName'] ?? null,
                        'city' => $data['city'] ?? null,
                        'latitude' => $data['lat'] ?? null,
                        'longitude' => $data['lon'] ?? null,
                    ];
                }
            }
        } catch (\Exception $e) {
            // Silently fail
        }

        return [];
    }

    /**
     * Check if IP is private/local
     */
    protected function isPrivateIp(?string $ip): bool
    {
        if (!$ip) {
            return true;
        }

        return !filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
}
