<?php

namespace App\Components\DNS;

interface DnsProviderInterface
{
    /**
     * Add or update a DNS record
     *
     * @param string $domain The root domain
     * @param string $host The subdomain (or '@')
     * @param string $value The IP address
     * @param string $type Record type (A or AAAA)
     * @return bool
     */
    public function updateRecord($domain, $host, $value, $type = 'A');
}
