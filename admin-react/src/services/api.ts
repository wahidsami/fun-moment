/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { Language } from '../types';
import { apiClient } from '../api/client';
import {
  ApiResponse,
  PaginatedData,
  DashboardSummary,
  OrderListItem,
  OrderDetail,
  OrderUpdatePayload,
  OrderFilters,
  ServiceListItem,
  ServiceDetail,
  ServiceCreateUpdatePayload,
  ServiceFilters,
  SellerListItem,
  SellerDetail,
  SellerCreateUpdatePayload,
  BuyerListItem,
  BuyerDetail,
  BuyerCreateUpdatePayload,
  UserFilters,
  CategoryListItem,
  CategoryDetail,
  CategoryCreateUpdatePayload,
  SubcategoryListItem,
  SubcategoryDetail,
  SubcategoryCreateUpdatePayload,
  ChildCategoryListItem,
  ChildCategoryCreateUpdatePayload,
  CountryListItem,
  CountryCreateUpdatePayload,
  CityListItem,
  CityCreateUpdatePayload,
  AreaListItem,
  AreaCreateUpdatePayload,
  BlogListItem,
  BlogDetail,
  BlogCreateUpdatePayload,
  PageListItem,
  PageDetail,
  PageCreateUpdatePayload,
  MenuListItem,
  MenuCreateUpdatePayload,
  WidgetListItem,
  WidgetCreateUpdatePayload,
  NotificationListItem,
  SalesReport,
  ActivityLogReport,
  SupportTicketListItem,
  SupportTicketDetail,
  SupportTicketUpdatePayload,
  PaymentGatewayListItem,
  PaymentGatewayDetail,
  PaymentGatewayUpdatePayload,
  GeneralSettings,
  GeneralSettingsUpdatePayload,
  LocalizationConfig,
  EmailTemplateListItem,
  EmailTemplateDetail,
  EmailTemplateUpdatePayload,
  WalletSummary,
  ChatRoomListItem,
  JobListItem,
  SubscriptionTierListItem,
  FeatureFlagsPayload,
  FeatureFlagContract
} from '../types/contracts';

/**
 * =========================================================================
 * FUN MOMENT SYSTEM API SERVICES
 * Each method maps cleanly to a future Laravel backend route.
 * Handlers are marked with required API implementation flags.
 * =========================================================================
 */

export const DashboardService = {
  /**
   * @route GET /api/v2/admin/dashboard-summary
   * @required_backend LIVE SUMMARY ENGINE COMPILATION
   */
  async getSummary(lang: Language = 'en'): Promise<ApiResponse<DashboardSummary>> {
    return apiClient.get<DashboardSummary>('/admin/dashboard-summary', lang);
  }
};

export const OrdersService = {
  /**
   * @route GET /api/v2/admin/orders
   * @required_backend PAGINATION AND CUSTOM SCOPES FOR SELLER/BUYER
   */
  async list(filters: OrderFilters, lang: Language = 'en'): Promise<ApiResponse<PaginatedData<OrderListItem>>> {
    const query = new URLSearchParams(filters as any).toString();
    return apiClient.get<PaginatedData<OrderListItem>>(`/admin/orders?${query}`, lang);
  },

  /**
   * @route GET /api/v2/admin/orders/{id}
   * @required_backend ORDER WORKFLOW LOGS RETRIEVAL
   */
  async getDetail(id: number, lang: Language = 'en'): Promise<ApiResponse<OrderDetail>> {
    return apiClient.get<OrderDetail>(`/admin/orders/${id}`, lang);
  },

  /**
   * @route PUT /api/v2/admin/orders/{id}/status
   * @required_backend NOTIFICATION DISPATCHING & ESCROW MOVEMENT
   */
  async updateStatus(id: number, payload: OrderUpdatePayload, lang: Language = 'en'): Promise<ApiResponse<OrderDetail>> {
    return apiClient.put<OrderDetail, OrderUpdatePayload>(`/admin/orders/${id}/status`, payload, lang);
  }
};

