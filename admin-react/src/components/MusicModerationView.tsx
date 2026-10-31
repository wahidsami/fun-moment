/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useState, useEffect, useCallback, useRef } from 'react';
import {
  Music,
  Headphones,
  CheckCircle,
  XCircle,
  AlertTriangle,
  Clock,
  Search,
  RefreshCw,
  Eye,
  Shield,
  FileText,
  User,
  Sliders,
  Play,
  Pause,
  AlertCircle,
  ChevronRight,
  ChevronLeft,
  X,
  Volume2
} from 'lucide-react';
import { Language, UserRole, MusicModerationTrackItem, MusicModerationCounts } from '../types';
import { translations } from '../translations';
import { api } from '../api';
import { checkPermission } from '../utils/auditLogger';

interface MusicModerationViewProps {
  language: Language;
  activeRole: UserRole;
}

export default function MusicModerationView({ language, activeRole }: MusicModerationViewProps) {
  const t = translations[language];
  const isRtl = language === 'ar';

  const canModerate = checkPermission(activeRole, 'manage_services') ||
    checkPermission(activeRole, 'manage_settings') ||
    activeRole === 'super_admin';

  const [activeTab, setActiveTab] = useState<'pending' | 'approved' | 'rejected' | 'suspended' | 'all'>('pending');
  const [searchQuery, setSearchQuery] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const [loading, setLoading] = useState(false);
  const [tracks, setTracks] = useState<MusicModerationTrackItem[]>([]);
  const [counts, setCounts] = useState<MusicModerationCounts>({ pending: 0, approved: 0, rejected: 0, suspended: 0, all: 0 });
  const [totalPages, setTotalPages] = useState(1);
  const [totalItems, setTotalItems] = useState(0);

  // Inspector modal state
  const [selectedTrack, setSelectedTrack] = useState<MusicModerationTrackItem | null>(null);
  const [inspectorLoading, setInspectorLoading] = useState(false);
  const [audioPlaying, setAudioPlaying] = useState(false);
  const audioRef = useRef<HTMLAudioElement | null>(null);

  // Moderation action modals
  const [rejectModalOpen, setRejectModalOpen] = useState(false);
  const [rejectionReason, setRejectionReason] = useState('');
  const [actionLoading, setActionLoading] = useState(false);
  const [feedbackMessage, setFeedbackMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

  const fetchSubmissions = useCallback(async () => {
    setLoading(true);
    try {
      const response = await api.getMusicSubmissions({
        tab: activeTab,
        search: searchQuery.trim() || undefined,
        page: currentPage,
        per_page: 15,
      });
      setTracks(response.tracks?.data || []);
      setCounts(response.counts || { pending: 0, approved: 0, rejected: 0, suspended: 0, all: 0 });
      setTotalPages(response.tracks?.last_page || 1);
      setTotalItems(response.tracks?.total || 0);
    } catch (err: any) {
      setFeedbackMessage({ type: 'error', text: err?.message || 'Failed to load music submissions.' });
    } finally {
      setLoading(false);
    }
  }, [activeTab, searchQuery, currentPage]);

  useEffect(() => {
    fetchSubmissions();
  }, [fetchSubmissions]);

  const handleOpenInspector = async (track: MusicModerationTrackItem) => {
    setSelectedTrack(track);
    setInspectorLoading(true);
    setAudioPlaying(false);
    try {
      const detail = await api.getMusicSubmissionDetail(track.id);
      if (detail?.track) {
        setSelectedTrack(detail.track);
      }
    } catch {
      // Keep basic track info if detail load fails
    } finally {
      setInspectorLoading(false);
    }
  };

  const handleCloseInspector = () => {
    if (audioRef.current) {
      audioRef.current.pause();
    }
    setAudioPlaying(false);
    setSelectedTrack(null);
  };

  const handleApprove = async (trackId: number) => {
    if (!canModerate) return;
    setActionLoading(true);
    try {
      const res = await api.approveMusicSubmission(trackId);
      setFeedbackMessage({ type: 'success', text: isRtl ? 'تم اعتماد ونشر المسار بنجاح.' : res.message_en || 'Track approved and published successfully.' });
      handleCloseInspector();
      fetchSubmissions();
    } catch (err: any) {
      setFeedbackMessage({ type: 'error', text: err?.message || 'Failed to approve track.' });
    } finally {
      setActionLoading(false);
    }
  };

  const handleReject = async () => {
    if (!selectedTrack || !canModerate) return;
    if (!rejectionReason.trim() || rejectionReason.trim().length < 3) {
      setFeedbackMessage({ type: 'error', text: isRtl ? 'يرجى تقديم سبب رفض واضح للبائع (3 أحرف على الأقل).' : 'Please enter a valid rejection reason (min 3 chars).' });
      return;
    }

    setActionLoading(true);
    try {
      const res = await api.rejectMusicSubmission(selectedTrack.id, rejectionReason.trim());
      setFeedbackMessage({ type: 'success', text: isRtl ? 'تم رفض المسار بنجاح.' : res.message_en || 'Track rejected successfully.' });
      setRejectModalOpen(false);
      setRejectionReason('');
      handleCloseInspector();
      fetchSubmissions();
    } catch (err: any) {
      setFeedbackMessage({ type: 'error', text: err?.message || 'Failed to reject track.' });
    } finally {
      setActionLoading(false);
    }
  };

  const handleSuspend = async (trackId: number) => {
    if (!canModerate) return;
    const reason = window.prompt(isRtl ? 'اكتب سبب التعليق الإداري (اختياري):' : 'Enter suspension reason (optional):');
    if (reason === null) return; // user cancelled prompt

    setActionLoading(true);
    try {
      const res = await api.suspendMusicSubmission(trackId, reason);
      setFeedbackMessage({ type: 'success', text: isRtl ? 'تم تعليق المسار بنجاح.' : res.message_en || 'Track suspended successfully.' });
      handleCloseInspector();
      fetchSubmissions();
    } catch (err: any) {
      setFeedbackMessage({ type: 'error', text: err?.message || 'Failed to suspend track.' });
    } finally {
      setActionLoading(false);
    }
  };

  const toggleAudio = () => {
    if (!audioRef.current) return;
    if (audioPlaying) {
      audioRef.current.pause();
      setAudioPlaying(false);
    } else {
      audioRef.current.play().then(() => setAudioPlaying(true)).catch(() => setAudioPlaying(false));
    }
  };

  const formatSeconds = (sec?: number | null) => {
    if (!sec) return '0:00';
    const m = Math.floor(sec / 60);
    const s = Math.floor(sec % 60);
    return `${m}:${s < 10 ? '0' : ''}${s}`;
  };

  const renderStatusBadge = (status: string) => {
    switch (status) {
      case 'approved':
        return (
          <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
            <CheckCircle className="w-3 h-3 text-emerald-600" />
            {isRtl ? 'معتمد' : 'Approved'}
          </span>
        );
      case 'rejected':
        return (
          <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800">
            <XCircle className="w-3 h-3 text-rose-600" />
            {isRtl ? 'مرفوض' : 'Rejected'}
          </span>
        );
      case 'suspended':
        return (
          <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
            <AlertTriangle className="w-3 h-3 text-amber-600" />
            {isRtl ? 'معلق' : 'Suspended'}
          </span>
        );
      case 'pending':
      default:
        return (
          <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
            <Clock className="w-3 h-3 text-blue-600" />
            {isRtl ? 'قيد المراجعة' : 'Pending Review'}
          </span>
        );
    }
  };

  const renderMediaStatusBadge = (mediaStatus: string) => {
    switch (mediaStatus) {
      case 'ready':
        return (
          <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
            ✓ {isRtl ? 'جاهز' : 'Ready'}
          </span>
        );
      case 'processing':
        return (
          <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-medium bg-indigo-50 text-indigo-700 border border-indigo-200 animate-pulse">
            <RefreshCw className="w-2.5 h-2.5 animate-spin" />
            {isRtl ? 'قيد المعالجة' : 'Processing'}
          </span>
        );
      case 'failed':
        return (
          <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-medium bg-rose-50 text-rose-700 border border-rose-200">
            <AlertCircle className="w-2.5 h-2.5" />
            {isRtl ? 'فشلت المعالجة' : 'Failed'}
          </span>
        );
      default:
        return (
          <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-medium bg-slate-50 text-slate-600 border border-slate-200">
            {mediaStatus}
          </span>
        );
    }
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-3">
            <div className="p-2.5 bg-indigo-50 text-indigo-600 rounded-xl">
              <Headphones className="w-6 h-6" />
            </div>
            <div>
              <h1 className="text-xl font-bold text-slate-900 tracking-tight">
                {t.music_mod_title}
              </h1>
              <p className="text-sm text-slate-500">
                {t.music_mod_subtitle}
              </p>
            </div>
          </div>
        </div>

        <div className="flex items-center gap-2">
          <button
            onClick={() => fetchSubmissions()}
            disabled={loading}
            className="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition shadow-2xs disabled:opacity-50"
          >
            <RefreshCw className={`w-3.5 h-3.5 ${loading ? 'animate-spin' : ''}`} />
            {t.refresh}
          </button>
        </div>
      </div>

      {/* Feedback banner */}
      {feedbackMessage && (
        <div
          className={`p-4 rounded-xl text-sm flex items-center justify-between border ${
            feedbackMessage.type === 'success'
              ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
              : 'bg-rose-50 text-rose-800 border-rose-200'
          }`}
        >
          <span>{feedbackMessage.text}</span>
          <button onClick={() => setFeedbackMessage(null)} className="p-1 hover:opacity-75">
            <X className="w-4 h-4" />
          </button>
        </div>
      )}

      {/* Tabs and Filters */}
      <div className="bg-white rounded-2xl border border-slate-200/80 shadow-2xs overflow-hidden">
        <div className="p-4 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-4">
          {/* Status Tabs */}
          <div className="flex items-center gap-1 overflow-x-auto pb-1 md:pb-0">
            {[
              { key: 'pending', label: t.music_mod_tab_pending, count: counts.pending, color: 'blue' },
              { key: 'approved', label: t.music_mod_tab_approved, count: counts.approved, color: 'emerald' },
              { key: 'rejected', label: t.music_mod_tab_rejected, count: counts.rejected, color: 'rose' },
              { key: 'suspended', label: t.music_mod_tab_suspended, count: counts.suspended, color: 'amber' },
              { key: 'all', label: t.music_mod_tab_all, count: counts.all, color: 'slate' },
            ].map(tab => {
              const active = activeTab === tab.key;
              return (
                <button
                  key={tab.key}
                  onClick={() => {
                    setActiveTab(tab.key as any);
                    setCurrentPage(1);
                  }}
                  className={`inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition ${
                    active
                      ? 'bg-indigo-600 text-white shadow-xs'
                      : 'text-slate-600 hover:bg-slate-100'
                  }`}
                >
                  <span>{tab.label}</span>
                  <span
                    className={`px-1.5 py-0.2 rounded-full text-[10px] ${
                      active ? 'bg-indigo-700 text-white' : 'bg-slate-200/70 text-slate-700'
                    }`}
                  >
                    {tab.count}
                  </span>
                </button>
              );
            })}
          </div>

          {/* Search box */}
          <div className="relative min-w-[240px]">
            <Search className="w-4 h-4 text-slate-400 absolute left-3 rtl:left-auto rtl:right-3 top-1/2 -translate-y-1/2" />
            <input
              type="text"
              value={searchQuery}
              onChange={e => {
                setSearchQuery(e.target.value);
                setCurrentPage(1);
              }}
              placeholder={t.searchPlaceholder}
              className="w-full pl-9 pr-4 rtl:pl-4 rtl:pr-9 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition"
            />
          </div>
        </div>

        {/* Tracks Table */}
        <div className="overflow-x-auto">
          <table className="w-full text-left rtl:text-right border-collapse">
            <thead>
              <tr className="border-b border-slate-100 bg-slate-50/60 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                <th className="py-3 px-4">{isRtl ? 'المسار' : 'Track'}</th>
                <th className="py-3 px-4">{t.music_mod_seller}</th>
                <th className="py-3 px-4">{isRtl ? 'النوع / التصنيف' : 'Genre'}</th>
                <th className="py-3 px-4">{t.music_mod_offers}</th>
                <th className="py-3 px-4">{isRtl ? 'حالة الصوت' : 'Audio Status'}</th>
                <th className="py-3 px-4">{t.status}</th>
                <th className="py-3 px-4 text-right rtl:text-left">{t.actions}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 text-xs">
              {loading ? (
                <tr>
                  <td colSpan={7} className="py-12 text-center text-slate-400">
                    <RefreshCw className="w-5 h-5 animate-spin mx-auto mb-2 text-indigo-500" />
                    <span>{t.loading}</span>
                  </td>
                </tr>
              ) : tracks.length === 0 ? (
                <tr>
                  <td colSpan={7} className="py-12 text-center text-slate-400">
                    <Music className="w-8 h-8 mx-auto mb-2 text-slate-300" />
                    <p className="font-medium text-slate-600">{isRtl ? 'لا توجد تقديمات مطابقة' : 'No music submissions found'}</p>
                    <p className="text-[11px] text-slate-400 mt-1">{isRtl ? 'جرب تغيير علامة التبويب أو كلمات البحث.' : 'Try switching tabs or adjusting search filters.'}</p>
                  </td>
                </tr>
              ) : (
                tracks.map(track => (
                  <tr key={track.id} className="hover:bg-slate-50/80 transition">
                    {/* Track info */}
                    <td className="py-3 px-4">
                      <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded-lg overflow-hidden bg-slate-100 border border-slate-200 shrink-0">
                          {track.cover_image_url ? (
                            <img src={track.cover_image_variants?.thumbnail || track.cover_image_url} alt={track.title} className="w-full h-full object-cover" />
                          ) : (
                            <div className="w-full h-full flex items-center justify-center text-slate-400">
                              <Music className="w-4 h-4" />
                            </div>
                          )}
                        </div>
                        <div className="min-w-0">
                          <p className="font-semibold text-slate-900 truncate max-w-[200px]" title={track.title}>
                            {track.title}
                          </p>
                          <p className="text-[11px] text-slate-500 truncate">
                            {track.artist_name || (isRtl ? 'فنان غير محدد' : 'Unknown Artist')} • {formatSeconds(track.duration_seconds)}
                          </p>
                        </div>
                      </div>
                    </td>

                    {/* Seller */}
                    <td className="py-3 px-4">
                      <div className="min-w-0">
                        <p className="font-medium text-slate-800 truncate">
                          {track.seller?.name || `Seller #${track.seller_id}`}
                        </p>
                        <p className="text-[11px] text-slate-400 truncate">
                          {track.seller?.email || '—'}
                        </p>
                      </div>
                    </td>

                    {/* Genre */}
                    <td className="py-3 px-4">
                      <span className="inline-block px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 text-slate-700">
                        {track.genre_name || (isRtl ? 'عام' : 'General')}
                      </span>
                      {track.secondary_genres && track.secondary_genres.length > 0 && (
                        <span className="text-[10px] text-slate-400 ml-1">
                          +{track.secondary_genres.length}
                        </span>
                      )}
                    </td>

                    {/* Offers */}
                    <td className="py-3 px-4">
                      <div className="flex flex-wrap gap-1">
                        {track.offers && track.offers.length > 0 ? (
                          track.offers.map((offer, idx) => (
                            <span
                              key={idx}
                              className={`text-[10px] font-semibold px-1.5 py-0.5 rounded uppercase ${
                                offer.offer_type === 'free'
                                  ? 'bg-blue-50 text-blue-700'
                                  : offer.offer_type === 'rent'
                                  ? 'bg-purple-50 text-purple-700'
                                  : 'bg-emerald-50 text-emerald-700'
                              }`}
                            >
                              {offer.offer_type}
                              {offer.price > 0 ? ` $${offer.price}` : ''}
                            </span>
                          ))
                        ) : (
                          <span className="text-[11px] text-slate-400">{isRtl ? 'لا عروض' : 'No offers'}</span>
                        )}
                      </div>
                    </td>

                    {/* Media status */}
                    <td className="py-3 px-4">
                      {renderMediaStatusBadge(track.media_status)}
                    </td>

                    {/* Moderation status */}
                    <td className="py-3 px-4">
                      {renderStatusBadge(track.status)}
                    </td>

                    {/* Actions */}
                    <td className="py-3 px-4 text-right rtl:text-left">
                      <button
                        onClick={() => handleOpenInspector(track)}
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 transition"
                      >
                        <Eye className="w-3.5 h-3.5" />
                        <span>{t.music_mod_inspect}</span>
                      </button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination footer */}
        {totalPages > 1 && (
          <div className="p-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
            <span>
              {isRtl ? `صفحة ${currentPage} من ${totalPages}` : `Page ${currentPage} of ${totalPages}`} ({totalItems} {isRtl ? 'مسار' : 'tracks'})
            </span>
            <div className="flex items-center gap-1">
              <button
                disabled={currentPage <= 1 || loading}
                onClick={() => setCurrentPage(prev => Math.max(1, prev - 1))}
                className="px-2.5 py-1.5 border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-40"
              >
                {isRtl ? <ChevronRight className="w-3.5 h-3.5" /> : <ChevronLeft className="w-3.5 h-3.5" />}
              </button>
              <button
                disabled={currentPage >= totalPages || loading}
                onClick={() => setCurrentPage(prev => Math.min(totalPages, prev + 1))}
                className="px-2.5 py-1.5 border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-40"
              >
                {isRtl ? <ChevronLeft className="w-3.5 h-3.5" /> : <ChevronRight className="w-3.5 h-3.5" />}
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Track Inspection Drawer / Modal */}
      {selectedTrack && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
          <div className="bg-white rounded-3xl shadow-2xl max-w-2xl w-full max-h-[90vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            {/* Modal Header */}
            <div className="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
              <div className="flex items-center gap-2">
                <Music className="w-5 h-5 text-indigo-600" />
                <h3 className="font-bold text-slate-900 text-sm">
                  {t.music_mod_track_details} #{selectedTrack.id}
                </h3>
              </div>
              <button
                onClick={handleCloseInspector}
                className="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            {/* Modal Content */}
            <div className="p-6 overflow-y-auto space-y-6 flex-1 text-xs">
              {/* Primary Track Card */}
              <div className="flex flex-col sm:flex-row items-start sm:items-center gap-4 p-4 rounded-2xl bg-slate-50 border border-slate-200/80">
                <div className="w-20 h-20 rounded-xl overflow-hidden bg-white border border-slate-200 shrink-0 shadow-2xs">
                  {selectedTrack.cover_image_url ? (
                    <img src={selectedTrack.cover_image_variants?.medium || selectedTrack.cover_image_url} alt={selectedTrack.title} className="w-full h-full object-cover" />
                  ) : (
                    <div className="w-full h-full flex items-center justify-center text-slate-400">
                      <Music className="w-8 h-8" />
                    </div>
                  )}
                </div>

                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2 flex-wrap">
                    <h4 className="text-base font-bold text-slate-900">{selectedTrack.title}</h4>
                    {renderStatusBadge(selectedTrack.status)}
                    {renderMediaStatusBadge(selectedTrack.media_status)}
                  </div>
                  <p className="text-xs text-slate-500 mt-0.5">
                    {selectedTrack.artist_name || (isRtl ? 'فنان غير محدد' : 'Unknown Artist')} • {selectedTrack.genre_name || 'General'}
                  </p>
                  <p className="text-[11px] text-slate-400 mt-1">
                    {isRtl ? 'تاريخ التقديم:' : 'Submitted:'} {selectedTrack.submitted_at || selectedTrack.created_at}
                  </p>
                </div>
              </div>

              {/* Audio Preview Player */}
              <div className="p-4 rounded-2xl border border-indigo-100 bg-indigo-50/40 space-y-3">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <Volume2 className="w-4 h-4 text-indigo-600" />
                    <span className="font-bold text-indigo-950">{isRtl ? 'معاينة الصوت الإدارية المعتمدة' : 'Admin Authorized Audio Preview'}</span>
                  </div>
                  <span className="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-800">
                    {formatSeconds(selectedTrack.duration_seconds)}
                  </span>
                </div>

                {selectedTrack.preview_stream_url ? (
                  <div className="space-y-2">
                    <audio
                      ref={audioRef}
                      src={selectedTrack.preview_stream_url}
                      onPlay={() => setAudioPlaying(true)}
                      onPause={() => setAudioPlaying(false)}
                      onEnded={() => setAudioPlaying(false)}
                      controls
                      className="w-full h-10 rounded-lg outline-none"
                    />
                    <p className="text-[10px] text-indigo-600/80">
                      {isRtl
                        ? 'تنبيه أمان: يتم البث عبر تذكرة رقمية مشفرة ومؤقتة. الملف الصوتي الأصلي الماستر محمي ولا يمكن تحميله.'
                        : 'Security: Streamed via an ephemeral signed admin token. Master file is stored privately.'}
                    </p>
                  </div>
                ) : (
                  <div className="p-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-[11px] flex items-center gap-2">
                    <AlertTriangle className="w-4 h-4 shrink-0 text-amber-600" />
                    <span>
                      {selectedTrack.media_status === 'ready'
                        ? (isRtl ? 'رابط المعاينة غير متاح حالياً.' : 'Preview stream unavailable.')
                        : (isRtl ? 'الملف الصوتي لا يزال قيد المعالجة، المعاينة غير متاحة حتى يكتمل التحويل.' : 'Audio is still processing or failed. Preview unavailable until ready.')}
                    </span>
                  </div>
                )}
              </div>

              {/* Technical Specifications & Audio Props */}
              <div className="space-y-2">
                <h5 className="font-bold text-slate-800 flex items-center gap-2">
                  <Sliders className="w-4 h-4 text-slate-500" />
                  <span>{t.music_mod_audio_props}</span>
                </h5>
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
                  <div className="p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                    <span className="text-[10px] text-slate-400 block uppercase font-medium">{isRtl ? 'الصيغة' : 'Format'}</span>
                    <span className="font-semibold text-slate-800 uppercase">{selectedTrack.audio_format || '—'}</span>
                  </div>
                  <div className="p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                    <span className="text-[10px] text-slate-400 block uppercase font-medium">{isRtl ? 'معدل البت' : 'Bitrate'}</span>
                    <span className="font-semibold text-slate-800">{selectedTrack.audio_bitrate_kbps ? `${selectedTrack.audio_bitrate_kbps} kbps` : '—'}</span>
                  </div>
                  <div className="p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                    <span className="text-[10px] text-slate-400 block uppercase font-medium">{isRtl ? 'التردد' : 'Sample Rate'}</span>
                    <span className="font-semibold text-slate-800">{selectedTrack.audio_sample_rate_hz ? `${selectedTrack.audio_sample_rate_hz} Hz` : '—'}</span>
                  </div>
                  <div className="p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                    <span className="text-[10px] text-slate-400 block uppercase font-medium">{isRtl ? 'السرعة والدرجة' : 'BPM / Key'}</span>
                    <span className="font-semibold text-slate-800">{selectedTrack.bpm ? `${selectedTrack.bpm} BPM` : ''} {selectedTrack.musical_key || '—'}</span>
                  </div>
                </div>
              </div>

              {/* Rights Declaration */}
              <div className="space-y-2">
                <h5 className="font-bold text-slate-800 flex items-center gap-2">
                  <Shield className="w-4 h-4 text-slate-500" />
                  <span>{t.music_mod_rights}</span>
                </h5>
                <div className="p-3 rounded-xl bg-slate-50 border border-slate-100 space-y-1.5">
                  <div className="flex items-center justify-between">
                    <span className="text-[11px] font-semibold text-slate-700 capitalize">
                      {isRtl ? 'نوع الإقرار:' : 'Declaration Type:'} {selectedTrack.rights_declaration || 'standard'}
                    </span>
                    <span className="text-[10px] text-emerald-700 font-semibold bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                      ✓ {isRtl ? 'تم التعهد والموافقة' : 'Acknowledged & Accepted'}
                    </span>
                  </div>
                  {selectedTrack.rights_notes && (
                    <p className="text-[11px] text-slate-600 bg-white p-2 rounded-lg border border-slate-200/60 mt-1">
                      {selectedTrack.rights_notes}
                    </p>
                  )}
                </div>
              </div>

              {/* Configured Offers */}
              <div className="space-y-2">
                <h5 className="font-bold text-slate-800 flex items-center gap-2">
                  <FileText className="w-4 h-4 text-slate-500" />
                  <span>{t.music_mod_offers}</span>
                </h5>
                <div className="divide-y divide-slate-100 rounded-xl bg-slate-50 border border-slate-100 overflow-hidden">
                  {selectedTrack.offers && selectedTrack.offers.length > 0 ? (
                    selectedTrack.offers.map((offer, idx) => (
                      <div key={idx} className="p-3 flex items-center justify-between bg-white/60">
                        <div>
                          <span className="font-semibold text-slate-900 uppercase tracking-wide text-xs">
                            {offer.offer_type}
                          </span>
                          {offer.rental_duration_days && (
                            <span className="text-slate-400 text-[11px] ml-2 rtl:mr-2">
                              ({offer.rental_duration_days} {isRtl ? 'يوماً' : 'days'})
                            </span>
                          )}
                        </div>
                        <span className="font-bold text-indigo-700 text-xs">
                          {offer.offer_type === 'free' ? (isRtl ? 'مجاني' : 'Free') : `$${offer.price}`}
                        </span>
                      </div>
                    ))
                  ) : (
                    <div className="p-3 text-slate-400 text-center">{isRtl ? 'لا توجد تراخيص مهيأة' : 'No commercial offers configured'}</div>
                  )}
                </div>
              </div>

              {/* Rejection / Admin Notes Banner if present */}
              {selectedTrack.rejection_reason && (
                <div className="p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 space-y-1">
                  <div className="flex items-center gap-2 font-bold text-rose-900">
                    <XCircle className="w-4 h-4" />
                    <span>{t.music_mod_rejection_reason}</span>
                  </div>
                  <p className="text-xs">{selectedTrack.rejection_reason}</p>
                </div>
              )}
            </div>

            {/* Modal Actions Footer */}
            <div className="p-4 border-t border-slate-100 bg-slate-50/70 flex flex-col sm:flex-row items-center justify-between gap-3">
              <div className="text-[11px] text-slate-500">
                {selectedTrack.media_status !== 'ready' && (
                  <span className="text-amber-600 font-medium flex items-center gap-1">
                    <AlertTriangle className="w-3.5 h-3.5" />
                    {t.music_mod_audio_not_ready}
                  </span>
                )}
              </div>

              <div className="flex items-center gap-2 w-full sm:w-auto justify-end">
                {/* Reject Button */}
                {canModerate && selectedTrack.status !== 'rejected' && (
                  <button
                    onClick={() => setRejectModalOpen(true)}
                    disabled={actionLoading}
                    className="px-3.5 py-2 text-xs font-semibold text-rose-700 bg-rose-50 border border-rose-200 rounded-xl hover:bg-rose-100 transition disabled:opacity-50"
                  >
                    {t.music_mod_reject}
                  </button>
                )}

                {/* Suspend Button */}
                {canModerate && selectedTrack.status === 'approved' && (
                  <button
                    onClick={() => handleSuspend(selectedTrack.id)}
                    disabled={actionLoading}
                    className="px-3.5 py-2 text-xs font-semibold text-amber-800 bg-amber-50 border border-amber-200 rounded-xl hover:bg-amber-100 transition disabled:opacity-50"
                  >
                    {t.music_mod_suspend}
                  </button>
                )}

                {/* Approve Button */}
                {canModerate && selectedTrack.status !== 'approved' && (
                  <button
                    onClick={() => handleApprove(selectedTrack.id)}
                    disabled={actionLoading || selectedTrack.media_status !== 'ready'}
                    title={selectedTrack.media_status !== 'ready' ? t.music_mod_audio_not_ready : ''}
                    className="px-4 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-xs transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1.5"
                  >
                    <CheckCircle className="w-4 h-4" />
                    <span>{t.music_mod_approve}</span>
                  </button>
                )}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Rejection Prompt Modal */}
      {rejectModalOpen && (
        <div className="fixed inset-0 z-60 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
          <div className="bg-white rounded-2xl shadow-xl max-w-md w-full p-5 space-y-4 animate-in fade-in zoom-in-95 duration-150">
            <div className="flex items-center gap-2 text-rose-700">
              <XCircle className="w-5 h-5" />
              <h4 className="font-bold text-sm text-slate-900">{t.music_mod_rejection_reason}</h4>
            </div>

            <p className="text-xs text-slate-500">
              {isRtl
                ? 'يرجى تقديم توضيح للبائع يوضح سبب عدم قبول المسار أو متطلبات التصحيح اللازمة.'
                : 'Provide constructive feedback explaining why this track was rejected.'}
            </p>

            <textarea
              rows={3}
              value={rejectionReason}
              onChange={e => setRejectionReason(e.target.value)}
              placeholder={t.music_mod_rejection_placeholder}
              className="w-full text-xs p-3 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-rose-500"
            />

            <div className="flex items-center justify-end gap-2 pt-2">
              <button
                onClick={() => {
                  setRejectModalOpen(false);
                  setRejectionReason('');
                }}
                disabled={actionLoading}
                className="px-3.5 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-xl transition"
              >
                {t.cancel}
              </button>
              <button
                onClick={handleReject}
                disabled={actionLoading || !rejectionReason.trim()}
                className="px-4 py-2 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-xl shadow-xs transition disabled:opacity-50"
              >
                {actionLoading ? t.loading : t.confirm}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
