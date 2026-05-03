<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class DnsRecord extends Model
{
    protected $table = 'dns_records';
    protected $primaryKey = 'id';

    protected $fillable = [
        'node_id', 'root_domain', 'subdomain', 'record_type',
        'ip_addr', 'cf_record_id', 'proxied',
    ];

    protected $casts = [
        'proxied' => 'boolean',
    ];

    function node()
    {
        return $this->belongsTo(SsNode::class, 'node_id', 'id');
    }
}
