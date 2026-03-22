<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts as Middleware;

class TrustHosts extends Middleware
{
    /**
     * Get the host patterns that should be trusted.
     *
     * @return array
     */
    public function hosts()
    {
        $patterns = array_filter([
            $this->allSubdomainsOfApplicationUrl(),
        ]);

        $extra = config('app.trusted_host_patterns', []);
        if (is_array($extra)) {
            foreach ($extra as $p) {
                if (is_string($p) && $p !== '') {
                    $patterns[] = $p;
                }
            }
        }

        return array_values(array_unique(array_filter($patterns)));
    }
}
