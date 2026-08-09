/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useState, useEffect } from 'react';
import { Language, UserRole } from '../types';
import { translations } from '../translations';
import {
  BookOpen,
  FolderTree,
  Tag,
  Image as ImageIcon,
  FileText,
  Menu as MenuIcon,
  Grid,
  Home,
  Globe,
  Sliders,
  LayoutTemplate,
  Cog,
  Search,
  Code,
  AlertTriangle,
  Palette,
  Type,
  Map,
  Mail,
  Plus,
  Trash2,
  Edit2,
  Save,
  Eye,
  RefreshCcw,
  Check,
  CheckCircle2,
  ChevronUp,
  ChevronDown,
  Layers,
  Settings,
  FileCode,
  Upload,
  X,
  Copy,
  ExternalLink,
  Lock,
  Laptop,
  Moon,
  Sun,
  ArrowUpRight,
  Sparkles
} from 'lucide-react';
import { useRef } from 'react';

interface CMSViewProps {
  language: Language;
  activeRole: UserRole;
}

// Interfaces
interface BlogPost {
  id: number;
  title_en: string;
  title_ar: string;
  slug: string;
  category: string;
  tags: string[];
  status: 'published' | 'draft';
  image: string;
  date: string;
  content_en: string;
  content_ar: string;
}

interface StaticPage {
  id: string;
  title_en: string;
  title_ar: string;
  slug: string;
  status: 'published' | 'draft';
  blocks: PageBlock[];
}

interface PageBlock {
  id: string;
  type: 'hero' | 'text' | 'features' | 'cta';
  title_en: string;
  title_ar: string;
  content_en: string;
  content_ar: string;
}

interface MenuItem {
  id: string;
  label_en: string;
  label_ar: string;
  url: string;
}

interface WidgetItem {
  id: string;
  zone: 'sidebar' | 'footer';
  type: string;
  title_en: string;
  title_ar: string;
}

interface HomepageSection {
  id: string;
  type: string;
  title_en: string;
  title_ar: string;
  enabled: boolean;
}

interface EmailTemplate {
  id: string;
  name: string;
  subject_en: string;
  subject_ar: string;
  body_en: string;
  body_ar: string;
}

interface MediaItem {
  id: number;
  name: string;
  url: string;
  size: string;
  dimensions: string;
  alt_en: string;
  alt_ar: string;
}