export const ServicesService = {
  /**
   * @route GET /api/v2/admin/services
   */
  async list(filters: ServiceFilters, lang: Language = 'en'): Promise<ApiResponse<PaginatedData<ServiceListItem>>> {
    const query = new URLSearchParams(filters as any).toString();
    return apiClient.get<PaginatedData<ServiceListItem>>(`/admin/services?${query}`, lang);
  },

  /**
   * @route GET /api/v2/admin/services/{id}
   */
  async getDetail(id: number, lang: Language = 'en'): Promise<ApiResponse<ServiceDetail>> {
    return apiClient.get<ServiceDetail>(`/admin/services/${id}`, lang);
  },

  /**
   * @route POST /api/v2/admin/services
   */
  async create(payload: ServiceCreateUpdatePayload, lang: Language = 'en'): Promise<ApiResponse<ServiceDetail>> {
    return apiClient.post<ServiceDetail, ServiceCreateUpdatePayload>('/admin/services', payload, lang);
  },

  /**
   * @route PUT /api/v2/admin/services/{id}
   */
  async update(id: number, payload: ServiceCreateUpdatePayload, lang: Language = 'en'): Promise<ApiResponse<ServiceDetail>> {
    return apiClient.put<ServiceDetail, ServiceCreateUpdatePayload>(`/admin/services/${id}`, payload, lang);
  },

  /**
   * @route DELETE /api/v2/admin/services/{id}
   */
  async delete(id: number, lang: Language = 'en'): Promise<ApiResponse<{ id: number }>> {
    return apiClient.delete<{ id: number }>(`/admin/services/${id}`, lang);
  }
};

export const SellersService = {
  /**
   * @route GET /api/v2/admin/sellers
   */
  async list(filters: UserFilters, lang: Language = 'en'): Promise<ApiResponse<PaginatedData<SellerListItem>>> {
    const query = new URLSearchParams(filters as any).toString();
    return apiClient.get<PaginatedData<SellerListItem>>(`/admin/sellers?${query}`, lang);
  },

  /**
   * @route GET /api/v2/admin/sellers/{id}
   */
  async getDetail(id: number, lang: Language = 'en'): Promise<ApiResponse<SellerDetail>> {
    return apiClient.get<SellerDetail>(`/admin/sellers/${id}`, lang);
  },

  /**
   * @route POST /api/v2/admin/sellers
   */
  async create(payload: SellerCreateUpdatePayload, lang: Language = 'en'): Promise<ApiResponse<SellerDetail>> {
    return apiClient.post<SellerDetail, SellerCreateUpdatePayload>('/admin/sellers', payload, lang);
  },

  /**
   * @route PUT /api/v2/admin/sellers/{id}
   */
  async update(id: number, payload: SellerCreateUpdatePayload, lang: Language = 'en'): Promise<ApiResponse<SellerDetail>> {
    return apiClient.put<SellerDetail, SellerCreateUpdatePayload>(`/admin/sellers/${id}`, payload, lang);
  }
};

export const BuyersService = {
  /**
   * @route GET /api/v2/admin/buyers
   */
  async list(filters: UserFilters, lang: Language = 'en'): Promise<ApiResponse<PaginatedData<BuyerListItem>>> {
    const query = new URLSearchParams(filters as any).toString();
    return apiClient.get<PaginatedData<BuyerListItem>>(`/admin/buyers?${query}`, lang);
  },

  /**
   * @route GET /api/v2/admin/buyers/{id}
   */
  async getDetail(id: number, lang: Language = 'en'): Promise<ApiResponse<BuyerDetail>> {
    return apiClient.get<BuyerDetail>(`/admin/buyers/${id}`, lang);
  },

  /**
   * @route POST /api/v2/admin/buyers
   */
  async create(payload: BuyerCreateUpdatePayload, lang: Language = 'en'): Promise<ApiResponse<BuyerDetail>> {
    return apiClient.post<BuyerDetail, BuyerCreateUpdatePayload>('/admin/buyers', payload, lang);
  },

  /**
   * @route PUT /api/v2/admin/buyers/{id}
   */
  async update(id: number, payload: BuyerCreateUpdatePayload, lang: Language = 'en'): Promise<ApiResponse<BuyerDetail>> {
    return apiClient.put<BuyerDetail, BuyerCreateUpdatePayload>(`/admin/buyers/${id}`, payload, lang);
  }
};

