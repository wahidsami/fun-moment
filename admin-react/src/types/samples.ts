/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

/**
 * FUN MOMENT Blueprint Contracts
 * Sample request and response payloads representing the exact wire-format
 * expected between the React frontend and the Laravel API v2 backend.
 */

export const SAMPLES = {
  /**
   * 1. GET /api/v2/admin/dashboard-summary
   */
  dashboardSummaryResponse: {
    success: true,
    data: {
      revenue: {
        total_sar: 142500.50,
        growth_percentage: 12.4,
        chart_series: [
          { date: "2026-06-20", amount: 15400 },
          { date: "2026-06-21", amount: 18200 },
          { date: "2026-06-22", amount: 12100 },
          { date: "2026-06-23", amount: 20400 }
        ]
      },
      orders: {
        total_count: 850,
        growth_percentage: 8.2,
        pending_count: 34,
        completed_count: 780
      },
      services: {
        total_count: 145,
        growth_percentage: 3.1,
        pending_approval: 12
      },
      users: {
        total_count: 3120,
        growth_percentage: 15.6,
        sellers_count: 240,
        buyers_count: 2880
      },
      category_distribution: [
        { category_id: 1, name_en: "Events", name_ar: "فعاليات", percentage: 45, order_count: 382 },
        { category_id: 2, name_en: "Workshops", name_ar: "ورش عمل", percentage: 35, order_count: 297 },
        { category_id: 3, name_en: "Rentals", name_ar: "تأجير مستلزمات", percentage: 20, order_count: 171 }
      ],
      system_health: {
        laravel_uptime: "24 days, 3 hours",
        db_connection_status: "healthy",
        active_jobs: 0
      }
    },
    message_en: "Dashboard analytics compiled successfully",
    message_ar: "تم تجميع إحصاءات لوحة القيادة بنجاح",
    meta: {
      timestamp: "2026-06-28T20:15:00Z",
      api_version: "v2.0.0",
      execution_time_ms: 45,
      locale: "en",
      direction: "ltr"
    }
  },

  /**
   * 2. GET /api/v2/admin/orders/1024
   */
  orderDetailResponse: {
    success: true,
    data: {
      id: 1024,
      service_id: 45,
      service_title_en: "Desert Safari Camp VIP Experience",
      service_title_ar: "تجربة مخيم سفاري الصحراء لكبار الشخصيات",
      buyer_id: 88,
      buyer_name: "Fahad Al-Harbi",
      buyer_phone: "+966501234567",
      buyer_email: "fahad@example.sa",
      seller_id: 12,
      seller_name: "Saudi Tourism Hub Ltd",
      seller_phone: "+966507654321",
      seller_email: "info@sauditourism.sa",
      amount: 1200.00,
      payment_method: "credit_card",
      transaction_reference: "pay_98218a20b",
      status: "in_progress",
      payment_status: "paid",
      notes_en: "Require customized Arabic coffee and dates on arrival.",
      notes_ar: "يرجى توفير قهوة عربية وتمر مخصصين عند الوصول.",
      created_at: "2026-06-25T14:30:00Z",
      logs: [
        { action_en: "Order created", action_ar: "تم إنشاء الطلب", created_at: "2026-06-25T14:30:00Z", actor_name: "Fahad Al-Harbi" },
        { action_en: "Payment authorized via STC Pay", action_ar: "تم تفويض الدفع عبر إس تي سي باي", created_at: "2026-06-25T14:31:05Z", actor_name: "Payment Gateway" }
      ]
    },
    message_en: "Order retrieved successfully",
    message_ar: "تم استرداد الطلب بنجاح"
  },

  /**
   * 3. POST /api/v2/admin/services
   */
  serviceCreatePayload: {
    title_en: "Jeddah Yacht Cruise Elite",
    title_ar: "رحلة يخت جدة النخبة",
    category_id: 1,
    subcategory_id: 4,
    child_category_id: 12,
    price: 3500.00,
    duration: "4 Hours",
    description_en: "Luxury yacht cruise with live entertainment and traditional dinner.",
    description_ar: "رحلة يخت فاخرة مع ترفيه حي وعشاء تقليدي مميز.",
    gallery_urls: [
      "https://example.com/yacht1.jpg",
      "https://example.com/yacht2.jpg"
    ],
    terms_en: "Cancellation allowed 48h before cruise.",
    terms_ar: "يسمح بالإلغاء قبل 48 ساعة من موعد الرحلة.",
    max_capacity: 12
  },

  /**
   * 4. GET /api/v2/addons/wallet/summary
   */
  walletSummaryResponse: {
    success: true,
    data: {
      module_enabled: true,
      total_escrow_balance: 384000.00,
      total_withdrawn_amount: 1548000.00,
      recent_transactions: [
        {
          id: 4920,
          user_id: 12,
          user_name: "Saudi Tourism Hub Ltd",
          type: "payout",
          amount: 12400.00,
          currency: "SAR",
          status: "completed",
          created_at: "2026-06-28T10:00:00Z",
          description_en: "Monthly merchant payout remittance",
          description_ar: "حوالة دفعات التاجر الشهرية"
        },
        {
          id: 4921,
          user_id: 88,
          user_name: "Fahad Al-Harbi",
          type: "booking_escrow",
          amount: 1200.00,
          currency: "SAR",
          status: "pending",
          created_at: "2026-06-25T14:31:05Z",
          description_en: "Escrow hold for safari booking #1024",
          description_ar: "حجز الضمان لرحلة السفاري رقم #1024"
        }
      ]
    },
    message_en: "Wallet telemetry compiled successfully",
    message_ar: "تم تجميع بيانات المحفظة بنجاح"
  }
};
