export interface SocialMediaItem {
  platform: string;
  username: string;
  icon: string;
  url: string;
}
export class SocialMediaService {
  protected static platforms: Record<string, { icon: string; base_url: string }> = {
    "X / Twitter": {
      icon: "fab fa-x-twitter",
      base_url: "https://twitter.com/",
    },
    Facebook: { icon: "fab fa-facebook", base_url: "https://facebook.com/" },
    Instagram: { icon: "fab fa-instagram", base_url: "https://instagram.com/" },
    LinkedIn: { icon: "fab fa-linkedin", base_url: "https://linkedin.com/in/" },
    Youtube: { icon: "fab fa-youtube", base_url: "https://youtube.com/@" },
    Whatsapp: { icon: "fab fa-whatsapp", base_url: "https://wa.me/" },
    Tiktok: { icon: "fab fa-tiktok", base_url: "https://tiktok.com/@" },
    Threads: { icon: "fab fa-threads", base_url: "https://threads.net/@" },
    Telegram: { icon: "fab fa-telegram", base_url: "https://t.me/" },
  };

  static getLinkData(platform: string, username: string): SocialMediaItem | null {
    const config = SocialMediaService.platforms[platform];
    if (!config || !username) return null;
    if (platform === "Whatsapp")
      return SocialMediaService.getWhatsappLinkData(platform, config, username);
    if (platform === "LinkedIn")
      return SocialMediaService.getLinkedInLinkData(platform, config, username);
    return SocialMediaService.getDefaultLinkData(platform, config, username);
  }

  private static getWhatsappLinkData(
    platform: string,
    config: { icon: string; base_url: string },
    username: string
  ): SocialMediaItem {
    if (/^https?:\/\/wa\.me\/qr\/[A-Z0-9]+$/i.test(username)) {
      return { platform, icon: config.icon, url: username, username };
    }
    const waMeMatch = /^(?:https?:\/\/)?wa\.me\/(\d+)$/i.exec(username);
    if (waMeMatch) {
      const number = waMeMatch[1];
      return {
        platform,
        icon: config.icon,
        url: `https://wa.me/${number}`,
        username: `+${number}`,
      };
    }
    if (/^https?:\/\/((api|web)\.whatsapp\.com)/.test(username)) {
      return { platform, icon: config.icon, url: username, username };
    }
    const clean_number = username.replace(/[^0-9]/g, "");
    return {
      platform,
      icon: config.icon,
      url: config.base_url + clean_number,
      username,
    };
  }

  private static getLinkedInLinkData(
    platform: string,
    config: { icon: string; base_url: string },
    username: string
  ): SocialMediaItem {
    if (/^https?:\/\//i.test(username)) {
      return { platform, icon: config.icon, url: username, username };
    }
    const clean_username = username.replace(/^@/, "");
  const companyMatch = /^company[:/](.+)$/i.exec(clean_username);
    let url;
    if (companyMatch) {
      url = `https://linkedin.com/company/${companyMatch[1]}`;
    } else {
      url = config.base_url + clean_username;
    }
    return { platform, icon: config.icon, url, username };
  }

  private static getDefaultLinkData(
    platform: string,
    config: { icon: string; base_url: string },
    username: string
  ): SocialMediaItem {
    if (/^https?:\/\//i.test(username)) {
      return { platform, icon: config.icon, url: username, username };
    }
    const clean_username = username.replace(/^@/, "");
    const url = config.base_url + clean_username;
    return { platform, icon: config.icon, url, username };
  }

  static createSocialMediaItems(
    socialMediaData: Record<string, string | string[]>
  ): SocialMediaItem[] {
    const processedItems: SocialMediaItem[] = [];
    for (const platform in socialMediaData) {
      const usernames = Array.isArray(socialMediaData[platform])
        ? socialMediaData[platform]
        : [socialMediaData[platform]];
      for (const username of usernames) {
        if (!platform || !username) continue;
        const linkData = SocialMediaService.getLinkData(platform, username);
        if (linkData) {
          processedItems.push(linkData);
        }
      }
    }
    return processedItems;
  }
}
