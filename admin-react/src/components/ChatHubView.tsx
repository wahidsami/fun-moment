import React, { useState, useEffect } from 'react';
import {
  MessageSquare,
  Search,
  RefreshCw,
  Archive,
  CheckCircle,
  Eye,
  X,
  User,
  Clock,
  ShieldAlert,
  Send,
  Calendar,
  Layers,
  ArrowRight,
  ExternalLink,
} from 'lucide-react';
import { LaravelAPI } from '../api';
import { Language, UserRole, ChatConversationItem, ChatHubSummary, ChatConversationDetail } from '../types';
import { checkPermission } from '../utils/auditLogger';

interface ChatHubViewProps {
  language: Language;
  activeRole: UserRole;
}

export default function ChatHubView({ language, activeRole }: ChatHubViewProps) {
  const isRtl = language === 'ar';
  const canModerate = checkPermission(activeRole, 'manage_support');

  const [loading, setLoading] = useState<boolean>(true);
  const [conversations, setConversations] = useState<ChatConversationItem[]>([]);
  const [summary, setSummary] = useState<ChatHubSummary | null>(null);
  const [search, setSearch] = useState<string>('');
  const [statusFilter, setStatusFilter] = useState<'all' | 'active' | 'archived'>('all');

  // Inspection drawer state
  const [selectedConversationId, setSelectedConversationId] = useState<number | null>(null);
  const [detailLoading, setDetailLoading] = useState<boolean>(false);
  const [conversationDetail, setConversationDetail] = useState<ChatConversationDetail | null>(null);

  const fetchChatHubData = async () => {
    try {
      setLoading(true);
      const data = await LaravelAPI.getChatConversations(search, statusFilter);
      setSummary(data.summary);
      setConversations(data.conversations || []);
    } catch (err) {
      console.error('Failed to load chat hub data:', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchChatHubData();
  }, [statusFilter]);

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    fetchChatHubData();
  };

  const handleInspect = async (id: number) => {
    setSelectedConversationId(id);
    try {
      setDetailLoading(true);
      const detail = await LaravelAPI.getChatConversationDetails(id);
      setConversationDetail(detail);
    } catch (err) {
      console.error('Failed to inspect conversation:', err);
    } finally {
      setDetailLoading(false);
    }
  };

  const handleToggleStatus = async (id: number, currentStatus: 'active' | 'archived') => {
    const nextStatus = currentStatus === 'active' ? 'archived' : 'active';
    try {
      await LaravelAPI.updateChatConversationStatus(id, nextStatus);
      // Update locally
      setConversations(prev =>
        prev.map(c => (c.id === id ? { ...c, status: nextStatus } : c))
      );
      if (conversationDetail && conversationDetail.conversation.id === id) {
        setConversationDetail({
          ...conversationDetail,
          conversation: {
            ...conversationDetail.conversation,
            status: nextStatus,
          },
        });
      }
    } catch (err) {
      console.error('Failed to update status:', err);
    }
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <div className="flex items-center gap-3">
            <div className="p-2.5 bg-indigo-50 text-indigo-600 rounded-xl">
              <MessageSquare className="h-6 w-6" />
            </div>
            <div>
              <h1 className="text-xl font-black text-slate-900 tracking-tight">
                {isRtl ? 'مركز المحادثات الحية (Live Chat Hub)' : 'Live Chat Hub'}
              </h1>
              <p className="text-xs text-slate-500 font-medium">
                {isRtl
                  ? 'مراقبة وإشراف على رسائل المشترين ومقدمي الخدمات بشكل حي وفوري'
                  : 'Real-time buyer & seller conversation control plane & moderation'}
              </p>
            </div>
          </div>
        </div>

        <button
          onClick={fetchChatHubData}
          className="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-bold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition shadow-xs"
        >
          <RefreshCw className={`h-3.5 w-3.5 ${loading ? 'animate-spin' : ''}`} />
          {isRtl ? 'تحديث البيانات' : 'Refresh'}
        </button>
      </div>

      {/* KPI Cards */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div className="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs">
          <div className="flex items-center justify-between text-slate-500 mb-1">
            <span className="text-xs font-bold uppercase tracking-wider">{isRtl ? 'إجمالي المحادثات' : 'Conversations'}</span>
            <Layers className="h-4 w-4 text-indigo-500" />
          </div>
          <div className="text-2xl font-black text-slate-900">{summary?.total_conversations ?? 0}</div>
          <span className="text-[11px] text-slate-400 font-medium">{isRtl ? 'قنوات اتصال مسجلة' : 'Recorded channels'}</span>
        </div>

        <div className="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs">
          <div className="flex items-center justify-between text-slate-500 mb-1">
            <span className="text-xs font-bold uppercase tracking-wider">{isRtl ? 'إجمالي الرسائل' : 'Total Messages'}</span>
            <MessageSquare className="h-4 w-4 text-emerald-500" />
          </div>
          <div className="text-2xl font-black text-slate-900">{summary?.total_messages ?? 0}</div>
          <span className="text-[11px] text-slate-400 font-medium">{isRtl ? 'رسالة متبادلة' : 'Exchanged messages'}</span>
        </div>

        <div className="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs">
          <div className="flex items-center justify-between text-slate-500 mb-1">
            <span className="text-xs font-bold uppercase tracking-wider">{isRtl ? 'رسائل اليوم' : 'Sent Today'}</span>
            <Calendar className="h-4 w-4 text-amber-500" />
          </div>
          <div className="text-2xl font-black text-slate-900">{summary?.messages_today ?? 0}</div>
          <span className="text-[11px] text-slate-400 font-medium">{isRtl ? 'نشاط الساعات الأخيرة' : 'Past 24h activity'}</span>
        </div>

        <div className="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs">
          <div className="flex items-center justify-between text-slate-500 mb-1">
            <span className="text-xs font-bold uppercase tracking-wider">{isRtl ? 'القنوات النشطة' : 'Active Channels'}</span>
            <CheckCircle className="h-4 w-4 text-teal-500" />
          </div>
          <div className="text-2xl font-black text-slate-900">{summary?.active_conversations ?? 0}</div>
          <span className="text-[11px] text-slate-400 font-medium">{isRtl ? 'محادثات قيد التداول' : 'Under active dialogue'}</span>
        </div>
      </div>

      {/* Filter and Search Bar */}
      <div className="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs">
        <form onSubmit={handleSearchSubmit} className="flex flex-col md:flex-row gap-3 items-center justify-between">
          <div className="relative flex-1 w-full">
            <Search className="absolute left-3.5 rtl:left-auto rtl:right-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
            <input
              type="text"
              value={search}
              onChange={e => setSearch(e.target.value)}
              placeholder={isRtl ? 'بحث باسم المشتري، البائع، أو البريد الإلكتروني...' : 'Search by buyer or seller name, email...'}
              className="w-full pl-10 pr-4 rtl:pl-4 rtl:pr-10 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
            />
          </div>

          <div className="flex items-center gap-2 w-full md:w-auto">
            <div className="inline-flex rounded-xl bg-slate-100 p-1 border border-slate-200/60 text-xs">
              <button
                type="button"
                onClick={() => setStatusFilter('all')}
                className={`px-3 py-1.5 rounded-lg font-bold transition ${statusFilter === 'all' ? 'bg-white text-indigo-600 shadow-xs' : 'text-slate-600 hover:text-slate-900'}`}
              >
                {isRtl ? 'الكل' : 'All'}
              </button>
              <button
                type="button"
                onClick={() => setStatusFilter('active')}
                className={`px-3 py-1.5 rounded-lg font-bold transition ${statusFilter === 'active' ? 'bg-white text-indigo-600 shadow-xs' : 'text-slate-600 hover:text-slate-900'}`}
              >
                {isRtl ? 'نشطة' : 'Active'}
              </button>
              <button
                type="button"
                onClick={() => setStatusFilter('archived')}
                className={`px-3 py-1.5 rounded-lg font-bold transition ${statusFilter === 'archived' ? 'bg-white text-indigo-600 shadow-xs' : 'text-slate-600 hover:text-slate-900'}`}
              >
                {isRtl ? 'مؤرشفة' : 'Archived'}
              </button>
            </div>

            <button
              type="submit"
              className="px-4 py-2 text-xs font-bold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 transition shadow-xs"
            >
              {isRtl ? 'بحث' : 'Search'}
            </button>
          </div>
        </form>
      </div>

      {/* Conversations Table */}
      <div className="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left rtl:text-right text-xs">
            <thead className="bg-slate-50/80 border-b border-slate-200/80 text-slate-500 uppercase tracking-wider font-bold">
              <tr>
                <th className="py-3.5 px-4">{isRtl ? 'المحادثة / المعرف' : 'Channel / ID'}</th>
                <th className="py-3.5 px-4">{isRtl ? 'المشتري (Buyer)' : 'Buyer'}</th>
                <th className="py-3.5 px-4">{isRtl ? 'مقدم الخدمة (Seller)' : 'Seller'}</th>
                <th className="py-3.5 px-4">{isRtl ? 'آخر رسالة' : 'Last Message'}</th>
                <th className="py-3.5 px-4 text-center">{isRtl ? 'الرسائل' : 'Messages'}</th>
                <th className="py-3.5 px-4">{isRtl ? 'الحالة' : 'Status'}</th>
                <th className="py-3.5 px-4 text-right rtl:text-left">{isRtl ? 'الإجراءات' : 'Actions'}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {loading ? (
                <tr>
                  <td colSpan={7} className="py-12 text-center text-slate-400">
                    <RefreshCw className="h-6 w-6 animate-spin mx-auto mb-2 text-indigo-500" />
                    {isRtl ? 'جارٍ تحميل المحادثات...' : 'Loading active conversations...'}
                  </td>
                </tr>
              ) : conversations.length === 0 ? (
                <tr>
                  <td colSpan={7} className="py-12 text-center text-slate-400">
                    <MessageSquare className="h-8 w-8 mx-auto mb-2 text-slate-300" />
                    {isRtl ? 'لا توجد محادثات مطابقة للمحددات.' : 'No conversations found matching filters.'}
                  </td>
                </tr>
              ) : (
                conversations.map(conv => (
                  <tr key={conv.id} className="hover:bg-slate-50/60 transition group">
                    <td className="py-3.5 px-4 font-mono font-bold text-indigo-600">
                      #{conv.id}
                    </td>
                    <td className="py-3.5 px-4">
                      <div className="font-bold text-slate-800">{conv.buyer.name}</div>
                      <div className="text-[11px] text-slate-400">{conv.buyer.email}</div>
                    </td>
                    <td className="py-3.5 px-4">
                      <div className="font-bold text-slate-800">{conv.seller.name}</div>
                      <div className="text-[11px] text-slate-400">{conv.seller.email}</div>
                    </td>
                    <td className="py-3.5 px-4 max-w-xs">
                      <div className="truncate text-slate-600 font-medium">
                        {conv.last_message || <span className="text-slate-300 italic">{isRtl ? 'لا توجد رسائل' : 'Empty'}</span>}
                      </div>
                      <div className="text-[10px] text-slate-400 mt-0.5">{conv.last_message_at}</div>
                    </td>
                    <td className="py-3.5 px-4 text-center">
                      <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 text-slate-700">
                        {conv.total_messages}
                      </span>
                    </td>
                    <td className="py-3.5 px-4">
                      <span
                        className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider ${
                          conv.status === 'active'
                            ? 'bg-emerald-50 text-emerald-700 border border-emerald-200/60'
                            : 'bg-slate-100 text-slate-500 border border-slate-200'
                        }`}
                      >
                        <span className={`h-1.5 w-1.5 rounded-full ${conv.status === 'active' ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'}`} />
                        {conv.status === 'active' ? (isRtl ? 'نشطة' : 'Active') : (isRtl ? 'مؤرشفة' : 'Archived')}
                      </span>
                    </td>
                    <td className="py-3.5 px-4 text-right rtl:text-left">
                      <div className="flex items-center justify-end rtl:justify-start gap-1.5">
                        <button
                          onClick={() => handleInspect(conv.id)}
                          className="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-bold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition"
                          title={isRtl ? 'معاينة المحادثة' : 'Inspect'}
                        >
                          <Eye className="h-3.5 w-3.5" />
                          <span>{isRtl ? 'معاينة' : 'Inspect'}</span>
                        </button>

                        {canModerate && (
                          <button
                            onClick={() => handleToggleStatus(conv.id, conv.status)}
                            className={`p-1.5 rounded-lg border transition ${
                              conv.status === 'active'
                                ? 'border-amber-200 text-amber-600 hover:bg-amber-50'
                                : 'border-emerald-200 text-emerald-600 hover:bg-emerald-50'
                            }`}
                            title={conv.status === 'active' ? (isRtl ? 'أرشفة' : 'Archive') : (isRtl ? 'استعادة' : 'Reactivate')}
                          >
                            <Archive className="h-3.5 w-3.5" />
                          </button>
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

      {/* Inspection Drawer */}
      {selectedConversationId && (
        <div className="fixed inset-0 z-50 overflow-hidden bg-slate-900/60 backdrop-blur-xs flex justify-end">
          <div className="w-full max-w-xl bg-white h-full shadow-2xl flex flex-col animate-in slide-in-from-right duration-200">
            {/* Drawer Header */}
            <div className="p-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
              <div className="flex items-center gap-2.5">
                <div className="p-2 bg-indigo-100 text-indigo-700 rounded-xl">
                  <MessageSquare className="h-5 w-5" />
                </div>
                <div>
                  <h3 className="text-sm font-black text-slate-900">
                    {isRtl ? 'معاينة سجل المحادثة' : 'Conversation Transcript'} #{selectedConversationId}
                  </h3>
                  <p className="text-[11px] text-slate-500">
                    {conversationDetail
                      ? `${conversationDetail.conversation.buyer.name} ↔ ${conversationDetail.conversation.seller.name}`
                      : isRtl ? 'جارٍ التحميل...' : 'Loading...'}
                  </p>
                </div>
              </div>

              <button
                onClick={() => {
                  setSelectedConversationId(null);
                  setConversationDetail(null);
                }}
                className="p-1.5 text-slate-400 hover:text-slate-700 rounded-lg hover:bg-slate-200 transition"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            {/* Participants Bar */}
            {conversationDetail && (
              <div className="px-4 py-3 bg-slate-100/60 border-b border-slate-200/80 grid grid-cols-2 gap-3 text-xs">
                <div className="p-2 bg-white rounded-xl border border-slate-200 shadow-2xs">
                  <span className="text-[10px] font-bold text-indigo-500 uppercase tracking-wider block">{isRtl ? 'المشتري' : 'Buyer'}</span>
                  <span className="font-black text-slate-800">{conversationDetail.conversation.buyer.name}</span>
                  <span className="text-[11px] text-slate-400 block truncate">{conversationDetail.conversation.buyer.email}</span>
                </div>

                <div className="p-2 bg-white rounded-xl border border-slate-200 shadow-2xs">
                  <span className="text-[10px] font-bold text-emerald-600 uppercase tracking-wider block">{isRtl ? 'مقدم الخدمة' : 'Seller'}</span>
                  <span className="font-black text-slate-800">{conversationDetail.conversation.seller.name}</span>
                  <span className="text-[11px] text-slate-400 block truncate">{conversationDetail.conversation.seller.email}</span>
                </div>
              </div>
            )}

            {/* Messages Scroll Area */}
            <div className="flex-1 overflow-y-auto p-4 space-y-3 bg-slate-50/50">
              {detailLoading ? (
                <div className="py-20 text-center text-slate-400">
                  <RefreshCw className="h-6 w-6 animate-spin mx-auto mb-2 text-indigo-500" />
                  {isRtl ? 'جارٍ جلب الرسائل...' : 'Loading transcript...'}
                </div>
              ) : conversationDetail?.messages.length === 0 ? (
                <div className="py-20 text-center text-slate-400 text-xs">
                  {isRtl ? 'لا توجد رسائل مسجلة في هذه القناة.' : 'No messages in this conversation yet.'}
                </div>
              ) : (
                conversationDetail?.messages.map(msg => {
                  const isBuyer = msg.sender_role === 'buyer';
                  return (
                    <div
                      key={msg.id}
                      className={`flex flex-col ${isBuyer ? 'items-start' : 'items-end'}`}
                    >
                      <div className="flex items-center gap-1.5 mb-1 px-1">
                        <span
                          className={`text-[9px] font-black uppercase tracking-wider px-1.5 py-0.5 rounded ${
                            isBuyer ? 'bg-indigo-100 text-indigo-700' : 'bg-emerald-100 text-emerald-800'
                          }`}
                        >
                          {isBuyer ? (isRtl ? 'مشتري' : 'Buyer') : (isRtl ? 'بائع' : 'Seller')}
                        </span>
                        <span className="text-[11px] font-bold text-slate-700">{msg.sender_name}</span>
                        <span className="text-[10px] text-slate-400">{msg.time_str}</span>
                      </div>

                      <div
                        className={`max-w-[85%] rounded-2xl p-3 text-xs leading-relaxed shadow-2xs ${
                          isBuyer
                            ? 'bg-white border border-indigo-100 text-slate-800 rounded-tl-xs'
                            : 'bg-indigo-600 text-white rounded-tr-xs'
                        }`}
                      >
                        {msg.message && <p className="whitespace-pre-wrap">{msg.message}</p>}

                        {msg.image_url && (
                          <div className="mt-2">
                            <a href={msg.image_url} target="_blank" rel="noreferrer" className="block overflow-hidden rounded-lg border border-black/10">
                              <img src={msg.image_url} alt="Attachment" className="max-h-40 w-auto object-cover" />
                            </a>
                          </div>
                        )}
                      </div>
                    </div>
                  );
                })
              )}
            </div>

            {/* Drawer Footer Moderation Bar */}
            {conversationDetail && canModerate && (
              <div className="p-4 border-t border-slate-200 bg-white flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <ShieldAlert className="h-4 w-4 text-amber-500" />
                  <span className="text-xs text-slate-600 font-medium">
                    {isRtl ? 'إشراف أمني على المحادثة' : 'Security Audit Active'}
                  </span>
                </div>

                <button
                  onClick={() =>
                    handleToggleStatus(conversationDetail.conversation.id, conversationDetail.conversation.status)
                  }
                  className={`px-3 py-1.5 text-xs font-bold rounded-xl transition ${
                    conversationDetail.conversation.status === 'active'
                      ? 'bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100'
                      : 'bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100'
                  }`}
                >
                  {conversationDetail.conversation.status === 'active'
                    ? (isRtl ? 'تعليق / أرشفة المحادثة' : 'Archive Conversation')
                    : (isRtl ? 'إعادة التنشيط' : 'Reactivate Conversation')}
                </button>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
