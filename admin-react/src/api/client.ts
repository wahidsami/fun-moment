/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { Language, UserRole } from '../types';
import { ApiResponse, ApiError } from '../types/contracts';

/**
 * FUN MOMENT Frontend API Client Helper
 * Handles communication headers with Laravel backend, auth token caching,
 * localization injection, and standard response transformations.
 */
class ApiClient {
  private baseUrl: string;
  private token: string | null = null;

  constructor() {
    // Configured for local proxying / api base url
    this.baseUrl = '/api/v2';
  }

  /**
   * Auth Token Handling
   */
  public getToken(): string | null {
    return this.token;
  }

  public setToken(token: string): void {
    this.token = token;
  }

  public clearToken(): void {
    this.token = null;
  }

  /**
   * Role and Permissions validation
   */
  public hasPermission(userPermissions: string[], requiredPermission: string): boolean {
    return userPermissions.includes(requiredPermission) || userPermissions.includes('*');
  }

  public hasRole(userRole: UserRole, allowedRoles: UserRole[]): boolean {
    return allowedRoles.includes(userRole);
  }

  /**
   * Header generator incorporating active session token, locale, and layout direction
   */
  private getHeaders(language: Language): HeadersInit {
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'Accept-Language': language,
      'X-App-Locale': language,
      'X-Layout-Direction': language === 'ar' ? 'rtl' : 'ltr',
    };

    const token = this.getToken();
    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }

    return headers;
  }

  /**
   * Transform standard fetch responses or format uniform mock exceptions
   */
  private async handleResponse<T>(response: Response, language: Language): Promise<ApiResponse<T>> {
    if (!response.ok) {
      let errorData: Partial<ApiError> = {};
      try {
        errorData = await response.json();
      } catch {
        // Fallback for non-JSON or raw error codes
      }

      const formattedError: ApiError = {
        success: false,
        error_code: errorData.error_code || `HTTP_${response.status}`,
        message_en: errorData.message_en || `A server error occurred (Status ${response.status})`,
        message_ar: errorData.message_ar || `حدث خطأ في الاتصال بالخادم (رمز الحالة ${response.status})`,
        errors: errorData.errors,
      };

      throw formattedError;
    }

    const payload = await response.json();
    return payload as ApiResponse<T>;
  }

  /**
   * Core request wrappers simulating network calls to Laravel
   */
  public async get<T>(endpoint: string, language: Language = 'en'): Promise<ApiResponse<T>> {
    try {
      const response = await fetch(`${this.baseUrl}${endpoint}`, {
        method: 'GET',
        headers: this.getHeaders(language),
      });
      return await this.handleResponse<T>(response, language);
    } catch (error) {
      return this.handleLocalOrNetworkError<T>(error, language);
    }
  }

  public async post<T, P>(endpoint: string, payload: P, language: Language = 'en'): Promise<ApiResponse<T>> {
    try {
      const response = await fetch(`${this.baseUrl}${endpoint}`, {
        method: 'POST',
        headers: this.getHeaders(language),
        body: JSON.stringify(payload),
      });
      return await this.handleResponse<T>(response, language);
    } catch (error) {
      return this.handleLocalOrNetworkError<T>(error, language);
    }
  }

  public async put<T, P>(endpoint: string, payload: P, language: Language = 'en'): Promise<ApiResponse<T>> {
    try {
      const response = await fetch(`${this.baseUrl}${endpoint}`, {
        method: 'PUT',
        headers: this.getHeaders(language),
        body: JSON.stringify(payload),
      });
      return await this.handleResponse<T>(response, language);
    } catch (error) {
      return this.handleLocalOrNetworkError<T>(error, language);
    }
  }

  public async delete<T>(endpoint: string, language: Language = 'en'): Promise<ApiResponse<T>> {
    try {
      const response = await fetch(`${this.baseUrl}${endpoint}`, {
        method: 'DELETE',
        headers: this.getHeaders(language),
      });
      return await this.handleResponse<T>(response, language);
    } catch (error) {
      return this.handleLocalOrNetworkError<T>(error, language);
    }
  }

  /**
   * Helper fallback when network fails or API path is not yet built
   */
  private handleLocalOrNetworkError<T>(error: any, language: Language): Promise<ApiResponse<T>> {
    if (error && 'success' in error && error.success === false) {
      throw error;
    }

    const networkError: ApiError = {
      success: false,
      error_code: 'CONNECTION_FAILED',
      message_en: 'Backend API endpoint not responding or offline.',
      message_ar: 'قناة الاتصال بالواجهة الخلفية معطلة أو غير متوفرة حالياً.',
    };

    throw networkError;
  }
}

export const apiClient = new ApiClient();
