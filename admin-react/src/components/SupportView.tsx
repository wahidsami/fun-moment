/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { Language, SupportTicket, UserRole } from '../types';
import { translations } from '../translations';
import { useState, useEffect } from 'react';
import { LaravelAPI } from '../api';
import { Search, Eye, ShieldAlert, CheckCircle2, RefreshCcw } from 'lucide-react';

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
                        onClick={() => setSelectedTicket(tkt)}
                        className="rounded p-1 text-slate-500 hover:bg-slate-100 transition-colors"
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

      {/* Ticket Reply Modal */}
      {selectedTicket && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
          <div className="w-full max-w-lg rounded-2xl border border-slate-100 bg-white p-6 shadow-2xl animate-scale-in">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
              <span className="text-[10px] font-bold text-slate-400 uppercase">
                {language === 'en' ? 'Dispute Help Desk File' : 'ملف تذكرة الدعم والنزاع'} #{selectedTicket.id}
              </span>
              <button
                onClick={() => setSelectedTicket(null)}
                className="text-slate-400 hover:text-slate-600 text-sm font-bold"
              >
                ✕
              </button>
            </div>

            <div className="space-y-4">
              <div className="rounded-xl bg-indigo-50/30 border border-indigo-100/50 p-3">
                <p className="text-[10px] font-bold text-indigo-800 uppercase">{language === 'en' ? 'Subject' : 'الموضوع الرئيسي'}</p>
                <p className="text-xs font-bold text-slate-800 mt-1">
                  {language === 'en' ? selectedTicket.subject_en : selectedTicket.subject_ar}
                </p>
              </div>

              <div className="bg-slate-50 rounded-xl p-3 border border-slate-100">
                <p className="text-[10px] font-bold text-slate-400 uppercase">{language === 'en' ? 'Latest User Appeal Message' : 'نص رسالة العميل الأخيرة'}</p>
                <p className="text-xs text-slate-700 mt-1.5 leading-relaxed font-medium">
                  "{selectedTicket.last_message}"
                </p>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <h4 className="text-xs text-slate-400 font-bold uppercase">{language === 'en' ? 'Filed By' : 'مقدم البلاغ'}</h4>
                  <p className="text-xs font-semibold text-slate-700 mt-1">{selectedTicket.user_name}</p>
                </div>
                <div>
                  <h4 className="text-xs text-slate-400 font-bold uppercase">{t.col_ticket_category}</h4>
                  <p className="text-xs font-semibold text-slate-700 mt-1 uppercase">{selectedTicket.category}</p>
                </div>
              </div>
            </div>

            <div className="mt-8 flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
              <button
                onClick={() => setSelectedTicket(null)}
                className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100"
              >
                {t.back}
              </button>

              {hasPermission && selectedTicket.status !== 'resolved' && (
                <>
                  <button
                    onClick={() => handleUpdateStatus(selectedTicket.id, 'in_progress')}
                    className="rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700"
                  >
                    {language === 'en' ? 'Mark In Progress' : 'تغيير الحالة لقيد العمل'}
                  </button>
                  <button
                    onClick={() => handleUpdateStatus(selectedTicket.id, 'resolved')}
                    className="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-700"
                  >
                    {language === 'en' ? 'Mark Resolved' : 'تسوية وحل التذكرة'}
                  </button>
                </>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
