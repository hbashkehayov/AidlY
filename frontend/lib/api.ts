import axios, { AxiosError } from 'axios';

const API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api/v1';
const AUTH_API_URL = process.env.NEXT_PUBLIC_AUTH_API_URL || 'http://localhost:8001/api/v1/auth';
const AUTH_SERVICE_URL = 'http://localhost:8001/api/v1'; // Base URL for auth service (not just /auth)
const TICKET_API_URL = process.env.NEXT_PUBLIC_TICKET_API_URL || 'http://localhost:8002/api/v1';
const CLIENT_API_URL = process.env.NEXT_PUBLIC_CLIENT_API_URL || 'http://localhost:8003/api/v1';
const ANALYTICS_API_URL = process.env.NEXT_PUBLIC_ANALYTICS_API_URL || 'http://localhost:8007/api/v1';
const NOTIFICATION_API_URL = process.env.NEXT_PUBLIC_NOTIFICATION_API_URL || 'http://localhost:8004/api/v1';
const AI_API_URL = process.env.NEXT_PUBLIC_AI_API_URL || 'http://localhost:8006/api/v1';

// Create axios instances for each service
export const authApi = axios.create({
  baseURL: AUTH_API_URL,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Create a separate instance for user management endpoints
export const userApi = axios.create({
  baseURL: AUTH_SERVICE_URL,
  headers: {
    'Content-Type': 'application/json',
  },
});

export const ticketApi = axios.create({
  baseURL: TICKET_API_URL,
  headers: {
    'Content-Type': 'application/json',
  },
});

export const clientApi = axios.create({
  baseURL: CLIENT_API_URL,
  headers: {
    'Content-Type': 'application/json',
  },
});

export const analyticsApi = axios.create({
  baseURL: ANALYTICS_API_URL,
  headers: {
    'Content-Type': 'application/json',
  },
});

export const notificationApi = axios.create({
  baseURL: NOTIFICATION_API_URL,
  headers: {
    'Content-Type': 'application/json',
  },
});

export const aiApi = axios.create({
  baseURL: AI_API_URL,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Add request interceptor to add auth token
const addAuthInterceptor = (instance: any) => {
  instance.interceptors.request.use(
    (config) => {
      const token = localStorage.getItem('auth_token');
      if (token) {
        config.headers.Authorization = `Bearer ${token}`;
      }
      return config;
    },
    (error) => {
      return Promise.reject(error);
    }
  );

  // Add response interceptor for error handling
  instance.interceptors.response.use(
    (response) => response,
    async (error: AxiosError) => {
      if (error.response?.status === 401) {
        // Only redirect if we have a token but it's invalid
        // Don't redirect for endpoints that might work without auth
        const hasToken = localStorage.getItem('auth_token');
        const isAuthEndpoint = error.config?.url?.includes('/auth/');
        const isPublicEndpoint = error.config?.url?.includes('/public/');

        if (hasToken && !isAuthEndpoint && !isPublicEndpoint) {
          // Token expired or invalid - clear and redirect
          localStorage.removeItem('auth_token');
          localStorage.removeItem('user');
          window.location.href = '/auth/login';
        }
      }
      return Promise.reject(error);
    }
  );
};

// Apply auth interceptor to all instances
addAuthInterceptor(authApi);
addAuthInterceptor(userApi);
addAuthInterceptor(ticketApi);
addAuthInterceptor(clientApi);
addAuthInterceptor(analyticsApi);
addAuthInterceptor(notificationApi);
addAuthInterceptor(aiApi);

// API Methods
export const api = {
  // Auth
  auth: {
    login: (email: string, password: string) =>
      authApi.post('/login', { email, password }),
    register: (data: any) =>
      authApi.post('/register', data),
    logout: () =>
      authApi.post('/logout'),
    me: () =>
      authApi.get('/me'),
    refresh: () =>
      authApi.post('/refresh'),
    forgotPassword: (email: string) =>
      authApi.post('/forgot-password', { email }),
    resetPassword: (token: string, password: string) =>
      authApi.post('/reset-password', { token, password }),
    updateProfile: (data: { name?: string }) =>
      authApi.put('/update-profile', data),
    changePassword: (data: { current_password: string; new_password: string; new_password_confirmation: string }) =>
      authApi.post('/change-password', data),
  },

  // Tickets - Use authenticated routes when token is available, public as fallback
  tickets: {
    list: (params?: any) => {
      const hasToken = !!localStorage.getItem('auth_token');
      const endpoint = hasToken ? '/tickets' : '/public/tickets';
      return ticketApi.get(endpoint, { params });
    },
    get: (id: string) => {
      const hasToken = !!localStorage.getItem('auth_token');
      const endpoint = hasToken ? `/tickets/${id}` : `/public/tickets/${id}`;
      return ticketApi.get(endpoint);
    },
    create: (data: any) => {
      const hasToken = !!localStorage.getItem('auth_token');
      const endpoint = hasToken ? '/tickets' : '/public/tickets';
      return ticketApi.post(endpoint, data);
    },
    update: (id: string, data: any) => {
      const hasToken = !!localStorage.getItem('auth_token');
      const endpoint = hasToken ? `/tickets/${id}` : `/public/tickets/${id}`;
      return ticketApi.put(endpoint, data);
    },
    delete: (id: string) => {
      const hasToken = !!localStorage.getItem('auth_token');
      const endpoint = hasToken ? `/tickets/${id}` : `/public/tickets/${id}`;
      return ticketApi.delete(endpoint);
    },
    stats: () =>
      ticketApi.get('/tickets/stats'),
    assign: (id: string, agentId: string) => {
      const hasToken = !!localStorage.getItem('auth_token');
      const endpoint = hasToken ? `/tickets/${id}/assign` : `/public/tickets/${id}/assign`;
      return ticketApi.post(endpoint, { assigned_agent_id: agentId });
    },
    addComment: (id: string, content: string, isInternal?: boolean, clientEmail?: string) => {
      // ALWAYS use public endpoint to avoid authentication issues
      // The backend will handle authentication if present
      const endpoint = `/public/tickets/${id}/comments`;

      const payload: any = {
        content,
        is_internal_note: isInternal || false
      };

      // Include client email if provided
      if (clientEmail) {
        payload.client_email = clientEmail;
      }

      return ticketApi.post(endpoint, payload);
    },
    history: (id: string) =>
      ticketApi.get(`/tickets/${id}/history`),
  },

  // Statistics (Enhanced with Analytics Service)
  stats: {
    dashboard: () =>
      analyticsApi.get('/dashboard/stats'),
    trends: (params?: any) =>
      analyticsApi.get('/dashboard/trends', { params }),
    recent: () =>
      ticketApi.get('/stats/recent'),
    slaCompliance: (params?: any) =>
      analyticsApi.get('/dashboard/sla-compliance', { params }),
    agentPerformance: (params?: any) =>
      analyticsApi.get('/dashboard/agent-performance', { params }),
    activity: (params?: any) =>
      analyticsApi.get('/dashboard/activity', { params }),
  },

  // Notifications
  notifications: {
    counts: () =>
      ticketApi.get('/stats/notification-counts'),
    list: (params?: any) => {
      const user = typeof window !== 'undefined' ? JSON.parse(localStorage.getItem('user') || '{}') : {};
      const userId = user?.id;
      const userRole = user?.role;

      // DEBUG: Log what role we're reading
      console.log('[API DEBUG] notifications.list - user from localStorage:', user);
      console.log('[API DEBUG] notifications.list - userRole:', userRole);

      // Convert unread_only to proper boolean if present
      const processedParams = { ...params };
      if (processedParams.unread_only !== undefined) {
        processedParams.unread_only = processedParams.unread_only === true || processedParams.unread_only === 'true' ? 1 : 0;
      }
      if (processedParams.view_all !== undefined) {
        processedParams.view_all = processedParams.view_all === true || processedParams.view_all === 'true' ? 1 : 0;
      }

      // Always add user_role for proper authorization
      processedParams.user_role = userRole;
      processedParams.notifiable_type = 'user';

      console.log('[API DEBUG] notifications.list - processedParams:', processedParams);

      // Admin can view all notifications
      if (processedParams.view_all && userRole === 'admin') {
        return notificationApi.get('/notifications', {
          params: processedParams
        });
      }

      // Regular users see only their notifications
      if (!userId) return Promise.reject(new Error('User ID not found'));

      return notificationApi.get('/notifications', {
        params: {
          notifiable_id: userId,
          ...processedParams
        }
      });
    },
    unread: () => {
      const user = typeof window !== 'undefined' ? JSON.parse(localStorage.getItem('user') || '{}') : {};
      const userId = user?.id;
      if (!userId) return Promise.reject(new Error('User ID not found'));

      return notificationApi.get('/notifications/unread', {
        params: { user_id: userId }
      });
    },
    stats: (viewAll?: boolean) => {
      const user = typeof window !== 'undefined' ? JSON.parse(localStorage.getItem('user') || '{}') : {};
      const userId = user?.id;
      const userRole = user?.role;

      // Admin can view all stats
      if (viewAll && userRole === 'admin') {
        return notificationApi.get('/notifications/stats', {
          params: {
            notifiable_type: 'user',
            view_all: 1,
            user_role: userRole
          }
        });
      }

      // Regular users see only their stats
      if (!userId) return Promise.reject(new Error('User ID not found'));

      return notificationApi.get('/notifications/stats', {
        params: {
          notifiable_id: userId,
          notifiable_type: 'user',
          user_role: userRole
        }
      });
    },
    markAsRead: (id: string) => {
      const user = typeof window !== 'undefined' ? JSON.parse(localStorage.getItem('user') || '{}') : {};
      const userId = user?.id;
      if (!userId) return Promise.reject(new Error('User ID not found'));

      return notificationApi.post(`/notifications/${id}/read`, {
        notifiable_id: userId,
        notifiable_type: 'user'
      });
    },
    markAsUnread: (id: string) => {
      const user = typeof window !== 'undefined' ? JSON.parse(localStorage.getItem('user') || '{}') : {};
      const userId = user?.id;
      if (!userId) return Promise.reject(new Error('User ID not found'));

      return notificationApi.post(`/notifications/${id}/unread`, {
        notifiable_id: userId,
        notifiable_type: 'user'
      });
    },
    markMultipleAsRead: (notificationIds: string[]) => {
      const user = typeof window !== 'undefined' ? JSON.parse(localStorage.getItem('user') || '{}') : {};
      const userId = user?.id;
      if (!userId) return Promise.reject(new Error('User ID not found'));

      return notificationApi.post('/notifications/mark-read', {
        user_id: userId,
        notification_ids: notificationIds
      });
    },
    delete: (id: string) => {
      const user = typeof window !== 'undefined' ? JSON.parse(localStorage.getItem('user') || '{}') : {};
      const userId = user?.id;
      if (!userId) return Promise.reject(new Error('User ID not found'));

      return notificationApi.delete(`/notifications/${id}`, {
        params: { user_id: userId }
      });
    },
    // Webhook for ticket assignment
    notifyTicketAssigned: (payload: {
      ticket_id: string;
      ticket_number: string;
      subject: string;
      priority: string;
      customer_name: string;
      assigned_to_id: string;
      assigned_to_name: string;
      assigned_to_email: string;
      assigned_by?: string;
    }) =>
      notificationApi.post('/webhooks/ticket-assigned', payload),
  },

  // Clients
  clients: {
    list: (params?: any) =>
      clientApi.get('/clients', { params }),
    get: (id: string) =>
      clientApi.get(`/clients/${id}`),
    create: (data: any) =>
      clientApi.post('/clients', data),
    update: (id: string, data: any) =>
      clientApi.put(`/clients/${id}`, data),
    delete: (id: string) =>
      clientApi.delete(`/clients/${id}`),
    tickets: (id: string) =>
      clientApi.get(`/clients/${id}/tickets`),
    merge: (primaryId: string, mergeId: string) =>
      clientApi.post('/clients/merge', { primary_id: primaryId, merge_id: mergeId }),
    notes: {
      list: (clientId: string) =>
        clientApi.get(`/clients/${clientId}/notes`),
      create: (clientId: string, note: string) =>
        clientApi.post(`/clients/${clientId}/notes`, { note }),
      update: (clientId: string, noteId: string, note: string) =>
        clientApi.put(`/clients/${clientId}/notes/${noteId}`, { note }),
      delete: (clientId: string, noteId: string) =>
        clientApi.delete(`/clients/${clientId}/notes/${noteId}`),
      pin: (clientId: string, noteId: string) =>
        clientApi.post(`/clients/${clientId}/notes/${noteId}/pin`),
    },
  },

  // Messages (ticket comments)
  messages: {
    list: (params?: any) =>
      ticketApi.get('/comments', { params }),
    get: (id: string) =>
      ticketApi.get(`/comments/${id}`),
    create: (data: any) =>
      ticketApi.post('/comments', data),
    update: (id: string, data: any) =>
      ticketApi.put(`/comments/${id}`, data),
    delete: (id: string) =>
      ticketApi.delete(`/comments/${id}`),
    byTicket: (ticketId: string, params?: any) =>
      ticketApi.get(`/tickets/${ticketId}/comments`, { params }),
    markRead: (id: string) =>
      ticketApi.post(`/public/comments/${id}/read`),
    reply: (ticketId: string, content: string, isInternal?: boolean) =>
      ticketApi.post(`/tickets/${ticketId}/comments`, {
        content,
        is_internal_note: isInternal || false
      }),
  },

  // Categories
  categories: {
    list: () =>
      ticketApi.get('/categories'),
    tree: () =>
      ticketApi.get('/categories/tree'),
    get: (id: string) =>
      ticketApi.get(`/categories/${id}`),
    create: (data: any) =>
      ticketApi.post('/categories', data),
    update: (id: string, data: any) =>
      ticketApi.put(`/categories/${id}`, data),
    delete: (id: string) =>
      ticketApi.delete(`/categories/${id}`),
  },

  // Users
  users: {
    list: (params?: any) =>
      userApi.get('/users', { params }),
    listAssignable: () =>
      userApi.get('/users/assignable'),
    get: (id: string) =>
      userApi.get(`/users/${id}`),
    create: (data: any) =>
      userApi.post('/users', data),
    update: (id: string, data: any) =>
      userApi.put(`/users/${id}`, data),
    delete: (id: string) =>
      userApi.delete(`/users/${id}`),
    activate: (id: string) =>
      userApi.post(`/users/${id}/activate`),
    deactivate: (id: string) =>
      userApi.post(`/users/${id}/deactivate`),
  },

  // Analytics & Reports
  analytics: {
    // Dashboard analytics
    dashboard: {
      stats: (params?: any) =>
        analyticsApi.get('/dashboard/stats', { params }),
      trends: (params?: any) =>
        analyticsApi.get('/dashboard/trends', { params }),
      activity: (params?: any) =>
        analyticsApi.get('/dashboard/activity', { params }),
      slaCompliance: (params?: any) =>
        analyticsApi.get('/dashboard/sla-compliance', { params }),
      agentPerformance: (params?: any) =>
        analyticsApi.get('/dashboard/agent-performance', { params }),
    },

    // Agent-specific dashboard
    agent: {
      queue: (agentId?: string) => {
        const user = typeof window !== 'undefined' ? JSON.parse(localStorage.getItem('user') || '{}') : {};
        const id = agentId || user?.id;
        console.log('[API] agent.queue - user:', user, 'id:', id);
        return analyticsApi.get('/dashboard/agent-queue', {
          params: { agent_id: id }
        });
      },
      stats: (agentId?: string) => {
        const user = typeof window !== 'undefined' ? JSON.parse(localStorage.getItem('user') || '{}') : {};
        const id = agentId || user?.id;
        console.log('[API] agent.stats - user:', user, 'id:', id);
        return analyticsApi.get('/dashboard/agent-stats', {
          params: { agent_id: id }
        });
      },
      activity: (agentId?: string, limit?: number) => {
        const user = typeof window !== 'undefined' ? JSON.parse(localStorage.getItem('user') || '{}') : {};
        const id = agentId || user?.id;
        console.log('[API] agent.activity - user:', user, 'id:', id);
        return analyticsApi.get('/dashboard/agent-activity', {
          params: { agent_id: id, limit }
        });
      },
      productivity: (agentId?: string) => {
        const user = typeof window !== 'undefined' ? JSON.parse(localStorage.getItem('user') || '{}') : {};
        const id = agentId || user?.id;
        console.log('[API] agent.productivity - user:', user, 'id:', id);
        return analyticsApi.get('/dashboard/agent-productivity', {
          params: { agent_id: id }
        });
      },
      replies: (agentId?: string, limit?: number) => {
        const user = typeof window !== 'undefined' ? JSON.parse(localStorage.getItem('user') || '{}') : {};
        const id = agentId || user?.id;
        console.log('[API] agent.replies - user:', user, 'id:', id);
        return analyticsApi.get('/dashboard/agent-replies', {
          params: { agent_id: id, limit }
        });
      },
    },

    // Reports
    reports: {
      list: (params?: any) =>
        analyticsApi.get('/reports', { params }),
      get: (id: string) =>
        analyticsApi.get(`/reports/${id}`),
      create: (data: any) =>
        analyticsApi.post('/reports', data),
      update: (id: string, data: any) =>
        analyticsApi.put(`/reports/${id}`, data),
      delete: (id: string) =>
        analyticsApi.delete(`/reports/${id}`),
      execute: (id: string, params?: any) =>
        analyticsApi.post(`/reports/${id}/execute`, params),
      schedule: (id: string, scheduleData: any) =>
        analyticsApi.post(`/reports/${id}/schedule`, scheduleData),
      executions: (id: string) =>
        analyticsApi.get(`/reports/${id}/executions`),
    },

    // Exports
    exports: {
      reports: async (format: 'excel' | 'pdf', params?: any) => {
        const response = await analyticsApi.post('/exports/reports',
          { format, ...params },
          { responseType: 'blob' }
        );
        return response;
      },
      tickets: (params: any) =>
        analyticsApi.post('/exports/tickets', params),
      agents: (params: any) =>
        analyticsApi.post('/exports/agents', params),
      custom: (query: string, params?: any) =>
        analyticsApi.post('/exports/custom', { query, ...params }),
      status: (executionId: string) =>
        analyticsApi.get(`/exports/${executionId}/status`),
      download: (executionId: string) =>
        `${ANALYTICS_API_URL}/exports/${executionId}/download`,
    },

    // Events
    events: {
      track: (eventData: any) =>
        analyticsApi.post('/events', eventData),
      trackBatch: (events: any[]) =>
        analyticsApi.post('/events/batch', { events }),
      types: () =>
        analyticsApi.get('/events/types'),
      statistics: (params?: any) =>
        analyticsApi.get('/events/statistics', { params }),
    },

    // Real-time data
    realtime: {
      currentStats: () =>
        analyticsApi.get('/realtime/current-stats'),
      activeAgents: () =>
        analyticsApi.get('/realtime/active-agents'),
      queueStatus: () =>
        analyticsApi.get('/realtime/queue-status'),
    },

    // Metrics
    metrics: {
      ticketMetrics: (params?: any) =>
        analyticsApi.get('/metrics/ticket-metrics', { params }),
      agentMetrics: (params?: any) =>
        analyticsApi.get('/metrics/agent-metrics', { params }),
      clientMetrics: (params?: any) =>
        analyticsApi.get('/metrics/client-metrics', { params }),
    },
  },

  // AI Integration
  ai: {
    autoWrite: (data: {
      ticket_id: string;
      context?: string;
      tone?: 'professional' | 'friendly' | 'empathetic' | 'formal';
      length?: 'short' | 'medium' | 'long';
    }) =>
      aiApi.post('/process/auto-write', data),
    categorize: (data: { subject: string; description: string; categories?: string[] }) =>
      aiApi.post('/process/ticket/categorize', data),
    prioritize: (data: { subject: string; description: string }) =>
      aiApi.post('/process/ticket/prioritize', data),
    suggestResponse: (data: { ticket_id: string; ticket: any; context?: any }) =>
      aiApi.post('/process/ticket/suggest-response', data),
    analyzeSentiment: (data: { text: string }) =>
      aiApi.post('/process/ticket/analyze-sentiment', data),
    summarize: (data: { text: string }) =>
      aiApi.post('/process/ticket/summarize', data),
  },
};

export default api;