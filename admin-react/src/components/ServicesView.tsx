/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { Language, Service, UserRole } from '../types';
import { translations } from '../translations';
import React, { useState, useEffect } from 'react';
import { LaravelAPI } from '../api';
import { saveAuditLog, saveChangeRecord } from '../utils/auditLogger';
import {
  Search,
  Filter,
  ShieldCheck,
  AlertTriangle,
  Eye,
  CheckCircle2,
  Ban,
  RefreshCcw,
  Plus,
  Edit2,
  Trash2,
  Folder,
  MapPin,
  Image as ImageIcon,
  Upload,
  Copy,
  Link,
  ChevronDown,
  Check,
  Globe
} from 'lucide-react';

interface ServicesViewProps {
  language: Language;
  activeRole: UserRole;
}

// Categories, subcategories & child categories types
interface CategoryNode {
  id: number;
  nameEn: string;
  nameAr: string;
  slug: string;
  status: 'active' | 'inactive';
  servicesCount: number;
  subcategories?: SubcategoryNode[];
}

interface SubcategoryNode {
  id: number;
  parentId: number;
  nameEn: string;
  nameAr: string;
  slug: string;
  status: 'active' | 'inactive';
  servicesCount: number;
  childCategories?: ChildCategoryNode[];
}

interface ChildCategoryNode {
  id: number;
  parentId: number;
  nameEn: string;
  nameAr: string;
  slug: string;
  status: 'active' | 'inactive';
  servicesCount: number;
}

// Geography types
interface Country {
  id: string; // e.g., 'SA'
  nameEn: string;
  nameAr: string;
  phoneCode: string;
  currency: string;
  status: 'active' | 'inactive';
}

interface City {
  id: number;
  countryId: string;
  nameEn: string;
  nameAr: string;
  status: 'active' | 'inactive';
}

interface Area {
  id: number;
  cityId: number;
  nameEn: string;
  nameAr: string;
  status: 'active' | 'inactive';
}

// Media file type
interface MediaFile {
  id: number;
  name: string;
  size: string;
  type: 'image' | 'video' | 'audio' | 'pdf';
  url: string;
  dimensions?: string;
  uploadedAt: string;
}

