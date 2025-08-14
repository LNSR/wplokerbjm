
export class RouterService {
  static getJobSlugFromRoute(routePath: string): string | null {
    const match = routePath.match(/^\/lowongan\/([^/?#]+)/);
    return match ? match[1] : null;
  }
}