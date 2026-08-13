# How the Node DNS Resolution API Works

The `api/node/resolve_dns` endpoint ensures that DNS records in Cloudflare match the desired state of a node cluster (IP addresses and server domains).

## Core Architecture

### 1. Fission Awareness (Node Clusters)
The API is cluster-aware. When triggered for a main node (`node_id`), it automatically extracts the `node_ids` group (which includes the main node and all its clones) and reconciles DNS for the entire族群 (family) in a single request.

### 2. DNS Record vs Direct Connection
Only **clone ipv4 nodes** carry a DNS record; main nodes and all IPv6 nodes connect directly and need no DNS entry:
- **Clone IPv4 Nodes (Type A):** The subdomain is parsed from the node's `server` field (the clone connection domain, e.g. `{random8}n{id}`). Example: `a1b2c3d4n123.example.com` → resolves to the node IPv4.
- **Main Nodes & IPv6 Nodes (Direct Connect, no record):** Main ipv4 nodes connect via their raw IP; IPv6 nodes (whose `server` is the native IPv6 literal) connect via IPv6. They carry **no DNS record**, and any residual records left from older layouts are deleted during reconciliation.

Node classification is driven by the `server` field (clone ipv4 = `{random8}n{id}` domain; IPv6 = raw IPv6 literal / legacy `ipv6n` marker), not by which IP column is populated. This keeps DNS records minimal — only clone ipv4 connection domains are reconciled — leaving headroom under the Cloudflare record quota.

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
    - **Main / IPv6 Nodes:** Skipped (direct connect) — delete any residual records; no DNS record is created.
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