export default function ServicesView({ language, activeRole }: ServicesViewProps) {
  const t = translations[language];
  const isRtl = language === 'ar';

  const [activeSubTab, setActiveSubTab] = useState<'services' | 'categories' | 'locations' | 'media'>('services');
  const [services, setServices] = useState<Service[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [selectedService, setSelectedService] = useState<Service | null>(null);
  const [actionSuccess, setActionSuccess] = useState('');
  const [validationError, setValidationError] = useState('');

  // Bulk operation tracking
  const [selectedServiceIds, setSelectedServiceIds] = useState<number[]>([]);

  // Service form variables (Create/Edit state)
  const [formMode, setFormMode] = useState<'list' | 'create' | 'edit'>('list');
  const [editingServiceId, setEditingServiceId] = useState<number | null>(null);
  const [formTitleEn, setFormTitleEn] = useState('');
  const [formTitleAr, setFormTitleAr] = useState('');
  const [formCategoryEn, setFormCategoryEn] = useState('');
  const [formCategoryAr, setFormCategoryAr] = useState('');
  const [formCategoryId, setFormCategoryId] = useState<number | ''>('');
  const [formSubcategoryId, setFormSubcategoryId] = useState<number | ''>('');
  const [formSellerId, setFormSellerId] = useState<number | ''>('');
  const [formPrice, setFormPrice] = useState<number>(100);
  const [formDescEn, setFormDescEn] = useState('');
  const [formDescAr, setFormDescAr] = useState('');
  const [formImageFile, setFormImageFile] = useState<File | null>(null);
  const [formImagePreview, setFormImagePreview] = useState<string>('');
  const [formSelectedMediaId, setFormSelectedMediaId] = useState<number | null>(null);
  const [showMediaPickerModal, setShowMediaPickerModal] = useState(false);

  // Sellers and Categories lists loaded from backend
  const [sellers, setSellers] = useState<{ id: number; name: string; email: string }[]>([]);
  const [categoryOptions, setCategoryOptions] = useState<any[]>([]);

  // Nested categories initial state
  const [categories, setCategories] = useState<CategoryNode[]>([]);

  // Categories interactive modes
  const [showAddCategoryModal, setShowAddCategoryModal] = useState(false);
  const [catLevel, setCatLevel] = useState<'parent' | 'sub' | 'child'>('parent');
  const [selectedParentCatId, setSelectedParentCatId] = useState<number>(1);
  const [selectedParentSubId, setSelectedParentSubId] = useState<number>(11);
  const [newCatEn, setNewCatEn] = useState('');
  const [newCatAr, setNewCatAr] = useState('');
  const [newCatSlug, setNewCatSlug] = useState('');

  // Geography initial state
  const [countries, setCountries] = useState<Country[]>([]);

  const [cities, setCities] = useState<City[]>([]);

  const [areas, setAreas] = useState<Area[]>([]);

  // Geolocation active tabs & modals
  const [geoTab, setGeoTab] = useState<'countries' | 'cities' | 'areas'>('countries');
  const [showAddGeoModal, setShowAddGeoModal] = useState(false);
  const [geoNameEn, setGeoNameEn] = useState('');
  const [geoNameAr, setGeoNameAr] = useState('');
  const [geoParam1, setGeoParam1] = useState(''); // country id or phone code
  const [geoParam2, setGeoParam2] = useState(''); // currency code
  const [geoParentId, setGeoParentId] = useState(''); // parent ID link

  // Media Library state
  const [mediaFiles, setMediaFiles] = useState<MediaFile[]>([]);
  const [mediaSearch, setMediaSearch] = useState('');
  const [mediaTypeFilter, setMediaTypeFilter] = useState<string>('all');
  const [copiedId, setCopiedId] = useState<number | null>(null);
  const [uploadProgress, setUploadProgress] = useState<number | null>(null);
  const mediaVaultInputRef = React.useRef<HTMLInputElement>(null);

  // Permission Verification
  const hasPermission = activeRole === 'super_admin' || activeRole === 'moderator';

  const loadServices = async () => {
    setLoading(true);
    try {
      const data = await LaravelAPI.getServicesPayload();
      setServices(data.services);
      if (data.categories && data.categories.length > 0) {
        setCategoryOptions(data.categories);
      }
      if (data.sellers && data.sellers.length > 0) {
        setSellers(data.sellers);
      } else {
        const users = await LaravelAPI.getUsers().catch(() => []);
        const sellerUsers = users
          .filter(u => u.role === 'seller')
          .map(u => ({ id: u.id, name: u.name, email: u.email }));
        setSellers(sellerUsers);
      }
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  const loadMedia = async () => {
    try {
      const assets = await LaravelAPI.getMediaLibrary();
      if (Array.isArray(assets)) {
        setMediaFiles(assets.map(a => ({
          id: a.id,
          name: a.slug || a.title_en || `asset-${a.id}`,
          size: a.size || '',
          type: 'image',
          url: a.image || a.url || '',
          uploadedAt: a.updated_at || 'Recently',
          dimensions: a.dimensions || ''
        })));
      }
    } catch (err) {
      console.error("Failed to load media vault", err);
    }
  };

  const loadCategoryTree = async () => {
    try {
      const tree = await LaravelAPI.getCategories();
      if (Array.isArray(tree)) {
        setCategories(tree);
      }
    } catch (err) {
      console.error("Failed to load categories tree", err);
    }
  };

  const loadGeographies = async () => {
    try {
      const locs = await LaravelAPI.getLocations();
      if (locs) {
        if (Array.isArray(locs.countries)) setCountries(locs.countries);
        if (Array.isArray(locs.cities)) setCities(locs.cities);
        if (Array.isArray(locs.areas)) setAreas(locs.areas);
      }
    } catch (err) {
      console.error("Failed to load geographies", err);
    }
  };

  useEffect(() => {
    loadServices();
    loadCategoryTree();
    loadGeographies();
    loadMedia();
  }, []);

  // Update Status
  const handleUpdateStatus = async (id: number, status: 'active' | 'pending' | 'suspended') => {
    if (!hasPermission) {
      saveAuditLog({
        actorRole: activeRole,
        actorName: 'Current Active Admin',
        action: 'status change',
        resource: `Service #${id}`,
        detailsEn: `Unauthorized attempt by ${activeRole} to change status to ${status}.`,
        detailsAr: `محاولة غير مصرح بها من ${activeRole} لتغيير حالة الخدمة إلى ${status}.`,
        status: 'denied'
      });
      alert(language === 'en' ? "Access Denied: Read-Only or Unauthorized role." : "تم رفض الوصول: دورك غير مصرح له.");
      return;
    }
    try {
      const original = services.find(s => s.id === id);
      const updated = await LaravelAPI.updateServiceStatus(id, status);
      setServices(services.map(s => s.id === id ? updated : s));
      if (selectedService && selectedService.id === id) {
        setSelectedService(updated);
      }
      
      // Save Audit Log
      saveAuditLog({
        actorRole: activeRole,
        actorName: 'Current Active Admin',
        action: 'status change',
        resource: `Service #${id} (${updated.title_en})`,
        detailsEn: `Service status updated to '${status}' successfully.`,
        detailsAr: `تم تحديث حالة الخدمة إلى '${status === 'active' ? 'نشط' : status === 'pending' ? 'معلق' : 'موقوف'}' بنجاح.`,
        status: 'success'
      });

      if (original) {
        saveChangeRecord({
          resource: `Service #${id} (${updated.title_en})`,
          actorName: 'Current Active Admin',
          field: 'Status',
          oldValue: original.status.toUpperCase(),
          newValue: status.toUpperCase()
        });
      }

      showSuccess(language === 'en' ? `Service status updated to ${status}!` : `تم تعديل حالة الخدمة بنجاح!`);
    } catch (err: any) {
      console.error(err);
      alert(err?.message || (language === 'en' ? 'Failed to update service status' : 'فشل تحديث حالة الخدمة'));
    }
  };

  // Delete Service
  const handleDeleteService = async (id: number) => {
    if (!hasPermission) {
      saveAuditLog({
        actorRole: activeRole,
        actorName: 'Current Active Admin',
        action: 'delete',
        resource: `Service #${id}`,
        detailsEn: `Unauthorized attempt by ${activeRole} to delete service permanently.`,
        detailsAr: `محاولة غير مصرح بها من ${activeRole} لحذف الخدمة بشكل نهائي.`,
        status: 'denied'
      });
      alert(language === 'en' ? "Access Denied." : "تم رفض الإجراء.");
      return;
    }
    const original = services.find(s => s.id === id);
    const confirmed = window.confirm(
      language === 'en' 
        ? "Are you sure you want to delete this service permanently from the Laravel repository?" 
        : "هل أنت متأكد من حذف هذه الخدمة نهائياً من سجلات الخادم؟"
    );
    if (!confirmed) return;

    await LaravelAPI.deleteService(id);
    setServices(services.filter(s => s.id !== id));
    
    // Save Audit Log
    saveAuditLog({
      actorRole: activeRole,
      actorName: 'Current Active Admin',
      action: 'delete',
      resource: `Service #${id} ${original ? `(${original.title_en})` : ''}`,
      detailsEn: `Permanently deleted service from repository listing.`,
      detailsAr: `حذف الخدمة نهائياً من قائمة النظام والواجهة الأمامية.`,
      status: 'success'
    });

    showSuccess(language === 'en' ? "Service deleted successfully!" : "تم حذف الخدمة ومزامنة قاعدة البيانات!");
    setSelectedService(null);
  };

  // Bulk Operations
  const handleBulkStatusChange = async (status: 'active' | 'suspended') => {
    if (!hasPermission) {
      alert(language === 'en' ? "Access Denied." : "تم رفض الإجراء.");
      return;
    }
    if (selectedServiceIds.length === 0) return;

    await LaravelAPI.bulkUpdateServices(selectedServiceIds, status);

    // Log Audit
    saveAuditLog({
      actorRole: activeRole,
      actorName: 'Current Active Admin',
      action: 'status change',
      resource: `${selectedServiceIds.length} Services (Bulk)`,
      detailsEn: `Bulk updated status of services [${selectedServiceIds.join(', ')}] to ${status}.`,
      detailsAr: `تحديث جماعي لحالة الخدمات [${selectedServiceIds.join(', ')}] إلى ${status === 'active' ? 'نشط' : 'موقوف'}.`,
      status: 'success'
    });

    setServices(services.map(s => selectedServiceIds.includes(s.id) ? { ...s, status } : s));
    setSelectedServiceIds([]);
    showSuccess(
      language === 'en' 
        ? `Successfully updated ${selectedServiceIds.length} services to ${status}.` 
        : `تم بنجاح تحديث حالة ${selectedServiceIds.length} خدمات إلى وضع ${status === 'active' ? 'نشط' : 'موقوف'}.`
    );
  };

  const handleBulkDelete = async () => {
    if (!hasPermission) {
      alert(language === 'en' ? "Access Denied." : "تم رفض الإجراء.");
      return;
    }
    if (selectedServiceIds.length === 0) return;

    const confirmed = window.confirm(
      language === 'en'
        ? `Are you sure you want to delete ${selectedServiceIds.length} services permanently?`
        : `هل أنت متأكد من حذف ${selectedServiceIds.length} خدمات نهائياً؟`
    );
    if (!confirmed) return;

    // Log Audit
    saveAuditLog({
      actorRole: activeRole,
      actorName: 'Current Active Admin',
      action: 'delete',
      resource: `${selectedServiceIds.length} Services (Bulk)`,
      detailsEn: `Bulk deleted services with IDs: [${selectedServiceIds.join(', ')}].`,
      detailsAr: `حذف جماعي نهائي للخدمات ذات المعرفات: [${selectedServiceIds.join(', ')}].`,
      status: 'success'
    });

    await LaravelAPI.bulkDeleteServices(selectedServiceIds);
    setServices(services.filter(s => !selectedServiceIds.includes(s.id)));
    setSelectedServiceIds([]);
    showSuccess(language === 'en' ? "Selected services deleted!" : "تم حذف الخدمات المحددة بنجاح!");
  };

  // Create or Update Form Actions
  const handleOpenCreateForm = () => {
    setFormMode('create');
    setValidationError('');
    setFormTitleEn('');
    setFormTitleAr('');
    const defaultCatId = categoryOptions[0]?.id ?? '';
    setFormCategoryId(defaultCatId);
    const defaultCatName = categoryOptions[0]?.name ?? '';
    setFormCategoryEn(defaultCatName);
    setFormCategoryAr(defaultCatName);
    setFormSubcategoryId('');
    setFormSellerId(sellers[0]?.id ?? '');
    setFormPrice(450);
    setFormDuration('1 day');
    setFormDescEn('');
    setFormDescAr('');
    setFormImageFile(null);
    setFormImagePreview('');
    setFormSelectedMediaId(null);
  };

  const handleOpenEditForm = (service: Service) => {
    setFormMode('edit');
    setEditingServiceId(service.id);
    setValidationError('');
    setFormTitleEn(service.title_en);
    setFormTitleAr(service.title_ar);
    const catId = service.category_id || categoryOptions.find(c => c.name === service.category_en)?.id || '';
    setFormCategoryId(catId);
    setFormCategoryEn(service.category_en);
    setFormCategoryAr(service.category_ar);
    setFormSubcategoryId(service.subcategory_id || '');
    setFormSellerId(service.seller_id);
    setFormPrice(service.price);
    setFormDuration(service.duration);
    setFormDescEn(service.description_en || '');
    setFormDescAr(service.description_ar || '');
    setFormImageFile(null);
    setFormImagePreview(service.image_url || '');
    setFormSelectedMediaId(typeof service.image === 'number' ? service.image : null);
  };

  const handleSaveForm = async (e: React.FormEvent) => {
    e.preventDefault();
    setValidationError('');

    if (!formTitleEn.trim() || !formTitleAr.trim() || !formDescEn.trim() || !formDescAr.trim()) {
      setValidationError(language === 'en' ? "Please fill in all bilingual Arabic & English fields." : "يرجى ملء كافة الحقول الثنائية باللغتين العربية والإنجليزية.");
      return;
    }

    if (formPrice <= 0) {
      setValidationError(language === 'en' ? "Price must be greater than zero." : "يجب أن يكون السعر أكبر من صفر.");
      return;
    }

    if (!formCategoryId) {
      setValidationError(language === 'en' ? "Please select a category." : "يرجى اختيار تصنيف الخدمة.");
      return;
    }

    if (!formSellerId) {
      setValidationError(language === 'en' ? "Please select a service provider (seller)." : "يرجى اختيار مزود الخدمة (البائع).");
      return;
    }

    setLoading(true);
    try {
      if (formMode === 'create') {
        const payload = {
          title_en: formTitleEn,
          title_ar: formTitleAr,
          category_id: Number(formCategoryId),
          subcategory_id: formSubcategoryId ? Number(formSubcategoryId) : undefined,
          category_en: formCategoryEn,
          category_ar: formCategoryAr,
          price: formPrice,
          duration: formDuration,
          status: 'active' as const,
          seller_id: Number(formSellerId),
          description_en: formDescEn,
          description_ar: formDescAr,
          imageFile: formImageFile || undefined,
          image: formSelectedMediaId || undefined,
        };
        const created = await LaravelAPI.createService(payload);
        setServices([created, ...services]);
        showSuccess(language === 'en' ? "Service created and synced with Laravel database!" : "تم إنشاء الخدمة ومزامنتها مع قاعدة البيانات بنجاح!");
      } else if (formMode === 'edit' && editingServiceId) {
        const originalService = services.find(s => s.id === editingServiceId);
        const updatedService = await LaravelAPI.updateService(editingServiceId, {
          title_en: formTitleEn,
          title_ar: formTitleAr,
          category_id: Number(formCategoryId),
          subcategory_id: formSubcategoryId ? Number(formSubcategoryId) : undefined,
          category_en: formCategoryEn,
          category_ar: formCategoryAr,
          price: formPrice,
          duration: formDuration,
          description_en: formDescEn,
          description_ar: formDescAr,
          seller_id: Number(formSellerId),
          status: originalService?.status ?? 'active',
          imageFile: formImageFile || undefined,
          image: formSelectedMediaId || originalService?.image,
        });
        setServices(services.map(s => s.id === editingServiceId ? updatedService : s));
        showSuccess(language === 'en' ? "Service updated and synced with Laravel database!" : "تم تحديث بيانات الخدمة وحفظ التغييرات بالكامل!");
      }
      setFormMode('list');
    } catch (err: any) {
      console.error(err);
      setValidationError(err?.message || (language === 'en' ? "Failed to save service" : "فشل حفظ الخدمة"));
    } finally {
      setLoading(false);
    }
  };

  // Helper success trigger
  const showSuccess = (msg: string) => {
    setActionSuccess(msg);
    setTimeout(() => setActionSuccess(''), 4000);
  };

  // Copy a real asset URL if the backend supplied one.
  const handleCopyLink = (file: MediaFile) => {
    navigator.clipboard.writeText(file.url || file.name);
    setCopiedId(file.id);
    setTimeout(() => setCopiedId(null), 2000);
  };

  // Real upload handler for Media Vault
  const handleUploadMediaAsset = async (file: File) => {
    if (!hasPermission) return;
    setUploadProgress(0);
    try {
      const item = await LaravelAPI.uploadMedia(file, (percent) => {
        setUploadProgress(percent);
      });
      const newAsset: MediaFile = {
        id: item.id,
        name: item.slug || item.title_en || file.name,
        size: item.size || `${(file.size / 1024).toFixed(1)} KB`,
        type: 'image',
        url: item.image || item.url || '',
        uploadedAt: 'Just now',
        dimensions: item.dimensions || ''
      };
      setMediaFiles(prev => [newAsset, ...prev]);
      showSuccess(language === 'en' ? "Media asset uploaded successfully!" : "تم رفع أصل الوسائط بنجاح!");
    } catch (err: any) {
      console.error(err);
      alert(err?.message || (language === 'en' ? "Failed to upload asset" : "فشل رفع أصل الوسائط"));
    } finally {
      setUploadProgress(null);
    }
  };

  // Real delete handler for Media Vault
  const handleDeleteMediaAsset = async (fileId: number) => {
    if (!hasPermission) return;
    if (!window.confirm(language === 'en' ? "Delete this asset permanently?" : "هل أنت متأكد من حذف هذا الملف نهائياً؟")) return;
    try {
      await LaravelAPI.deleteMedia(fileId);
      setMediaFiles(prev => prev.filter(m => m.id !== fileId));
      showSuccess(language === 'en' ? "Asset removed permanently." : "تم حذف أصل الملف بنجاح.");
    } catch (err: any) {
      console.error(err);
      alert(err?.message || (language === 'en' ? "Failed to delete asset" : "فشل حذف الملف"));
    }
  };

  // Category Tree add node handler
  const handleAddCategoryNode = async (e: React.FormEvent) => {
    e.preventDefault();
    setValidationError('');
    if (!newCatEn.trim() || !newCatAr.trim()) {
      setValidationError(language === 'en' ? 'Category names in Arabic and English are required.' : 'يرجى إدخال اسم القسم بالعربية والإنجليزية.');
      return;
    }

    try {
      const parentId = catLevel === 'sub' ? selectedParentCatId : catLevel === 'child' ? selectedParentSubId : undefined;
      await LaravelAPI.createCategory({
        level: catLevel,
        name_en: newCatEn,
        name_ar: newCatAr,
        slug: newCatSlug,
        parent_id: parentId
      });

      await loadCategoryTree();
      setShowAddCategoryModal(false);
      setNewCatEn('');
      setNewCatAr('');
      setNewCatSlug('');
      showSuccess(language === 'en' ? 'Category created and persisted to database!' : 'تم إنشاء التصنيف وحفظه في قاعدة البيانات بنجاح!');
    } catch (err: any) {
      console.error(err);
      setValidationError(err?.message || 'Failed to create category');
    }
  };

  // Add Geography Node Handler
  const handleAddGeoNode = async (e: React.FormEvent) => {
    e.preventDefault();
    setValidationError('');
    if (!geoNameEn.trim() || !geoNameAr.trim()) {
      setValidationError(language === 'en' ? 'Names in Arabic and English are required.' : 'يرجى إدخال الأسماء بالعربية والإنجليزية.');
      return;
    }

    try {
      const level = geoTab === 'countries' ? 'country' : geoTab === 'cities' ? 'city' : 'area';
      await LaravelAPI.createLocation({
        level,
        name_en: geoNameEn,
        name_ar: geoNameAr,
        country_id: geoTab === 'cities' ? geoParentId : undefined,
        city_id: geoTab === 'areas' ? Number(geoParentId) : undefined,
        phone_code: geoTab === 'countries' ? geoParam1 : undefined,
        currency: geoTab === 'countries' ? geoParam2 : undefined,
        code: geoTab === 'countries' ? (geoParam1 || geoNameEn.slice(0, 2).toUpperCase()) : undefined
      });

      await loadGeographies();
      setShowAddGeoModal(false);
      setGeoNameEn('');
      setGeoNameAr('');
      setGeoParam1('');
      setGeoParam2('');
      showSuccess(language === 'en' ? 'Location created and persisted to database!' : 'تمت إضافة المنطقة وحفظها في قاعدة البيانات بنجاح!');
    } catch (err: any) {
      console.error(err);
      setValidationError(err?.message || 'Failed to create location');
    }
  };

  // Delete category node helper
  const handleDeleteCategoryNode = async (id: number, level: 'parent' | 'sub' | 'child') => {
    if (!hasPermission) {
      alert(language === 'en' ? "Access Denied." : "تم رفض الوصول.");
      return;
    }
    const confirmDel = window.confirm(language === 'en' ? `Are you sure you want to delete this category?` : `هل أنت متأكد من حذف هذا القسم؟`);
    if (!confirmDel) return;

    try {
      await LaravelAPI.deleteCategory(id, level);
      await loadCategoryTree();
      showSuccess(language === 'en' ? 'Category deleted successfully!' : 'تم حذف القسم بنجاح!');
    } catch (err: any) {
      console.error(err);
      setValidationError(err?.message || 'Failed to delete category (it may have dependent services or subcategories).');
    }
  };

  // Toggle Geo entry status
  const toggleGeoStatus = async (id: string | number, level: 'country' | 'city' | 'area') => {
    if (!hasPermission) return;
    try {
      let currentStatus: 'active' | 'inactive' = 'active';
      if (level === 'country') {
        const item = countries.find(c => c.id === id);
        currentStatus = item?.status ?? 'active';
      } else if (level === 'city') {
        const item = cities.find(c => c.id === id);
        currentStatus = item?.status ?? 'active';
      } else {
        const item = areas.find(a => a.id === id);
        currentStatus = item?.status ?? 'active';
      }
      const nextStatus = currentStatus === 'active' ? 'inactive' : 'active';
      await LaravelAPI.updateLocationStatus(id, level, nextStatus);
      await loadGeographies();
      showSuccess(language === 'en' ? `Location status changed to ${nextStatus}!` : `تم تحديث حالة المنطقة بنجاح!`);
    } catch (err: any) {
      console.error(err);
      setValidationError(err?.message || 'Failed to update location status');
    }
  };

  // Checkbox toggle
  const toggleServiceCheckbox = (id: number) => {
    if (selectedServiceIds.includes(id)) {
      setSelectedServiceIds(selectedServiceIds.filter(item => item !== id));
    } else {
      setSelectedServiceIds([...selectedServiceIds, id]);
    }
  };

  const toggleAllServices = () => {
    const visibleServiceIds = filteredServicesList.map(s => s.id);
    const allSelected = visibleServiceIds.every(id => selectedServiceIds.includes(id));

    if (allSelected) {
      setSelectedServiceIds(selectedServiceIds.filter(id => !visibleServiceIds.includes(id)));
    } else {
      // Add only non-duplicates
      const merged = Array.from(new Set([...selectedServiceIds, ...visibleServiceIds]));
      setSelectedServiceIds(merged);
    }
  };

  // Filter lists based on search
  const filteredServicesList = services.filter(service => {
    const title = language === 'en' ? service.title_en : service.title_ar;
    const cat = language === 'en' ? service.category_en : service.category_ar;
    const matchesSearch =
      title.toLowerCase().includes(search.toLowerCase()) ||
      cat.toLowerCase().includes(search.toLowerCase()) ||
      service.seller_name.toLowerCase().includes(search.toLowerCase());

    const matchesStatus = statusFilter === 'all' || service.status === statusFilter;
    return matchesSearch && matchesStatus;
  });

  const filteredMedia = mediaFiles.filter(file => {
    const matchesSearch = file.name.toLowerCase().includes(mediaSearch.toLowerCase());
    const matchesType = mediaTypeFilter === 'all' || file.type === mediaTypeFilter;
    return matchesSearch && matchesType;
  });

  return (
    <div className="space-y-6">
      {/* View Header with Sub-Tabs */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 pb-4">
        <div>
          <h2 className="text-xl font-bold tracking-tight text-slate-800">
            {language === 'en' ? 'Catalog & Service Control' : 'مراقبة الخدمات وهيكلة السوق'}
          </h2>
          <p className="text-xs text-slate-500">
            {language === 'en' 
              ? 'Configure marketplace categories, regional availability, asset libraries, and approve listings.' 
              : 'هندسة التصنيفات، توزيع المناطق الجغرافية، إدارة مكتبة الميديا، والتحقق والموافقة على العروض.'}
          </p>
        </div>

        {/* Dynamic Top Tabs */}
        <div className="flex flex-wrap items-center gap-1.5 rounded-xl bg-slate-100 p-1 text-xs font-semibold self-start sm:self-center">
          <button
            onClick={() => { setActiveSubTab('services'); setFormMode('list'); }}
            className={`rounded-lg px-3 py-1.5 transition ${
              activeSubTab === 'services' ? 'bg-white text-slate-800 shadow-xs' : 'text-slate-500 hover:text-slate-800'
            }`}
          >
            {language === 'en' ? 'Services Directory' : 'دليل الخدمات'}
          </button>
          <button
            onClick={() => setActiveSubTab('categories')}
            className={`rounded-lg px-3 py-1.5 transition ${
              activeSubTab === 'categories' ? 'bg-white text-slate-800 shadow-xs' : 'text-slate-500 hover:text-slate-800'
            }`}
          >
            {language === 'en' ? 'Categories Tree' : 'شجرة التصنيفات'}
          </button>
          <button
            onClick={() => setActiveSubTab('locations')}
            className={`rounded-lg px-3 py-1.5 transition ${
              activeSubTab === 'locations' ? 'bg-white text-slate-800 shadow-xs' : 'text-slate-500 hover:text-slate-800'
            }`}
          >
            {language === 'en' ? 'Geographies' : 'المناطق والبلدان'}
          </button>
          <button
            onClick={() => setActiveSubTab('media')}
            className={`rounded-lg px-3 py-1.5 transition ${
              activeSubTab === 'media' ? 'bg-white text-slate-800 shadow-xs' : 'text-slate-500 hover:text-slate-800'
            }`}
          >
            {language === 'en' ? 'Media Vault' : 'مستودع الوسائط'}
          </button>
        </div>
      </div>

      {/* Role Restriction warning */}
      {!hasPermission && (
        <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs text-amber-800 flex items-start gap-2.5">
          <AlertTriangle className="h-4.5 w-4.5 text-amber-600 shrink-0" />
          <div>
            <p className="font-bold">{language === 'en' ? 'Limited Role Permissions (Read-Only Mode)' : 'صلاحيات محدودة لهذا الحساب (عرض فقط)'}</p>
            <p className="mt-1 leading-relaxed text-amber-700">
              {language === 'en'
                ? `You are logged in as a ${activeRole.replace('_', ' ')}. You can inspect directory records, but publishing or removing database configurations is locked.`
                : `لقد سجلت الدخول بصلاحيات محاكاة. يمكنك معاينة المدخلات، ولكن لا تملك صلاحية النشر الفوري للأقسام أو حذف البنية التحتية.`}
            </p>
          </div>
        </div>
      )}

      {/* Action successes */}
      {actionSuccess && (
        <div className="rounded-xl bg-emerald-50 border border-emerald-100 p-3 text-xs text-emerald-800 font-semibold animate-fade-in flex items-center gap-1.5">
          <CheckCircle2 className="h-4 w-4 text-emerald-600" />
          <span>{actionSuccess}</span>
        </div>
      )}

      {/* ======================= SUB TAB 1: SERVICES DIRECTORY ======================= */}
      {activeSubTab === 'services' && (
        <div className="space-y-6">
          {formMode === 'list' ? (
            <>
              {/* Search, Filter & Bulk controls */}
              <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between rounded-xl border border-slate-200 bg-white p-4">
                <div className="relative flex-1">
                  <Search className="absolute top-2.5 left-3 h-4 w-4 text-slate-400 rtl:right-3 rtl:left-auto" />
                  <input
                    type="text"
                    placeholder={t.searchPlaceholder}
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    className="w-full rounded-lg border border-slate-200 bg-slate-50 py-2 pl-9 pr-3 text-xs outline-hidden focus:border-indigo-500 focus:bg-white rtl:pr-9 rtl:pl-3"
                  />
                </div>

                <div className="flex flex-wrap items-center gap-2">
                  <span className="text-xs text-slate-400 font-semibold">{t.filter}:</span>
                  <button
                    onClick={() => setStatusFilter('all')}
                    className={`rounded-lg px-2.5 py-1 text-xs font-semibold border ${
                      statusFilter === 'all' ? 'border-indigo-200 bg-indigo-50 text-indigo-600' : 'border-slate-200 bg-slate-50 text-slate-600'
                    }`}
                  >
                    {t.all}
                  </button>
                  <button
                    onClick={() => setStatusFilter('active')}
                    className={`rounded-lg px-2.5 py-1 text-xs font-semibold border ${
                      statusFilter === 'active' ? 'border-indigo-200 bg-indigo-50 text-indigo-600' : 'border-slate-200 bg-slate-50 text-slate-600'
                    }`}
                  >
                    {t.status_active}
                  </button>
                  <button
                    onClick={() => setStatusFilter('pending')}
                    className={`rounded-lg px-2.5 py-1 text-xs font-semibold border ${
                      statusFilter === 'pending' ? 'border-indigo-200 bg-indigo-50 text-indigo-600' : 'border-slate-200 bg-slate-50 text-slate-600'
                    }`}
                  >
                    {t.status_pending}
                  </button>
                  {hasPermission && (
                    <button
                      onClick={handleOpenCreateForm}
                      className="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-indigo-700 flex items-center gap-1.5 rtl:mr-auto"
                    >
                      <Plus className="h-3.5 w-3.5" />
                      <span>{language === 'en' ? 'Add Service' : 'إضافة خدمة جديدة'}</span>
                    </button>
                  )}
                </div>
              </div>

              {/* Bulk Actions Console */}
              {selectedServiceIds.length > 0 && (
                <div className="rounded-xl border border-indigo-100 bg-indigo-50/40 p-3.5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between animate-fade-in text-xs">
                  <div className="font-semibold text-indigo-900">
                    {language === 'en' 
                      ? `${selectedServiceIds.length} services selected for bulk operations`
                      : `تم اختيار عدد ${selectedServiceIds.length} خدمات لإجراءات الدفعة`}
                  </div>
                  <div className="flex items-center gap-2">
                    <button
                      onClick={() => handleBulkStatusChange('active')}
                      className="rounded bg-emerald-600 px-3 py-1.5 font-bold text-white hover:bg-emerald-700"
                    >
                      {language === 'en' ? 'Approve Selected' : 'الموافقة على المحدد'}
                    </button>
                    <button
                      onClick={() => handleBulkStatusChange('suspended')}
                      className="rounded bg-amber-600 px-3 py-1.5 font-bold text-white hover:bg-amber-700"
                    >
                      {language === 'en' ? 'Suspend Selected' : 'إيقاف المحدد مؤقتاً'}
                    </button>
                    <button
                      onClick={handleBulkDelete}
                      className="rounded bg-rose-600 px-3 py-1.5 font-bold text-white hover:bg-rose-700"
                    >
                      {language === 'en' ? 'Delete Selected' : 'حذف المحدد نهائياً'}
                    </button>
                    <button
                      onClick={() => setSelectedServiceIds([])}
                      className="rounded border border-slate-200 bg-white px-3 py-1.5 font-semibold text-slate-600 hover:bg-slate-50"
                    >
                      {language === 'en' ? 'Clear selection' : 'إلغاء التحديد'}
                    </button>
                  </div>
                </div>
              )}

              {/* Table Card */}
              <div className="overflow-hidden rounded-2xl border border-slate-200/60 bg-white shadow-sm">
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-xs text-slate-500 rtl:text-right">
                    <thead className="bg-slate-50/70 text-[10px] font-bold tracking-wider text-slate-500 uppercase border-b border-slate-100">
                      <tr>
                        <th className="px-4 py-4 text-center w-12">
                          <input
                            type="checkbox"
                            checked={filteredServicesList.length > 0 && filteredServicesList.every(s => selectedServiceIds.includes(s.id))}
                            onChange={toggleAllServices}
                            className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 h-3.5 w-3.5"
                          />
                        </th>
                        <th className="px-6 py-4">ID</th>
                        <th className="px-6 py-4">{t.col_service_title}</th>
                        <th className="px-6 py-4">{t.col_category}</th>
                        <th className="px-6 py-4">{t.col_seller}</th>
                        <th className="px-6 py-4">{t.col_price}</th>
                        <th className="px-6 py-4">{t.col_rating}</th>
                        <th className="px-6 py-4">{t.status}</th>
                        <th className="px-6 py-4 text-center">{t.actions}</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100 font-medium text-slate-700">
                      {loading ? (
                        <tr>
                          <td colSpan={9} className="py-10 text-center text-slate-400">
                            <RefreshCcw className="mx-auto h-5 w-5 animate-spin mb-2" />
                            <span>{t.loading}</span>
                          </td>
                        </tr>
                      ) : filteredServicesList.length === 0 ? (
                        <tr>
                          <td colSpan={9} className="py-10 text-center text-slate-400">
                            {language === 'en' ? 'No services found match criteria.' : 'لا توجد خدمات تطابق خيارات التصفية الحالية.'}
                          </td>
                        </tr>
                      ) : (
                        filteredServicesList.map((service) => (
                          <tr key={service.id} className="hover:bg-slate-50/50 transition-colors">
                            <td className="px-4 py-4 text-center">
                              <input
                                type="checkbox"
                                checked={selectedServiceIds.includes(service.id)}
                                onChange={() => toggleServiceCheckbox(service.id)}
                                className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 h-3.5 w-3.5"
                              />
                            </td>
                            <td className="px-6 py-4 text-slate-400 font-bold">#{service.id}</td>
                            <td className="px-6 py-4 max-w-[200px]">
                              <div className="flex items-center gap-2.5">
                                {service.image_url ? (
                                  <img src={service.image_url} alt="" className="w-8 h-8 rounded-md object-cover border border-slate-200 shrink-0" />
                                ) : (
                                  <div className="w-8 h-8 rounded-md bg-slate-100 flex items-center justify-center text-slate-400 shrink-0">
                                    <ImageIcon className="w-4 h-4" />
                                  </div>
                                )}
                                <div>
                                  <div className="font-bold text-slate-800 line-clamp-1">
                                    {language === 'en' ? service.title_en : service.title_ar}
                                  </div>
                                  <div className="text-[10px] text-slate-400 mt-0.5">{service.duration}</div>
                                </div>
                              </div>
                            </td>
                            <td className="px-6 py-4 text-slate-500">
                              {language === 'en' ? service.category_en : service.category_ar}
                            </td>
                            <td className="px-6 py-4 font-semibold text-slate-800">{service.seller_name}</td>
                            <td className="px-6 py-4 text-slate-900 font-bold">{service.price} SAR</td>
                            <td className="px-6 py-4">★ {service.rating || '—'}</td>
                            <td className="px-6 py-4">
                              <span className={`inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[10px] font-bold ${
                                service.status === 'active'
                                  ? 'bg-emerald-50 text-emerald-700'
                                  : service.status === 'pending'
                                  ? 'bg-amber-50 text-amber-700'
                                  : 'bg-rose-50 text-rose-700'
                              }`}>
                                {service.status === 'active' ? t.status_active : service.status === 'pending' ? t.status_pending : t.status_suspended}
                              </span>
                            </td>
                            <td className="px-6 py-4">
                              <div className="flex items-center justify-center gap-1">
                                {hasPermission && service.status === 'pending' && (
                                  <button
                                    onClick={() => handleUpdateStatus(service.id, 'active')}
                                    className="rounded p-1 bg-emerald-50 text-emerald-600 hover:bg-emerald-100 transition"
                                    title={t.btn_approve || 'Approve'}
                                  >
                                    <Check className="h-4 w-4" />
                                  </button>
                                )}
                                <button
                                  onClick={() => setSelectedService(service)}
                                  className="rounded p-1 text-slate-500 hover:bg-slate-100 transition"
                                  title={t.view}
                                >
                                  <Eye className="h-4 w-4" />
                                </button>
                                {hasPermission && (
                                  <>
                                    <button
                                      onClick={() => handleOpenEditForm(service)}
                                      className="rounded p-1 text-indigo-600 hover:bg-indigo-50 transition"
                                      title={t.edit}
                                    >
                                      <Edit2 className="h-4 w-4" />
                                    </button>
                                    <button
                                      onClick={() => handleDeleteService(service.id)}
                                      className="rounded p-1 text-rose-600 hover:bg-rose-50 transition"
                                      title={t.delete}
                                    >
                                      <Trash2 className="h-4 w-4" />
                                    </button>
                                  </>
                                )}
                              </div>
                            </td>
                          </tr>
                        ))
                      )}
                    </tbody>
                  </table>
                </div>
              </div>
            </>
          ) : (
            /* Create or Edit Form screen */
            <form onSubmit={handleSaveForm} className="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs space-y-6">
              <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 className="text-sm font-bold text-slate-800">
                  {formMode === 'create' 
                    ? (language === 'en' ? 'Add New Marketplace Service' : 'إدراج خدمة جديدة بالسوق') 
                    : (language === 'en' ? `Edit Service Node #${editingServiceId}` : `تعديل مدخلات الخدمة رقم #${editingServiceId}`)}
                </h3>
                <button
                  type="button"
                  onClick={() => setFormMode('list')}
                  className="rounded bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-200"
                >
                  {t.back}
                </button>
              </div>

              {validationError && (
                <div className="rounded-lg border border-rose-100 bg-rose-50 p-3 text-xs text-rose-800 font-semibold animate-fade-in">
                  {validationError}
                </div>
              )}

              <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                {/* English Title */}
                <div>
                  <label className="text-[10px] font-bold uppercase text-slate-400 block mb-1">
                    {language === 'en' ? 'Service Title (English)' : 'اسم الخدمة (بالإنجليزية)'} *
                  </label>
                  <input
                    type="text"
                    value={formTitleEn}
                    onChange={(e) => setFormTitleEn(e.target.value)}
                    className="w-full rounded-lg border border-slate-200 px-3.5 py-2 text-xs outline-hidden focus:border-indigo-500"
                    placeholder="e.g., Yacht Charter with Private Chef"
                  />
                </div>

                {/* Arabic Title */}
                <div>
                  <label className="text-[10px] font-bold uppercase text-slate-400 block mb-1">
                    {language === 'en' ? 'Service Title (Arabic)' : 'اسم الخدمة (بالعربية)'} *
                  </label>
                  <input
                    type="text"
                    value={formTitleAr}
                    onChange={(e) => setFormTitleAr(e.target.value)}
                    className="w-full rounded-lg border border-slate-200 px-3.5 py-2 text-xs outline-hidden focus:border-indigo-500 text-right"
                    placeholder="مثال: حجز يخت خاص مع وجبة طعام مجهزة"
                  />
                </div>

                {/* Category Selection */}
                <div>
                  <label className="text-[10px] font-bold uppercase text-slate-400 block mb-1">
                    {t.col_category} *
                  </label>
                  <select
                    value={formCategoryId}
                    onChange={(e) => {
                      const id = Number(e.target.value);
                      setFormCategoryId(id);
                      const cat = categoryOptions.find(c => c.id === id);
                      if (cat) {
                        setFormCategoryEn(cat.name);
                        setFormCategoryAr(cat.name);
                      }
                      setFormSubcategoryId('');
                    }}
                    className="w-full rounded-lg border border-slate-200 px-3.5 py-2 text-xs outline-hidden focus:border-indigo-500 bg-white"
                    required
                  >
                    <option value="">{language === 'en' ? '— Select Real Category —' : '— اختر التصنيف —'}</option>
                    {categoryOptions.map((cat) => (
                      <option key={cat.id} value={cat.id}>
                        {cat.name} (ID: #{cat.id})
                      </option>
                    ))}
                  </select>
                </div>

                {/* Subcategory Selection */}
                <div>
                  <label className="text-[10px] font-bold uppercase text-slate-400 block mb-1">
                    {language === 'en' ? 'Subcategory (Optional)' : 'القسم الفرعي (اختياري)'}
                  </label>
                  <select
                    value={formSubcategoryId}
                    onChange={(e) => setFormSubcategoryId(e.target.value ? Number(e.target.value) : '')}
                    className="w-full rounded-lg border border-slate-200 px-3.5 py-2 text-xs outline-hidden focus:border-indigo-500 bg-white"
                  >
                    <option value="">{language === 'en' ? '— None / Main Category —' : '— بدون قسم فرعي —'}</option>
                    {categoryOptions
                      .find((c) => c.id === Number(formCategoryId))
                      ?.subcategories?.map((sub: any) => (
                        <option key={sub.id} value={sub.id}>
                          {sub.name} (ID: #{sub.id})
                        </option>
                      ))}
                  </select>
                </div>

                {/* Service Provider (Seller) Selection */}
                <div>
                  <label className="text-[10px] font-bold uppercase text-slate-400 block mb-1">
                    {language === 'en' ? 'Service Provider (Seller)' : 'مزود الخدمة (البائع)'} *
                  </label>
                  <select
                    value={formSellerId}
                    onChange={(e) => setFormSellerId(e.target.value ? Number(e.target.value) : '')}
                    className="w-full rounded-lg border border-slate-200 px-3.5 py-2 text-xs outline-hidden focus:border-indigo-500 bg-white"
                    required
                  >
                    <option value="">{language === 'en' ? '— Select Real Provider —' : '— اختر مزود الخدمة —'}</option>
                    {sellers.map((s) => (
                      <option key={s.id} value={s.id}>
                        {s.name} ({s.email}) — ID #{s.id}
                      </option>
                    ))}
                  </select>
                </div>

                {/* Price in SAR */}
                <div>
                  <label className="text-[10px] font-bold uppercase text-slate-400 block mb-1">
                    {language === 'en' ? 'Price (SAR)' : 'سعر الخدمة (ريال سعودي)'} *
                  </label>
                  <input
                    type="number"
                    value={formPrice}
                    onChange={(e) => setFormPrice(parseFloat(e.target.value) || 0)}
                    className="w-full rounded-lg border border-slate-200 px-3.5 py-2 text-xs outline-hidden focus:border-indigo-500"
                  />
                </div>

                {/* Duration */}
                <div>
                  <label className="text-[10px] font-bold uppercase text-slate-400 block mb-1">
                    {language === 'en' ? 'Fulfillment Duration' : 'مدة تنفيذ وتسليم الخدمة'}
                  </label>
                  <input
                    type="text"
                    value={formDuration}
                    onChange={(e) => setFormDuration(e.target.value)}
                    className="w-full rounded-lg border border-slate-200 px-3.5 py-2 text-xs outline-hidden focus:border-indigo-500"
                    placeholder="e.g., 4 hours / 2 days"
                  />
                </div>
              </div>

              {/* Descriptions */}
              <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div>
                  <label className="text-[10px] font-bold uppercase text-slate-400 block mb-1">
                    {language === 'en' ? 'Description (English)' : 'شرح وتفاصيل الخدمة (بالإنجليزية)'} *
                  </label>
                  <textarea
                    value={formDescEn}
                    onChange={(e) => setFormDescEn(e.target.value)}
                    rows={4}
                    className="w-full rounded-lg border border-slate-200 px-3.5 py-2 text-xs outline-hidden focus:border-indigo-500"
                    placeholder="Enter full specification, inclusions and service guarantees..."
                  />
                </div>

                <div>
                  <label className="text-[10px] font-bold uppercase text-slate-400 block mb-1">
                    {language === 'en' ? 'Description (Arabic)' : 'شرح وتفاصيل الخدمة (بالعربية)'} *
                  </label>
                  <textarea
                    value={formDescAr}
                    onChange={(e) => setFormDescAr(e.target.value)}
                    rows={4}
                    className="w-full rounded-lg border border-slate-200 px-3.5 py-2 text-xs outline-hidden focus:border-indigo-500 text-right"
                    placeholder="اكتب شرح كامل للخدمة، المخرجات، والضمانات الفنية لمزود الخدمة..."
                  />
                </div>

                {/* Service Thumbnail / Main Image */}
                <div className="md:col-span-2">
                  <label className="text-[10px] font-bold uppercase text-slate-400 block mb-1">
                    {language === 'en' ? 'Service Image / Thumbnail' : 'صورة / غلاف الخدمة'}
                  </label>
                  <div className="flex flex-wrap items-center gap-4">
                    {formImagePreview ? (
                      <div className="relative w-28 h-28 rounded-lg overflow-hidden border border-slate-200 bg-slate-50 flex items-center justify-center group shadow-xs">
                        <img src={formImagePreview} alt="Preview" className="w-full h-full object-cover" />
                        <button
                          type="button"
                          onClick={() => { setFormImageFile(null); setFormImagePreview(''); setFormSelectedMediaId(null); }}
                          className="absolute inset-0 bg-black/60 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
                          title={language === 'en' ? 'Remove Image' : 'إزالة الصورة'}
                        >
                          <Trash2 className="w-5 h-5 text-rose-300" />
                        </button>
                      </div>
                    ) : (
                      <div 
                        onDragOver={(e) => { e.preventDefault(); e.stopPropagation(); }}
                        onDrop={(e) => {
                          e.preventDefault();
                          e.stopPropagation();
                          const file = e.dataTransfer.files?.[0];
                          if (file) {
                            setFormImageFile(file);
                            setFormImagePreview(URL.createObjectURL(file));
                            setFormSelectedMediaId(null);
                          }
                        }}
                        className="w-28 h-28 rounded-lg border-2 border-dashed border-slate-300 flex flex-col items-center justify-center text-slate-400 bg-slate-50/70"
                      >
                        <ImageIcon className="w-6 h-6 mb-1 text-slate-300" />
                        <span className="text-[10px]">{language === 'en' ? 'Drop or Select' : 'اسحب أو اختر'}</span>
                      </div>
                    )}

                    <div className="flex flex-col gap-2">
                      <div className="flex items-center gap-2">
                        <input
                          type="file"
                          id="service-image-upload"
                          accept="image/jpeg,image/png,image/webp,image/gif"
                          className="hidden"
                          onChange={(e) => {
                            const file = e.target.files?.[0];
                            if (file) {
                              setFormImageFile(file);
                              setFormImagePreview(URL.createObjectURL(file));
                              setFormSelectedMediaId(null);
                            }
                          }}
                        />
                        <label
                          htmlFor="service-image-upload"
                          className="cursor-pointer inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50"
                        >
                          <Upload className="w-3.5 h-3.5" />
                          {formImagePreview ? (language === 'en' ? 'Upload New File' : 'رفع ملف جديد') : (language === 'en' ? 'Upload Thumbnail' : 'رفع صورة')}
                        </label>

                        <button
                          type="button"
                          onClick={() => setShowMediaPickerModal(true)}
                          className="inline-flex items-center gap-1.5 rounded-lg border border-indigo-200 bg-indigo-50/70 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100"
                        >
                          <ImageIcon className="w-3.5 h-3.5" />
                          <span>{language === 'en' ? 'Choose from Media Library' : 'اختر من مكتبة الوسائط'}</span>
                        </button>
                      </div>
                      <p className="text-[10px] text-slate-400">
                        {language === 'en' ? 'JPG, PNG, GIF, WEBP up to 10MB' : 'يدعم JPG, PNG, GIF, WEBP حتى ١٠ ميجابايت'}
                      </p>
                      {formSelectedMediaId && (
                        <span className="text-[10px] font-mono text-indigo-600 bg-indigo-50 rounded px-1.5 py-0.5 inline-block w-fit">
                          {language === 'en' ? `Selected Asset #${formSelectedMediaId}` : `الأصل المحدد #${formSelectedMediaId}`}
                        </span>
                      )}
                    </div>
                  </div>
                </div>
              </div>

              <div className="border-t border-slate-100 pt-4 flex items-center justify-end gap-2.5">
                <button
                  type="button"
                  onClick={() => setFormMode('list')}
                  className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                >
                  {t.cancel}
                </button>
                <button
                  type="submit"
                  className="rounded-lg bg-indigo-600 px-5 py-2 text-xs font-bold text-white hover:bg-indigo-700"
                >
                  {t.save}
                </button>
              </div>
            </form>
          )}

          {/* Media Picker Modal */}
          {showMediaPickerModal && (
            <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
              <div className="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl space-y-4 max-h-[85vh] flex flex-col">
                <div className="flex items-center justify-between border-b pb-3">
                  <div className="flex items-center gap-2">
                    <ImageIcon className="h-5 w-5 text-indigo-600" />
                    <h4 className="font-bold text-slate-800 text-sm">
                      {language === 'en' ? 'Select Thumbnail from Media Vault' : 'اختيار صورة الغلاف من مكتبة الوسائط'}
                    </h4>
                  </div>
                  <button
                    type="button"
                    onClick={() => setShowMediaPickerModal(false)}
                    className="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                  >
                    ✕
                  </button>
                </div>

                <div className="overflow-y-auto flex-1 grid grid-cols-2 sm:grid-cols-3 gap-3 p-1">
                  {mediaFiles.length === 0 ? (
                    <div className="col-span-full py-8 text-center text-slate-400 text-xs">
                      {language === 'en' ? 'No media assets found in library.' : 'لم يتم العثور على وسائط في المكتبة.'}
                    </div>
                  ) : (
                    mediaFiles.map((asset) => (
                      <div
                        key={asset.id}
                        onClick={() => {
                          setFormImagePreview(asset.url);
                          setFormSelectedMediaId(asset.id);
                          setFormImageFile(null);
                          setShowMediaPickerModal(false);
                        }}
                        className="group relative rounded-xl border border-slate-200 overflow-hidden cursor-pointer hover:border-indigo-600 hover:shadow-md transition bg-slate-50"
                      >
                        <img src={asset.url} alt={asset.name} className="w-full h-24 object-cover" />
                        <div className="p-1.5 bg-white text-[9px] truncate font-medium text-slate-700">
                          {asset.name}
                        </div>
                        <span className="absolute top-1 right-1 bg-black/60 text-white rounded px-1 text-[8px] font-mono">
                          #{asset.id}
                        </span>
                      </div>
                    ))
                  )}
                </div>

                <div className="border-t pt-3 flex justify-end">
                  <button
                    type="button"
                    onClick={() => setShowMediaPickerModal(false)}
                    className="rounded-lg bg-slate-100 px-4 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-200"
                  >
                    {t.cancel}
                  </button>
                </div>
              </div>
            </div>
          )}
        </div>
      )}

      {/* ======================= SUB TAB 2: CATEGORIES TREE HIERARCHY ======================= */}
      {activeSubTab === 'categories' && (
        <div className="space-y-6">
          <div className="flex items-center justify-between">
            <div>
              <h3 className="text-sm font-bold text-slate-800">
                {language === 'en' ? 'Bilingual Catalog Hierarchy' : 'شجرة هيكلة التصنيفات المتعددة'}
              </h3>
              <p className="text-[11px] text-slate-400">
                {language === 'en' ? 'Configure Categories -> Subcategories -> Child Categories.' : 'إدارة تصنيفات السوق والمستويات الفرعية التابعة لها.'}
              </p>
            </div>
            {hasPermission && (
              <button
                onClick={() => {
                  setNewCatEn('');
                  setNewCatAr('');
                  setNewCatSlug('');
                  setCatLevel('parent');
                  setShowAddCategoryModal(true);
                }}
                className="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-indigo-700 flex items-center gap-1.5"
              >
                <Plus className="h-3.5 w-3.5" />
                <span>{language === 'en' ? 'Add Node' : 'إضافة تفريعة'}</span>
              </button>
            )}
          </div>

          {/* Interactive Nested List Canvas */}
          <div className="rounded-2xl border border-slate-200/60 bg-white p-6 shadow-xs space-y-6">
            {categories.map((cat) => (
              <div key={cat.id} className="border border-slate-100 rounded-xl p-4 bg-slate-50/50">
                {/* Level 1: Category */}
                <div className="flex items-center justify-between border-b border-slate-100 pb-2 mb-3">
                  <div className="flex items-center gap-2">
                    <Folder className="h-4.5 w-4.5 text-indigo-500" />
                    <span className="font-bold text-slate-800 text-xs">
                      {language === 'en' ? cat.nameEn : cat.nameAr}
                    </span>
                    <span className="text-[10px] text-slate-400 font-mono">/{cat.slug}</span>
                    <span className="rounded bg-indigo-50 text-[9px] text-indigo-600 font-bold px-1.5">
                      {cat.servicesCount} listings
                    </span>
                  </div>
                  {hasPermission && (
                    <div className="flex items-center gap-1">
                      <button
                        onClick={() => {
                          setSelectedParentCatId(cat.id);
                          setCatLevel('sub');
                          setNewCatEn('');
                          setNewCatAr('');
                          setNewCatSlug('');
                          setShowAddCategoryModal(true);
                        }}
                        className="text-[10px] bg-white border border-slate-200 rounded px-2 py-0.5 text-slate-600 font-bold hover:bg-slate-50"
                      >
                        + Subcategory
                      </button>
                      <button
                        onClick={() => handleDeleteCategoryNode(cat.id, 'parent')}
                        className="p-1 text-rose-500 hover:bg-rose-50 rounded"
                      >
                        <Trash2 className="h-3.5 w-3.5" />
                      </button>
                    </div>
                  )}
                </div>

                {/* Level 2: Subcategory */}
                <div className="space-y-4 pl-4 rtl:pl-0 rtl:pr-4 border-l border-dashed border-slate-200 rtl:border-l-0 rtl:border-r">
                  {cat.subcategories && cat.subcategories.length > 0 ? (
                    cat.subcategories.map((sub) => (
                      <div key={sub.id} className="bg-white rounded-lg p-3 border border-slate-100">
                        <div className="flex items-center justify-between">
                          <div className="flex items-center gap-2">
                            <span className="h-1.5 w-1.5 rounded-full bg-slate-400" />
                            <span className="font-semibold text-slate-700 text-xs">
                              {language === 'en' ? sub.nameEn : sub.nameAr}
                            </span>
                            <span className="text-[10px] text-slate-400 font-mono">/{sub.slug}</span>
                            <span className="text-[10px] bg-slate-100 text-slate-500 px-1 rounded">
                              {sub.servicesCount} items
                            </span>
                          </div>
                          {hasPermission && (
                            <div className="flex items-center gap-1">
                              <button
                                onClick={() => {
                                  setSelectedParentSubId(sub.id);
                                  setCatLevel('child');
                                  setNewCatEn('');
                                  setNewCatAr('');
                                  setNewCatSlug('');
                                  setShowAddCategoryModal(true);
                                }}
                                className="text-[9px] bg-slate-50 border rounded px-1.5 py-0.5 font-bold hover:bg-slate-100"
                              >
                                + Child Category
                              </button>
                              <button
                                onClick={() => handleDeleteCategoryNode(sub.id, 'sub')}
                                className="p-1 text-rose-500 hover:bg-rose-50 rounded"
                              >
                                <Trash2 className="h-3 w-3" />
                              </button>
                            </div>
                          )}
                        </div>

                        {/* Level 3: Child category */}
                        {sub.childCategories && sub.childCategories.length > 0 && (
                          <div className="mt-2.5 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2 pl-4 rtl:pl-0 rtl:pr-4 pt-2 border-t border-slate-50">
                            {sub.childCategories.map((child) => (
                              <div key={child.id} className="flex items-center justify-between rounded bg-slate-50 px-2 py-1.5 border border-slate-100/50">
                                <div className="min-w-0">
                                  <p className="text-[10px] font-bold text-slate-600 truncate">
                                    {language === 'en' ? child.nameEn : child.nameAr}
                                  </p>
                                  <p className="text-[8px] font-mono text-slate-400">/{child.slug}</p>
                                </div>
                                {hasPermission && (
                                  <button
                                    onClick={() => handleDeleteCategoryNode(child.id, 'child')}
                                    className="text-rose-400 hover:text-rose-600"
                                  >
                                    ✕
                                  </button>
                                )}
                              </div>
                            ))}
                          </div>
                        )}
                      </div>
                    ))
                  ) : (
                    <p className="text-[10px] text-slate-400 italic">No subcategories created yet.</p>
                  )}
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* ======================= SUB TAB 3: REGIONAL GEOGRAPHY ======================= */}
      {activeSubTab === 'locations' && (
        <div className="space-y-6">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div className="flex items-center gap-2 rounded-lg bg-slate-100 p-1 text-xs">
              <button
                onClick={() => setGeoTab('countries')}
                className={`rounded px-3 py-1 font-semibold transition ${
                  geoTab === 'countries' ? 'bg-white text-slate-800 shadow-xs' : 'text-slate-400 hover:text-slate-700'
                }`}
              >
                {language === 'en' ? 'Countries' : 'الدول والعملات'}
              </button>
              <button
                onClick={() => setGeoTab('cities')}
                className={`rounded px-3 py-1 font-semibold transition ${
                  geoTab === 'cities' ? 'bg-white text-slate-800 shadow-xs' : 'text-slate-400 hover:text-slate-700'
                }`}
              >
                {language === 'en' ? 'Cities' : 'المدن والمحافظات'}
              </button>
              <button
                onClick={() => setGeoTab('areas')}
                className={`rounded px-3 py-1 font-semibold transition ${
                  geoTab === 'areas' ? 'bg-white text-slate-800 shadow-xs' : 'text-slate-400 hover:text-slate-700'
                }`}
              >
                {language === 'en' ? 'Areas & Sectors' : 'الأحياء والمناطق'}
              </button>
            </div>

            {hasPermission && (
              <button
                onClick={() => {
                  setGeoNameEn('');
                  setGeoNameAr('');
                  setGeoParam1('');
                  setGeoParam2('');
                  setGeoParentId(
                    geoTab === 'cities' 
                      ? countries[0]?.id 
                      : cities[0]?.id.toString() || ''
                  );
                  setShowAddGeoModal(true);
                }}
                className="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-indigo-700 flex items-center gap-1.5"
              >
                <Plus className="h-3.5 w-3.5" />
                <span>
                  {geoTab === 'countries' && (language === 'en' ? 'Add Country' : 'إضافة دولة')}
                  {geoTab === 'cities' && (language === 'en' ? 'Add City' : 'إضافة مدينة')}
                  {geoTab === 'areas' && (language === 'en' ? 'Add Area' : 'إضافة حي')}
                </span>
              </button>
            )}
          </div>

          {/* Countries Table */}
          {geoTab === 'countries' && (
            <div className="overflow-hidden rounded-2xl border border-slate-200/60 bg-white">
              <table className="w-full text-left text-xs text-slate-500 rtl:text-right">
                <thead className="bg-slate-50 font-bold uppercase text-[10px] text-slate-400 border-b">
                  <tr>
                    <th className="px-6 py-3">Code</th>
                    <th className="px-6 py-3">Country Name</th>
                    <th className="px-6 py-3">Phone Code</th>
                    <th className="px-6 py-3">Currency</th>
                    <th className="px-6 py-3">Status</th>
                    <th className="px-6 py-3 text-center">Action</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 text-slate-700 font-medium">
                  {countries.map(c => (
                    <tr key={c.id} className="hover:bg-slate-50/50">
                      <td className="px-6 py-4 font-bold text-slate-500">{c.id}</td>
                      <td className="px-6 py-4 text-slate-800 font-bold">
                        {language === 'en' ? c.nameEn : c.nameAr}
                      </td>
                      <td className="px-6 py-4 font-mono">{c.phoneCode}</td>
                      <td className="px-6 py-4">{c.currency}</td>
                      <td className="px-6 py-4">
                        <span className={`rounded-full px-2 py-0.5 text-[10px] font-bold ${
                          c.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'
                        }`}>
                          {c.status === 'active' ? t.status_active : t.status_suspended}
                        </span>
                      </td>
                      <td className="px-6 py-4 text-center">
                        <button
                          onClick={() => toggleGeoStatus(c.id, 'country')}
                          className="text-[10px] text-indigo-600 hover:underline"
                        >
                          {c.status === 'active' ? 'Deactivate' : 'Activate'}
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          {/* Cities Table */}
          {geoTab === 'cities' && (
            <div className="overflow-hidden rounded-2xl border border-slate-200/60 bg-white">
              <table className="w-full text-left text-xs text-slate-500 rtl:text-right">
                <thead className="bg-slate-50 font-bold uppercase text-[10px] text-slate-400 border-b">
                  <tr>
                    <th className="px-6 py-3">City ID</th>
                    <th className="px-6 py-3">Country</th>
                    <th className="px-6 py-3">City Name</th>
                    <th className="px-6 py-3">Status</th>
                    <th className="px-6 py-3 text-center">Action</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 text-slate-700 font-medium">
                  {cities.map(city => (
                    <tr key={city.id} className="hover:bg-slate-50/50">
                      <td className="px-6 py-4 text-slate-400">#{city.id}</td>
                      <td className="px-6 py-4 font-bold text-slate-500">{city.countryId}</td>
                      <td className="px-6 py-4 text-slate-800 font-bold">
                        {language === 'en' ? city.nameEn : city.nameAr}
                      </td>
                      <td className="px-6 py-4">
                        <span className={`rounded-full px-2 py-0.5 text-[10px] font-bold ${
                          city.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'
                        }`}>
                          {city.status === 'active' ? t.status_active : t.status_suspended}
                        </span>
                      </td>
                      <td className="px-6 py-4 text-center">
                        <button
                          onClick={() => toggleGeoStatus(city.id, 'city')}
                          className="text-[10px] text-indigo-600 hover:underline"
                        >
                          {city.status === 'active' ? 'Deactivate' : 'Activate'}
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          {/* Areas Table */}
          {geoTab === 'areas' && (
            <div className="overflow-hidden rounded-2xl border border-slate-200/60 bg-white">
              <table className="w-full text-left text-xs text-slate-500 rtl:text-right">
                <thead className="bg-slate-50 font-bold uppercase text-[10px] text-slate-400 border-b">
                  <tr>
                    <th className="px-6 py-3">Area ID</th>
                    <th className="px-6 py-3">City Link</th>
                    <th className="px-6 py-3">Area Name</th>
                    <th className="px-6 py-3">Status</th>
                    <th className="px-6 py-3 text-center">Action</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 text-slate-700 font-medium">
                  {areas.map(area => {
                    const parentCity = cities.find(c => c.id === area.cityId);
                    return (
                      <tr key={area.id} className="hover:bg-slate-50/50">
                        <td className="px-6 py-4 text-slate-400">#{area.id}</td>
                        <td className="px-6 py-4 font-bold text-slate-500">
                          {parentCity ? (language === 'en' ? parentCity.nameEn : parentCity.nameAr) : area.cityId}
                        </td>
                        <td className="px-6 py-4 text-slate-800 font-bold">
                          {language === 'en' ? area.nameEn : area.nameAr}
                        </td>
                        <td className="px-6 py-4">
                          <span className={`rounded-full px-2 py-0.5 text-[10px] font-bold ${
                            area.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'
                          }`}>
                            {area.status === 'active' ? t.status_active : t.status_suspended}
                          </span>
                        </td>
                        <td className="px-6 py-4 text-center">
                          <button
                            onClick={() => toggleGeoStatus(area.id, 'area')}
                            className="text-[10px] text-indigo-600 hover:underline"
                          >
                            {area.status === 'active' ? 'Deactivate' : 'Activate'}
                          </button>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {/* ======================= SUB TAB 4: MEDIA VAULT (LIBRARY) ======================= */}
      {activeSubTab === 'media' && (
        <div className="space-y-6">
          <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex-1 max-w-md relative">
              <Search className="absolute top-2.5 left-3 h-4 w-4 text-slate-400 rtl:right-3 rtl:left-auto" />
              <input
                type="text"
                value={mediaSearch}
                onChange={(e) => setMediaSearch(e.target.value)}
                placeholder={language === 'en' ? 'Search media assets...' : 'البحث في ملفات الميديا...'}
                className="w-full rounded-lg border border-slate-200 bg-white py-2 pl-9 pr-3 text-xs outline-hidden focus:border-indigo-500 rtl:pr-9 rtl:pl-3"
              />
            </div>

            <div className="flex items-center gap-2">
              <input
                ref={mediaVaultInputRef}
                type="file"
                accept="image/jpeg,image/png,image/webp,image/gif"
                className="hidden"
                onChange={(e) => {
                  const file = e.target.files?.[0];
                  if (file) {
                    handleUploadMediaAsset(file);
                    e.currentTarget.value = '';
                  }
                }}
              />

              {hasPermission && (
                <button
                  onClick={() => mediaVaultInputRef.current?.click()}
                  disabled={uploadProgress !== null}
                  className="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-indigo-700 flex items-center gap-1.5 disabled:opacity-50"
                >
                  <Upload className="h-3.5 w-3.5" />
                  <span>{uploadProgress !== null ? `${uploadProgress}%` : (language === 'en' ? 'Upload Image' : 'رفع صورة')}</span>
                </button>
              )}
            </div>
          </div>

          {/* Drag & Drop real dropzone */}
          {hasPermission && uploadProgress === null && (
            <div 
              onClick={() => mediaVaultInputRef.current?.click()}
              onDragOver={(e) => {
                e.preventDefault();
                e.stopPropagation();
              }}
              onDrop={(e) => {
                e.preventDefault();
                e.stopPropagation();
                const file = e.dataTransfer.files?.[0];
                if (file) handleUploadMediaAsset(file);
              }}
              className="border-2 border-dashed border-slate-200 bg-slate-50 rounded-2xl p-6 text-center cursor-pointer hover:bg-indigo-50/20 hover:border-indigo-300 transition-colors"
            >
              <Upload className="mx-auto h-8 w-8 text-slate-400 mb-2 animate-bounce" />
              <p className="text-xs font-bold text-slate-700">
                {language === 'en' ? 'Drag & drop an image here, or click to browse' : 'اسحب صورة وأفلتها هنا، أو اضغط للتصفح'}
              </p>
              <p className="text-[10px] text-slate-400 mt-1">
                {language === 'en' ? 'Supports JPG, PNG, GIF, WEBP up to 10MB' : 'يدعم صيغ JPG, PNG, GIF, WEBP حتى ١٠ ميجابايت'}
              </p>
            </div>
          )}

          {/* Upload Progress Bar */}
          {uploadProgress !== null && (
            <div className="rounded-xl border border-indigo-100 bg-indigo-50/50 p-4">
              <div className="flex items-center justify-between text-xs font-bold text-indigo-950 mb-1.5">
                <span>{language === 'en' ? 'Compressing & Syncing Asset...' : 'جاري ضغط ورفع أصل الملف...'}</span>
                <span>{uploadProgress}%</span>
              </div>
              <div className="h-2 w-full bg-slate-100 rounded-full overflow-hidden border">
                <div className="h-full bg-indigo-600 transition-all duration-150" style={{ width: `${uploadProgress}%` }} />
              </div>
            </div>
          )}

          {/* Media Grid */}
          <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
            {filteredMedia.map(file => (
              <div key={file.id} className="rounded-xl border border-slate-200/60 bg-white overflow-hidden shadow-xs hover:shadow-md transition duration-150 flex flex-col group">
                {/* Visual Preview */}
                <div className="h-32 bg-slate-100 border-b border-slate-100 relative overflow-hidden flex items-center justify-center">
                  <img 
                    src={file.url} 
                    alt={file.name} 
                    className="w-full h-full object-cover group-hover:scale-105 transition duration-200"
                    referrerPolicy="no-referrer"
                  />

                  <span className="absolute top-1.5 left-1.5 bg-slate-900/80 text-white rounded px-1 text-[8px] font-bold uppercase tracking-wider">
                    #{file.id}
                  </span>
                </div>

                {/* Metadata */}
                <div className="p-3 flex-1 flex flex-col justify-between text-xs">
                  <div className="min-w-0">
                    <p className="font-bold text-slate-700 truncate" title={file.name}>{file.name}</p>
                    <p className="text-[10px] text-slate-400 font-medium mt-0.5">{file.size} {file.dimensions ? `• ${file.dimensions}` : ''}</p>
                  </div>

                  {/* Actions: Use as thumbnail, copy CDN link, delete */}
                  <div className="mt-3 pt-2.5 border-t border-slate-50 flex flex-col gap-1.5">
                    <button
                      onClick={() => {
                        setFormImagePreview(file.url);
                        setFormSelectedMediaId(file.id);
                        setFormImageFile(null);
                        setFormMode('create');
                        setActiveSubTab('services');
                        showSuccess(language === 'en' ? `Selected asset #${file.id} as service thumbnail!` : `تم تعيين الأصل #${file.id} كغلاف للخدمة!`);
                      }}
                      className="w-full rounded bg-indigo-50 border border-indigo-200 py-1 font-bold text-[10px] text-indigo-700 hover:bg-indigo-100 flex items-center justify-center gap-1"
                    >
                      <ImageIcon className="h-3 w-3" />
                      <span>{language === 'en' ? 'Use as Thumbnail' : 'استخدم كغلاف للخدمة'}</span>
                    </button>

                    <div className="flex items-center gap-1">
                      <button
                        onClick={() => handleCopyLink(file)}
                        className="flex-1 flex items-center justify-center gap-1 rounded bg-slate-50 border py-1 font-bold text-[10px] text-slate-600 hover:bg-slate-100"
                      >
                        {copiedId === file.id ? (
                          <>
                            <Check className="h-3 w-3 text-emerald-600" />
                            <span className="text-emerald-600">Copied</span>
                          </>
                        ) : (
                          <>
                            <Copy className="h-3 w-3" />
                            <span>CDN URL</span>
                          </>
                        )}
                      </button>
                      {hasPermission && (
                        <button
                          onClick={() => handleDeleteMediaAsset(file.id)}
                          className="p-1 rounded bg-slate-50 border text-rose-500 hover:bg-rose-50 hover:border-rose-100"
                          title={language === 'en' ? 'Delete asset' : 'حذف الملف'}
                        >
                          <Trash2 className="h-3.5 w-3.5" />
                        </button>
                      )}
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* ======================= DETAILED MODERATION SERVICE DRAWER MODAL ======================= */}
      {selectedService && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
          <div className="w-full max-w-xl rounded-2xl border border-slate-100 bg-white p-6 shadow-2xl animate-scale-in">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
              <span className="text-[10px] font-bold text-slate-400 uppercase">
                {language === 'en' ? 'Service Moderation File' : 'ملف مراجعة وتعديل الخدمة'} #{selectedService.id}
              </span>
              <button
                onClick={() => setSelectedService(null)}
                className="text-slate-400 hover:text-slate-600 text-sm font-bold"
              >
                ✕
              </button>
            </div>

            <div className="space-y-4">
              <div>
                <h4 className="text-xs text-slate-400 font-bold uppercase">{language === 'en' ? 'Service Title (English)' : 'اسم الخدمة (بالإنجليزية)'}</h4>
                <p className="text-sm font-bold text-slate-800 mt-1">{selectedService.title_en}</p>
              </div>

              <div>
                <h4 className="text-xs text-slate-400 font-bold uppercase">{language === 'en' ? 'Service Title (Arabic)' : 'اسم الخدمة (بالعربية)'}</h4>
                <p className="text-sm font-bold text-slate-800 mt-1 font-arabic text-right">{selectedService.title_ar}</p>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <h4 className="text-xs text-slate-400 font-bold uppercase">{t.col_category}</h4>
                  <p className="text-xs font-semibold text-slate-700 mt-1">
                    {language === 'en' ? selectedService.category_en : selectedService.category_ar}
                  </p>
                </div>
                <div>
                  <h4 className="text-xs text-slate-400 font-bold uppercase">{t.col_price}</h4>
                  <p className="text-sm font-bold text-slate-900 mt-1">{selectedService.price} SAR</p>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <h4 className="text-xs text-slate-400 font-bold uppercase">{t.col_seller}</h4>
                  <p className="text-xs font-semibold text-slate-700 mt-1">{selectedService.seller_name}</p>
                </div>
                <div>
                  <h4 className="text-xs text-slate-400 font-bold uppercase">{t.status}</h4>
                  <span className={`inline-block mt-1.5 rounded px-2 py-0.5 text-[10px] font-bold ${
                    selectedService.status === 'active' ? 'bg-emerald-50 text-emerald-700' : selectedService.status === 'pending' ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700'
                  }`}>
                    {selectedService.status === 'active' ? t.status_active : selectedService.status === 'pending' ? t.status_pending : t.status_suspended}
                  </span>
                </div>
              </div>

              <div className="border-t border-slate-100 pt-3">
                <h4 className="text-xs text-slate-400 font-bold uppercase mb-1">{language === 'en' ? 'Description (AR)' : 'الوصف (بالعربية)'}</h4>
                <p className="text-xs text-slate-600 font-arabic leading-relaxed text-right">{selectedService.description_ar}</p>
              </div>
            </div>

            {/* Moderation Actions Inside Modal */}
            <div className="mt-6 flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
              <button
                onClick={() => setSelectedService(null)}
                className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100"
              >
                {t.back}
              </button>
              {hasPermission && (
                <>
                  {selectedService.status !== 'suspended' && (
                    <button
                      onClick={() => handleUpdateStatus(selectedService.id, 'suspended')}
                      className="rounded-lg bg-rose-600 px-4 py-2 text-xs font-semibold text-white transition hover:bg-rose-700"
                    >
                      {t.btn_suspend}
                    </button>
                  )}
                  {selectedService.status !== 'active' && (
                    <button
                      onClick={() => handleUpdateStatus(selectedService.id, 'active')}
                      className="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700"
                    >
                      {t.btn_approve}
                    </button>
                  )}
                </>
              )}
            </div>
          </div>
        </div>
      )}

      {/* ======================= ADD CATALOG NODE MODAL ======================= */}
      {showAddCategoryModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
          <form onSubmit={handleAddCategoryNode} className="w-full max-w-md rounded-2xl border border-slate-100 bg-white p-6 shadow-2xl animate-scale-in space-y-4">
            <h3 className="text-sm font-bold text-slate-800 border-b border-slate-100 pb-3">
              {language === 'en' ? 'Append Catalog Node' : 'ربط تفرع جديد بالفهرس'}
            </h3>

            <div>
              <label className="text-[10px] font-bold uppercase text-slate-400">Node Level</label>
              <div className="grid grid-cols-3 gap-1.5 mt-1 bg-slate-100 rounded p-1 text-xs font-semibold">
                <button
                  type="button"
                  onClick={() => setCatLevel('parent')}
                  className={`rounded py-1 ${catLevel === 'parent' ? 'bg-white text-slate-800 shadow-xs' : 'text-slate-500'}`}
                >
                  Category
                </button>
                <button
                  type="button"
                  onClick={() => setCatLevel('sub')}
                  className={`rounded py-1 ${catLevel === 'sub' ? 'bg-white text-slate-800 shadow-xs' : 'text-slate-500'}`}
                >
                  Subcategory
                </button>
                <button
                  type="button"
                  onClick={() => setCatLevel('child')}
                  className={`rounded py-1 ${catLevel === 'child' ? 'bg-white text-slate-800 shadow-xs' : 'text-slate-500'}`}
                >
                  Child Cat
                </button>
              </div>
            </div>

            {/* If Subcategory selection */}
            {catLevel === 'sub' && (
              <div>
                <label className="text-[10px] font-bold uppercase text-slate-400">Select Parent Category</label>
                <select
                  value={selectedParentCatId}
                  onChange={(e) => setSelectedParentCatId(parseInt(e.target.value))}
                  className="w-full mt-1 rounded-lg border border-slate-200 px-3 py-1.5 text-xs bg-white outline-hidden"
                >
                  {categories.map(c => (
                    <option key={c.id} value={c.id}>{language === 'en' ? c.nameEn : c.nameAr}</option>
                  ))}
                </select>
              </div>
            )}

            {/* If Child Category selection */}
            {catLevel === 'child' && (
              <div>
                <label className="text-[10px] font-bold uppercase text-slate-400">Select Parent Subcategory</label>
                <select
                  value={selectedParentSubId}
                  onChange={(e) => setSelectedParentSubId(parseInt(e.target.value))}
                  className="w-full mt-1 rounded-lg border border-slate-200 px-3 py-1.5 text-xs bg-white outline-hidden"
                >
                  {categories.flatMap(c => c.subcategories || []).map(sub => (
                    <option key={sub.id} value={sub.id}>{language === 'en' ? sub.nameEn : sub.nameAr}</option>
                  ))}
                </select>
              </div>
            )}

            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="text-[10px] font-bold uppercase text-slate-400">English Name</label>
                <input
                  type="text"
                  required
                  value={newCatEn}
                  onChange={(e) => setNewCatEn(e.target.value)}
                  className="w-full mt-1 rounded-lg border border-slate-200 px-3 py-1.5 text-xs outline-hidden focus:border-indigo-500"
                  placeholder="e.g., Yacht Cruises"
                />
              </div>
              <div>
                <label className="text-[10px] font-bold uppercase text-slate-400">Arabic Name</label>
                <input
                  type="text"
                  required
                  value={newCatAr}
                  onChange={(e) => setNewCatAr(e.target.value)}
                  className="w-full mt-1 rounded-lg border border-slate-200 px-3 py-1.5 text-xs outline-hidden focus:border-indigo-500 text-right"
                  placeholder="مثال: رحلات اليخوت"
                />
              </div>
            </div>

            <div>
              <label className="text-[10px] font-bold uppercase text-slate-400">Url Slug</label>
              <input
                type="text"
                required
                value={newCatSlug}
                onChange={(e) => setNewCatSlug(e.target.value)}
                className="w-full mt-1 rounded-lg border border-slate-200 px-3 py-1.5 text-xs outline-hidden focus:border-indigo-500"
                placeholder="yacht-cruises"
              />
            </div>

            <div className="mt-6 flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
              <button
                type="button"
                onClick={() => setShowAddCategoryModal(false)}
                className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100"
              >
                {t.cancel}
              </button>
              <button
                type="submit"
                className="rounded-lg bg-indigo-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700"
              >
                {t.save}
              </button>
            </div>
          </form>
        </div>
      )}

      {/* ======================= ADD GEOGRAPHY MODAL ======================= */}
      {showAddGeoModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
          <form onSubmit={handleAddGeoNode} className="w-full max-w-sm rounded-2xl border border-slate-100 bg-white p-6 shadow-2xl animate-scale-in space-y-4">
            <h3 className="text-sm font-bold text-slate-800 border-b border-slate-100 pb-3">
              {geoTab === 'countries' && (language === 'en' ? 'Log New Country Record' : 'إدراج دولة جديدة')}
              {geoTab === 'cities' && (language === 'en' ? 'Log New City Record' : 'إدراج مدينة جديدة')}
              {geoTab === 'areas' && (language === 'en' ? 'Log New Area Record' : 'إدراج حي / قطاع')}
            </h3>

            {/* Parents linkage */}
            {geoTab === 'cities' && (
              <div>
                <label className="text-[10px] font-bold uppercase text-slate-400">Linked Country</label>
                <select
                  value={geoParentId}
                  onChange={(e) => setGeoParentId(e.target.value)}
                  className="w-full mt-1 rounded-lg border border-slate-200 px-3 py-1.5 text-xs bg-white outline-hidden"
                >
                  {countries.map(c => (
                    <option key={c.id} value={c.id}>{language === 'en' ? c.nameEn : c.nameAr}</option>
                  ))}
                </select>
              </div>
            )}

            {geoTab === 'areas' && (
              <div>
                <label className="text-[10px] font-bold uppercase text-slate-400">Linked City</label>
                <select
                  value={geoParentId}
                  onChange={(e) => setGeoParentId(e.target.value)}
                  className="w-full mt-1 rounded-lg border border-slate-200 px-3 py-1.5 text-xs bg-white outline-hidden"
                >
                  {cities.map(city => (
                    <option key={city.id} value={city.id}>{language === 'en' ? city.nameEn : city.nameAr}</option>
                  ))}
                </select>
              </div>
            )}

            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="text-[10px] font-bold uppercase text-slate-400">English Name</label>
                <input
                  type="text"
                  required
                  value={geoNameEn}
                  onChange={(e) => setGeoNameEn(e.target.value)}
                  className="w-full mt-1 rounded-lg border border-slate-200 px-3 py-1.5 text-xs outline-hidden focus:border-indigo-500"
                  placeholder="Riyadh"
                />
              </div>
              <div>
                <label className="text-[10px] font-bold uppercase text-slate-400">Arabic Name</label>
                <input
                  type="text"
                  required
                  value={geoNameAr}
                  onChange={(e) => setGeoNameAr(e.target.value)}
                  className="w-full mt-1 rounded-lg border border-slate-200 px-3 py-1.5 text-xs outline-hidden focus:border-indigo-500 text-right"
                  placeholder="الرياض"
                />
              </div>
            </div>

            {geoTab === 'countries' && (
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="text-[10px] font-bold uppercase text-slate-400">Country ISO Code</label>
                  <input
                    type="text"
                    required
                    maxLength={2}
                    value={geoParam1}
                    onChange={(e) => setGeoParam1(e.target.value)}
                    className="w-full mt-1 rounded-lg border border-slate-200 px-3 py-1.5 text-xs uppercase outline-hidden"
                    placeholder="SA"
                  />
                </div>
                <div>
                  <label className="text-[10px] font-bold uppercase text-slate-400">Phone Code</label>
                  <input
                    type="text"
                    required
                    value={geoParam2}
                    onChange={(e) => setGeoParam2(e.target.value)}
                    className="w-full mt-1 rounded-lg border border-slate-200 px-3 py-1.5 text-xs outline-hidden"
                    placeholder="+966"
                  />
                </div>
              </div>
            )}

            <div className="mt-6 flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
              <button
                type="button"
                onClick={() => setShowAddGeoModal(false)}
                className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100"
              >
                {t.cancel}
              </button>
              <button
                type="submit"
                className="rounded-lg bg-indigo-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700"
              >
                {t.save}
              </button>
            </div>
          </form>
        </div>
      )}
    </div>
  );
}
