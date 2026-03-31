import { PostTypesMetabox } from './MetaBox';
export enum WPPostType {
    Lowongan = PostTypesMetabox.Lowongan,
}
export interface WPBasePost {
    id: number;
    title: string;
    slug?: string;
    post_type?: string;
    post_time?: string;
    permalink?: string | null;
}

// WordPress taxonomy term
export interface WPTaxonomyTerm {
    term_id: number;
    name: string;
    slug: string;
    term_group: number;
    term_taxonomy_id: number;
    taxonomy: string;
    description: string;
    parent: number;
    count: number;
}

// WordPress user
export interface WPUser {
    ID: number;
    user_login: string;
    user_email: string;
    display_name: string;
    user_nicename: string;
}

// WordPress attachment
export interface WPAttachment {
    id: number;
    title: string;
    url: string;
    alt?: string;
    caption?: string;
    description?: string;
    mime_type: string;
    width?: number;
    height?: number;
}

// WordPress comment
export interface WPComment {
    comment_ID: number;
    comment_post_ID: number;
    comment_author: string;
    comment_author_email: string;
    comment_author_url: string;
    comment_author_IP: string;
    comment_date: string;
    comment_date_gmt: string;
    comment_content: string;
    comment_karma: number;
    comment_approved: string;
    comment_agent: string;
    comment_type: string;
    comment_parent: number;
    user_id: number;
}

// WordPress menu item
export interface WPMenuItem {
    ID: number;
    post_title: string;
    post_name: string;
    menu_item_parent: string;
    url: string;
    title: string;
    target: string;
    classes: string[];
    xfn: string;
}

// WordPress REST API response meta
export interface WPRestMeta {
    total?: number;
    maxNumPages?: number;
    links?: {
        self?: string;
        collection?: string;
        about?: string;
        author?: string;
        replies?: string;
        'version-history'?: string;
        'predecessor-version'?: string;
        'wp:attachment'?: string;
        'wp:term'?: string;
        'wp:action-publish'?: string;
        'wp:action-unfiltered-html'?: string;
        'wp:action-sticky'?: string;
        'wp:action-assign-author'?: string;
        'wp:action-create-post'?: string;
        'wp:action-assign-categories'?: string;
        'wp:action-assign-tags'?: string;
        curies?: Array<{
            name: string;
            href: string;
            templated: boolean;
        }>;
    };
}