export const CategoryService = {
  // Categories
  async listCategories(lang: Language = 'en'): Promise<ApiResponse<CategoryListItem[]>> {
    return apiClient.get<CategoryListItem[]>('/admin/categories', lang);
  },
  async getCategory(id: number, lang: Language = 'en'): Promise<ApiResponse<CategoryDetail>> {
    return apiClient.get<CategoryDetail>(`/admin/categories/${id}`, lang);
  },
  async createCategory(payload: CategoryCreateUpdatePayload, lang: Language = 'en'): Promise<ApiResponse<CategoryDetail>> {
    return apiClient.post<CategoryDetail, CategoryCreateUpdatePayload>('/admin/categories', payload, lang);
  },
  async updateCategory(id: number, payload: CategoryCreateUpdatePayload, lang: Language = 'en'): Promise<ApiResponse<CategoryDetail>> {
    return apiClient.put<CategoryDetail, CategoryCreateUpdatePayload>(`/admin/categories/${id}`, payload, lang);
  },

  // Subcategories
  async createSubcategory(payload: SubcategoryCreateUpdatePayload, lang: Language = 'en'): Promise<ApiResponse<SubcategoryDetail>> {
    return apiClient.post<SubcategoryDetail, SubcategoryCreateUpdatePayload>('/admin/subcategories', payload, lang);
  },
  async updateSubcategory(id: number, payload: SubcategoryCreateUpdatePayload, lang: Language = 'en'): Promise<ApiResponse<SubcategoryDetail>> {
    return apiClient.put<SubcategoryDetail, SubcategoryCreateUpdatePayload>(`/admin/subcategories/${id}`, payload, lang);
  },

  // Child Categories
  async createChildCategory(payload: ChildCategoryCreateUpdatePayload, lang: Language = 'en'): Promise<ApiResponse<ChildCategoryListItem>> {
    return apiClient.post<ChildCategoryListItem, ChildCategoryCreateUpdatePayload>('/admin/child-categories', payload, lang);
  },
  async updateChildCategory(id: number, payload: ChildCategoryCreateUpdatePayload, lang: Language = 'en'): Promise<ApiResponse<ChildCategoryListItem>> {
    return apiClient.put<ChildCategoryListItem, ChildCategoryCreateUpdatePayload>(`/admin/child-categories/${id}`, payload, lang);
  }
};

export const GeographyService = {
  // Countries
  async listCountries(lang: Language = 'en'): Promise<ApiResponse<CountryListItem[]>> {
    return apiClient.get<CountryListItem[]>('/admin/countries', lang);
  },
  async createCountry(payload: CountryCreateUpdatePayload, lang: Language = 'en'): Promise<ApiResponse<CountryListItem>> {
    return apiClient.post<CountryListItem, CountryCreateUpdatePayload>('/admin/countries', payload, lang);
  },

  // Cities
  async createCity(payload: CityCreateUpdatePayload, lang: Language = 'en'): Promise<ApiResponse<CityListItem>> {
    return apiClient.post<CityListItem, CityCreateUpdatePayload>('/admin/cities', payload, lang);
  },

  // Areas
  async createArea(payload: AreaCreateUpdatePayload, lang: Language = 'en'): Promise<ApiResponse<AreaListItem>> {
    return apiClient.post<AreaListItem, AreaCreateUpdatePayload>('/admin/areas', payload, lang);
  }
};

export const BlogsService = {
  async list(lang: Language = 'en'): Promise<ApiResponse<BlogListItem[]>> {
    return apiClient.get<BlogListItem[]>('/admin/blogs', lang);
  },
  async getDetail(id: number, lang: Language = 'en'): Promise<ApiResponse<BlogDetail>> {
    return apiClient.get<BlogDetail>(`/admin/blogs/${id}`, lang);
  },
  async create(payload: BlogCreateUpdatePayload, lang: Language = 'en'): Promise<ApiResponse<BlogDetail>> {
    return apiClient.post<BlogDetail, BlogCreateUpdatePayload>('/admin/blogs', payload, lang);
  },
  async update(id: number, payload: BlogCreateUpdatePayload, lang: Language = 'en'): Promise<ApiResponse<BlogDetail>> {
    return apiClient.put<BlogDetail, BlogCreateUpdatePayload>(`/admin/blogs/${id}`, payload, lang);
  }
};

