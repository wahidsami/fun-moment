/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import {
  User,
  AdminAccount,
  AdminRoleRecord,
  Service,
  Order,
  Payment,
  SupportTicket,
  CMSContent,
  OverviewStats,
  DashboardActivityLog,
  DashboardSummaryPayload,
} from './types';

export interface DashboardSettings {
  commission_percentage: number;
  min_payout_amount: number;
  maintenance_mode: boolean;
  required_app_version: string;
  base_currency: string;
  exchange_rate_usd: number;
}

// In-memory changes tracker for real-time demo updates
export const LaravelAPI = {
  async getDashboardSummary(): Promise<DashboardSummaryPayload> {
    const response = await fetch('/admin-home/dashboard-summary', {
      method: 'GET',
      headers: { 'Accept': 'application/json' },
      credentials: 'include',
    });

    if (!response.ok) {
      throw new Error(`Failed to load dashboard summary (${response.status})`);
    }

    const payload = await response.json();
    return (payload?.data ?? payload) as DashboardSummaryPayload;
  },

  // Stats
  async getOverviewStats(): Promise<OverviewStats> {
    const response = await fetch('/admin-home/dashboard-summary', {
      method: 'GET',
      headers: { 'Accept': 'application/json' },
      credentials: 'include',
    });

    if (!response.ok) {
      throw new Error(`Failed to load dashboard stats (${response.status})`);
    }

    const payload: DashboardSummaryPayload = await response.json();
    if (payload?.overview_stats) {
      return payload.overview_stats;
    }

    throw new Error('Dashboard stats payload missing overview_stats');
  },

    // Users
    async getUsers(): Promise<User[]> {
      const response = await fetch('/admin-home/frontend-users', {
        method: 'GET',
        headers: { 'Accept': 'application/json' },
        credentials: 'include',
      });

      if (!response.ok) {
        throw new Error(`Failed to load users (${response.status})`);
      }

      const payload = await response.json();
      if (Array.isArray(payload?.users)) {
        return payload.users;
      }

      throw new Error('Users payload missing users array');
    },
  
    async updateUserStatus(id: number, status: 'active' | 'suspended' | 'pending'): Promise<User> {
      const response = await fetch(`/admin-home/frontend-users/${id}/status`, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify({ status }),
      });

      if (!response.ok) {
        throw new Error(`Failed to update user status (${response.status})`);
      }

      const payload = await response.json();
      if (payload?.user) {
        return payload.user;
      }

      throw new Error(payload?.message || 'User status update failed');
    },
  
    async updateUserBalance(id: number, balance: number): Promise<User> {
      const response = await fetch(`/admin-home/frontend-users/${id}/balance`, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify({ balance }),
      });

      if (!response.ok) {
        throw new Error(`Failed to update user balance (${response.status})`);
      }

      const payload = await response.json();
      if (payload?.user) {
        return payload.user;
      }

      throw new Error(payload?.message || 'User balance update failed');
    },

    async getAdminDirectory(): Promise<{ admins: AdminAccount[]; roles: AdminRoleRecord[] }> {
      try {
        const response = await fetch('/admin-home/admin-directory', {
          method: 'GET',
          headers: { 'Accept': 'application/json' },
          credentials: 'include',
        });

        const payload = await response.json();
        if (Array.isArray(payload?.admins) && Array.isArray(payload?.roles)) {
          return {
            admins: payload.admins,
            roles: payload.roles,
          };
        }
      } catch (error) {
        console.warn('Falling back to empty admin directory', error);
      }

      return { admins: [], roles: [] };
    },

    // Services
    async getServicesPayload(): Promise<{
      services: Service[];
      categories: { id: number; name: string }[];
      sellers: { id: number; name: string; email: string }[];
    }> {
      const response = await fetch('/admin-home/services-json', {
        method: 'GET',
        headers: { 'Accept': 'application/json' },
        credentials: 'include',
      });

      if (!response.ok) {
        throw new Error(`Failed to load services (${response.status})`);
      }

      const payload = await response.json();
      return {
        services: Array.isArray(payload?.services) ? payload.services : [],
        categories: Array.isArray(payload?.categories) ? payload.categories : [],
        sellers: Array.isArray(payload?.sellers) ? payload.sellers : [],
      };
    },

    async getServices(): Promise<Service[]> {
      const data = await this.getServicesPayload();
      return data.services;
    },
  
    async updateServiceStatus(id: number, status: 'active' | 'pending' | 'suspended'): Promise<Service> {
      const response = await fetch(`/admin-home/services-json/${id}/status`, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify({ status }),
      });

      if (!response.ok) {
        throw new Error(`Failed to update service status (${response.status})`);
      }

      const payload = await response.json();
      if (payload?.service) {
        return payload.service;
      }

      throw new Error(payload?.message || 'Service status update failed');
    },
  
    async createService(serviceData: Omit<Service, 'id' | 'seller_name' | 'rating' | 'sales_count' | 'created_at'>): Promise<Service> {
      const response = await fetch('/admin-home/services-json', {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify({
          title_en: serviceData.title_en,
          title_ar: serviceData.title_ar,
          category_id: serviceData.category_id || 1,
          seller_id: serviceData.seller_id,
          price: serviceData.price,
          duration: serviceData.duration,
          description_en: serviceData.description_en,
          description_ar: serviceData.description_ar,
        }),
      });

      if (!response.ok) {
        let msg = `Failed to create service (${response.status})`;
        try {
          const errBody = await response.json();
          if (errBody?.message) msg = errBody.message;
          if (errBody?.errors) {
            const errs = Object.values(errBody.errors).flat().join(', ');
            if (errs) msg = `${msg}: ${errs}`;
          }
        } catch (_) {}
        throw new Error(msg);
      }

      const payload = await response.json();
      if (payload?.service) {
        return payload.service;
      }

      throw new Error(payload?.message || 'Service creation failed');
    },

    async updateService(id: number, serviceData: Omit<Service, 'id' | 'seller_name' | 'rating' | 'sales_count' | 'created_at'>): Promise<Service> {
      const response = await fetch(`/admin-home/services-json/${id}`, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify({
          title_en: serviceData.title_en,
          title_ar: serviceData.title_ar,
          category_id: serviceData.category_id || 1,
          seller_id: serviceData.seller_id,
          price: serviceData.price,
          duration: serviceData.duration,
          description_en: serviceData.description_en,
          description_ar: serviceData.description_ar,
        }),
      });

      if (!response.ok) {
        let msg = `Failed to update service (${response.status})`;
        try {
          const errBody = await response.json();
          if (errBody?.message) msg = errBody.message;
          if (errBody?.errors) {
            const errs = Object.values(errBody.errors).flat().join(', ');
            if (errs) msg = `${msg}: ${errs}`;
          }
        } catch (_) {}
        throw new Error(msg);
      }

      const payload = await response.json();
      if (payload?.service) {
        return payload.service;
      }

      throw new Error(payload?.message || 'Service update failed');
    },

    async deleteService(id: number): Promise<void> {
      const response = await fetch(`/admin-home/services-json/${id}/delete`, {
        method: 'POST',
        headers: { 'Accept': 'application/json' },
        credentials: 'include',
      });

      if (!response.ok) {
        throw new Error(`Failed to delete service (${response.status})`);
      }
    },

    async bulkUpdateServices(ids: number[], status: 'active' | 'pending' | 'suspended'): Promise<void> {
      const response = await fetch('/admin-home/services-json/bulk-status', {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify({ ids, status }),
      });

      if (!response.ok) {
        throw new Error(`Failed to bulk update services (${response.status})`);
      }
    },

    async bulkDeleteServices(ids: number[]): Promise<void> {
      const response = await fetch('/admin-home/services-json/bulk-delete', {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify({ ids }),
      });

      if (!response.ok) {
        throw new Error(`Failed to bulk delete services (${response.status})`);
      }
    },

    // Orders
    async getOrders(): Promise<Order[]> {
      const response = await fetch('/admin-home/orders-json', {
        method: 'GET',
        headers: { 'Accept': 'application/json' },
        credentials: 'include',
      });

      if (!response.ok) {
        throw new Error(`Failed to load orders (${response.status})`);
      }

      const payload = await response.json();
      if (Array.isArray(payload?.orders)) {
        return payload.orders;
      }

      throw new Error('Orders payload missing orders array');
    },
  
    async updateOrderStatus(id: number, status: 'pending' | 'in_progress' | 'completed' | 'cancelled'): Promise<Order> {
      const response = await fetch(`/admin-home/orders-json/${id}/status`, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify({ status }),
      });

      if (!response.ok) {
        throw new Error(`Failed to update order status (${response.status})`);
      }

      const payload = await response.json();
      if (payload?.order) {
        return payload.order;
      }

      throw new Error(payload?.message || 'Order status update failed');
    },

    async getOrderDetails(id: number): Promise<{ order: Order; includes?: unknown[]; additionals?: unknown[] }> {
      const response = await fetch(`/admin-home/orders-json/${id}`, {
        method: 'GET',
        headers: { 'Accept': 'application/json' },
        credentials: 'include',
      });

      if (!response.ok) {
        throw new Error(`Failed to load order details (${response.status})`);
      }

      const payload = await response.json();
      if (payload?.order) {
        return payload;
      }

      throw new Error(payload?.message || 'Order details payload missing order');
    },

  // Payments
  async getPayments(): Promise<Payment[]> {
    const response = await fetch('/admin-home/payments-json', {
      method: 'GET',
      headers: { 'Accept': 'application/json' },
      credentials: 'include',
    });

    if (!response.ok) {
      throw new Error(`Failed to load payments (${response.status})`);
    }

    const payload = await response.json();
    if (Array.isArray(payload?.payments)) {
      return payload.payments;
    }

    throw new Error('Payments payload missing payments array');
  },

  async refundPayment(txnId: string): Promise<Payment> {
    const response = await fetch(`/admin-home/payments-json/${txnId}/refund`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      credentials: 'include',
      body: JSON.stringify({ reason: 'dashboard_refund' }),
    });

    if (!response.ok) {
      throw new Error(`Failed to refund payment (${response.status})`);
    }

    const payload = await response.json();
    if (payload?.payment) {
      return payload.payment;
    }

    throw new Error(payload?.message || 'Refund failed');
  },

  // Tickets
  async getTickets(): Promise<SupportTicket[]> {
    const response = await fetch('/admin-home/support-tickets-json', {
      method: 'GET',
      headers: { 'Accept': 'application/json' },
      credentials: 'include',
    });

    if (!response.ok) {
      throw new Error(`Failed to load support tickets (${response.status})`);
    }

    const payload = await response.json();
    if (Array.isArray(payload?.tickets)) {
      return payload.tickets;
    }

    throw new Error('Support tickets payload missing tickets array');
  },

  async updateTicketStatus(id: number, status: 'open' | 'in_progress' | 'resolved' | 'closed'): Promise<SupportTicket> {
    const response = await fetch(`/admin-home/support-tickets-json/${id}/status`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      credentials: 'include',
      body: JSON.stringify({ status }),
    });

    if (!response.ok) {
      throw new Error(`Failed to update ticket status (${response.status})`);
    }

    const payload = await response.json();
    if (payload?.ticket) {
      return payload.ticket;
    }

    throw new Error(payload?.message || 'Ticket update failed');
  },

  // CMS
  async getCMSContent(): Promise<CMSContent[]> {
    const response = await fetch('/admin-home/cms-inventory', {
      method: 'GET',
      headers: { 'Accept': 'application/json' },
      credentials: 'include',
    });

    if (!response.ok) {
      throw new Error(`Failed to load CMS inventory (${response.status})`);
    }

    const payload = await response.json();
    const items = [
      ...(Array.isArray(payload?.blogs) ? payload.blogs : []),
      ...(Array.isArray(payload?.pages) ? payload.pages : []),
      ...(Array.isArray(payload?.widgets) ? payload.widgets : []),
      ...(Array.isArray(payload?.menus) ? payload.menus : []),
      ...(Array.isArray(payload?.media) ? payload.media : []),
    ];

    return items;
  },

  async createCMSBlog(item: Omit<CMSContent, 'id' | 'updated_at'>): Promise<CMSContent> {
    const response = await fetch('/admin-home/cms-blogs', {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      credentials: 'include',
      body: JSON.stringify(item),
    });
    const payload = await response.json();
    if (payload?.item) return payload.item;
    throw new Error(payload?.message || 'Blog creation failed');
  },

  async updateCMSBlog(id: number, item: Omit<CMSContent, 'id' | 'updated_at'>): Promise<CMSContent> {
    const response = await fetch(`/admin-home/cms-blogs/${id}`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      credentials: 'include',
      body: JSON.stringify(item),
    });
    const payload = await response.json();
    if (payload?.item) return payload.item;
    throw new Error(payload?.message || 'Blog update failed');
  },

  async deleteCMSBlog(id: number): Promise<void> {
    await fetch(`/admin-home/cms-blogs/${id}/delete`, {
      method: 'POST',
      headers: { 'Accept': 'application/json' },
      credentials: 'include',
    });
  },

  async createCMSPage(item: Omit<CMSContent, 'id' | 'updated_at'>): Promise<CMSContent> {
    const response = await fetch('/admin-home/cms-pages', {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      credentials: 'include',
      body: JSON.stringify(item),
    });
    const payload = await response.json();
    if (payload?.item) return payload.item;
    throw new Error(payload?.message || 'Page creation failed');
  },

  async updateCMSPage(id: number, item: Omit<CMSContent, 'id' | 'updated_at'>): Promise<CMSContent> {
    const response = await fetch(`/admin-home/cms-pages/${id}`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      credentials: 'include',
      body: JSON.stringify(item),
    });
    const payload = await response.json();
    if (payload?.item) return payload.item;
    throw new Error(payload?.message || 'Page update failed');
  },

  async deleteCMSPage(id: number): Promise<void> {
    await fetch(`/admin-home/cms-pages/${id}/delete`, {
      method: 'POST',
      headers: { 'Accept': 'application/json' },
      credentials: 'include',
    });
  },

  async createCMSMenu(item: { title: string; content?: string; label_ar?: string; status?: string }): Promise<CMSContent> {
    const response = await fetch('/admin-home/cms-menus', {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      credentials: 'include',
      body: JSON.stringify(item),
    });
    const payload = await response.json();
    if (payload?.item) return payload.item;
    throw new Error(payload?.message || 'Menu creation failed');
  },

  async updateCMSMenu(id: number, item: { title: string; content?: string; label_ar?: string; status?: string }): Promise<CMSContent> {
    const response = await fetch(`/admin-home/cms-menus/${id}`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      credentials: 'include',
      body: JSON.stringify(item),
    });
    const payload = await response.json();
    if (payload?.item) return payload.item;
    throw new Error(payload?.message || 'Menu update failed');
  },

  async deleteCMSMenu(id: number): Promise<void> {
    await fetch(`/admin-home/cms-menus/${id}/delete`, {
      method: 'POST',
      headers: { 'Accept': 'application/json' },
      credentials: 'include',
    });
  },

  async setDefaultCMSMenu(id: number): Promise<CMSContent> {
    const response = await fetch(`/admin-home/cms-menus/${id}/default`, {
      method: 'POST',
      headers: { 'Accept': 'application/json' },
      credentials: 'include',
    });
    const payload = await response.json();
    if (payload?.item) return payload.item;
    throw new Error(payload?.message || 'Menu default update failed');
  },

  async createCMSWidget(item: { widget_name: string; widget_order: number; widget_location: string; [key: string]: unknown }): Promise<CMSContent> {
    const response = await fetch('/admin-home/cms-widgets', {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      credentials: 'include',
      body: JSON.stringify(item),
    });
    const payload = await response.json();
    if (payload?.item) return payload.item;
    throw new Error(payload?.message || 'Widget creation failed');
  },

  async updateCMSWidget(id: number, item: { widget_name: string; widget_order: number; widget_location: string; [key: string]: unknown }): Promise<CMSContent> {
    const response = await fetch(`/admin-home/cms-widgets/${id}`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      credentials: 'include',
      body: JSON.stringify(item),
    });
    const payload = await response.json();
    if (payload?.item) return payload.item;
    throw new Error(payload?.message || 'Widget update failed');
  },

  async deleteCMSWidget(id: number): Promise<void> {
    await fetch(`/admin-home/cms-widgets/${id}/delete`, {
      method: 'POST',
      headers: { 'Accept': 'application/json' },
      credentials: 'include',
    });
  },

  async reorderCMSWidget(id: number, widget_order: number): Promise<CMSContent> {
    const response = await fetch(`/admin-home/cms-widgets/${id}/order`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      credentials: 'include',
      body: JSON.stringify({ widget_order }),
    });
    const payload = await response.json();
    if (payload?.item) return payload.item;
    throw new Error(payload?.message || 'Widget reorder failed');
  },

  async getMediaLibrary(): Promise<CMSContent[]> {
    try {
      const response = await fetch('/admin-home/cms-media', {
        method: 'GET',
        headers: { 'Accept': 'application/json' },
        credentials: 'include',
      });
      const payload = await response.json();
      if (Array.isArray(payload?.media)) {
        return payload.media;
      }
    } catch (error) {
      console.warn('Falling back to empty media library', error);
    }

    return [];
  },

  async uploadMedia(file: File): Promise<CMSContent> {
    const formData = new FormData();
    formData.append('file', file);
    const response = await fetch('/admin-home/cms-media/upload', {
      method: 'POST',
      headers: { 'Accept': 'application/json' },
      credentials: 'include',
      body: formData,
    });
    const payload = await response.json();
    if (payload?.item) return payload.item;
    throw new Error(payload?.message || 'Media upload failed');
  },

  async deleteMedia(id: number): Promise<void> {
    await fetch(`/admin-home/cms-media/${id}/delete`, {
      method: 'POST',
      headers: { 'Accept': 'application/json' },
      credentials: 'include',
    });
  },

  async updateMediaAlt(id: number, alt: string): Promise<CMSContent> {
    const response = await fetch(`/admin-home/cms-media/${id}/alt`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      credentials: 'include',
      body: JSON.stringify({ alt }),
    });
    const payload = await response.json();
    if (payload?.item) return payload.item;
    throw new Error(payload?.message || 'Media alt update failed');
  },

  async updateCMSStatus(id: number, status: 'published' | 'draft'): Promise<CMSContent> {
    const inventoryResponse = await fetch('/admin-home/cms-inventory', {
      method: 'GET',
      headers: { 'Accept': 'application/json' },
      credentials: 'include',
    });

    if (!inventoryResponse.ok) {
      throw new Error(`Failed to load CMS inventory (${inventoryResponse.status})`);
    }

    const inventoryPayload = await inventoryResponse.json();
    const isBlog = [
      ...(Array.isArray(inventoryPayload?.blogs) ? inventoryPayload.blogs : []),
      ...(Array.isArray(inventoryPayload?.pages) ? inventoryPayload.pages : []),
    ].find((entry: CMSContent) => Number(entry.id) === Number(id) && entry.type === 'blog');

    const contentType = isBlog ? 'blog' : 'page';
    const response = await fetch(`/admin-home/cms-inventory/${contentType}/${id}/status`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      credentials: 'include',
      body: JSON.stringify({ status }),
    });

    if (!response.ok) {
      throw new Error(`Failed to update CMS status (${response.status})`);
    }

    const payload = await response.json();
    if (payload?.item) {
      return payload.item;
    }

    throw new Error(payload?.message || 'CMS status update failed');
  },

  async createCMSItem(item: Omit<CMSContent, 'id' | 'updated_at'>): Promise<CMSContent> {
    throw new Error(`createCMSItem is not wired to a backend route for type "${item.type}"`);
  },

  // Settings
  async getSettings(): Promise<DashboardSettings> {
    const response = await fetch('/admin-home/settings-json', {
      method: 'GET',
      headers: { 'Accept': 'application/json' },
      credentials: 'include',
    });

    if (!response.ok) {
      throw new Error(`Failed to load settings (${response.status})`);
    }

    const payload = await response.json();
    if (payload?.data) {
      return payload.data as DashboardSettings;
    }

    throw new Error(payload?.message_en || 'Settings payload missing data');
  },

  async saveSettings(settings: DashboardSettings): Promise<DashboardSettings> {
    const response = await fetch('/admin-home/settings-json', {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      credentials: 'include',
      body: JSON.stringify(settings),
    });

    if (!response.ok) {
      throw new Error(`Failed to save settings (${response.status})`);
    }

    const payload = await response.json();
    if (payload?.data) {
      return payload.data as DashboardSettings;
    }

    throw new Error(payload?.message_en || 'Settings payload missing data');
  },

  // Activity log
  async getActivityLogs(): Promise<DashboardActivityLog[]> {
    const response = await fetch('/admin-home/dashboard-summary', {
      method: 'GET',
      headers: { 'Accept': 'application/json' },
      credentials: 'include',
    });

    if (!response.ok) {
      throw new Error(`Failed to load activity logs (${response.status})`);
    }

    const payload: DashboardSummaryPayload = await response.json();
    if (payload?.activity_logs) {
      return payload.activity_logs;
    }

    throw new Error('Dashboard summary payload missing activity_logs');
  },

  // Seller Verification
  async verifySeller(id: number, status?: boolean): Promise<User> {
    const response = await fetch(`/admin-home/frontend-users/${id}/verify`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      credentials: 'include',
      body: JSON.stringify(status !== undefined ? { status } : {}),
    });

    if (!response.ok) {
      throw new Error(`Failed to verify seller (${response.status})`);
    }

    const payload = await response.json();
    if (payload?.user) {
      return payload.user;
    }
    throw new Error(payload?.message || 'Verification update failed');
  },

  async getSellerVerification(id: number): Promise<any> {
    const response = await fetch(`/admin-home/frontend-users/${id}/verification`, {
      method: 'GET',
      headers: { 'Accept': 'application/json' },
      credentials: 'include',
    });

    if (!response.ok) {
      throw new Error(`Failed to load seller verification (${response.status})`);
    }

    const payload = await response.json();
    return payload?.verification ?? null;
  },

  // Categories Hierarchy
  async getCategories(): Promise<any[]> {
    const response = await fetch('/admin-home/categories-json', {
      method: 'GET',
      headers: { 'Accept': 'application/json' },
      credentials: 'include',
    });

    if (!response.ok) {
      throw new Error(`Failed to load categories (${response.status})`);
    }

    const payload = await response.json();
    return Array.isArray(payload?.categories) ? payload.categories : [];
  },

  async createCategory(node: { level: 'parent' | 'sub' | 'child'; name_en: string; name_ar?: string; slug?: string; parent_id?: number }): Promise<any> {
    const response = await fetch('/admin-home/categories-json', {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      credentials: 'include',
      body: JSON.stringify(node),
    });

    if (!response.ok) {
      const err = await response.json().catch(() => ({}));
      throw new Error(err?.message || `Failed to create category (${response.status})`);
    }

    const payload = await response.json();
    return payload?.node;
  },

  async updateCategoryStatus(level: 'parent' | 'sub' | 'child', id: number, status?: 'active' | 'inactive'): Promise<any> {
    const response = await fetch(`/admin-home/categories-json/${level}/${id}/status`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      credentials: 'include',
      body: JSON.stringify(status ? { status } : {}),
    });

    if (!response.ok) {
      throw new Error(`Failed to update category status (${response.status})`);
    }

    return response.json();
  },

  async deleteCategory(level: 'parent' | 'sub' | 'child', id: number): Promise<void> {
    const response = await fetch(`/admin-home/categories-json/${level}/${id}/delete`, {
      method: 'POST',
      headers: { 'Accept': 'application/json' },
      credentials: 'include',
    });

    if (!response.ok) {
      const err = await response.json().catch(() => ({}));
      throw new Error(err?.message || `Failed to delete category (${response.status})`);
    }
  },

  // Locations / Coverage
  async getLocations(): Promise<{ countries: any[]; cities: any[]; areas: any[] }> {
    const response = await fetch('/admin-home/locations-json', {
      method: 'GET',
      headers: { 'Accept': 'application/json' },
      credentials: 'include',
    });

    if (!response.ok) {
      throw new Error(`Failed to load locations (${response.status})`);
    }

    const payload = await response.json();
    return {
      countries: Array.isArray(payload?.countries) ? payload.countries : [],
      cities: Array.isArray(payload?.cities) ? payload.cities : [],
      areas: Array.isArray(payload?.areas) ? payload.areas : [],
    };
  },

  async createLocation(node: { level: 'country' | 'city' | 'area'; name_en: string; name_ar?: string; code?: string; country_id?: number; city_id?: number }): Promise<any> {
    const response = await fetch('/admin-home/locations-json', {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      credentials: 'include',
      body: JSON.stringify(node),
    });

    if (!response.ok) {
      const err = await response.json().catch(() => ({}));
      throw new Error(err?.message || `Failed to create location (${response.status})`);
    }

    const payload = await response.json();
    return payload?.node;
  },

  async updateLocationStatus(level: 'country' | 'city' | 'area', id: string | number, status?: 'active' | 'inactive'): Promise<any> {
    const response = await fetch(`/admin-home/locations-json/${level}/${id}/status`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      credentials: 'include',
      body: JSON.stringify(status ? { status } : {}),
    });

    if (!response.ok) {
      throw new Error(`Failed to update location status (${response.status})`);
    }

    return response.json();
  },

  async deleteLocation(level: 'country' | 'city' | 'area', id: string | number): Promise<void> {
    const response = await fetch(`/admin-home/locations-json/${level}/${id}/delete`, {
      method: 'POST',
      headers: { 'Accept': 'application/json' },
      credentials: 'include',
    });

    if (!response.ok) {
      const err = await response.json().catch(() => ({}));
      throw new Error(err?.message || `Failed to delete location (${response.status})`);
    }
  },

  // Payouts
  async getPayouts(): Promise<{ payouts: any[]; summary: any }> {
    const response = await fetch('/admin-home/payouts-json', {
      method: 'GET',
      headers: { 'Accept': 'application/json' },
      credentials: 'include',
    });

    if (!response.ok) {
      throw new Error(`Failed to load payouts (${response.status})`);
    }

    const payload = await response.json();
    return {
      payouts: Array.isArray(payload?.payouts) ? payload.payouts : [],
      summary: payload?.summary ?? {},
    };
  },

  async updatePayoutStatus(id: number, status: 'pending' | 'completed' | 'rejected', admin_note?: string): Promise<any> {
    const response = await fetch(`/admin-home/payouts-json/${id}/status`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      credentials: 'include',
      body: JSON.stringify({ status, admin_note }),
    });

    if (!response.ok) {
      throw new Error(`Failed to update payout status (${response.status})`);
    }

    const payload = await response.json();
    return payload?.payout;
  },

  // Ticket Conversation Details & Reply
  async getTicketDetails(id: number): Promise<{ ticket: SupportTicket; messages: any[] }> {
    const response = await fetch(`/admin-home/support-tickets-json/${id}`, {
      method: 'GET',
      headers: { 'Accept': 'application/json' },
      credentials: 'include',
    });

    if (!response.ok) {
      throw new Error(`Failed to load ticket details (${response.status})`);
    }

    const payload = await response.json();
    return {
      ticket: payload?.ticket,
      messages: Array.isArray(payload?.messages) ? payload.messages : [],
    };
  },

  async replyTicket(id: number, message: string): Promise<{ ticket: SupportTicket; messages: any[] }> {
    const response = await fetch(`/admin-home/support-tickets-json/${id}/reply`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      credentials: 'include',
      body: JSON.stringify({ message }),
    });

    if (!response.ok) {
      throw new Error(`Failed to send reply (${response.status})`);
    }

    const payload = await response.json();
    return {
      ticket: payload?.ticket,
      messages: Array.isArray(payload?.messages) ? payload.messages : [],
    };
  },

  // Payment Gateways
  async getPaymentGateways(): Promise<any> {
    const response = await fetch('/admin-home/payment-gateways-json', {
      method: 'GET',
      headers: { 'Accept': 'application/json' },
      credentials: 'include',
    });

    if (!response.ok) {
      throw new Error(`Failed to load payment gateways (${response.status})`);
    }

    const payload = await response.json();
    return payload?.gateways ?? null;
  },

  async updatePaymentGateways(gateways: any): Promise<any> {
    const response = await fetch('/admin-home/payment-gateways-json', {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      credentials: 'include',
      body: JSON.stringify(gateways),
    });

    if (!response.ok) {
      throw new Error(`Failed to update payment gateways (${response.status})`);
    }

    const payload = await response.json();
    return payload?.gateways;
  },

  // Immutable Audit Logs from PostgreSQL
  async getImmutableAuditLogs(): Promise<any[]> {
    const response = await fetch('/admin-home/audit-logs-json', {
      method: 'GET',
      headers: { 'Accept': 'application/json' },
      credentials: 'include',
    });

    if (!response.ok) {
      throw new Error(`Failed to load audit logs (${response.status})`);
    }

    const payload = await response.json();
    return Array.isArray(payload?.logs) ? payload.logs : [];
  }
};
