# How the Node DNS Resolution API Works

The `api/node/resolve_dns` endpoint ensures that DNS records in Cloudflare match the desired state of a node cluster (IP addresses and server domains).

## Core Architecture

### 1. Fission Awareness (Node Clusters)
The API is cluster-aware. When triggered for a main node (`node_id`), it automatically extracts the `node_ids` group (which includes the main node and all its clones) and reconciles DNS for the entire族群 (family) in a single request.

### 2. Strict Dual-Stack Isolation
To prevent conflicts between IPv4 and IPv6 addresses, the system enforces a strict prefix-based isolation:
- **IPv4 Records (Type A):** Only **clone ipv4 nodes** carry a DNS record; the subdomain is parsed from the node's `server` field (the clone connection domain, e.g. `{random8}n{id}`). Example: `a1b2c3d4n123.example.com`
- **IPv6 Records (Type AAAA):** Uses the `ipv6node` prefix. Example: `ipv6node123.example.com`

This ensures that a single node can have both A and AAAA records on distinct subdomains, avoiding issues with clients that might prefer one over the other in a shared-subdomain setup.

### 3. Ghost Record Detection (State Desync Fix)
The system uses a **DB-Driven Diff with Real-time Verification** strategy.
- **Normal Flow:** It compares the `ss_node` state with the local `dns_records` table. If they match, it initially considers it a `no_change` scenario.
- **Ghost Check:** Even if the local database suggests the records are up-to-date, the system performs a mandatory real-time query to Cloudflare to verify the record's existence and IP correctness.
- **Auto-Repair:**
    - If the record is **missing** on Cloudflare (but exists in the DB), the local stale record is deleted and a new one is created.
    - If the record exists on Cloudflare with the **wrong IP**, the system updates its local reference and executes a Cloudflare `PUT` update.

## The Reconciliation Process

When `resolve_dns` is called:

1.  **Extract Cluster:** Expands the `node_ids` for the given main node.
2.  **Iterate Nodes:** For each node in the cluster:
    - **IPv4 Reconciliation:** For clone ipv4 nodes, parse the subdomain from the node's `server` field (e.g. `{random8}n{id}`) and reconcile the Type A record to `node_ip`.
    - **IPv6 Reconciliation:** If `node_ipv6` exists, reconcile `ipv6node{$id}` (Type AAAA).
3.  **Diff & Verify:**
    - **Scene A: Identical & Verified:** DB matches desired state AND Cloudflare verification confirms it. (Response: `no_change`)
    - **Scene B: IP Update:** Domain matches, but IP differs. (Response: `updated_ip`)
    - **Scene C: Domain Swap:** Node moved to a different domain. (Response: `swapped_domain`)
    - **Scene D: Creation:** New record needed. (Response: `created`)

## API Usage

**Endpoint:** `POST /api/node/resolve_dns`
**Parameters:**
- `node_id` (int): The main node ID.
- `token` (string): API security token.
- `force` (bool, optional): If `1`, bypasses certain optimizations (though Ghost Check already provides high reliability).

---
*Generated on 2026-05-02*