export const PagesService = {
  async list(lang: Language = 'en'): Promise<ApiResponse<PageListItem[]>> {
    return apiClient.get<PageListItem[]>('/admin/pages', lang);
  },
  async getDetail(id: number, lang: Language = 'en'): Promise<ApiResponse<PageDetail>> {
    return apiClient.get<PageDetail>(`/admin/pages/${id}`, lang);
  },
  async create(payload: PageCreateUpdatePayload, lang: Language = 'en'): Promise<ApiResponse<PageDetail>> {
    return apiClient.post<PageDetail, PageCreateUpdatePayload>('/admin/pages', payload, lang);
  },
  async update(id: number, payload: PageCreateUpdatePayload, lang: Language = 'en'): Promise<ApiResponse<PageDetail>> {
    return apiClient.put<PageDetail, PageCreateUpdatePayload>(`/admin/pages/${id}`, payload, lang);
  }
};

export const MenusService = {
  async list(lang: Language = 'en'): Promise<ApiResponse<MenuListItem[]>> {
    return apiClient.get<MenuListItem[]>('/admin/menus', lang);
  },
  async update(id: number, payload: MenuCreateUpdatePayload, lang: Language = 'en'): Promise<ApiResponse<MenuListItem>> {
    return apiClient.put<MenuListItem, MenuCreateUpdatePayload>(`/admin/menus/${id}`, payload, lang);
  }
};

export const WidgetsService = {
  async list(lang: Language = 'en'): Promise<ApiResponse<WidgetListItem[]>> {
    return apiClient.get<WidgetListItem[]>('/admin/widgets', lang);
  },
  async update(id: number, payload: WidgetCreateUpdatePayload, lang: Language = 'en'): Promise<ApiResponse<WidgetListItem>> {
    return apiClient.put<WidgetListItem, WidgetCreateUpdatePayload>(`/admin/widgets/${id}`, payload, lang);
  }
};

export const NotificationsService = {
  async list(lang: Language = 'en'): Promise<ApiResponse<NotificationListItem[]>> {
    return apiClient.get<NotificationListItem[]>('/admin/notifications', lang);
  },
  async markRead(id: string, lang: Language = 'en'): Promise<ApiResponse<{ id: string }>> {
    return apiClient.post<{ id: string }, {}>(`/admin/notifications/${id}/read`, {}, lang);
  }
};

export const ReportsService = {
  async getSalesReport(start: string, end: string, lang: Language = 'en'): Promise<ApiResponse<SalesReport>> {
    return apiClient.get<SalesReport>(`/admin/reports/sales?start_date=${start}&end_date=${end}`, lang);
  },
  async getActivityLogs(lang: Language = 'en'): Promise<ApiResponse<ActivityLogReport[]>> {
    return apiClient.get<ActivityLogReport[]>('/admin/reports/activity-logs', lang);
  }
};

export const SupportTicketsService = {
  async list(lang: Language = 'en'): Promise<ApiResponse<SupportTicketListItem[]>> {
    return apiClient.get<SupportTicketListItem[]>('/admin/support-tickets', lang);
  },
  async getDetail(id: number, lang: Language = 'en'): Promise<ApiResponse<SupportTicketDetail>> {
    return apiClient.get<SupportTicketDetail>(`/admin/support-tickets/${id}`, lang);
  },
  async update(id: number, payload: SupportTicketUpdatePayload, lang: Language = 'en'): Promise<ApiResponse<SupportTicketDetail>> {
    return apiClient.put<SupportTicketDetail, SupportTicketUpdatePayload>(`/admin/support-tickets/${id}`, payload, lang);
  }
};

export const PaymentGatewaysService = {
  async list(lang: Language = 'en'): Promise<ApiResponse<PaymentGatewayListItem[]>> {
    return apiClient.get<PaymentGatewayListItem[]>('/admin/payment-gateways', lang);
  },
  async getDetail(id: string, lang: Language = 'en'): Promise<ApiResponse<PaymentGatewayDetail>> {
    return apiClient.get<PaymentGatewayDetail>(`/admin/payment-gateways/${id}`, lang);
  },
  async update(id: string, payload: PaymentGatewayUpdatePayload, lang: Language = 'en'): Promise<ApiResponse<PaymentGatewayDetail>> {
    return apiClient.put<PaymentGatewayDetail, PaymentGatewayUpdatePayload>(`/admin/payment-gateways/${id}`, payload, lang);
  }
};

