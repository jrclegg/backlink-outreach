<?php

namespace App\Services\Discovery;

use Illuminate\Support\Collection;

class ProxyPool
{
    private Collection $proxies;

    /**
     * Initialize proxy pool from config or provided list
     */
    public function __construct(?array $proxyList = null)
    {
        if ($proxyList) {
            $this->proxies = collect($proxyList);
        } else {
            // Load from config if available
            $this->proxies = collect(config('discovery.proxies', []));
        }
    }

    /**
     * Check if proxies are available
     */
    public function hasProxies(): bool
    {
        return $this->proxies->count() > 0;
    }

    /**
     * Get a random proxy from the pool
     */
    public function getRandomProxy(): ?string
    {
        if (!$this->hasProxies()) {
            return null;
        }

        return $this->proxies->random();
    }

    /**
     * Add a proxy to the pool
     */
    public function addProxy(string $proxy): self
    {
        if (!$this->proxies->contains($proxy)) {
            $this->proxies->push($proxy);
        }

        return $this;
    }

    /**
     * Remove a proxy from the pool
     */
    public function removeProxy(string $proxy): self
    {
        $this->proxies = $this->proxies->reject(fn($p) => $p === $proxy);

        return $this;
    }

    /**
     * Get all proxies
     */
    public function all(): Collection
    {
        return $this->proxies;
    }
}
