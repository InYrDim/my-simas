/**
 * Wayfinder bakes domain-bound routes as protocol-relative URLs
 * (`//console.localhost/login`) which drop the dev server port. The
 * console is always served from its own host, so a host-less path is
 * correct and works on any port.
 */
export function consolePath(url: string): string {
    return url.replace(/^\/\/[^/]+/, '');
}