export const SettingsService = {
  async getGeneral(lang: Language = 'en'): Promise<ApiResponse<GeneralSettings>> {
    return apiClient.get<GeneralSettings>('/admin/settings/general', lang);
  },
  async updateGeneral(payload: GeneralSettingsUpdatePayload, lang: Language = 'en'): Promise<ApiResponse<GeneralSettings>> {
    return apiClient.put<GeneralSettings, GeneralSettingsUpdatePayload>('/admin/settings/general', payload, lang);
  },
  async getLocalization(lang: Language = 'en'): Promise<ApiResponse<LocalizationConfig>> {
    return apiClient.get<LocalizationConfig>('/admin/settings/localization', lang);
  },
  async updateLocalization(payload: Partial<LocalizationConfig>, lang: Language = 'en'): Promise<ApiResponse<LocalizationConfig>> {
    return apiClient.put<LocalizationConfig, Partial<LocalizationConfig>>('/admin/settings/localization', payload, lang);
  }
};

export const EmailTemplatesService = {
  async list(lang: Language = 'en'): Promise<ApiResponse<EmailTemplateListItem[]>> {
    return apiClient.get<EmailTemplateListItem[]>('/admin/email-templates', lang);
  },
  async getDetail(id: string, lang: Language = 'en'): Promise<ApiResponse<EmailTemplateDetail>> {
    return apiClient.get<EmailTemplateDetail>(`/admin/email-templates/${id}`, lang);
  },
  async update(id: string, payload: EmailTemplateUpdatePayload, lang: Language = 'en'): Promise<ApiResponse<EmailTemplateDetail>> {
    return apiClient.put<EmailTemplateDetail, EmailTemplateUpdatePayload>(`/admin/email-templates/${id}`, payload, lang);
  }
};

/**
 * ==========================================
 * ADD-ON SERVICES
 * Enabled or locked based on license/module state.
 * ==========================================
 */

export const AddonsService = {
  async getFeatureFlags(lang: Language = 'en'): Promise<ApiResponse<FeatureFlagsPayload>> {
    const response = await fetch('/admin-home/feature-flags', {
      method: 'GET',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Accept-Language': lang,
        'X-App-Locale': lang,
        'X-Layout-Direction': lang === 'ar' ? 'rtl' : 'ltr',
      },
    });

    return response.json();
  },

  async updateFeatureFlag(module: string, enabled: boolean, lang: Language = 'en'): Promise<ApiResponse<{ module: FeatureFlagContract; modules: FeatureFlagContract[] }>> {
    const response = await fetch(`/admin-home/feature-flags/${module}`, {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Accept-Language': lang,
        'X-App-Locale': lang,
        'X-Layout-Direction': lang === 'ar' ? 'rtl' : 'ltr',
      },
      body: JSON.stringify({ enabled }),
    });

    return response.json();
  },

  // Wallet Add-on Module
  async getWalletSummary(lang: Language = 'en'): Promise<ApiResponse<WalletSummary>> {
    return apiClient.get<WalletSummary>('/addons/wallet/summary', lang);
  },

  // Live Chat Add-on Module
  async listChatRooms(lang: Language = 'en'): Promise<ApiResponse<ChatRoomListItem[]>> {
    return apiClient.get<ChatRoomListItem[]>('/addons/chat/rooms', lang);
  },

  // Jobs/Bidding Add-on Module
  async listJobs(lang: Language = 'en'): Promise<ApiResponse<JobListItem[]>> {
    return apiClient.get<JobListItem[]>('/addons/jobs', lang);
  },

  // Subscription Add-on Module
  async listSubscriptionTiers(lang: Language = 'en'): Promise<ApiResponse<SubscriptionTierListItem[]>> {
    return apiClient.get<SubscriptionTierListItem[]>('/addons/subscriptions/tiers', lang);
  }
};