export default function CMSView({ language, activeRole }: CMSViewProps) {
  const t = translations[language];
  const isRtl = language === 'ar';
  const fileInputRef = useRef<HTMLInputElement | null>(null);

  const [activeTab, setActiveTab] = useState<string>('blogs');
  const [successMsg, setSuccessMsg] = useState<string>('');
  const [editingLang, setEditingLang] = useState<'en' | 'ar'>('en');

  // Super admins & moderators have CMS permissions
  const hasPermission = activeRole === 'super_admin' || activeRole === 'moderator';

  // 1. Blogs & Taxonomies State
  const [blogs, setBlogs] = useState<BlogPost[]>([]);
  const [categories, setCategories] = useState<string[]>([]);
  const [tags, setTags] = useState<string[]>([]);
  const [selectedBlog, setSelectedBlog] = useState<BlogPost | null>(null);
  const [blogForm, setBlogForm] = useState<BlogPost | null>(null);
  const [newTaxonomy, setNewTaxonomy] = useState<{ type: 'cat' | 'tag'; value: string }>({ type: 'cat', value: '' });

  // 2. Static Pages State
  const [pages, setPages] = useState<StaticPage[]>([]);
  const [selectedPage, setSelectedPage] = useState<StaticPage | null>(null);

  // 3. Navigation & Widgets State
  const [menus, setMenus] = useState<MenuItem[]>([]);
  const [widgets, setWidgets] = useState<WidgetItem[]>([]);
  const [newMenu, setNewMenu] = useState({ label_en: '', label_ar: '', url: '' });
  const [newWidget, setNewWidget] = useState({ zone: 'sidebar' as 'sidebar' | 'footer', type: 'Recent Posts', title_en: '', title_ar: '' });

  // 4. Homepage Content Settings
  const [homepageSections, setHomepageSections] = useState<HomepageSection[]>([]);

  // 5. Brand & Styling Configuration
  const [siteIdentity, setSiteIdentity] = useState({
    title_en: 'FUN MOMENT | Event Marketplace',
    title_ar: 'فان مومنت | سوق الفعاليات والترفيه',
    slogan_en: 'Where Moments turn into Lifetime Memories',
    slogan_ar: 'حيث تتحول اللحظات العابرة لذكريات تدوم طويلاً',
    logo: 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=120',
    favicon: 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=32',
    headerVariant: 'centered' as 'classic' | 'centered' | 'deluxe' | 'split',
    footerVariant: 'grid' as 'grid' | 'minimal' | 'corporate'
  });
  const [themeConfig, setThemeConfig] = useState({
    primaryColor: '#4f46e5', // Indigo
    secondaryColor: '#06b6d4', // Cyan
    fontSizeScale: 'standard' as 'small' | 'standard' | 'large',
    fontPairing: 'outfit_inter' as 'outfit_inter' | 'cairo_outfit' | 'mono_space'
  });

  // 6. SEO Configuration
  const [seoConfig, setSeoConfig] = useState({
    metaTitleEn: 'FUN MOMENT - Book Premium Events in Saudi Arabia',
    metaTitleAr: 'فان مومنت - حجز وتنسيق الفعاليات الفاخرة بالمملكة',
    metaDescEn: 'Connect with certified entertainers, musicians, VR setups, and secure escrow services.',
    metaDescAr: 'منصتك الموثوقة لحجز مقدمي الترفيه، الموسيقيين، ألعاب الواقع الافتراضي مع نظام الضمان المالي المعتمد.',
    focusKeywords: 'Riyadh events, Fun Moment, corporate bookings, Saudi entertainment',
    indexAllowed: true,
    sitemapActive: true,
    lastGenerated: '2026-06-28 14:25'
  });

  // 7. Custom Scripts & CSS
  const [scriptsConfig, setScriptsConfig] = useState({
    headerScripts: '<!-- Global site tag (gtag.js) - Google Analytics -->\n<script async src="https://www.googletagmanager.com/gtag/js?id=G-CMSDUMMY"></script>',
    footerScripts: '<!-- Meta Pixel Code -->\n<script>console.log("Meta Pixel Active");</script>',
    customCss: '/* Brand Custom Aesthetic Overrides */\n.fun-hover:hover {\n  transform: scale(1.02);\n  transition: all 0.2s ease;\n}',
    customJs: '/* Global client interactive tracking */\nwindow.addEventListener("DOMContentLoaded", () => {\n  console.log("Fun Moment Public Frontend Mounted");\n});'
  });

  // 8. Emails & Utility Pages (Email Templates, 404 & Maintenance Mode)
  const [emailTemplates, setEmailTemplates] = useState<EmailTemplate[]>([]);
  const [selectedEmail, setSelectedEmail] = useState<EmailTemplate | null>(null);
  const [maintenanceMode, setMaintenanceMode] = useState(false);
  const [maintenanceReasonEn, setMaintenanceReasonEn] = useState('Scheduled Platform and Database Upgrades.');
  const [maintenanceReasonAr, setMaintenanceReasonAr] = useState('ترقية وصيانة دورية لخوادم قاعدة البيانات ونظام الدفع المالي.');
  const [maintenanceTimer, setMaintenanceTimer] = useState('2026-07-05T12:00');
  const [custom404TextEn, setCustom404TextEn] = useState('Oops! This event location does not exist on our maps.');
  const [custom404TextAr, setCustom404TextAr] = useState('عذراً! وجهة الفعالية المطلوبة غير مدرجة بخرائط المنصة.');

  // 9. Media Library State
  const [mediaGallery, setMediaGallery] = useState<MediaItem[]>([]);
  const [searchMedia, setSearchMedia] = useState('');
  const [selectedMedia, setSelectedMedia] = useState<MediaItem | null>(null);

  // Alert Success triggers
  const triggerSuccess = (msg: string) => {
    setSuccessMsg(msg);
    setTimeout(() => setSuccessMsg(''), 3500);
  };

  useEffect(() => {
    let mounted = true;

    const loadCmsInventory = async () => {
      try {
        const items = await LaravelAPI.getCMSContent();
        if (!mounted || !Array.isArray(items) || items.length === 0) {
          return;
        }

        const liveBlogs = items
          .filter((item) => item.type === 'blog')
          .map((item) => ({
            id: item.id,
            title_en: item.title_en,
            title_ar: item.title_ar,
            slug: item.slug ?? `blog-${item.id}`,
            category: item.category ?? 'General',
            tags: item.tags ?? [],
            status: item.status,
            image: item.image ?? 'https://images.unsplash.com/photo-1543002588-bfa74002ed7e?w=400',
            date: item.updated_at ?? '',
            content_en: item.content_en ?? '',
            content_ar: item.content_ar ?? '',
          }));

        const livePages = items
          .filter((item) => item.type === 'page')
          .map((item) => ({
            id: String(item.id),
            title_en: item.title_en,
            title_ar: item.title_ar,
            slug: item.slug ?? `page-${item.id}`,
            status: item.status,
            blocks: item.blocks && item.blocks.length > 0 ? item.blocks : [{
              id: `page-${item.id}-block-1`,
              type: 'text' as const,
              title_en: item.title_en,
              title_ar: item.title_ar,
              content_en: item.content_en ?? '',
              content_ar: item.content_ar ?? '',
            }],
          }));

        const liveWidgets = items
          .filter((item) => item.type === 'widget')
          .map((item) => ({
            id: String(item.id),
            zone: item.zone === 'sidebar' ? 'sidebar' : 'footer',
            type: item.widget_name ?? 'Widget',
            title_en: item.title_en,
            title_ar: item.title_ar,
          }));

        const liveMenus = items
          .filter((item) => item.type === 'menu')
          .map((item) => ({
            id: String(item.id),
            label_en: item.title_en,
            label_ar: item.title_ar,
            url: item.url || (item.content && typeof item.content === 'string' ? item.content : '/'),
          }));

        const liveMedia = items
          .filter((item) => item.type === 'media')
          .map((item) => ({
            id: item.id,
            name: item.slug ?? `media-${item.id}`,
            url: item.image ?? '',
            size: item.size ?? '',
            dimensions: item.dimensions ?? '',
            alt_en: item.alt_en ?? '',
            alt_ar: item.alt_ar ?? '',
          }));

        if (liveBlogs.length > 0) {
          setBlogs(liveBlogs as BlogPost[]);
          setSelectedBlog(liveBlogs[0] as BlogPost);
        }

        if (livePages.length > 0) {
          setPages(livePages as StaticPage[]);
          setSelectedPage(livePages[0] as StaticPage);
        }

        if (liveWidgets.length > 0) {
          setWidgets(liveWidgets as WidgetItem[]);
        }

        if (liveMenus.length > 0) {
          setMenus(liveMenus as MenuItem[]);
        }

        if (liveMedia.length > 0) {
          setMediaGallery(liveMedia as MediaItem[]);
          setSelectedMedia(liveMedia[0] as MediaItem);
        }
      } catch (error) {
        console.warn('CMS inventory load failed', error);
      }
    };

    loadCmsInventory();

    return () => {
      mounted = false;
    };
  }, []);

  // Up/Down reordering functions
  const moveHomepageSection = (index: number, direction: 'up' | 'down') => {
    if (!hasPermission) return;
    const nextIndex = direction === 'up' ? index - 1 : index + 1;
    if (nextIndex < 0 || nextIndex >= homepageSections.length) return;

    const updated = [...homepageSections];
    const temp = updated[index];
    updated[index] = updated[nextIndex];
    updated[nextIndex] = temp;
    setHomepageSections(updated);
    triggerSuccess(language === 'en' ? 'Homepage block hierarchy rearranged!' : 'تم تغيير ترتيب بلوكات الصفحة الرئيسية بنجاح!');
  };

  const moveMenu = (index: number, direction: 'up' | 'down') => {
    if (!hasPermission) return;
    const nextIndex = direction === 'up' ? index - 1 : index + 1;
    if (nextIndex < 0 || nextIndex >= menus.length) return;

    const updated = [...menus];
    const temp = updated[index];
    updated[index] = updated[nextIndex];
    updated[nextIndex] = temp;
    setMenus(updated);
    triggerSuccess(language === 'en' ? 'Navigation nodes reordered!' : 'تم تعديل تسلسل قائمة التنقل!');
  };

  // Create Blog Action
  const handleSaveBlog = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!hasPermission) return;
    if (!blogForm) return;

    const payload = {
      title_en: blogForm.title_en,
      title_ar: blogForm.title_ar,
      slug: blogForm.slug,
      category: blogForm.category,
      status: blogForm.status,
      image: blogForm.image,
      content_en: blogForm.content_en,
      content_ar: blogForm.content_ar,
      tags: blogForm.tags ?? [],
    };

    try {
      const result = blogForm.id === 0
        ? await LaravelAPI.createCMSBlog(payload)
        : await LaravelAPI.updateCMSBlog(blogForm.id, payload);

      const normalized = {
        ...blogForm,
        id: result.id,
        status: result.status,
        date: result.updated_at?.substring(0, 10) || new Date().toISOString().substring(0, 10),
      };

      setBlogs(current => blogForm.id === 0 ? [normalized, ...current] : current.map(b => b.id === blogForm.id ? normalized : b));
      setSelectedBlog(normalized);
      triggerSuccess(language === 'en' ? 'Blog post synced with database!' : 'تمت مزامنة المقال مع قاعدة البيانات!');
    } catch (error) {
      console.error(error);
      triggerSuccess(language === 'en' ? 'Blog sync failed' : 'فشلت مزامنة المقال');
    }
    setBlogForm(null);
  };

  const handleDeleteBlog = async (id: number) => {
    if (!hasPermission) return;
    try {
      await LaravelAPI.deleteCMSBlog(id);
      const updated = blogs.filter(b => b.id !== id);
      setBlogs(updated);
      if (selectedBlog?.id === id) {
        setSelectedBlog(updated[0] || null);
      }
      triggerSuccess(language === 'en' ? 'Blog post deleted' : 'تم حذف المقال من الأرشيف');
    } catch (error) {
      console.error(error);
    }
  };

  const handleToggleBlogStatus = async (post: BlogPost) => {
    if (!hasPermission) return;
    try {
      const nextStatus = post.status === 'published' ? 'draft' : 'published';
      const updated = await LaravelAPI.updateCMSStatus(post.id, nextStatus);
      setBlogs(current => current.map(item => item.id === post.id ? { ...item, status: updated.status } : item));
      if (selectedBlog?.id === post.id) {
        setSelectedBlog(current => current ? { ...current, status: updated.status } : current);
      }
      triggerSuccess(language === 'en' ? 'Blog status updated' : 'تم تحديث حالة المقال');
    } catch (error) {
      console.error(error);
    }
  };

  const handleSavePage = async () => {
    if (!hasPermission || !selectedPage) return;

    const payload = {
      title_en: selectedPage.title_en,
      title_ar: selectedPage.title_ar,
      slug: selectedPage.slug,
      status: selectedPage.status,
      content_en: selectedPage.blocks.map(block => `${block.title_en}\n${block.content_en}`).join('\n\n'),
      content_ar: selectedPage.blocks.map(block => `${block.title_ar}\n${block.content_ar}`).join('\n\n'),
    };

    try {
      const isNewPage = String(selectedPage.id).startsWith('new-');
      const result = isNewPage
        ? await LaravelAPI.createCMSPage(payload)
        : await LaravelAPI.updateCMSPage(Number(selectedPage.id), payload);

      const normalized: StaticPage = {
        ...selectedPage,
        id: String(result.id),
        status: result.status,
      };

      setPages(current => isNewPage ? [normalized, ...current.filter(page => page.id !== selectedPage.id)] : current.map(page => page.id === selectedPage.id ? normalized : page));
      setSelectedPage(normalized);
      triggerSuccess(language === 'en' ? 'Page synced with database!' : 'تمت مزامنة الصفحة مع قاعدة البيانات!');
    } catch (error) {
      console.error(error);
      triggerSuccess(language === 'en' ? 'Page sync failed' : 'فشلت مزامنة الصفحة');
    }
  };

  const handleDeletePage = async (id: string) => {
    if (!hasPermission) return;
    if (String(id).startsWith('new-')) {
      const updated = pages.filter(page => page.id !== id);
      setPages(updated);
      setSelectedPage(updated[0] || null);
      return;
    }
    try {
      await LaravelAPI.deleteCMSPage(Number(id));
      const updated = pages.filter(page => page.id !== id);
      setPages(updated);
      setSelectedPage(updated[0] || null);
      triggerSuccess(language === 'en' ? 'Page deleted' : 'تم حذف الصفحة');
    } catch (error) {
      console.error(error);
    }
  };

  const handleTogglePageStatus = async (page: StaticPage) => {
    if (!hasPermission) return;
    try {
      const nextStatus = page.status === 'published' ? 'draft' : 'published';
      const updated = await LaravelAPI.updateCMSStatus(Number(page.id), nextStatus);
      const normalizedStatus = updated.status;
      setPages(current => current.map(item => item.id === page.id ? { ...item, status: normalizedStatus } : item));
      if (selectedPage?.id === page.id) {
        setSelectedPage(current => current ? { ...current, status: normalizedStatus } : current);
      }
      triggerSuccess(language === 'en' ? 'Page status updated' : 'تم تحديث حالة الصفحة');
    } catch (error) {
      console.error(error);
    }
  };

  const handleAddMenu = async () => {
    if (!hasPermission || !newMenu.label_en || !newMenu.label_ar) return;
    try {
      const result = await LaravelAPI.createCMSMenu({
        title: newMenu.label_en,
        content: newMenu.url,
        label_ar: newMenu.label_ar,
        status: '',
      });

      const normalized = {
        id: String(result.id),
        label_en: result.title_en || newMenu.label_en,
        label_ar: result.title_ar || newMenu.label_ar,
        url: result.url || newMenu.url,
      };

      setMenus(current => [...current, normalized]);
      setNewMenu({ label_en: '', label_ar: '', url: '' });
      triggerSuccess(language === 'en' ? 'Navigation node added!' : 'تمت إضافة رابط تنقل جديد!');
    } catch (error) {
      console.error(error);
    }
  };

  const handleDeleteMenu = async (id: string) => {
    if (!hasPermission) return;
    try {
      await LaravelAPI.deleteCMSMenu(Number(id));
      setMenus(current => current.filter(menu => menu.id !== id));
      triggerSuccess(language === 'en' ? 'Navigation link removed' : 'تم حذف رابط التنقل');
    } catch (error) {
      console.error(error);
    }
  };

  const handleEditMenu = async (menu: MenuItem) => {
    if (!hasPermission) return;
    const labelEn = window.prompt(language === 'en' ? 'Menu label (EN)' : 'عنوان الرابط بالإنجليزية', menu.label_en) ?? menu.label_en;
    const labelAr = window.prompt(language === 'en' ? 'Menu label (AR)' : 'عنوان الرابط بالعربية', menu.label_ar) ?? menu.label_ar;
    const url = window.prompt(language === 'en' ? 'Menu URL' : 'رابط الصفحة', menu.url) ?? menu.url;

    try {
      const result = await LaravelAPI.updateCMSMenu(Number(menu.id), {
        title: labelEn,
        content: url,
        label_ar: labelAr,
        status: '',
      });

      setMenus(current => current.map(item => item.id === menu.id ? {
        id: menu.id,
        label_en: result.title_en || labelEn,
        label_ar: result.title_ar || labelAr,
        url: result.url || url,
      } : item));
      triggerSuccess(language === 'en' ? 'Navigation node updated!' : 'تم تحديث رابط التنقل!');
    } catch (error) {
      console.error(error);
    }
  };

  const handleAddWidget = async () => {
    if (!hasPermission || !newWidget.title_en || !newWidget.title_ar) return;
    try {
      const result = await LaravelAPI.createCMSWidget({
        widget_name: newWidget.title_en,
        widget_order: widgets.length + 1,
        widget_location: newWidget.zone,
        widget_title_ar: newWidget.title_ar,
      });

      const normalized = {
        id: String(result.id),
        zone: (result.zone === 'sidebar' ? 'sidebar' : 'footer') as 'sidebar' | 'footer',
        type: result.widget_name ?? newWidget.type,
        title_en: result.title_en || newWidget.title_en,
        title_ar: result.title_ar || newWidget.title_ar,
      };

      setWidgets(current => [...current, normalized]);
      setNewWidget({ zone: 'sidebar', type: 'Recent Posts', title_en: '', title_ar: '' });
      triggerSuccess(language === 'en' ? 'Widget deployed successfully!' : 'تم نشر الودجت بنجاح!');
    } catch (error) {
      console.error(error);
    }
  };

  const handleDeleteWidget = async (id: string) => {
    if (!hasPermission) return;
    try {
      await LaravelAPI.deleteCMSWidget(Number(id));
      setWidgets(current => current.filter(widget => widget.id !== id));
      triggerSuccess(language === 'en' ? 'Widget removed' : 'تم حذف الودجت');
    } catch (error) {
      console.error(error);
    }
  };

  const handleEditWidget = async (widget: WidgetItem) => {
    if (!hasPermission) return;
    const titleEn = window.prompt(language === 'en' ? 'Widget title (EN)' : 'عنوان الودجت بالإنجليزية', widget.title_en) ?? widget.title_en;
    const titleAr = window.prompt(language === 'en' ? 'Widget title (AR)' : 'عنوان الودجت بالعربية', widget.title_ar) ?? widget.title_ar;
    const zone = window.prompt(language === 'en' ? 'Widget zone (sidebar/footer)' : 'منطقة الودجت (sidebar/footer)', widget.zone) ?? widget.zone;

    try {
      const idx = widgets.findIndex(item => item.id === widget.id);
      const result = await LaravelAPI.updateCMSWidget(Number(widget.id), {
        widget_name: titleEn,
        widget_order: idx >= 0 ? idx + 1 : 1,
        widget_location: zone,
        widget_title_ar: titleAr,
      });

      setWidgets(current => current.map(item => item.id === widget.id ? {
        id: widget.id,
        zone: (result.zone === 'sidebar' ? 'sidebar' : 'footer') as 'sidebar' | 'footer',
        type: result.widget_name || widget.type,
        title_en: result.title_en || titleEn,
        title_ar: result.title_ar || titleAr,
      } : item));
      triggerSuccess(language === 'en' ? 'Widget updated!' : 'تم تحديث الودجت!');
    } catch (error) {
      console.error(error);
    }
  };

  const handleUploadMediaFile = async (file?: File | null) => {
    if (!hasPermission || !file) return;
    try {
      const item = await LaravelAPI.uploadMedia(file);
      const normalized = {
        id: item.id,
        name: item.slug ?? `media-${item.id}`,
        url: item.image ?? item.url ?? '',
        size: item.size ?? '',
        dimensions: item.dimensions ?? '',
        alt_en: item.alt_en ?? '',
        alt_ar: item.alt_ar ?? '',
      };
      setMediaGallery(current => [normalized, ...current]);
      setSelectedMedia(normalized);
      triggerSuccess(language === 'en' ? 'Asset uploaded successfully!' : 'تم رفع الملف بنجاح!');
    } catch (error) {
      console.error(error);
    }
  };

  const handleDeleteMedia = async (id: number) => {
    if (!hasPermission) return;
    try {
      await LaravelAPI.deleteMedia(id);
      const updated = mediaGallery.filter(item => item.id !== id);
      setMediaGallery(updated);
      setSelectedMedia(updated[0] || null);
      triggerSuccess(language === 'en' ? 'Asset deleted' : 'تم حذف الملف');
    } catch (error) {
      console.error(error);
    }
  };

  const handleUpdateMediaAlt = async (id: number, alt: string, lang: 'en' | 'ar') => {
    if (!hasPermission) return;
    try {
      const item = await LaravelAPI.updateMediaAlt(id, alt);
      setMediaGallery(current => current.map(media => media.id === id ? {
        ...media,
        alt_en: lang === 'en' ? (item.alt_en ?? alt) : media.alt_en,
        alt_ar: lang === 'ar' ? (item.alt_ar ?? alt) : media.alt_ar,
      } : media));
      if (selectedMedia?.id === id) {
        setSelectedMedia(current => current ? {
          ...current,
          alt_en: lang === 'en' ? alt : current.alt_en,
          alt_ar: lang === 'ar' ? alt : current.alt_ar,
        } : current);
      }
    } catch (error) {
      console.error(error);
    }
  };

  // Add Taxonomy (Category/Tag)
  const handleAddTaxonomy = () => {
    if (!newTaxonomy.value) return;
    if (newTaxonomy.type === 'cat') {
      if (!categories.includes(newTaxonomy.value)) {
        setCategories([...categories, newTaxonomy.value]);
        triggerSuccess(language === 'en' ? `Category "${newTaxonomy.value}" added` : `تمت إضافة التصنيف "${newTaxonomy.value}"`);
      }
    } else {
      if (!tags.includes(newTaxonomy.value)) {
        setTags([...tags, newTaxonomy.value]);
        triggerSuccess(language === 'en' ? `Tag "#${newTaxonomy.value}" added` : `تمت إضافة الوسم "#${newTaxonomy.value}"`);
      }
    }
    setNewTaxonomy({ ...newTaxonomy, value: '' });
  };

  // Drag and drop simulator for Media
  const handleSimulateUpload = () => {
    fileInputRef.current?.click();
  };

  return (
    <div className="space-y-6">
      {/* Top Banner section */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 pb-4">
        <div>
          <h2 className="text-xl font-bold tracking-tight text-slate-800">
            {language === 'en' ? 'Public Website Studio & CMS' : 'استوديو إدارة موقع الويب والـ CMS'}
          </h2>
          <p className="text-xs text-slate-500">
            {language === 'en' 
              ? 'Control public branding, write blogs, manage static pages, layout navigation nodes, edit scripts, SEO sitemaps, and design header variants.' 
              : 'تحكم بالهوية البصرية والبنرات، اكتب المقالات، ابن الصفحات الثابتة، نسق القوائم البرمجية، واكتب شيفرات التهيئة ومؤشرات البحث.'}
          </p>
        </div>

        {/* Global Control toggles */}
        <div className="flex items-center gap-2 text-xs">
          <span className="text-slate-400 font-semibold">{language === 'en' ? 'Active Editor Locale:' : 'لغة التحرير النشطة:'}</span>
          <div className="rounded-lg bg-slate-100 p-0.5 flex gap-1 font-bold">
            <button
              onClick={() => setEditingLang('en')}
              className={`rounded px-2.5 py-1 ${editingLang === 'en' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'}`}
            >
              EN
            </button>
            <button
              onClick={() => setEditingLang('ar')}
              className={`rounded px-2.5 py-1 ${editingLang === 'ar' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'}`}
            >
              AR
            </button>
          </div>
        </div>
      </div>

      {successMsg && (
        <div className="rounded-xl bg-emerald-50 border border-emerald-100 p-3.5 text-xs text-emerald-800 font-semibold animate-fade-in flex items-center gap-2">
          <CheckCircle2 className="h-4.5 w-4.5 text-emerald-600" />
          <span>{successMsg}</span>
        </div>
      )}

      {/* Main Grid Wrapper */}
      <div className="grid grid-cols-1 lg:grid-cols-4 gap-6 items-start">
        {/* Left Side Navigation Menu */}
        <div className="lg:col-span-1 rounded-2xl border border-slate-200/60 bg-white p-3 space-y-1 shadow-xs">
          <p className="text-[10px] font-bold text-slate-400 uppercase tracking-wider px-3 mb-2">
            {language === 'en' ? 'Content & Layout Studio' : 'الاستوديو وصناعة المحتوى'}
          </p>
          {[
            { id: 'blogs', label_en: 'Blogs & Taxonomy', label_ar: 'المدونات والتصنيفات', icon: BookOpen },
            { id: 'pages', label_en: 'Static Pages Builder', label_ar: 'منشئ الصفحات الثابتة', icon: FileText },
            { id: 'menus', label_en: 'Menus & Widgets', label_ar: 'القوائم والقطع البرمجية', icon: MenuIcon },
            { id: 'homepage', label_en: 'Homepage Settings', label_ar: 'ترتيب الصفحة الرئيسية', icon: Grid },
            { id: 'brand', label_en: 'Identity & Layouts', label_ar: 'الهوية وقوالب العرض', icon: Palette },
            { id: 'seo', label_en: 'SEO & Sitemaps', label_ar: 'الأرشفة ومحركات البحث', icon: Globe },
            { id: 'scripts', label_en: 'Custom Code & Scripts', label_ar: 'البرمجيات والأكواد الخاصة', icon: Code },
            { id: 'emails', label_en: 'Email & Utilities', label_ar: 'قوالب البريد والأنظمة', icon: Mail },
            { id: 'media', label_en: 'Media Gallery', label_ar: 'معرض الصور والوسائط', icon: ImageIcon }
          ].map(tab => {
            const Icon = tab.icon;
            const active = activeTab === tab.id;
            return (
              <button
                key={tab.id}
                onClick={() => {
                  setActiveTab(tab.id);
                  setBlogForm(null);
                }}
                className={`w-full flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-xs font-semibold transition ${
                  active 
                    ? 'bg-slate-900 text-white shadow-xs' 
                    : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                }`}
              >
                <Icon className={`h-4.5 w-4.5 shrink-0 ${active ? 'text-indigo-400' : 'text-slate-400'}`} />
                <span>{language === 'en' ? tab.label_en : tab.label_ar}</span>
              </button>
            );
          })}
        </div>

        {/* Center Config Panel and Right Live Preview Monitor */}
        <div className="lg:col-span-3 grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
          
          {/* Main workspace (Takes up 2 columns on extra wide screens) */}
          <div className="xl:col-span-2 rounded-2xl border border-slate-200 bg-white p-5 space-y-5 shadow-xs min-h-[500px]">
            
            {/* 1. TAB: BLOGS & TAXONOMY */}
            {activeTab === 'blogs' && (
              <div className="space-y-6">
                <div className="flex items-center justify-between border-b pb-3">
                  <h3 className="text-sm font-bold text-slate-800">{language === 'en' ? 'Bilingual Blogs Directory' : 'دليل المدونة ثنائي اللغة'}</h3>
                  {hasPermission && !blogForm && (
                    <button
                      onClick={() => setBlogForm({ id: 0, title_en: '', title_ar: '', slug: '', category: categories[0] || '', tags: [], status: 'draft', image: 'https://images.unsplash.com/photo-1543002588-bfa74002ed7e?w=400', date: '', content_en: '', content_ar: '' })}
                      className="flex items-center gap-1.5 rounded-lg bg-indigo-600 text-white font-bold px-3 py-1.5 text-xs hover:bg-indigo-700"
                    >
                      <Plus className="h-4 w-4" />
                      <span>{language === 'en' ? 'New Post' : 'كتابة مقال جديد'}</span>
                    </button>
                  )}
                </div>

                {!blogForm ? (
                  <div className="space-y-5">
                    {/* Taxonomy Add Quick bar */}
                    <div className="rounded-xl bg-slate-50 p-3 border border-slate-100 flex flex-wrap gap-3 items-center text-xs">
                      <span className="font-bold text-slate-500">{language === 'en' ? 'Quick Taxonomies:' : 'إضافة سريعة للمصطلحات:'}</span>
                      <select
                        value={newTaxonomy.type}
                        onChange={(e) => setNewTaxonomy({ ...newTaxonomy, type: e.target.value as 'cat' | 'tag' })}
                        className="rounded border border-slate-200 bg-white p-1 text-xs"
                      >
                        <option value="cat">{language === 'en' ? 'Category' : 'تصنيف'}</option>
                        <option value="tag">{language === 'en' ? 'Tag' : 'وسم'}</option>
                      </select>
                      <input
                        type="text"
                        placeholder={language === 'en' ? 'Add name...' : 'الاسم المختار...'}
                        value={newTaxonomy.value}
                        onChange={(e) => setNewTaxonomy({ ...newTaxonomy, value: e.target.value })}
                        className="rounded border border-slate-200 px-2 py-1 bg-white text-xs max-w-[130px]"
                      />
                      <button
                        onClick={handleAddTaxonomy}
                        className="rounded bg-slate-900 text-white px-3 py-1 font-bold text-[10px]"
                      >
                        {language === 'en' ? 'Add' : 'إضافة'}
                      </button>
                    </div>

                    {/* Blogs List */}
                    <div className="divide-y divide-slate-100">
                      {blogs.map(post => (
                        <div
                          key={post.id}
                          onClick={() => setSelectedBlog(post)}
                          className={`flex gap-4 py-3.5 cursor-pointer rounded-lg px-2 transition ${
                            selectedBlog?.id === post.id ? 'bg-indigo-50/40 border-l-2 border-indigo-600' : 'hover:bg-slate-50/40'
                          }`}
                        >
                          <img src={post.image} className="w-16 h-12 rounded-lg object-cover shrink-0" alt="post" />
                          <div className="flex-1 min-w-0">
                            <div className="flex items-center gap-2">
                              <span className="rounded bg-slate-100 px-1.5 py-0.5 text-[9px] font-bold text-slate-500">{post.category}</span>
                              <span className={`text-[8px] font-bold px-1 rounded uppercase ${post.status === 'published' ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600'}`}>{post.status}</span>
                            </div>
                            <p className="text-xs font-bold text-slate-800 mt-1 truncate">
                              {editingLang === 'en' ? post.title_en : post.title_ar}
                            </p>
                            <p className="text-[10px] text-slate-400 mt-0.5">{post.date}</p>
                          </div>
                          {hasPermission && (
                            <div className="flex items-center gap-1 shrink-0">
                              <button
                                onClick={(e) => {
                                  e.stopPropagation();
                                  handleToggleBlogStatus(post);
                                }}
                                className={`p-1.5 rounded-md ${post.status === 'published' ? 'hover:bg-amber-50 text-amber-600' : 'hover:bg-emerald-50 text-emerald-600'}`}
                                title={post.status === 'published' ? (language === 'en' ? 'Unpublish' : 'إلغاء النشر') : (language === 'en' ? 'Publish' : 'نشر')}
                              >
                                <RefreshCcw className="h-3.5 w-3.5" />
                              </button>
                              <button
                                onClick={(e) => {
                                  e.stopPropagation();
                                  setBlogForm(post);
                                }}
                                className="p-1.5 rounded-md hover:bg-slate-100 text-slate-500"
                              >
                                <Edit2 className="h-3.5 w-3.5" />
                              </button>
                              <button
                                onClick={(e) => {
                                  e.stopPropagation();
                                  handleDeleteBlog(post.id);
                                }}
                                className="p-1.5 rounded-md hover:bg-rose-50 text-rose-600"
                              >
                                <Trash2 className="h-3.5 w-3.5" />
                              </button>
                            </div>
                          )}
                        </div>
                      ))}
                    </div>
                  </div>
                ) : (
                  /* Form create/edit blog post */
                  <form onSubmit={handleSaveBlog} className="space-y-4 text-xs">
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <label className="text-[10px] font-bold text-slate-400 uppercase">Title (English)</label>
                        <input
                          type="text"
                          required
                          value={blogForm.title_en}
                          onChange={(e) => setBlogForm({ ...blogForm, title_en: e.target.value, slug: e.target.value.toLowerCase().replace(/ /g, '-') })}
                          className="w-full mt-1 rounded-lg border border-slate-200 px-3 py-2 text-xs"
                        />
                      </div>
                      <div>
                        <label className="text-[10px] font-bold text-slate-400 uppercase">العنوان (بالعربية)</label>
                        <input
                          type="text"
                          required
                          value={blogForm.title_ar}
                          onChange={(e) => setBlogForm({ ...blogForm, title_ar: e.target.value })}
                          className="w-full mt-1 rounded-lg border border-slate-200 px-3 py-2 text-xs font-arabic"
                        />
                      </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <label className="text-[10px] font-bold text-slate-400 uppercase">Slug Identifier URL</label>
                        <input
                          type="text"
                          required
                          value={blogForm.slug}
                          onChange={(e) => setBlogForm({ ...blogForm, slug: e.target.value })}
                          className="w-full mt-1 rounded-lg border border-slate-200 px-3 py-2 text-xs font-mono"
                        />
                      </div>
                      <div>
                        <label className="text-[10px] font-bold text-slate-400 uppercase">Category</label>
                        <select
                          value={blogForm.category}
                          onChange={(e) => setBlogForm({ ...blogForm, category: e.target.value })}
                          className="w-full mt-1 rounded-lg border border-slate-200 px-3 py-2 text-xs"
                        >
                          {categories.map(cat => (
                            <option key={cat} value={cat}>{cat}</option>
                          ))}
                        </select>
                      </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <label className="text-[10px] font-bold text-slate-400 uppercase">Post Publishing Status</label>
                        <select
                          value={blogForm.status}
                          onChange={(e: any) => setBlogForm({ ...blogForm, status: e.target.value })}
                          className="w-full mt-1 rounded-lg border border-slate-200 px-3 py-2 text-xs"
                        >
                          <option value="draft">Draft (Private)</option>
                          <option value="published">Published (Live Website)</option>
                        </select>
                      </div>
                      <div>
                        <label className="text-[10px] font-bold text-slate-400 uppercase">Feature Image URL</label>
                        <input
                          type="text"
                          value={blogForm.image}
                          onChange={(e) => setBlogForm({ ...blogForm, image: e.target.value })}
                          className="w-full mt-1 rounded-lg border border-slate-200 px-3 py-2 text-xs font-mono"
                        />
                      </div>
                    </div>

                    <div>
                      <label className="text-[10px] font-bold text-slate-400 uppercase">Post Content (English)</label>
                      <textarea
                        rows={4}
                        value={blogForm.content_en}
                        onChange={(e) => setBlogForm({ ...blogForm, content_en: e.target.value })}
                        className="w-full mt-1 rounded-lg border border-slate-200 px-3 py-2 text-xs"
                      />
                    </div>

                    <div>
                      <label className="text-[10px] font-bold text-slate-400 uppercase">المحتوى بالتفصيل (بالعربية)</label>
                      <textarea
                        rows={4}
                        value={blogForm.content_ar}
                        onChange={(e) => setBlogForm({ ...blogForm, content_ar: e.target.value })}
                        className="w-full mt-1 rounded-lg border border-slate-200 px-3 py-2 text-xs font-arabic"
                      />
                    </div>

                    <div className="flex items-center justify-end gap-2 pt-2 border-t">
                      <button
                        type="button"
                        onClick={() => setBlogForm(null)}
                        className="rounded-lg border bg-slate-50 px-3.5 py-1.5 font-semibold text-slate-700"
                      >
                        {t.cancel}
                      </button>
                      <button
                        type="submit"
                        className="rounded-lg bg-indigo-600 px-4 py-1.5 font-semibold text-white hover:bg-indigo-700"
                      >
                        {t.save}
                      </button>
                    </div>
                  </form>
                )}
              </div>
            )}

            {/* 2. TAB: PAGES BUILDER */}
            {activeTab === 'pages' && (
              <div className="space-y-6">
                <div className="flex items-center justify-between border-b pb-3">
                  <h3 className="text-sm font-bold text-slate-800">{language === 'en' ? 'Static Pages Builder' : 'منشئ الصفحات التعريفية'}</h3>
                  {hasPermission && (
                    <div className="flex items-center gap-2">
                      <button
                        onClick={() => {
                          const titleEn = window.prompt(language === 'en' ? 'Page title in English' : 'عنوان الصفحة بالإنجليزية') || '';
                          const titleAr = window.prompt(language === 'en' ? 'Page title in Arabic' : 'عنوان الصفحة بالعربية') || titleEn;
                          const slug = window.prompt(language === 'en' ? 'Page slug' : 'مُعرّف الصفحة') || `page-${Date.now()}`;
                          const newPage: StaticPage = {
                            id: `new-${Date.now()}`,
                            title_en: titleEn,
                            title_ar: titleAr,
                            slug,
                            status: 'draft',
                            blocks: [{
                              id: `block-${Date.now()}`,
                              type: 'text',
                              title_en: titleEn || 'New Page',
                              title_ar: titleAr || 'صفحة جديدة',
                              content_en: '',
                              content_ar: '',
                            }],
                          };
                          setPages([newPage, ...pages]);
                          setSelectedPage(newPage);
                        }}
                        className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-[10px] font-bold text-slate-700"
                      >
                        {language === 'en' ? 'New Page' : 'صفحة جديدة'}
                      </button>
                      <button
                        onClick={handleSavePage}
                        className="rounded-lg bg-indigo-600 px-3 py-1.5 text-[10px] font-bold text-white"
                      >
                        {language === 'en' ? 'Save Page' : 'حفظ الصفحة'}
                      </button>
                      {selectedPage && (
                        <button
                          onClick={() => handleDeletePage(selectedPage.id)}
                          className="rounded-lg bg-rose-600 px-3 py-1.5 text-[10px] font-bold text-white"
                        >
                          {language === 'en' ? 'Delete Page' : 'حذف الصفحة'}
                        </button>
                      )}
                    </div>
                  )}
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-5">
                  {/* Pages Directory */}
                  <div className="md:col-span-1 rounded-xl border border-slate-100 p-3 space-y-1.5 bg-slate-50/50">
                    <p className="text-[10px] font-bold text-slate-400 uppercase px-2 mb-1">{language === 'en' ? 'Pages list' : 'الصفحات المتاحة'}</p>
                      {pages.map(p => (
                        <div
                          key={p.id}
                          onClick={() => setSelectedPage(p)}
                          className={`w-full text-left rtl:text-right px-3 py-2 text-xs rounded-lg font-bold transition flex items-center justify-between ${
                            selectedPage?.id === p.id ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-700 hover:bg-white bg-transparent'
                          }`}
                        >
                        <span className="min-w-0 flex items-center gap-2">
                          <span className="truncate">{editingLang === 'en' ? p.title_en : p.title_ar}</span>
                          <span className={`text-[8px] px-1 py-0.5 rounded uppercase ${p.status === 'published' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-600'}`}>{p.status}</span>
                        </span>
                        <span className="flex items-center gap-1">
                          <span className="text-[8px] bg-black/10 px-1 py-0.5 rounded">{p.slug}</span>
                          {hasPermission && (
                            <button
                              onClick={(e) => {
                                e.stopPropagation();
                                handleTogglePageStatus(p);
                              }}
                              className={`p-1 rounded ${p.status === 'published' ? 'hover:bg-amber-50 text-amber-600' : 'hover:bg-emerald-50 text-emerald-600'}`}
                              title={p.status === 'published' ? (language === 'en' ? 'Unpublish' : 'إلغاء النشر') : (language === 'en' ? 'Publish' : 'نشر')}
                            >
                              <RefreshCcw className="h-3 w-3" />
                            </button>
                          )}
                        </span>
                        </div>
                      ))}
                  </div>

                  {/* Modular Block layout */}
                  {selectedPage && (
                    <div className="md:col-span-2 space-y-4">
                      <div className="flex items-center justify-between bg-slate-50 rounded-xl p-3 border">
                        <div>
                          <p className="text-[9px] text-slate-400 font-mono">Url: /{selectedPage.slug}</p>
                        </div>
                        <span className="text-[9px] bg-emerald-50 text-emerald-700 font-bold px-2 py-0.5 rounded-full uppercase">{selectedPage.status}</span>
                      </div>

                      <div className="grid grid-cols-2 gap-3 text-xs rounded-xl border border-slate-100 bg-slate-50/70 p-3">
                        <div>
                          <label className="text-[9px] text-slate-400 block uppercase">Page Title (EN)</label>
                          <input
                            type="text"
                            value={selectedPage.title_en}
                            onChange={(e) => {
                              const updated = { ...selectedPage, title_en: e.target.value };
                              setSelectedPage(updated);
                              setPages(current => current.map(page => page.id === selectedPage.id ? updated : page));
                            }}
                            className="w-full mt-1 rounded border border-slate-200 p-2"
                          />
                        </div>
                        <div>
                          <label className="text-[9px] text-slate-400 block uppercase">العنوان (AR)</label>
                          <input
                            type="text"
                            value={selectedPage.title_ar}
                            onChange={(e) => {
                              const updated = { ...selectedPage, title_ar: e.target.value };
                              setSelectedPage(updated);
                              setPages(current => current.map(page => page.id === selectedPage.id ? updated : page));
                            }}
                            className="w-full mt-1 rounded border border-slate-200 p-2 font-arabic"
                          />
                        </div>
                        <div>
                          <label className="text-[9px] text-slate-400 block uppercase">Slug</label>
                          <input
                            type="text"
                            value={selectedPage.slug}
                            onChange={(e) => {
                              const updated = { ...selectedPage, slug: e.target.value };
                              setSelectedPage(updated);
                              setPages(current => current.map(page => page.id === selectedPage.id ? updated : page));
                            }}
                            className="w-full mt-1 rounded border border-slate-200 p-2 font-mono"
                          />
                        </div>
                        <div>
                          <label className="text-[9px] text-slate-400 block uppercase">Status</label>
                          <select
                            value={selectedPage.status}
                            onChange={(e) => {
                              const updated = { ...selectedPage, status: e.target.value as 'published' | 'draft' };
                              setSelectedPage(updated);
                              setPages(current => current.map(page => page.id === selectedPage.id ? updated : page));
                            }}
                            className="w-full mt-1 rounded border border-slate-200 p-2"
                          >
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                          </select>
                        </div>
                      </div>

                      <div className="space-y-3">
                        <div className="flex items-center justify-between">
                          <p className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">{language === 'en' ? 'Modular Blocks' : 'بلوكات ومكونات الصفحة'}</p>
                          {hasPermission && (
                            <button
                              onClick={() => {
                                const newBlock: PageBlock = { id: 'b' + Date.now(), type: 'text', title_en: 'New Title', title_ar: 'عنوان جديد', content_en: 'New Description...', content_ar: 'وصف محتوى جديد...' };
                                const updated = pages.map(p => p.id === selectedPage.id ? { ...p, blocks: [...p.blocks, newBlock] } : p);
                                setPages(updated);
                                setSelectedPage(updated.find(p => p.id === selectedPage.id) || null);
                                triggerSuccess(language === 'en' ? 'New block added!' : 'تمت إضافة بلوك جديد للصفحة!');
                              }}
                              className="text-[10px] font-bold text-indigo-600 hover:underline flex items-center gap-0.5"
                            >
                              <Plus className="h-3 w-3" />
                              <span>{language === 'en' ? 'Add block' : 'إضافة مكون'}</span>
                            </button>
                          )}
                        </div>

                        {selectedPage.blocks.map((block, idx) => (
                          <div key={block.id} className="rounded-xl border border-slate-200 bg-white p-3.5 space-y-3 relative shadow-xs">
                            <div className="flex items-center justify-between border-b pb-2">
                              <span className="text-[9px] font-bold text-slate-400 bg-slate-100 px-1.5 rounded uppercase">Block #{idx + 1} ({block.type})</span>
                              
                              <div className="flex items-center gap-1.5">
                                <button
                                  onClick={() => {
                                    if (idx === 0) return;
                                    const updatedBlocks = [...selectedPage.blocks];
                                    const temp = updatedBlocks[idx];
                                    updatedBlocks[idx] = updatedBlocks[idx - 1];
                                    updatedBlocks[idx - 1] = temp;
                                    const updatedPages = pages.map(p => p.id === selectedPage.id ? { ...p, blocks: updatedBlocks } : p);
                                    setPages(updatedPages);
                                    setSelectedPage(updatedPages.find(p => p.id === selectedPage.id) || null);
                                    triggerSuccess('Block shifted up');
                                  }}
                                  className="p-1 hover:bg-slate-100 rounded text-slate-500"
                                >
                                  <ChevronUp className="h-3.5 w-3.5" />
                                </button>
                                <button
                                  onClick={() => {
                                    if (idx === selectedPage.blocks.length - 1) return;
                                    const updatedBlocks = [...selectedPage.blocks];
                                    const temp = updatedBlocks[idx];
                                    updatedBlocks[idx] = updatedBlocks[idx + 1];
                                    updatedBlocks[idx + 1] = temp;
                                    const updatedPages = pages.map(p => p.id === selectedPage.id ? { ...p, blocks: updatedBlocks } : p);
                                    setPages(updatedPages);
                                    setSelectedPage(updatedPages.find(p => p.id === selectedPage.id) || null);
                                    triggerSuccess('Block shifted down');
                                  }}
                                  className="p-1 hover:bg-slate-100 rounded text-slate-500"
                                >
                                  <ChevronDown className="h-3.5 w-3.5" />
                                </button>
                                <button
                                  onClick={() => {
                                    const updatedBlocks = selectedPage.blocks.filter(b => b.id !== block.id);
                                    const updatedPages = pages.map(p => p.id === selectedPage.id ? { ...p, blocks: updatedBlocks } : p);
                                    setPages(updatedPages);
                                    setSelectedPage(updatedPages.find(p => p.id === selectedPage.id) || null);
                                    triggerSuccess('Block removed');
                                  }}
                                  className="p-1 hover:bg-rose-50 text-rose-600 rounded"
                                >
                                  <Trash2 className="h-3.5 w-3.5" />
                                </button>
                              </div>
                            </div>

                            <div className="grid grid-cols-2 gap-3 text-xs">
                              <div>
                                <label className="text-[9px] text-slate-400 block uppercase">Block Title (EN)</label>
                                <input
                                  type="text"
                                  value={block.title_en}
                                  onChange={(e) => {
                                    const updatedB = selectedPage.blocks.map(b => b.id === block.id ? { ...b, title_en: e.target.value } : b);
                                    setPages(pages.map(p => p.id === selectedPage.id ? { ...p, blocks: updatedB } : p));
                                    setSelectedPage({ ...selectedPage, blocks: updatedB });
                                  }}
                                  className="w-full mt-1 rounded border border-slate-200 p-1.5"
                                />
                              </div>
                              <div>
                                <label className="text-[9px] text-slate-400 block uppercase">عنوان البلوك (AR)</label>
                                <input
                                  type="text"
                                  value={block.title_ar}
                                  onChange={(e) => {
                                    const updatedB = selectedPage.blocks.map(b => b.id === block.id ? { ...b, title_ar: e.target.value } : b);
                                    setPages(pages.map(p => p.id === selectedPage.id ? { ...p, blocks: updatedB } : p));
                                    setSelectedPage({ ...selectedPage, blocks: updatedB });
                                  }}
                                  className="w-full mt-1 rounded border border-slate-200 p-1.5 font-arabic"
                                />
                              </div>
                            </div>

                            <div className="grid grid-cols-2 gap-3 text-xs">
                              <div>
                                <label className="text-[9px] text-slate-400 block uppercase">Content text (EN)</label>
                                <textarea
                                  rows={2}
                                  value={block.content_en}
                                  onChange={(e) => {
                                    const updatedB = selectedPage.blocks.map(b => b.id === block.id ? { ...b, content_en: e.target.value } : b);
                                    setPages(pages.map(p => p.id === selectedPage.id ? { ...p, blocks: updatedB } : p));
                                    setSelectedPage({ ...selectedPage, blocks: updatedB });
                                  }}
                                  className="w-full mt-1 rounded border border-slate-200 p-1.5"
                                />
                              </div>
                              <div>
                                <label className="text-[9px] text-slate-400 block uppercase">مضمون النص (AR)</label>
                                <textarea
                                  rows={2}
                                  value={block.content_ar}
                                  onChange={(e) => {
                                    const updatedB = selectedPage.blocks.map(b => b.id === block.id ? { ...b, content_ar: e.target.value } : b);
                                    setPages(pages.map(p => p.id === selectedPage.id ? { ...p, blocks: updatedB } : p));
                                    setSelectedPage({ ...selectedPage, blocks: updatedB });
                                  }}
                                  className="w-full mt-1 rounded border border-slate-200 p-1.5 font-arabic"
                                />
                              </div>
                            </div>
                          </div>
                        ))}
                      </div>
                    </div>
                  )}
                </div>
              </div>
            )}

            {/* 3. TAB: NAVIGATION & WIDGETS */}
            {activeTab === 'menus' && (
              <div className="space-y-6">
                <div className="flex items-center justify-between border-b pb-3">
                  <h3 className="text-sm font-bold text-slate-800">{language === 'en' ? 'Visual Menu Nodes & Side Widgets' : 'أشرطة التنقل والودجات النشطة'}</h3>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  {/* Menus Tree */}
                  <div className="rounded-xl border p-4 space-y-4">
                    <h4 className="text-xs font-bold text-slate-400 uppercase tracking-wider">{language === 'en' ? 'Primary Header Menu Nodes' : 'روابط التنقل بالرأسية'}</h4>
                    
                    <div className="space-y-2">
                      {menus.map((menu, idx) => (
                        <div key={menu.id} className="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50 p-2.5 text-xs">
                          <div className="min-w-0">
                            <p className="font-bold text-slate-700">{editingLang === 'en' ? menu.label_en : menu.label_ar}</p>
                            <p className="text-[9px] text-slate-400 font-mono">{menu.url}</p>
                          </div>
                          <div className="flex items-center gap-1 shrink-0">
                            <button onClick={() => moveMenu(idx, 'up')} className="p-1 hover:bg-slate-200 rounded text-slate-500"><ChevronUp className="h-3 w-3" /></button>
                            <button onClick={() => moveMenu(idx, 'down')} className="p-1 hover:bg-slate-200 rounded text-slate-500"><ChevronDown className="h-3 w-3" /></button>
                            {hasPermission && (
                              <button
                                onClick={() => handleEditMenu(menu)}
                                className="p-1 hover:bg-slate-200 rounded text-slate-500"
                                title={language === 'en' ? 'Edit menu item' : 'تعديل رابط القائمة'}
                              >
                                <Edit2 className="h-3 w-3" />
                              </button>
                            )}
                            {hasPermission && (
                              <button
                                onClick={() => handleDeleteMenu(menu.id)}
                                className="p-1 hover:bg-rose-50 text-rose-600 rounded"
                              >
                                <Trash2 className="h-3 w-3" />
                              </button>
                            )}
                          </div>
                        </div>
                      ))}
                    </div>

                    {hasPermission && (
                      <div className="p-3 bg-slate-50 rounded-xl space-y-3 border text-xs">
                        <p className="font-bold text-slate-800">{language === 'en' ? 'Add Navigation Link' : 'إضافة رابط تنقل جديد'}</p>
                        <div className="grid grid-cols-2 gap-2">
                          <input
                            type="text"
                            placeholder="Label (EN)"
                            value={newMenu.label_en}
                            onChange={(e) => setNewMenu({ ...newMenu, label_en: e.target.value })}
                            className="rounded border p-1.5 bg-white"
                          />
                          <input
                            type="text"
                            placeholder="الاسم (AR)"
                            value={newMenu.label_ar}
                            onChange={(e) => setNewMenu({ ...newMenu, label_ar: e.target.value })}
                            className="rounded border p-1.5 bg-white font-arabic"
                          />
                        </div>
                        <input
                          type="text"
                          placeholder="Path Url (e.g., /contact)"
                          value={newMenu.url}
                          onChange={(e) => setNewMenu({ ...newMenu, url: e.target.value })}
                          className="w-full rounded border p-1.5 bg-white font-mono"
                        />
                        <button
                          onClick={handleAddMenu}
                          className="w-full rounded bg-slate-900 text-white font-bold p-1.5"
                        >
                          {language === 'en' ? 'Insert Node' : 'تضمين الرابط'}
                        </button>
                      </div>
                    )}
                  </div>

                  {/* Sidebar and Footer Widgets */}
                  <div className="rounded-xl border p-4 space-y-4">
                    <h4 className="text-xs font-bold text-slate-400 uppercase tracking-wider">{language === 'en' ? 'Active Widgets Blocks' : 'القطع والودجات البرمجية'}</h4>
                    
                    <div className="space-y-2">
                      {widgets.map(w => (
                        <div key={w.id} className="rounded-lg border border-slate-100 bg-slate-50 p-2.5 text-xs flex justify-between items-center">
                          <div>
                            <span className="text-[8px] bg-slate-200 px-1 py-0.5 rounded uppercase font-bold text-slate-500 mr-1">{w.zone}</span>
                            <span className="font-bold text-slate-700">{editingLang === 'en' ? w.title_en : w.title_ar}</span>
                            <p className="text-[9px] text-slate-400 mt-0.5">Type: {w.type}</p>
                          </div>
                          <div className="flex items-center gap-1">
                            {hasPermission && (
                              <button
                                onClick={() => handleEditWidget(w)}
                                className="text-slate-500 hover:bg-slate-200 p-1 rounded"
                                title={language === 'en' ? 'Edit widget' : 'تعديل الودجت'}
                              >
                                <Edit2 className="h-3.5 w-3.5" />
                              </button>
                            )}
                            {hasPermission && (
                              <button
                                onClick={() => handleDeleteWidget(w.id)}
                                className="text-rose-600 hover:bg-rose-50 p-1 rounded"
                              >
                                <Trash2 className="h-3.5 w-3.5" />
                              </button>
                            )}
                          </div>
                        </div>
                      ))}
                    </div>

                    {hasPermission && (
                      <div className="p-3 bg-slate-50 rounded-xl space-y-3 border text-xs">
                        <p className="font-bold text-slate-800">{language === 'en' ? 'Add Layout Widget' : 'تضمين ودجت خارجي جديد'}</p>
                        <div className="grid grid-cols-2 gap-2">
                          <select
                            value={newWidget.zone}
                            onChange={(e: any) => setNewWidget({ ...newWidget, zone: e.target.value })}
                            className="rounded border p-1.5 bg-white"
                          >
                            <option value="sidebar">Sidebar zone</option>
                            <option value="footer">Footer zone</option>
                          </select>
                          <select
                            value={newWidget.type}
                            onChange={(e) => setNewWidget({ ...newWidget, type: e.target.value })}
                            className="rounded border p-1.5 bg-white"
                          >
                            <option value="Recent Posts">Recent Articles</option>
                            <option value="Newsletter">Newsletter form</option>
                            <option value="Social Links">Social icons</option>
                            <option value="Custom HTML">Custom Text code</option>
                          </select>
                        </div>
                        <div className="grid grid-cols-2 gap-2">
                          <input
                            type="text"
                            placeholder="Title (EN)"
                            value={newWidget.title_en}
                            onChange={(e) => setNewWidget({ ...newWidget, title_en: e.target.value })}
                            className="rounded border p-1.5 bg-white"
                          />
                          <input
                            type="text"
                            placeholder="العنوان (AR)"
                            value={newWidget.title_ar}
                            onChange={(e) => setNewWidget({ ...newWidget, title_ar: e.target.value })}
                            className="rounded border p-1.5 bg-white font-arabic"
                          />
                        </div>
                        <button
                          onClick={handleAddWidget}
                          className="w-full rounded bg-slate-900 text-white font-bold p-1.5"
                        >
                          {language === 'en' ? 'Deploy Widget' : 'نشر وتضمين الودجت'}
                        </button>
                      </div>
                    )}
                  </div>
                </div>
              </div>
            )}

            {/* 4. TAB: HOMEPAGE SETTINGS */}
            {activeTab === 'homepage' && (
              <div className="space-y-6">
                <div className="flex items-center justify-between border-b pb-3">
                  <h3 className="text-sm font-bold text-slate-800">{language === 'en' ? 'Modular Homepage Content Builder' : 'منشئ وهيكلية الصفحة الرئيسية'}</h3>
                </div>

                <p className="text-xs text-slate-500 leading-relaxed">
                  {language === 'en' 
                    ? 'Show, hide, or rearrange the layout structure of the Fun Moment main public homepage.' 
                    : 'قم بتمكين، تعطيل أو إعادة ترتيب التسلسل الهيكلي للكتل والبنرات البرمجية في واجهة موقع فان مومنت العامة.'}
                </p>

                <div className="space-y-3">
                  {homepageSections.map((sect, idx) => (
                    <div key={sect.id} className="rounded-xl border border-slate-200 p-4 bg-white shadow-xs flex items-center justify-between">
                      <div className="flex items-center gap-3">
                        <span className="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center text-xs text-slate-400 font-bold">{idx + 1}</span>
                        <div>
                          <p className="text-xs font-extrabold text-slate-800">{editingLang === 'en' ? sect.title_en : sect.title_ar}</p>
                          <p className="text-[9px] text-slate-400">Section type: {sect.type}</p>
                        </div>
                      </div>

                      <div className="flex items-center gap-2">
                        {/* Toggle enabled / disabled */}
                        <button
                          onClick={() => {
                            if (!hasPermission) return;
                            setHomepageSections(homepageSections.map(s => s.id === sect.id ? { ...s, enabled: !s.enabled } : s));
                            triggerSuccess(language === 'en' ? 'Homepage section state toggled' : 'تم تعديل ظهور المكون في الصفحة الرئيسية');
                          }}
                          className={`rounded px-2.5 py-1 text-[10px] font-bold ${sect.enabled ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-400'}`}
                        >
                          {sect.enabled ? (language === 'en' ? 'ON' : 'نشط') : (language === 'en' ? 'OFF' : 'معطل')}
                        </button>

                        <div className="flex items-center gap-1">
                          <button
                            onClick={() => moveHomepageSection(idx, 'up')}
                            disabled={idx === 0}
                            className="p-1 hover:bg-slate-100 text-slate-400 hover:text-slate-800 disabled:opacity-40"
                          >
                            <ChevronUp className="h-4 w-4" />
                          </button>
                          <button
                            onClick={() => moveHomepageSection(idx, 'down')}
                            disabled={idx === homepageSections.length - 1}
                            className="p-1 hover:bg-slate-100 text-slate-400 hover:text-slate-800 disabled:opacity-40"
                          >
                            <ChevronDown className="h-4 w-4" />
                          </button>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* 5. TAB: BRAND & STYLING */}
            {activeTab === 'brand' && (
              <div className="space-y-6">
                <div className="flex items-center justify-between border-b pb-3">
                  <h3 className="text-sm font-bold text-slate-800">{language === 'en' ? 'Brand Identity, Colors & Layout Variants' : 'الهوية البصرية والمظهر وقوالب العرض'}</h3>
                </div>

                <div className="space-y-5 text-xs">
                  {/* Site identity configs */}
                  <div className="rounded-xl border p-4 bg-slate-50/50 space-y-4">
                    <h4 className="font-bold text-slate-800 flex items-center gap-1.5">
                      <Sparkles className="h-4 w-4 text-indigo-600" />
                      <span>{language === 'en' ? 'Site Identity Parameters' : 'بيانات الهوية والمنصة'}</span>
                    </h4>

                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <label className="text-[10px] text-slate-400 uppercase font-bold">Site Title (English)</label>
                        <input
                          type="text"
                          value={siteIdentity.title_en}
                          onChange={(e) => setSiteIdentity({ ...siteIdentity, title_en: e.target.value })}
                          className="w-full mt-1 rounded border border-slate-200 p-2 bg-white"
                        />
                      </div>
                      <div>
                        <label className="text-[10px] text-slate-400 uppercase font-bold">عنوان الموقع (العربية)</label>
                        <input
                          type="text"
                          value={siteIdentity.title_ar}
                          onChange={(e) => setSiteIdentity({ ...siteIdentity, title_ar: e.target.value })}
                          className="w-full mt-1 rounded border border-slate-200 p-2 bg-white font-arabic"
                        />
                      </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <label className="text-[10px] text-slate-400 uppercase font-bold">Branding Slogan (EN)</label>
                        <input
                          type="text"
                          value={siteIdentity.slogan_en}
                          onChange={(e) => setSiteIdentity({ ...siteIdentity, slogan_en: e.target.value })}
                          className="w-full mt-1 rounded border border-slate-200 p-2 bg-white"
                        />
                      </div>
                      <div>
                        <label className="text-[10px] text-slate-400 uppercase font-bold">شعار الهوية المكتوب (AR)</label>
                        <input
                          type="text"
                          value={siteIdentity.slogan_ar}
                          onChange={(e) => setSiteIdentity({ ...siteIdentity, slogan_ar: e.target.value })}
                          className="w-full mt-1 rounded border border-slate-200 p-2 bg-white font-arabic"
                        />
                      </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <label className="text-[10px] text-slate-400 uppercase font-bold">Header Variant Navigation style</label>
                        <select
                          value={siteIdentity.headerVariant}
                          onChange={(e: any) => setSiteIdentity({ ...siteIdentity, headerVariant: e.target.value })}
                          className="w-full mt-1 rounded border border-slate-200 p-2 bg-white"
                        >
                          <option value="classic">Classic Side Links Header</option>
                          <option value="centered">Centered Large Logo Navigation</option>
                          <option value="deluxe">Deluxe Expanded Search Header</option>
                          <option value="split">Split Minimal Sidebar Drawer</option>
                        </select>
                      </div>
                      <div>
                        <label className="text-[10px] text-slate-400 uppercase font-bold">Footer Layout column style</label>
                        <select
                          value={siteIdentity.footerVariant}
                          onChange={(e: any) => setSiteIdentity({ ...siteIdentity, footerVariant: e.target.value })}
                          className="w-full mt-1 rounded border border-slate-200 p-2 bg-white"
                        >
                          <option value="grid">Grid Directory columns (Recommended)</option>
                          <option value="minimal">Minimalist Row & Social icons</option>
                          <option value="corporate">B2B Corporate Partners Grid</option>
                        </select>
                      </div>
                    </div>
                  </div>

                  {/* Themes / Colors presets */}
                  <div className="rounded-xl border p-4 bg-slate-50/50 space-y-4">
                    <h4 className="font-bold text-slate-800 flex items-center gap-1.5">
                      <Palette className="h-4 w-4 text-indigo-600" />
                      <span>{language === 'en' ? 'Interactive Palette & Presets' : 'لوحة الألوان المخصصة والخطوط المدمجة'}</span>
                    </h4>

                    <div className="grid grid-cols-3 gap-3">
                      <div>
                        <span className="text-[10px] text-slate-400 block font-bold mb-1">Primary Color Preset</span>
                        <div className="flex items-center gap-1.5">
                          {['#4f46e5', '#10b981', '#f59e0b', '#ec4899', '#3b82f6'].map(color => (
                            <button
                              key={color}
                              onClick={() => setThemeConfig({ ...themeConfig, primaryColor: color })}
                              style={{ backgroundColor: color }}
                              className={`w-6 h-6 rounded-full border ${themeConfig.primaryColor === color ? 'border-black ring-2 ring-indigo-300' : 'border-transparent'}`}
                            />
                          ))}
                        </div>
                      </div>

                      <div>
                        <span className="text-[10px] text-slate-400 block font-bold mb-1">Secondary Brand Color</span>
                        <div className="flex items-center gap-1.5">
                          {['#06b6d4', '#14b8a6', '#f43f5e', '#8b5cf6', '#64748b'].map(color => (
                            <button
                              key={color}
                              onClick={() => setThemeConfig({ ...themeConfig, secondaryColor: color })}
                              style={{ backgroundColor: color }}
                              className={`w-6 h-6 rounded-full border ${themeConfig.secondaryColor === color ? 'border-black ring-2 ring-cyan-300' : 'border-transparent'}`}
                            />
                          ))}
                        </div>
                      </div>

                      <div>
                        <span className="text-[10px] text-slate-400 block font-bold mb-1">Font Pair Selection</span>
                        <select
                          value={themeConfig.fontPairing}
                          onChange={(e: any) => setThemeConfig({ ...themeConfig, fontPairing: e.target.value })}
                          className="w-full rounded border border-slate-200 p-1 bg-white text-xs"
                        >
                          <option value="outfit_inter">Outfit (Header) + Inter</option>
                          <option value="cairo_outfit">Cairo (AR Header) + Outfit</option>
                          <option value="mono_space">JetBrains Mono + Space Grotesk</option>
                        </select>
                      </div>
                    </div>
                  </div>

                  <button
                    onClick={() => triggerSuccess(language === 'en' ? 'Branding and theme parameters compiled successfully!' : 'تم حفظ وحفظ خيارات المظهر والتصميم!')}
                    className="w-full font-bold bg-slate-900 text-white rounded-xl py-2 hover:bg-black transition"
                  >
                    {language === 'en' ? 'Commit Layout Variables' : 'تطبيق خيارات الهوية والمظهر'}
                  </button>
                </div>
              </div>
            )}

            {/* 6. TAB: SEO & SITEMAPS */}
            {activeTab === 'seo' && (
              <div className="space-y-6">
                <div className="flex items-center justify-between border-b pb-3">
                  <h3 className="text-sm font-bold text-slate-800">{language === 'en' ? 'SEO Settings & Sitemap Generator' : 'إعدادات الأرشفة والخرائط البرمجية (SEO)'}</h3>
                </div>

                <div className="space-y-4 text-xs">
                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <label className="text-[10px] text-slate-400 uppercase font-bold">Meta Title Tag (English)</label>
                      <input
                        type="text"
                        value={seoConfig.metaTitleEn}
                        onChange={(e) => setSeoConfig({ ...seoConfig, metaTitleEn: e.target.value })}
                        className="w-full mt-1 rounded border border-slate-200 p-2 bg-white"
                      />
                    </div>
                    <div>
                      <label className="text-[10px] text-slate-400 uppercase font-bold">عنوان الصفحة التعريفي (العربية)</label>
                      <input
                        type="text"
                        value={seoConfig.metaTitleAr}
                        onChange={(e) => setSeoConfig({ ...seoConfig, metaTitleAr: e.target.value })}
                        className="w-full mt-1 rounded border border-slate-200 p-2 bg-white font-arabic"
                      />
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <label className="text-[10px] text-slate-400 uppercase font-bold">Meta Description Tag (EN)</label>
                      <textarea
                        rows={3}
                        value={seoConfig.metaDescEn}
                        onChange={(e) => setSeoConfig({ ...seoConfig, metaDescEn: e.target.value })}
                        className="w-full mt-1 rounded border border-slate-200 p-2 bg-white"
                      />
                    </div>
                    <div>
                      <label className="text-[10px] text-slate-400 uppercase font-bold">الوصف التعريفي المقتضب (AR)</label>
                      <textarea
                        rows={3}
                        value={seoConfig.metaDescAr}
                        onChange={(e) => setSeoConfig({ ...seoConfig, metaDescAr: e.target.value })}
                        className="w-full mt-1 rounded border border-slate-200 p-2 bg-white font-arabic"
                      />
                    </div>
                  </div>

                  <div>
                    <label className="text-[10px] text-slate-400 uppercase font-bold">Keywords Separated by Comma</label>
                    <input
                      type="text"
                      value={seoConfig.focusKeywords}
                      onChange={(e) => setSeoConfig({ ...seoConfig, focusKeywords: e.target.value })}
                      className="w-full mt-1 rounded border border-slate-200 p-2 bg-white font-mono"
                    />
                  </div>

                  <div className="rounded-xl border p-4 bg-indigo-50/40 space-y-3">
                    <p className="font-bold text-indigo-900">{language === 'en' ? 'Sitemap.xml Auto-Generation Engine' : 'محرك بناء ملفات خرائط sitemap.xml تلقائياً'}</p>
                    <p className="text-[11px] text-indigo-700">
                      {language === 'en' 
                        ? 'Includes all published events, static pages, and blog categories instantly. Auto-notifies Google Search Console.'
                        : 'يضمن أرشفة كافة الفعاليات والمدونات والصفحات المنشورة تلقائياً. مع خاصية التنبيه المباشر لمحركات البحث.'}
                    </p>
                    <div className="flex items-center justify-between text-[11px] font-bold text-slate-600">
                      <span>Last Compile: <span className="font-mono text-slate-800">{seoConfig.lastGenerated}</span></span>
                      <button
                        onClick={() => {
                          setSeoConfig({ ...seoConfig, lastGenerated: new Date().toISOString().replace('T', ' ').substring(0, 16) });
                          triggerSuccess(language === 'en' ? 'XML Sitemap generated and deployed to sitemap.xml successfully!' : 'تم إعادة بناء خرائط sitemap.xml وإرسالها بنجاح!');
                        }}
                        className="rounded-lg bg-indigo-600 text-white px-3.5 py-1.5 hover:bg-indigo-700"
                      >
                        {language === 'en' ? 'Force Regenerate' : 'تحديث وبناء الآن'}
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            )}

            {/* 7. TAB: CUSTOM SCRIPTS */}
            {activeTab === 'scripts' && (
              <div className="space-y-6">
                <div className="flex items-center justify-between border-b pb-3">
                  <h3 className="text-sm font-bold text-slate-800">{language === 'en' ? 'Analytics Scripts & Custom CSS/JS Overrides' : 'أكواد التتبع المخصصة والتعديلات المباشرة'}</h3>
                </div>

                <div className="space-y-4 text-xs">
                  <div>
                    <label className="text-[10px] text-slate-400 uppercase font-bold">Header Integration Code (e.g. Analytics, Pixels)</label>
                    <textarea
                      rows={3}
                      value={scriptsConfig.headerScripts}
                      onChange={(e) => setScriptsConfig({ ...scriptsConfig, headerScripts: e.target.value })}
                      className="w-full mt-1 rounded border border-slate-200 p-2 bg-slate-950 text-emerald-400 font-mono text-[10px]"
                    />
                  </div>

                  <div>
                    <label className="text-[10px] text-slate-400 uppercase font-bold">Footer Integration Code</label>
                    <textarea
                      rows={2}
                      value={scriptsConfig.footerScripts}
                      onChange={(e) => setScriptsConfig({ ...scriptsConfig, footerScripts: e.target.value })}
                      className="w-full mt-1 rounded border border-slate-200 p-2 bg-slate-950 text-emerald-400 font-mono text-[10px]"
                    />
                  </div>

                  <div>
                    <label className="text-[10px] text-slate-400 uppercase font-bold">Site-wide Custom CSS stylesheet Overrides</label>
                    <textarea
                      rows={3}
                      value={scriptsConfig.customCss}
                      onChange={(e) => setScriptsConfig({ ...scriptsConfig, customCss: e.target.value })}
                      className="w-full mt-1 rounded border border-slate-200 p-2 bg-slate-950 text-sky-400 font-mono text-[10px]"
                    />
                  </div>

                  <div>
                    <label className="text-[10px] text-slate-400 uppercase font-bold">Custom Javascript block code</label>
                    <textarea
                      rows={2}
                      value={scriptsConfig.customJs}
                      onChange={(e) => setScriptsConfig({ ...scriptsConfig, customJs: e.target.value })}
                      className="w-full mt-1 rounded border border-slate-200 p-2 bg-slate-950 text-amber-400 font-mono text-[10px]"
                    />
                  </div>

                  <button
                    onClick={() => triggerSuccess(language === 'en' ? 'Custom client codes re-injected' : 'تم حقن وتطبيق التعديلات البرمجية!')}
                    className="w-full font-bold bg-slate-900 text-white rounded-xl py-2"
                  >
                    {language === 'en' ? 'Inject Custom Script Overrides' : 'حفظ ونشر التعديلات البرمجية'}
                  </button>
                </div>
              </div>
            )}

            {/* 8. TAB: EMAIL TEMPLATES & SYSTEMS */}
            {activeTab === 'emails' && (
              <div className="space-y-6">
                <div className="flex items-center justify-between border-b pb-3">
                  <h3 className="text-sm font-bold text-slate-800">{language === 'en' ? 'Email Templates & Emergency Utility settings' : 'إدارة قوالب البريد الإلكتروني والصيانة'}</h3>
                </div>

                {/* Sub tab toggler inside section */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  {/* Email templates builder */}
                  <div className="rounded-xl border p-4 space-y-4 text-xs">
                    <h4 className="font-bold text-slate-800 uppercase tracking-wider text-[10px] text-slate-400">{language === 'en' ? 'System Emails Builder' : 'قوالب بريد النظام المبرمج'}</h4>
                    
                    <div>
                      <label className="text-[9px] text-slate-400 block font-bold">Select Active Template</label>
                      <select
                        value={selectedEmail.id}
                        onChange={(e) => {
                          const found = emailTemplates.find(item => item.id === e.target.value);
                          if (found) setSelectedEmail(found);
                        }}
                        className="w-full mt-1 rounded border border-slate-200 p-1.5"
                      >
                        {emailTemplates.map(item => (
                          <option key={item.id} value={item.id}>{item.name}</option>
                        ))}
                      </select>
                    </div>

                    <div className="space-y-2">
                      <p className="text-[9px] text-slate-400 font-bold uppercase">{language === 'en' ? 'Available Tokens (Click to Copy)' : 'رموز الاختصار البرمجية (انقر للنسخ)'}</p>
                      <div className="flex flex-wrap gap-1">
                        {['{{buyer_name}}', '{{order_id}}', '{{amount}}', '{{service_title}}'].map(token => (
                          <button
                            key={token}
                            type="button"
                            onClick={() => {
                              navigator.clipboard.writeText(token);
                              triggerSuccess(`Copied: ${token}`);
                            }}
                            className="bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded px-1.5 py-0.5 text-[8px] font-mono font-bold"
                          >
                            {token}
                          </button>
                        ))}
                      </div>
                    </div>

                    <div className="space-y-3 pt-2">
                      <div>
                        <label className="text-[9px] text-slate-400 block uppercase font-bold">Subject ({editingLang.toUpperCase()})</label>
                        <input
                          type="text"
                          value={editingLang === 'en' ? selectedEmail.subject_en : selectedEmail.subject_ar}
                          onChange={(e) => {
                            const updated = editingLang === 'en' 
                              ? { ...selectedEmail, subject_en: e.target.value } 
                              : { ...selectedEmail, subject_ar: e.target.value };
                            setSelectedEmail(updated);
                            setEmailTemplates(emailTemplates.map(item => item.id === selectedEmail.id ? updated : item));
                          }}
                          className={`w-full mt-1 rounded border border-slate-200 p-1.5 ${editingLang === 'ar' ? 'font-arabic' : ''}`}
                        />
                      </div>

                      <div>
                        <label className="text-[9px] text-slate-400 block uppercase font-bold">Email Content Markup</label>
                        <textarea
                          rows={4}
                          value={editingLang === 'en' ? selectedEmail.body_en : selectedEmail.body_ar}
                          onChange={(e) => {
                            const updated = editingLang === 'en' 
                              ? { ...selectedEmail, body_en: e.target.value } 
                              : { ...selectedEmail, body_ar: e.target.value };
                            setSelectedEmail(updated);
                            setEmailTemplates(emailTemplates.map(item => item.id === selectedEmail.id ? updated : item));
                          }}
                          className={`w-full mt-1 rounded border border-slate-200 p-1.5 font-mono text-[10px] ${editingLang === 'ar' ? 'font-arabic' : ''}`}
                        />
                      </div>
                    </div>
                  </div>

                  {/* Maintenance and 404 Pages control */}
                  <div className="rounded-xl border p-4 space-y-4 text-xs">
                    <h4 className="font-bold text-slate-800 uppercase tracking-wider text-[10px] text-slate-400">{language === 'en' ? 'Maintenance & Error Page Setup' : 'إعدادات الصيانة ومؤشرات الخطأ 404'}</h4>

                    {/* Maintenance toggle switch */}
                    <div className="rounded-xl p-3 bg-rose-50 border border-rose-100/50 space-y-3">
                      <div className="flex items-center justify-between">
                        <span className="font-bold text-rose-950 flex items-center gap-1.5">
                          <AlertTriangle className="h-4.5 w-4.5 text-rose-600 animate-pulse" />
                          <span>{language === 'en' ? 'Maintenance Mode Switch' : 'مفتاح وضع صيانة النظام'}</span>
                        </span>
                        <input
                          type="checkbox"
                          checked={maintenanceMode}
                          onChange={(e) => {
                            if (!hasPermission) return;
                            setMaintenanceMode(e.target.checked);
                            triggerSuccess(e.target.checked ? 'Maintenance page active!' : 'Public site online again!');
                          }}
                          className="rounded border-rose-300 text-rose-600 focus:ring-rose-500 h-4.5 w-4.5"
                        />
                      </div>
                      
                      {maintenanceMode && (
                        <div className="space-y-2 text-[11px]">
                          <div>
                            <span className="text-rose-900 block font-semibold">Under Construction Reason (EN/AR)</span>
                            <input
                              type="text"
                              value={maintenanceReasonEn}
                              onChange={(e) => setMaintenanceReasonEn(e.target.value)}
                              className="w-full mt-1 rounded border p-1 bg-white"
                              placeholder="Reason English"
                            />
                            <input
                              type="text"
                              value={maintenanceReasonAr}
                              onChange={(e) => setMaintenanceReasonAr(e.target.value)}
                              className="w-full mt-1 rounded border p-1 bg-white font-arabic"
                              placeholder="السبب بالعربية"
                            />
                          </div>
                          <div>
                            <span className="text-rose-900 block font-semibold">Planned Launch date</span>
                            <input
                              type="datetime-local"
                              value={maintenanceTimer}
                              onChange={(e) => setMaintenanceTimer(e.target.value)}
                              className="w-full mt-1 rounded border p-1 bg-white font-mono"
                            />
                          </div>
                        </div>
                      )}
                    </div>

                    {/* 404 configurations */}
                    <div className="space-y-2 border-t pt-3">
                      <p className="font-bold text-slate-700">{language === 'en' ? 'Custom 404 error header' : 'تخصيص نص خطأ 404 للصفحات المفقودة'}</p>
                      <div className="space-y-1">
                        <input
                          type="text"
                          value={custom404TextEn}
                          onChange={(e) => setCustom404TextEn(e.target.value)}
                          className="w-full rounded border p-1.5"
                          placeholder="404 Text EN"
                        />
                        <input
                          type="text"
                          value={custom404TextAr}
                          onChange={(e) => setCustom404TextAr(e.target.value)}
                          className="w-full rounded border p-1.5 font-arabic"
                          placeholder="الرسالة بالعربية"
                        />
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            )}

            {/* 9. TAB: MEDIA GALLERY MANAGER */}
            {activeTab === 'media' && (
              <div className="space-y-6">
                <div className="flex items-center justify-between border-b pb-3">
                  <h3 className="text-sm font-bold text-slate-800">{language === 'en' ? 'Media Upload Manager' : 'مدير الوسائط والصور المرفوعة'}</h3>
                  <button
                    onClick={handleSimulateUpload}
                    className="flex items-center gap-1.5 rounded-lg bg-slate-900 text-white font-bold px-3 py-1.5 text-xs hover:bg-black"
                  >
                    <Upload className="h-4 w-4" />
                    <span>{language === 'en' ? 'Upload File' : 'رفع ملف'}</span>
                  </button>
                </div>

                <input
                  ref={fileInputRef}
                  type="file"
                  accept="image/*"
                  className="hidden"
                  onChange={(e) => {
                    const file = e.target.files?.[0];
                    if (file) {
                      handleUploadMediaFile(file);
                      e.currentTarget.value = '';
                    }
                  }}
                />

                {/* Drag and drop simulated dropzone */}
                <div
                  onClick={handleSimulateUpload}
                  className="rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50/50 p-6 text-center cursor-pointer hover:bg-slate-50 hover:border-indigo-400 transition"
                >
                  <Upload className="h-8 w-8 text-slate-400 mx-auto mb-2 animate-bounce" />
                  <p className="text-xs font-bold text-slate-700">{language === 'en' ? 'Click to upload a real media file from disk' : 'انقر لرفع ملف وسائط حقيقي من الجهاز'}</p>
                  <p className="text-[10px] text-slate-400 mt-1">Supports JPEG, PNG, SVG up to 5MB size limit.</p>
                </div>

                {/* Search bar */}
                <div className="relative max-w-sm">
                  <Search className="absolute top-2.5 left-3 h-4 w-4 text-slate-400 rtl:right-3 rtl:left-auto" />
                  <input
                    type="text"
                    placeholder={language === 'en' ? 'Search media files...' : 'البحث بالملفات والوسائط...'}
                    value={searchMedia}
                    onChange={(e) => setSearchMedia(e.target.value)}
                    className="w-full rounded-lg border border-slate-200 py-1.5 pl-9 pr-3 text-xs outline-hidden focus:border-indigo-500 rtl:pr-9 rtl:pl-3"
                  />
                </div>

                {/* Media Grid */}
                <div className="grid grid-cols-2 sm:grid-cols-3 gap-4">
                  {mediaGallery
                    .filter(m => m.name.toLowerCase().includes(searchMedia.toLowerCase()))
                    .map(item => (
                      <div
                        key={item.id}
                        onClick={() => setSelectedMedia(item)}
                        className={`group relative rounded-xl border overflow-hidden cursor-pointer transition ${
                          selectedMedia?.id === item.id ? 'border-indigo-600 ring-2 ring-indigo-100' : 'border-slate-200'
                        }`}
                      >
                        <img src={item.url} className="w-full h-24 object-cover" alt="gallery" />
                        <div className="absolute inset-x-0 bottom-0 bg-black/60 p-1.5 text-white text-[9px] truncate">
                          {item.name}
                        </div>
                      </div>
                    ))}
                </div>

                {/* Media details pane */}
                {selectedMedia && (
                  <div className="rounded-xl border p-4 bg-slate-50/40 text-xs space-y-3">
                    <p className="font-bold text-slate-800 border-b pb-1.5 uppercase text-[10px] tracking-wider">{language === 'en' ? 'Selected Asset Metadata details' : 'تفاصيل الميتا للملف والربط الوطني للـ SEO'}</p>
                    
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <span className="text-slate-400 font-semibold block">Filename:</span>
                        <span className="font-mono break-all font-bold text-slate-700">{selectedMedia.name}</span>
                      </div>
                      <div>
                        <span className="text-slate-400 font-semibold block">Details:</span>
                        <span className="font-bold text-slate-700">{selectedMedia.size} ({selectedMedia.dimensions})</span>
                      </div>
                    </div>

                    <div className="grid grid-cols-2 gap-3 pt-1">
                      <div>
                        <label className="text-[9px] text-slate-400 font-bold uppercase">SEO Alternate Text (EN)</label>
                        <input
                          type="text"
                          value={selectedMedia.alt_en}
                          onChange={(e) => {
                            const updated = { ...selectedMedia, alt_en: e.target.value };
                            setSelectedMedia(updated);
                            setMediaGallery(mediaGallery.map(m => m.id === selectedMedia.id ? updated : m));
                          }}
                          onBlur={(e) => handleUpdateMediaAlt(selectedMedia.id, e.target.value, 'en')}
                          className="w-full mt-1 rounded border border-slate-200 p-1 bg-white"
                        />
                      </div>
                      <div>
                        <label className="text-[9px] text-slate-400 font-bold uppercase">شرح الصورة والـ ALT (AR)</label>
                        <input
                          type="text"
                          value={selectedMedia.alt_ar}
                          onChange={(e) => {
                            const updated = { ...selectedMedia, alt_ar: e.target.value };
                            setSelectedMedia(updated);
                            setMediaGallery(mediaGallery.map(m => m.id === selectedMedia.id ? updated : m));
                          }}
                          onBlur={(e) => handleUpdateMediaAlt(selectedMedia.id, e.target.value, 'ar')}
                          className="w-full mt-1 rounded border border-slate-200 p-1 bg-white font-arabic"
                        />
                      </div>
                    </div>
                    {hasPermission && (
                      <div className="flex justify-end">
                        <button
                          onClick={() => handleDeleteMedia(selectedMedia.id)}
                          className="rounded-lg bg-rose-600 px-3 py-1.5 text-[10px] font-bold text-white"
                        >
                          {language === 'en' ? 'Delete Media' : 'حذف الوسائط'}
                        </button>
                      </div>
                    )}
                  </div>
                )}
              </div>
            )}

          </div>

          {/* RIGHT SIDE: LIVE PUBLIC FRONTEND PREVIEW MONITOR */}
          <div className="xl:col-span-1 rounded-2xl border border-slate-300 bg-slate-950 p-4 space-y-4 shadow-xl select-none sticky top-20">
            <div className="flex items-center justify-between border-b border-slate-800 pb-2.5">
              <span className="text-[9px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-1.5">
                <span className="relative flex h-2 w-2">
                  <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                  <span className="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                </span>
                <span>{language === 'en' ? 'Live Website Preview' : 'المعاينة المباشرة للموقع'}</span>
              </span>
              <span className="rounded-md bg-slate-900 px-1.5 py-0.5 text-[8px] font-bold text-slate-500 font-mono">PUBLIC FEED</span>
            </div>

            {/* Simulating website browser preview */}
            <div
              dir={editingLang === 'ar' ? 'rtl' : 'ltr'}
              style={{ fontSize: themeConfig.fontSizeScale === 'large' ? '14px' : themeConfig.fontSizeScale === 'small' ? '11px' : '12px' }}
              className="rounded-xl bg-white border border-slate-100 overflow-hidden text-[11px] text-slate-700 min-h-[350px] shadow-lg flex flex-col justify-between"
            >
              {/* If maintenance mode active */}
              {maintenanceMode ? (
                <div className="flex-1 flex flex-col items-center justify-center p-6 text-center space-y-3 bg-slate-50">
                  <AlertTriangle className="h-10 w-10 text-amber-500 animate-bounce" />
                  <h4 className="font-extrabold text-slate-900 text-xs">
                    {editingLang === 'en' ? 'Site Under Construction' : 'موقع فان مومنت قيد الصيانة'}
                  </h4>
                  <p className="text-[10px] text-slate-500 leading-relaxed max-w-[180px]">
                    {editingLang === 'en' ? maintenanceReasonEn : maintenanceReasonAr}
                  </p>
                  <div className="rounded bg-slate-900 px-2 py-1 text-white font-mono text-[9px]">
                    Launch: {maintenanceTimer.replace('T', ' ')}
                  </div>
                </div>
              ) : (
                <>
                  {/* Public Header */}
                  <header className={`p-3 border-b border-slate-100 bg-slate-50 flex items-center justify-between ${
                    siteIdentity.headerVariant === 'centered' ? 'flex-col gap-1.5 text-center' : ''
                  }`}>
                    {/* Logo & Slogan */}
                    <div>
                      <span className="font-black text-[12px] text-slate-900" style={{ color: themeConfig.primaryColor }}>
                        {editingLang === 'en' ? siteIdentity.title_en.split('|')[0] : siteIdentity.title_ar.split('|')[0]}
                      </span>
                      <p className="text-[8px] text-slate-400 font-semibold italic">
                        {editingLang === 'en' ? siteIdentity.slogan_en : siteIdentity.slogan_ar}
                      </p>
                    </div>

                    {/* Navbar links based on Menus state */}
                    <nav className="flex items-center gap-2 mt-1 flex-wrap justify-center text-[9px] font-bold text-slate-600">
                      {menus.map(item => (
                        <span key={item.id} className="hover:text-indigo-600 cursor-pointer">
                          {editingLang === 'en' ? item.label_en : item.label_ar}
                        </span>
                      ))}
                    </nav>
                  </header>

                  {/* Public Body Content (Changes depending on Active Workspace Tab) */}
                  <main className="flex-1 p-3 space-y-3.5 bg-slate-50/30">
                    
                    {/* PREVIEW: Blogs tab */}
                    {activeTab === 'blogs' && selectedBlog && (
                      <div className="space-y-2 animate-fade-in">
                        <img src={selectedBlog.image} className="w-full h-20 object-cover rounded-lg" alt="preview" />
                        <span className="bg-slate-900 text-white rounded px-1 text-[8px] font-bold inline-block">{selectedBlog.category}</span>
                        <h4 className="font-black text-slate-900 text-xs leading-snug">
                          {editingLang === 'en' ? selectedBlog.title_en : selectedBlog.title_ar}
                        </h4>
                        <p className="text-[9px] text-slate-500 line-clamp-3 leading-relaxed">
                          {editingLang === 'en' ? selectedBlog.content_en : selectedBlog.content_ar}
                        </p>
                      </div>
                    )}

                    {/* PREVIEW: Static Pages */}
                    {activeTab === 'pages' && selectedPage && (
                      <div className="space-y-3 animate-fade-in">
                        <div className="bg-slate-100 p-2 rounded text-center">
                          <span className="text-[8px] text-indigo-600 font-bold block uppercase">{editingLang === 'en' ? 'Static Content' : 'محتوى تعريفي ثابت'}</span>
                          <h4 className="font-bold text-slate-900">{editingLang === 'en' ? selectedPage.title_en : selectedPage.title_ar}</h4>
                        </div>
                        <div className="space-y-2">
                          {selectedPage.blocks.map(b => (
                            <div key={b.id} className="p-2 border rounded-lg bg-white">
                              <p className="font-extrabold text-slate-800 text-[9px] border-b pb-0.5" style={{ color: themeConfig.secondaryColor }}>
                                {editingLang === 'en' ? b.title_en : b.title_ar}
                              </p>
                              <p className="text-[9px] text-slate-500 mt-1 leading-relaxed">
                                {editingLang === 'en' ? b.content_en : b.content_ar}
                              </p>
                            </div>
                          ))}
                        </div>
                      </div>
                    )}

                    {/* PREVIEW: Menus & Widgets */}
                    {activeTab === 'menus' && (
                      <div className="space-y-3 animate-fade-in text-xs">
                        <div className="rounded p-2 border bg-white space-y-1.5">
                          <p className="text-[8px] font-bold text-slate-400 block uppercase">Sidebar Widget Sandbox</p>
                          {widgets.filter(w => w.zone === 'sidebar').map(w => (
                            <div key={w.id} className="p-1 rounded bg-slate-50 font-bold border-l-2 border-indigo-600 text-[10px]">
                              {editingLang === 'en' ? w.title_en : w.title_ar}
                            </div>
                          ))}
                        </div>
                        <div className="rounded p-2 border bg-white space-y-1.5">
                          <p className="text-[8px] font-bold text-slate-400 block uppercase">Footer Widget Sandbox</p>
                          {widgets.filter(w => w.zone === 'footer').map(w => (
                            <div key={w.id} className="p-1 rounded bg-slate-50 font-bold text-[10px]">
                              {editingLang === 'en' ? w.title_en : w.title_ar}
                            </div>
                          ))}
                        </div>
                      </div>
                    )}

                    {/* PREVIEW: Homepage Builder */}
                    {activeTab === 'homepage' && (
                      <div className="space-y-2.5 animate-fade-in">
                        <p className="text-[8px] font-bold text-slate-400 text-center uppercase">{editingLang === 'en' ? 'Rearrangeable Homepage Sandbox' : 'محاكي واجهة الموقع العامة'}</p>
                        {homepageSections.filter(sect => sect.enabled).map((sect, idx) => (
                          <div
                            key={sect.id}
                            className="p-2.5 rounded-xl border border-dashed text-center bg-white shadow-xs"
                            style={{ borderColor: idx % 2 === 0 ? themeConfig.primaryColor : themeConfig.secondaryColor }}
                          >
                            <span className="text-[7px] text-slate-400 font-mono block uppercase">Block {idx+1}: {sect.type}</span>
                            <span className="font-extrabold text-slate-800 text-[10px]">
                              {editingLang === 'en' ? sect.title_en : sect.title_ar}
                            </span>
                          </div>
                        ))}
                      </div>
                    )}

                    {/* PREVIEW: Brand Identity and Fonts */}
                    {activeTab === 'brand' && (
                      <div className="space-y-4 animate-fade-in text-center py-4">
                        <div className="p-3 border rounded-xl bg-white space-y-2 max-w-[180px] mx-auto">
                          <p className="text-[8px] text-slate-400 font-bold uppercase">Color Specimen</p>
                          <div className="flex justify-center gap-1">
                            <span className="w-5 h-5 rounded-full" style={{ backgroundColor: themeConfig.primaryColor }} />
                            <span className="w-5 h-5 rounded-full" style={{ backgroundColor: themeConfig.secondaryColor }} />
                          </div>
                          <p className="text-[9px] font-bold text-slate-700 font-mono">Font Pair: {themeConfig.fontPairing}</p>
                          <button
                            className="rounded-lg w-full text-white font-bold py-1.5 text-[9px]"
                            style={{ backgroundColor: themeConfig.primaryColor }}
                          >
                            Primary Button
                          </button>
                          <button
                            className="rounded-lg w-full text-white font-bold py-1.5 text-[9px]"
                            style={{ backgroundColor: themeConfig.secondaryColor }}
                          >
                            Secondary Button
                          </button>
                        </div>
                      </div>
                    )}

                    {/* PREVIEW: SEO Tag indexing */}
                    {activeTab === 'seo' && (
                      <div className="space-y-2.5 animate-fade-in text-xs p-2 bg-slate-100 rounded-lg">
                        <p className="text-[8px] font-black text-slate-400 uppercase tracking-wider block border-b pb-0.5">Google Search SERP Preview</p>
                        <h4 className="text-blue-700 font-semibold hover:underline cursor-pointer leading-tight truncate">
                          {editingLang === 'en' ? seoConfig.metaTitleEn : seoConfig.metaTitleAr}
                        </h4>
                        <p className="text-emerald-700 text-[8px] font-mono font-medium leading-none">https://funmoment.com.sa</p>
                        <p className="text-slate-500 text-[9px] leading-relaxed line-clamp-2">
                          {editingLang === 'en' ? seoConfig.metaDescEn : seoConfig.metaDescAr}
                        </p>
                      </div>
                    )}

                    {/* PREVIEW: Custom Analytics Scripts */}
                    {activeTab === 'scripts' && (
                      <div className="space-y-2 animate-fade-in p-2 bg-slate-950 text-emerald-400 font-mono text-[8px] rounded-lg">
                        <p className="text-slate-500 border-b border-slate-800 pb-1 font-bold">CLIENT HEAD COMPILATION CONSOLE</p>
                        <p className="text-sky-400">Loading custom style rules...</p>
                        <p>{scriptsConfig.customCss.substring(0, 50)}...</p>
                        <p className="text-amber-400">Injected Javascript overrides successfully.</p>
                      </div>
                    )}

                    {/* PREVIEW: Email Templates wrapper */}
                    {activeTab === 'emails' && selectedEmail && (
                      <div className="space-y-2 animate-fade-in p-2.5 border rounded-lg bg-white text-[9px]">
                        <div className="bg-slate-50 p-1.5 border-b text-[8px] space-y-0.5 text-slate-500">
                          <p><span className="font-bold">From:</span> notifications@funmoment.com</p>
                          <p><span className="font-bold">Subject:</span> {editingLang === 'en' ? selectedEmail.subject_en : selectedEmail.subject_ar}</p>
                        </div>
                        <div className="p-1 leading-relaxed text-slate-600 font-sans whitespace-pre-wrap">
                          {editingLang === 'en' ? selectedEmail.body_en : selectedEmail.body_ar}
                        </div>
                      </div>
                    )}

                    {/* PREVIEW: Media alt gallery */}
                    {activeTab === 'media' && selectedMedia && (
                      <div className="space-y-2 animate-fade-in p-2 border rounded-lg bg-white">
                        <img src={selectedMedia.url} className="w-full h-20 object-cover rounded" alt="media" />
                        <div className="text-[8px] text-slate-500 space-y-1">
                          <p className="font-bold text-slate-800 break-all">{selectedMedia.name}</p>
                          <p><span className="font-extrabold text-slate-400 block uppercase">SEO Alt Text ({editingLang.toUpperCase()}):</span></p>
                          <p className="italic text-slate-600">"{editingLang === 'en' ? selectedMedia.alt_en : selectedMedia.alt_ar}"</p>
                        </div>
                      </div>
                    )}

                  </main>

                  {/* Public Footer */}
                  <footer className="p-3 border-t border-slate-100 bg-slate-50 text-[8px] text-slate-400 flex flex-col gap-1 text-center">
                    <p className="font-extrabold">© 2026 FUN MOMENT. Built & Approved with Saudi National Security Standard.</p>
                  </footer>
                </>
              )}
            </div>
          </div>

        </div>

      </div>
    </div>
  );
}
