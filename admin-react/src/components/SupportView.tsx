/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { Language, SupportTicket, UserRole, TicketMessageItem } from '../types';
import { translations } from '../translations';
import React, { useState, useEffect } from 'react';
import { LaravelAPI } from '../api';
import { Search, Eye, ShieldAlert, CheckCircle2, RefreshCcw, Send, MessageSquare, User, Shield } from 'lucide-react';

interface SupportViewProps {
  language: Language;
  activeRole: UserRole;
}

export default function SupportView({ language, activeRole }: SupportViewProps) {
  const t = translations[language];
  const [tickets, setTickets] = useState<SupportTicket[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [priorityFilter, setPriorityFilter] = useState('all');
  const [selectedTicket, setSelectedTicket] = useState<SupportTicket | null>(null);
  const [ticketMessages, setTicketMessages] = useState<TicketMessageItem[]>([]);
  const [loadingMessages, setLoadingMessages] = useState(false);
  const [replyMessage, setReplyMessage] = useState('');
  const [sendingReply, setSendingReply] = useState(false);
  const [successMsg, setSuccessMsg] = useState('');

  // Support Agents & Super Admins can manage tickets
  const hasPermission = activeRole === 'super_admin' || activeRole === 'support_agent';

  const loadTickets = async () => {
    setLoading(true);
    try {
      const data = await LaravelAPI.getTickets();
      setTickets(data);
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadTickets();
  }, []);

  const handleOpenTicket = async (ticket: SupportTicket) => {
    setSelectedTicket(ticket);
    setLoadingMessages(true);
    setReplyMessage('');
    try {
      const res = await LaravelAPI.getTicketDetails(ticket.id);
      if (res.messages && Array.isArray(res.messages)) {
        setTicketMessages(res.messages);
      } else {
        setTicketMessages([]);
      }
    } catch (err) {
      console.error(err);
      setTicketMessages([]);
    } finally {
      setLoadingMessages(false);
    }
  };

  const handleSendReply = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedTicket || !replyMessage.trim() || sendingReply) return;
    setSendingReply(true);
    try {
      const res = await LaravelAPI.replyTicket(selectedTicket.id, replyMessage.trim());
      if (res.messages && Array.isArray(res.messages)) {
        setTicketMessages(res.messages);
      }
      setReplyMessage('');
      setSuccessMsg(language === 'en' ? 'Reply recorded and sent to customer!' : 'تم حفظ الرد وإرساله للعميل بنجاح!');
      setTimeout(() => setSuccessMsg(''), 3000);
      loadTickets();
    } catch (err: any) {
      console.error(err);
      alert(err?.message || 'Failed to send reply');
    } finally {
      setSendingReply(false);
    }
  };

  const handleUpdateStatus = async (id: number, status: 'resolved' | 'in_progress') => {
    try {
      const updated = await LaravelAPI.updateTicketStatus(id, status);
      setTickets(current => current.map(t => t.id === id ? updated : t));
      if (selectedTicket && selectedTicket.id === id) {
        setSelectedTicket(updated);
      }
      setSuccessMsg(language === 'en' ? `Ticket #${id} marked as ${status}!` : `تم تعديل حالة تذكرة الدعم بنجاح!`);
      setTimeout(() => setSuccessMsg(''), 3000);
    } catch (err) {
      console.error(err);
    }
  };

  const filteredTickets = tickets.filter(t => {
    const subject = language === 'en' ? t.subject_en : t.subject_ar;
    const matchesSearch =
      subject.toLowerCase().includes(search.toLowerCase()) ||
      t.user_name.toLowerCase().includes(search.toLowerCase()) ||
      t.user_email.toLowerCase().includes(search.toLowerCase());

    const matchesPriority = priorityFilter === 'all' || t.priority === priorityFilter;

    return matchesSearch && matchesPriority;
  });

  return (
    <div className="space-y-6">
      {/* View Header */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 className="text-xl font-bold tracking-tight text-slate-800">
            {t.support_title}
          </h2>
          <p className="text-xs text-slate-500">
            {t.support_subtitle}
          </p>
        </div>
      </div>

      {successMsg && (
        <div className="rounded-xl bg-emerald-50 border border-emerald-100 p-3 text-xs text-emerald-800 font-semibold animate-fade-in flex items-center gap-1.5">
          <CheckCircle2 className="h-4 w-4 text-emerald-600" />
          <span>{successMsg}</span>
        </div>
      )}

      {/* Controls */}
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

        <div className="flex items-center gap-2">
          <span className="text-xs text-slate-400 font-semibold">{t.filter}:</span>
          <button
            onClick={() => setPriorityFilter('all')}
            className={`rounded-lg px-2.5 py-1 text-xs font-semibold border ${
              priorityFilter === 'all' ? 'border-indigo-200 bg-indigo-50 text-indigo-600' : 'border-slate-200 bg-slate-50 text-slate-600'
            }`}
          >
            {t.all}
          </button>
          <button
            onClick={() => setPriorityFilter('high')}
            className={`rounded-lg px-2.5 py-1 text-xs font-semibold border ${
              priorityFilter === 'high' ? 'border-indigo-200 bg-indigo-50 text-indigo-600' : 'border-slate-200 bg-slate-50 text-slate-600'
            }`}
          >
            {t.priority_high}
          </button>
          <button
            onClick={() => setPriorityFilter('medium')}
            className={`rounded-lg px-2.5 py-1 text-xs font-semibold border ${
              priorityFilter === 'medium' ? 'border-indigo-200 bg-indigo-50 text-indigo-600' : 'border-slate-200 bg-slate-50 text-slate-600'
            }`}
          >
            {t.priority_medium}
          </button>
        </div>
      </div>

      {/* Tickets Table */}
      <div className="overflow-hidden rounded-2xl border border-slate-200/60 bg-white shadow-sm">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs text-slate-500 rtl:text-right">
            <thead className="bg-slate-50/70 text-[10px] font-bold tracking-wider text-slate-500 uppercase border-b border-slate-100">
              <tr>
                <th className="px-6 py-4">ID</th>
                <th className="px-6 py-4">{t.col_subject}</th>
                <th className="px-6 py-4">{language === 'en' ? 'User Info' : 'المستعلم'}</th>
                <th className="px-6 py-4">{t.col_ticket_category}</th>
                <th className="px-6 py-4">{t.col_priority}</th>
                <th className="px-6 py-4">{t.status}</th>
                <th className="px-6 py-4 text-center">{t.actions}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 font-medium text-slate-700">
              {loading ? (
                <tr>
                  <td colSpan={7} className="py-10 text-center text-slate-400">
                    <RefreshCcw className="mx-auto h-5 w-5 animate-spin mb-2" />
                    <span>{t.loading}</span>
                  </td>
                </tr>
              ) : filteredTickets.length === 0 ? (
                <tr>
                  <td colSpan={7} className="py-10 text-center text-slate-400">
                    {language === 'en' ? 'No tickets found matching options.' : 'لا توجد تذاكر دعم تطابق التصفية.'}
                  </td>
                </tr>
              ) : (
                filteredTickets.map((tkt) => (
                  <tr key={tkt.id} className="hover:bg-slate-50/50 transition-colors">
                    <td className="px-6 py-4 text-slate-400 font-bold">#{tkt.id}</td>
                    <td className="px-6 py-4 max-w-[240px]">
                      <div className="font-bold text-slate-800 line-clamp-1">
                        {language === 'en' ? tkt.subject_en : tkt.subject_ar}
                      </div>
                      <div className="text-[10px] text-slate-400 mt-0.5 font-medium">{tkt.created_at}</div>
                    </td>
                    <td className="px-6 py-4">
                      <div className="font-semibold text-slate-800">{tkt.user_name}</div>
                      <div className="text-[10px] text-slate-400 mt-0.5">{tkt.user_email}</div>
                    </td>
                    <td className="px-6 py-4 uppercase font-semibold text-slate-500 text-[10px]">{tkt.category}</td>
                    <td className="px-6 py-4">
                      <span className={`inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-bold ${
                        tkt.priority === 'high' ? 'bg-rose-50 text-rose-700' : tkt.priority === 'medium' ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-600'
                      }`}>
                        {tkt.priority === 'high' ? t.priority_high : tkt.priority === 'medium' ? t.priority_medium : t.priority_low}
                      </span>
                    </td>
                    <td className="px-6 py-4">
                      <span className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold ${
                        tkt.status === 'resolved'
                          ? 'bg-emerald-50 text-emerald-700'
                          : tkt.status === 'in_progress'
                          ? 'bg-blue-50 text-blue-700'
                          : tkt.status === 'open'
                          ? 'bg-amber-50 text-amber-700'
                          : 'bg-slate-100 text-slate-600'
                      }`}>
                        {tkt.status === 'resolved' ? t.status_ticket_resolved : tkt.status === 'in_progress' ? t.status_ticket_in_progress : tkt.status === 'open' ? t.status_ticket_open : t.status_ticket_closed}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-center">
                      <button
                        onClick={() => handleOpenTicket(tkt)}
                        className="rounded p-1 text-slate-500 hover:bg-slate-100 transition-colors"
                        title={language === 'en' ? 'Open Ticket Conversation' : 'عرض محادثة التذكرة'}
                      >
                        <Eye className="h-4 w-4" />
                      </button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Ticket Conversation & Reply Modal */}
      {selectedTicket && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
          <div className="w-full max-w-2xl max-h-[90vh] flex flex-col rounded-2xl border border-slate-100 bg-white p-6 shadow-2xl animate-scale-in">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3 mb-3">
              <div>
                <span className="text-[10px] font-bold text-slate-400 uppercase">
                  {language === 'en' ? 'Support Ticket Dispute File' : 'ملف تذكرة الدعم والمنازعة'} #{selectedTicket.id}
                </span>
                <h3 className="text-sm font-bold text-slate-800 mt-0.5">
                  {language === 'en' ? selectedTicket.subject_en : selectedTicket.subject_ar}
                </h3>
              </div>
              <button
                onClick={() => setSelectedTicket(null)}
                className="text-slate-400 hover:text-slate-600 text-sm font-bold"
              >
                ✕
              </button>
            </div>

            {/* Ticket Metadata Bar */}
            <div className="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-slate-50 p-2.5 text-xs border border-slate-100 mb-3">
              <div>
                <span className="text-[10px] text-slate-400 uppercase font-bold">{language === 'en' ? 'Client' : 'العميل'}: </span>
                <span className="font-semibold text-slate-700">{selectedTicket.user_name} ({selectedTicket.user_email})</span>
              </div>
              <div className="flex items-center gap-2">
                <span className="rounded bg-indigo-50 text-indigo-700 px-2 py-0.5 text-[10px] font-bold uppercase">
                  {selectedTicket.category}
                </span>
                <span className={`rounded px-2 py-0.5 text-[10px] font-bold ${
                  selectedTicket.status === 'resolved' ? 'bg-emerald-50 text-emerald-700' : 'bg-blue-50 text-blue-700'
                }`}>
                  {selectedTicket.status}
                </span>
              </div>
            </div>

            {/* Conversation Messages Thread */}
            <div className="flex-1 overflow-y-auto space-y-3 p-3 bg-slate-50/60 rounded-xl border border-slate-100 min-h-[220px] max-h-[340px]">
              {loadingMessages ? (
                <div className="py-8 text-center text-slate-400">
                  <RefreshCcw className="mx-auto h-5 w-5 animate-spin mb-1 text-indigo-500" />
                  <p className="text-xs">{language === 'en' ? 'Loading conversation thread...' : 'جاري تحميل سجل المحادثة...'}</p>
                </div>
              ) : ticketMessages.length === 0 ? (
                <div className="py-8 text-center text-slate-400 text-xs">
                  {selectedTicket.last_message ? (
                    <div className="bg-white rounded-lg p-3 border text-left rtl:text-right">
                      <span className="text-[10px] font-bold uppercase text-slate-400 block mb-1">
                        {selectedTicket.user_name} (Initial Message):
                      </span>
                      <p className="text-slate-700">{selectedTicket.last_message}</p>
                    </div>
                  ) : (
                    <p>{language === 'en' ? 'No messages logged yet in this ticket.' : 'لا توجد رسائل مسجلة بعد في هذه التذكرة.'}</p>
                  )}
                </div>
              ) : (
                ticketMessages.map((msg) => {
                  const isAdmin = msg.type === 'admin';
                  return (
                    <div
                      key={msg.id}
                      className={`flex flex-col ${isAdmin ? 'items-end' : 'items-start'}`}
                    >
                      <div className="flex items-center gap-1.5 mb-1 text-[10px] font-semibold text-slate-400">
                        {isAdmin ? (
                          <>
                            <Shield className="h-3 w-3 text-indigo-500" />
                            <span className="text-indigo-600 font-bold">{msg.sender_name || 'Admin Support'}</span>
                          </>
                        ) : (
                          <>
                            <User className="h-3 w-3 text-slate-400" />
                            <span>{msg.sender_name || selectedTicket.user_name}</span>
                          </>
                        )}
                        <span>•</span>
                        <span>{msg.created_at}</span>
                      </div>
                      <div
                        className={`max-w-[85%] rounded-2xl px-4 py-2.5 text-xs leading-relaxed ${
                          isAdmin
                            ? 'bg-indigo-600 text-white rounded-tr-none'
                            : 'bg-white border border-slate-200 text-slate-800 rounded-tl-none shadow-xs'
                        }`}
                      >
                        {msg.message}
                      </div>
                    </div>
                  );
                })
              )}
            </div>

            {/* Admin Reply Box */}
            {hasPermission && selectedTicket.status !== 'resolved' && (
              <form onSubmit={handleSendReply} className="mt-3 space-y-2">
                <div className="relative">
                  <textarea
                    rows={2}
                    value={replyMessage}
                    onChange={(e) => setReplyMessage(e.target.value)}
                    placeholder={language === 'en' ? 'Write official admin reply (persists to customer portal)...' : 'اكتب رد الدعم الإداري الرسمي (يظهر للعميل في التطبيق والموقع)...'}
                    className="w-full rounded-xl border border-slate-200 bg-white p-2.5 text-xs outline-hidden focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                  />
                  <button
                    type="submit"
                    disabled={sendingReply || !replyMessage.trim()}
                    className="absolute bottom-2.5 right-2.5 rtl:right-auto rtl:left-2.5 rounded-lg bg-indigo-600 px-3 py-1 text-xs font-bold text-white hover:bg-indigo-700 disabled:opacity-40 transition flex items-center gap-1"
                  >
                    <Send className="h-3 w-3" />
                    <span>{sendingReply ? '...' : (language === 'en' ? 'Send Reply' : 'إرسال الرد')}</span>
                  </button>
                </div>
              </form>
            )}

            {/* Modal Bottom Actions */}
            <div className="mt-4 flex items-center justify-between border-t border-slate-100 pt-3">
              <button
                onClick={() => setSelectedTicket(null)}
                className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100"
              >
                {t.back}
              </button>

              {hasPermission && selectedTicket.status !== 'resolved' && (
                <div className="flex items-center gap-2">
                  <button
                    onClick={() => handleUpdateStatus(selectedTicket.id, 'in_progress')}
                    className="rounded-lg bg-blue-600 px-3.5 py-1.5 text-xs font-semibold text-white hover:bg-blue-700"
                  >
                    {language === 'en' ? 'Mark In Progress' : 'قيد العمل'}
                  </button>
                  <button
                    onClick={() => handleUpdateStatus(selectedTicket.id, 'resolved')}
                    className="rounded-lg bg-emerald-600 px-3.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700"
                  >
                    {language === 'en' ? 'Mark Resolved' : 'حل وإغلاق التذكرة'}
                  </button>
                </div>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
