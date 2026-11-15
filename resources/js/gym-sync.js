export const RESOURCES = new Set(['members', 'groups', 'payments', 'medical', 'access', 'requests', 'operators', 'audit', 'permissions', 'availability', 'clock', '*']);

// El mismo cambio puede llegar por el canal operativo y el canal individual.
export class SyncBatch {
    constructor(limit = 256) { this.limit = limit; this.seen = new Map(); this.pending = new Set(); }
    push(event) {
        if (!event || typeof event.change_id !== 'string' || event.change_id.length > 80 || !Array.isArray(event.resources)) return false;
        const seen = this.seen.get(event.change_id) ?? new Set();
        let changed = false;
        for (const resource of event.resources) {
            if (RESOURCES.has(resource) && !seen.has(resource)) {
                seen.add(resource); this.pending.add(resource); changed = true;
            }
        }
        this.seen.set(event.change_id, seen);
        while (this.seen.size > this.limit) this.seen.delete(this.seen.keys().next().value);
        return changed;
    }
    invalidate(resources) { for (const resource of resources) if (RESOURCES.has(resource)) this.pending.add(resource); }
    take() { const resources = [...this.pending]; this.pending.clear(); return resources; }
}

export function affects(dependencies, resources) {
    return resources.includes('*') || resources.some(resource => dependencies.includes(resource));
}